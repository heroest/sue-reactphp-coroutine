<?php

namespace Sue\Tests\Coroutine;

use PHPUnit\Framework\TestCase;
use React\EventLoop\{LoopInterface, Factory};
use function Sue\Coroutine\bootstrap;

abstract class BaseTestCase extends TestCase
{
    /** @var LoopInterface $loop */
    protected static $loop;

    public static function setUpBeforeClass(): void
    {
        if (null === self::$loop) {
            bootstrap(self::$loop = Factory::create());
        }
    }

    protected static function unwrapSettledPromise($promise)
    {
        $result = null;
        $closure = function ($val) use (&$result) {
            $result = $val;
        };
        $promise->then($closure, $closure);
        return $result;
    }
}