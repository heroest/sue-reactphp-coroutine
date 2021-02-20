<?php

namespace Sue\Coroutine\SystemCall;

use Sue\Coroutine\{Coroutine, CoroutineScheduler};
use function React\Promise\Timer\resolve;

class CoroutineSleep extends AbstractSystemCall
{
    private $seconds;

    public function __construct(float $seconds)
    {
        $this->seconds = $seconds;
    }

    public function execute(Coroutine $coroutine)
    {
        return resolve($this->seconds, CoroutineScheduler::getInstance()->getLoop());
    }
}