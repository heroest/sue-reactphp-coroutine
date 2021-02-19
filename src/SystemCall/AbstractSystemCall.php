<?php

namespace Sue\Coroutine;

use Sue\Coroutine\Coroutine;

abstract class AbstractSystemCall
{
    abstract public function execute(Coroutine $coroutine);
}