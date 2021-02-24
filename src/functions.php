<?php

namespace Sue\Coroutine {
    function bindLoop(\React\EventLoop\LoopInterface $loop)
    {
        \Sue\Coroutine\CoroutineScheduler::getInstance()
            ->registerLoop($loop);
    }

    function co(callable $callable, ...$params): \React\Promise\ExtendedPromiseInterface
    {
        return \Sue\Coroutine\CoroutineScheduler::getInstance()
            ->execute($callable, ...$params);
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

    function cancel()
    {
        return new CancelCoroutine();
    }
}
