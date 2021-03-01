<?php

namespace Sue\Coroutine\SystemCall;

use Sue\Coroutine\{Coroutine, CoroutineScheduler};

class CancelCoroutine extends AbstractSystemCall
{
    private $reason = '';

    public function __construct(string $reason = '')
    {
        $this->reason = $reason;
    }

    public function execute(Coroutine $coroutine)
    {
        $reason = $this->reason ?: 'Coroutine is canncelled by systemcall';
        CoroutineScheduler::getInstance()
            ->cancelCoroutine($coroutine, $reason);
    }
}
