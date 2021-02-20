<?php

namespace Sue\Coroutine\SystemCall;

use Sue\Coroutine\Coroutine;

abstract class AbstractSystemCall
{
    abstract public function execute(Coroutine $coroutine);
}