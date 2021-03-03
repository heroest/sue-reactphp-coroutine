<?php

namespace Sue\Coroutine\SystemCall;

use Sue\Coroutine\{Coroutine, CoroutineScheduler};

class CancelCoroutine extends AbstractSystemCall
{
    private $reason = '';
    private static $scheduler;

    public function __construct(string $reason = '')
    {
        self::$scheduler = self::$scheduler ?? CoroutineScheduler::getInstance();
        $this->reason = $reason;
    }

    public function execute(Coroutine $coroutine)
    {
        $reason = $this->reason ?: 'Coroutine is canncelled by systemcall';
        self::$scheduler->cancelCoroutine($coroutine, $reason);
    }
}
