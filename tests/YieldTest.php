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
        $this->assertEquals($word, $yielded);
    }

    public function testValue()
    {
        $yielded = null;
        $word = 'foo';
        co(function ($input_word) use (&$yielded) {
            $yielded = yield $input_word;
        }, $word);
        self::$loop->run();
        $this->assertEquals($word, $yielded);
    }

    public function testThorwable()
    {
        $yielded = false;
        $reject = false;
        $exception = new \Exception('foo');
        co(function () use (&$yielded, $exception) {
            $yielded = yield $exception;
        })->then(null, function ($error) use (&$reject) {
            $reject = $error;
        });
        self::$loop->run();
        $this->assertEquals($yielded, null);
        $this->assertEquals($exception, $reject);
    }

    public function testError()
    {
        $yielded = false;
        $reject = false;
        co(function () use (&$yielded) {
            $value = 1/0;
            $yielded = yield $value;
        })->then(null, function ($error) use (&$reject) {
            $reject = $error;
        });
        self::$loop->run();
        $this->assertEquals(null, $yielded);
        $this->assertEquals(new \ErrorException('Division by zero', 2, E_USER_ERROR), $reject);
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
        $this->assertNotEquals($yielded, 'foo');
    }

    public function testNestedGenerator()
    {
        $l2 = function () {
            $result = yield 'bar';
            return $result;
        };
        $l1 = function () use ($l2) {
            yield 'foo';
            return yield $l2();
        };
        
        $yielded = false;
        co(function () use (&$yielded, $l1) {
            $yielded = yield $l1();
        });
        self::$loop->run();
        $this->assertEquals($yielded, 'bar');
        $this->assertNotEquals($yielded, 'foo');
    }

    public function testYieldPromiseArray()
    {
        $yielded = false;
        co(function () use (&$yielded) {
            $yielded = yield [
                \React\Promise\resolve('foo'),
                \React\Promise\resolve('bar')
            ];
        });
        self::$loop->run();
        $this->assertEquals($yielded, ['foo', 'bar']);
        $this->assertNotEquals($yielded, ['bar', 'foo']); //reverse
    }

    public function testYieldGeneratorArray()
    {
        $yielded = false;
        co(function () use (&$yielded) {
            $yielded = yield [
                (function () {$result = yield 'foo'; return $result;})(),
                (function () {$result = yield 'bar'; return $result;})(),
            ];
        });
        self::$loop->run();
        $this->assertEquals($yielded, ['foo', 'bar']);
    }

    public function testYieldMixedArray()
    {
        $yielded = false;
        $exception = new \Exception('some-error');
        co(function () use (&$yielded, $exception) {
            $yielded = yield [
                (function () {$result = yield 'foo'; return $result;})(),
                \React\Promise\resolve('bar'),
                \React\Promise\reject($exception)
            ];
        });
        self::$loop->run();
        $this->assertEquals($yielded, ['foo', 'bar', $exception]);
    }
}