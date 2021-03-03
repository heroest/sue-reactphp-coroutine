<?php

namespace Sue\Coroutine {
    function bootstrap(\React\EventLoop\LoopInterface $loop): void
    {
        \Sue\Coroutine\CoroutineScheduler::getInstance()
            ->registerLoop($loop);
    }

    function co(callable $callable, ...$params): \React\Promise\PromiseInterface
    {
        return \Sue\Coroutine\CoroutineScheduler::getInstance()
            ->execute($callable, ...$params);
    }

    function defer(callable $callable, float $delay_seconds): \React\Promise\PromiseInterface
    {
        $loop = \Sue\Coroutine\CoroutineScheduler::getInstance()->getLoop();
        /** @var \React\EventLoop\TimerInterface $timer */
        $timer = null;
        $canceller = new \React\Promise\CancellationQueue();
        $deferred = new \React\Promise\Deferred(function () use (&$timer, $canceller, $loop) {
            $loop->cancelTimer($timer);
            $canceller();
        });
        $loop->addTimer(function () use ($callable, $deferred, $canceller) {
            $promise = \Sue\Coroutine\co($callable);
            $canceller->enqueue($promise);
            $callback = function ($value) use ($deferred) {
                ($value instanceof \Throwable)
                    ? $deferred->reject($value)
                    : $deferred->resolve($value);
            };
            $promise->then($callback, $callback);
        }, $delay_seconds);
        return $deferred->promise();
    }
}


namespace Sue\Coroutine\SystemCall {
    function sleep(float $seconds): AbstractSystemCall
    {
        return new CoroutineSleep($seconds);
    }

    function timeout(float $timeout_seconds): AbstractSystemCall
    {
        return new CoroutineTimout($timeout_seconds);
    }

    function cancel(string $reason): AbstractSystemCall
    {
        return new CancelCoroutine($reason);
    }
}
