<?php

namespace Sue\Tests\Coroutine;

use PHPUnit\Framework\TestCase;
use React\EventLoop\{LoopInterface, Factory};
use function React\Promise\resolve;
use function Sue\Coroutine\{bindLoop, co};

final class YieldTest extends TestCase
{
    /** @var LoopInterface $loop */
    private static $loop;

    public static function setUpBeforeClass(): void
    {
        bindLoop(self::$loop = Factory::create());
    }

    public function testPromise()
    {
        $yielded = null;
        $word = 'hello-world';
        co(function ($promise) use (&$yielded) {
            $yielded = yield $promise;
        }, resolve($word));
        self::$loop->run();
        $this->assertEquals($yielded, $word);
    }

    public function testValue()
    {
        $yielded = null;
        $word = 'foo';
        co(function ($input) use (&$yielded) {
            $yielded = yield $input;
        }, $word);
        self::$loop->run();
        $this->assertEquals($yielded, $word);
    }

    public function testGenerator()
    {
        $child = function () {
            yield 'foo';
        };
        $yielded = false;
        co(function () use (&$yielded, $child) {
            $yielded = yield $child();
        });
        self::$loop->run();
        $this->assertEquals($yielded, null);
    }

    public function testGeneratorWithReturn()
    {
        $child = function () {
            yield 'foo';
            return 'bar';
        };
        $yielded = false;
        co(function () use (&$yielded, $child) {
            $yielded = yield $child();
        });
        self::$loop->run();
        $this->assertEquals($yielded, 'bar');
    }

    public function testNestedGenerator()
    {
        $l2 = function () {
            yield;
            return 'bar';
        };
        $l1 = function () use ($l2) {
            yield;
            return yield $l2();
        };
        
        $yielded = false;
        co(function () use (&$yielded, $l1) {
            $yielded = yield $l1();
        });
        self::$loop->run();
        $this->assertEquals($yielded, 'bar');
    }
}