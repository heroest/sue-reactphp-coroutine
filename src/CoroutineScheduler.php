<?php

namespace Sue\Coroutine;

use Throwable;
use Generator;
use SplObjectStorage;
use SplFixedArray;
use ErrorException;
use Sue\Coroutine\{CoroutineException, State, SystemCall\AbstractSystemCall};
use React\EventLoop\LoopInterface;
use React\Promise\{Deferred, PromiseInterface, CancellationQueue};
use function React\Promise\{resolve, reject};

final class CoroutineScheduler
{
    private static $instance;
    private $coroutineWorking;
    /** @var \React\EventLoop\LoopInterface $loop */
    private $loop;
    /** @var \React\EventLoop\TimerInterface */
    private $tickTimer;

    private function __construct()
    {
        $this->coroutineWorking = new SplObjectStorage();
    }

    public static function getInstance(): self
    {
        return self::$instance ?? self::$instance = new self();
    }

    public function registerLoop(LoopInterface $loop): self
    {
        if (null !== $this->loop) {
            throw new CoroutineException('CoroutineScheduler has already been bind to eventloop');
        }
        $this->loop = $loop;
        return $this;
    }

    public function unregisterLoop(): self
    {
        if (null === $this->loop) {
            return $this;
        } elseif ($this->coroutineWorking->count()) {
            throw new CoroutineException("There is still coroutine running");
        } else {
            $this->loop = null;
        }
    }

    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    public function tick($timer)
    {
        if (0 === $count = $this->coroutineWorking->count()) {
            $this->loop->cancelTimer($timer);
            $this->tickTimer = null;
            return;
        }

        $this->coroutineWorking->rewind();
        $this->setErrorHandler();
        while ($count--) {
            /** @var \Sue\Coroutine\Coroutine $coroutine */
            if (null === $coroutine = $this->coroutineWorking->current()) {
                return;
            }

            $this->coroutineWorking->next();
            if ($coroutine->inState(State::DONE)) {
                $this->closeCoroutine($coroutine);
            } elseif ($coroutine->isTimeout()) {
                $this->closeCoroutine($coroutine, new CoroutineException('Coroutine is timeout'));
            } elseif ($coroutine->inState(State::PROGRESS)) {
                continue;
            } else {
                try {
                    $yielded = $coroutine->get();
                    $this->handleYielded($coroutine, $yielded);
                } catch (Throwable $e) {
                    $this->closeCoroutine($coroutine, $e);
                }
            }
        }
        $this->restoreErrorHandler();
    }

    public function execute(callable $callable, ...$params): PromiseInterface
    {        
        try {
            if (null === $this->loop) {
                throw new CoroutineException("Eventloop is not registered, maybe forget to call \Sue\Coroutine\bootstrap() before execute?");
            }
            $this->setErrorHandler();
            $result = call_user_func_array($callable, $params);
            if ($result instanceof Generator) {
                $coroutine = $this->createCoroutine($result);
                $this->coroutineWorking->attach($coroutine);
                $this->tickTimer ?? $this->tickTimer = $this->loop->addPeriodicTimer(0, [$this, 'tick']);
                return $coroutine->promise();
            } else {
                return resolve($result);
            }
        } catch (Throwable $e) {
            return reject($e);
        } finally {
            $this->restoreErrorHandler();
        }
    }

    public function cancelCoroutine(Coroutine $coroutine, string $message)
    {
        $this->closeCoroutine($coroutine, new CoroutineException($message));
    }

    private function handleYielded(Coroutine $coroutine, $value)
    {
        if ($value instanceof PromiseInterface) {
            $this->handlePromise($coroutine, $value);
        } elseif ($value instanceof Generator) {
            $this->handleGenerator($coroutine, $value);
        } elseif ($value instanceof AbstractSystemCall) {
            $this->handleYielded($coroutine, $value->execute($coroutine));
        } elseif ($this->isArray($value)) {
            $this->handleArray($coroutine, $value);
        } else {
            $coroutine->set($value);
        }
    }

    private function handlePromise(Coroutine $coroutine, PromiseInterface $promise)
    {
        /** @var \React\Promise\ExtendedPromiseInterface $promise*/
        $coroutine->appendProgress($promise);
        $closure = function ($value) use ($coroutine) {
            $coroutine->set($value);
        };
        $promise->done($closure, $closure);
    }

    private function handleGenerator(Coroutine $parent, Generator $generator)
    {
        $child = $this->createCoroutine($generator);
        $this->handlePromise($parent, $child->promise());
        $this->coroutineWorking->attach($child);
    }

    private function handleArray(Coroutine $parent, array $items)
    {
        $promises = [];
        foreach ($items as $item) {
            if ($items instanceof PromiseInterface) {
                $promises[] = $item;
            } elseif ($item instanceof Generator) {
                $child = $this->createCoroutine($item);
                $this->coroutineWorking->attach($child);
                $promises[] = $child->promise();
            } else {
                $promises[] = resolve($item);
            }
        }
        $this->handlePromise($parent, $this->await($promises));
    }

    private function createCoroutine(Generator $generator): Coroutine
    {
        return (new Coroutine())->start($generator);
    }

    private function closeCoroutine(Coroutine $coroutine, Throwable $exception = null)
    {
        $coroutine->cancel($exception);
        $this->coroutineWorking->detach($coroutine);
    }

    private function isArray($value): bool
    {
        if (empty($value) or !is_array($value)) {
            return false;
        } else {
            return array_keys($value) === range(0, count($value) - 1);
        }
    }

    private function await(array $promises): PromiseInterface
    {
        $canceller = new CancellationQueue();
        $deferred = new Deferred(function () use ($canceller) {
            $canceller();
            throw new CoroutineException("Awaitable promises have been cancelled");
        });

        $todo_count = count($promises);
        $result = new SplFixedArray($todo_count);
        foreach ($promises as $index => $promise) {
            /** @var \React\Promise\ExtendedPromiseInterface $promise*/
            $handler = function ($value) use ($index, $deferred, $result, &$todo_count) {
                $result[$index] = $value;
                if (0 === --$todo_count) {
                    $deferred->resolve($result->toArray());
                }
            };
            $promise->done($handler, $handler);
            $canceller->enqueue($promise);
        }
        return $deferred->promise();
    }

    private function setErrorHandler()
    {
        set_error_handler(function ($error_no, $error_str, $error_file, $error_line) {
            throw new ErrorException($error_str, $error_no, E_USER_ERROR, $error_file, $error_line);
        });
    }

    private function restoreErrorHandler()
    {
        restore_error_handler();
    }
}
