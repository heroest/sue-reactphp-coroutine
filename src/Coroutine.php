<?php

namespace Sue\Coroutine;

use Throwable;
use Generator;
use SplObjectStorage;
use Sue\Coroutine\{CoroutineException, CoroutineScheduler};
use React\Promise\{Deferred, PromiseInterface};

class Coroutine
{
    private $id;
    /** @var Generator $generator */
    private $generator;
    /** @var Deferred $deferred */
    private $deferred;
    /** @var bool $isDone */
    private $isDone = false;
    private $state;
    /** @var \React\Promise\CancellablePromiseInterface $progress */
    private $progress;
    private $children;
    private $timeExpired = 0;

    public function __construct()
    {
        $this->id = md5(spl_object_hash($this));
        $this->children = new SplObjectStorage();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function start(Generator $generator): self
    {
        $this->state = state::WORKING;
        $this->deferred = new Deferred(function () {
            $schedule = CoroutineScheduler::getInstance();
            $schedule->cancelCoroutine($this);
        });
        $this->generator = $generator;
        $this->timeExpired = 0;
        $this->isDone = false;
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

    public function isDone(): bool
    {
        return $this->isDone;
    }

    public function isTimeout(): bool
    {
        return $this->timeExpired and microtime(true) > $this->timeExpired;
    }

    public function setTimeout(float $timeout): void
    {
        $this->timeExpired = (float) bcadd(microtime(true), $timeout, 4);
    }

    public function children(): array
    {
        return iterator_to_array($this->children, false);
    }

    public function appendChild(Coroutine $child): self
    {
        $this->children->attach($child);
        /** @var \React\Promise\ExtendedPromiseInterface $promise */
        $promise = $child->promise();
        $promise->always(function () use ($child) {
            $this->children->detach($child);
        });
        return $this;
    }

    public function get()
    {
        if ($this->generator->valid()) {
            return $this->generator->current();
        } else {
            $this->generator->next();
            $this->settle($this->generator->getReturn());
        }
    }

    public function set($value)
    {
        if ($this->valid()) {
            try {
                ($value instanceof Throwable)
                    ? $this->generator->throw($value)
                    : $this->generator->send($value);
            } catch (Throwable $e) {
                $this->settle($e);
            }
        } else {
            $this->settle($value);
        }
    }

    public function appendProgress(PromiseInterface $promise)
    {
        $this->progress = $promise;
        $this->state = State::PROGRESS;
        /** @var \React\Promise\ExtendedPromiseInterface $promise */
        $promise->always(function () {
            $this->progress = null;
            $this->state = State::WORKING;
        });
    }

    public function cancel(Throwable $reason = null)
    {
        if ($reason) $this->settle($reason);

        if ($this->progress) {
            $this->progress->cancel();
            $this->progress = null;
        }
    }

    public function valid(): bool
    {
        return false === $this->isDone
            and null !== $this->generator
            and $this->generator->valid();
    }

    public function settle($value)
    {
        $this->isDone = true;
        $this->state = State::DONE;
        ($value instanceof Throwable)
            ? $this->deferred->reject($value)
            : $this->deferred->resolve($value);
    }
}
