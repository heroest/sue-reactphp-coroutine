<?php

namespace Sue\Coroutine;

use Throwable;
use SplObjectStorage;
use Generator;
use Sue\Coroutine\{CoroutineException, State, SystemCall\AbstractSystemCall};
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
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
        while ($count--) {
            /** @var \Sue\Coroutine\Coroutine $coroutine */
            if (null === $coroutine = $this->coroutineWorking->current()) {
                return;
            }

            $this->coroutineWorking->next();
            if ($coroutine->inState(State::IDLE)) {
                $this->closeCoroutine($coroutine);
            } elseif ($coroutine->isTimeout()) {
                $this->closeCoroutine($coroutine, new CoroutineException('Coroutine is timeout'));
            } elseif ($coroutine->inState(State::PROGRESS)) {
                continue;
            } else {
                $yielded = $coroutine->get();
                $this->handleYielded($coroutine, $yielded);
            }
        }
    }

    public function execute(callable $callable, ...$params): PromiseInterface
    {
        try {
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
        }
    }

    public function cancelCoroutine(Coroutine $coroutine)
    {
        $this->closeCoroutine($coroutine, new CoroutineException('Coroutine is canncelled'));
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
            //todo
        } elseif (is_array($value)) {
            $coroutine->set($value);
        }
    }

    private function handlePromise(Coroutine $coroutine, PromiseInterface $promise)
    {
        $coroutine->appendProgress($promise);
        $closure = function ($value) use ($coroutine) {
            $coroutine->set($value);
        };
        $promise->then($closure, $closure);
    }

    private function handleGenerator(Coroutine $parent, Generator $generator)
    {
        $child = $this->createCoroutine($generator);
        $parent->setChild($child);
        $this->handlePromise($parent, $child->promise());
        $this->coroutineWorking->attach($child);
    }

    private function createCoroutine(Generator $generator): Coroutine
    {
        return (new Coroutine())->start($generator);
    }

    private function closeCoroutine(Coroutine $coroutine, Throwable $reason = null)
    {
        if ($coroutine->valid()) {
            $coroutine->cancel($reason);
        }
        
        if (null !== $child = $coroutine->child()) {
            $this->closeCoroutine($child);
        }

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
}