<?php

namespace Sue\Coroutine;

if (!function_exists('\Sue\Co')) {
    function Co(callable $callable, ...$params): \React\Promise\ExtendedPromiseInterface
    {
        return \Sue\Coroutine\CoroutineScheduler::getInstance()->execute($callable, ...$params);
    }
}