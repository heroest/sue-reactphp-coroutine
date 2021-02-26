<?php

namespace Sue\Coroutine\SystemCall;

use Sue\Coroutine\{Coroutine, CoroutineScheduler};

class CancelCoroutine extends AbstractSystemCall
{
    public function execute(Coroutine $coroutine)
    {
        CoroutineScheduler::getInstance()
            ->cancelCoroutine($coroutine, 'Coroutine is canncelled by systemcall');
    }
}
