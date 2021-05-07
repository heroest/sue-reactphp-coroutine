<?php

namespace Sue\Coroutine\SystemCall;

use Sue\Coroutine\Coroutine;

class CoroutineTimeout extends AbstractSystemCall
{
    private $seconds;

    public function __construct(float $seconds)
    {
        $this->seconds = $seconds < 0 ? 0 : $seconds;
    }

    public function execute(Coroutine $coroutine)
    {
        $coroutine->setTimeout($this->seconds);
    }
}
