<?php

namespace Sue\Coroutine;

class State
{
    const IDLE = 1;
    const WORKING = 2;
    const PROGRESS = 3;

    private function __construct()
    {
    }
}
