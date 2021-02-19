<?php

namespace Sue\Coroutine;

use Throwable;
use Generator;
use Sue\Coroutine\{CoroutineException, CoroutineScheduler};
use React\Promise\{Deferred, PromiseInterface};

class Coroutine
{
    private $id;
    /** @var Generator $generator */
    private $generator;
    /** @var Deferred $deferred */
    private $deferred;
    /** @var bool $isFinished */
    private $isFinished = false;
    private $state;
    /** @var \React\Promise\CancellablePromiseInterface $progress */
    private $progress;
    private $parent;
    private $child;
    private $timeExpired = 0;

    public function __construct()
    {
        $this->id = md5(spl_object_hash($this));
    }

    public function start(Generator $generator): self
    {
        $this->deferred = new Deferred(function () {
            $schedule = CoroutineScheduler::getInstance();
            $schedule->cancelCoroutine($this);
        });
        $this->generator = $generator;
        $this->timeExpired = 0;
        $this->isFinished = false;
        return $this;
    }

    public function promise(): PromiseInterface
    {
        return $this->deferred->promise();
    }

    public function inState(int $state): bool
    {
        return $this->state === $state;
    }

    public function isTimeout(): bool
    {
        return $this->timeExpired and microtime(true) > $this->timeExpired;
    }

    public function setTimeout(float $timeout): void
    {
        $this->timeExpired = (float) bcadd(microtime(true), $timeout < 0 ? 0 : (float) $timeout, 4);
    }

    public function child(): ?Coroutine
    {
        return $this->child;
    }

    public function get()
    {
        if ($this->generator->valid()) {
            return $this->generator->current();
        } else {
            $this->coroutine->next();
            $return = $this->coroutine->getReturn();
            $this->settle($return);
        }
    }

    public function set($value)
    {
        if ($this->valid()) {
            try {
                ($value instanceof Throwable)
                    ? $this->coroutine->throw($value)
                    : $this->coroutine->send($value);
            } catch (Throwable $e) {
                $this->settle($e);
            }
        }
    }

    public function cancel(string $reason)
    {
        if (null !== $this->progress) {
            $this->progress->cancel();
            $this->progress = null;
        }
        $this->settle(new CoroutineException("Coroutine has been cancelled due to {$reason}"));
    }

    public function valid(): bool
    {
        return false === $this->isFinished
            and null !== $this->generator
            and $this->generator->valid();
    }

    public function reset()
    {
    }

    public function settle($value)
    {
        $this->isFinished = true;
        ($value instanceof Throwable)
            ? $this->deferred->reject($value)
            : $this->deferred->resolve($value);
    }
}
