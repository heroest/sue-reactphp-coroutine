<?php

namespace Sue\Coroutine;

use Throwable;
use SplStack;
use SplObjectStorage;
use Generator;
use Sue\Coroutine\{AbstractSystemCall, CoroutineException, State};
use React\EventLoop\LoopInterface;
use React\Promise\{Deferred, PromiseInterface};
use function React\Promise\{resolve, reject};

final class CoroutineScheduler
{
    private static $instance;
    private $coroutineStack;
    private $coroutineWorking;
    private $poolSize = 0;
    /** @var \React\EventLoop\LoopInterface $loop */
    private $loop;
    /** @var \React\EventLoop\TimerInterface */
    private $tickTimer;

    private function __construct()
    {
        $this->coroutineStack = new SplStack();
        $this->coroutineWorking = new SplObjectStorage();
    }

    public static function getInstance(): self
    {
        return self::$instance ?? self::$instance = new self();
    }

    public function bindEventLoop(LoopInterface $loop): self
    {
        if (null !== $this->loop) {
            throw new CoroutineException('CoroutineScheduler has already been bind to eventloop');
        }
        $this->loop = $loop;
        return $this;
    }

    public function setPoolSize(int $pool_size): self
    {
        $this->poolSize = ($pool_size <= 0) ? 0 : $pool_size;
        return $this;
    }

    public function tick($timer)
    {
        if (0 === $count = $this->coroutineWorking->count()) {
            $this->loop->cancelTimer($timer);
            $this->tickTimer = null;
            return;
        } else {
            $this->tickTimer ?? $this->tickTimer = $timer;
            $this->coroutineWorking->rewind();
        }
        
        while ($count--) {
            /** @var \Sue\Coroutine\Coroutine $coroutine */
            if (null === $coroutine = $this->coroutineWorking->current()) {
                return;
            }

            $this->coroutineWorking->next();
            if (!$coroutine->valid()) {
                $this->recycleCoroutine($coroutine);
            } elseif ($coroutine->isTimeout()) {
                $this->recycleCoroutine($coroutine, 'coroutine timeout');
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
                $coroutine = $this->createCoroutine();
                $coroutine->start($result);
                $this->coroutineWorking->attach($coroutine);
                $this->tickTimer = $this->tickTimer ?? $this->loop->addPeriodicTimer(0, [$this, 'tick']);
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
        $this->recycleCoroutine($coroutine, 'coroutine cancelled');
    }

    private function handleYielded(Coroutine $coroutine, $value)
    {
        if (!$coroutine->valid()) {
            $this->recycleCoroutine($coroutine);
            return;
        }

        if ($value instanceof PromiseInterface) {
            $this->handlePromise($coroutine, $value);
        } elseif ($value instanceof Generator) {
            $this->handleGenerator($coroutine, $value);
        } elseif ($value instanceof AbstractSystemCall) {
            $this->handleYielded($coroutine, $value->execute($coroutine));
        } else {
            $coroutine->set($value);
        }
    }

    private function handlePromise(Coroutine $coroutine, PromiseInterface $promise)
    {

    }

    private function handleGenerator(Coroutine $coroutine, Generator $generator)
    {

    }

    private function createCoroutine(): Coroutine
    {
        return $this->coroutineStack->valid()
                ? $this->coroutineStack->pop()
                : new Coroutine();
    }

    private function recycleCoroutine(Coroutine $coroutine, string $reason = 'coroutine recycled')
    {
        if (null !== $child = $coroutine->child()) {
            $this->recycleCoroutine($child, 'parent coroutine recycled');
        }

        if ($coroutine->valid()) {
            $coroutine->cancel($reason);
        }
        $this->coroutineWorking->detach($coroutine);

        if ($this->needRecycle()) {
            $coroutine->reset();
            $this->coroutineStack->push($coroutine);
        }
    }

    private function needRecycle(): bool
    {
        return $this->poolSize and $this->poolSize > $this->coroutineStack->count();
    }
}