<?php

namespace Sue\Coroutine\SystemCall;

use Sue\Coroutine\{Coroutine, CoroutineScheduler};
use function React\Promise\Timer\resolve;

class CoroutineSleep extends AbstractSystemCall
{
    private $seconds;
    private static $scheduler;

    public function __construct(float $seconds)
    {
        self::$scheduler = self::$scheduler ?? CoroutineScheduler::getInstance();
        $this->seconds = $seconds;
    }

    public function execute(Coroutine $coroutine)
    {
        return resolve($this->seconds, self::$scheduler->getLoop());
    }
}
