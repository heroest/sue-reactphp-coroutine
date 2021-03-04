<?php

namespace Sue\Tests\Coroutine;

use Sue\Tests\Coroutine\BaseTestCase;
use function React\Promise\resolve;
use function Sue\Coroutine\co;

final class YieldTest extends BaseTestCase
{
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

    public function testThrowableHandling()
    {
        $yielded = false;
        $exception = new \Exception('foo');
        co(function () use (&$yielded, $exception) {
            try {
                yield $exception;
            } catch (\Throwable $e) {
                $yielded = $e;
            }
        });
        self::$loop->run();
        $this->assertEquals($exception, $yielded);
    }

    public function testError()
    {
        $yielded = false;
        $reject = false;
        co(function () use (&$yielded) {
            $yielded = yield 1 / 0;
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
        $this->assertEquals(null, $yielded);
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
        $this->assertEquals('bar', $yielded);
        $this->assertNotEquals('foo', $yielded);
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
        $this->assertEquals('bar', $yielded);
        $this->assertNotEquals('foo', $yielded);
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
        $this->assertEquals(['foo', 'bar'], $yielded);
        $this->assertNotEquals(['bar', 'foo'], $yielded); //reverse
    }

    public function testYieldGeneratorArray()
    {
        $yielded = false;
        co(function () use (&$yielded) {
            $yielded = yield [
                (function () {
                    $result = yield 'foo';
                    return $result;
                })(),
                (function () {
                    $result = yield 'bar';
                    return $result;
                })(),
            ];
        });
        self::$loop->run();
        $this->assertEquals(['foo', 'bar'], $yielded);
    }

    public function testYieldMixedArray()
    {
        $yielded = false;
        $exception = new \Exception('some-error');
        co(function () use (&$yielded, $exception) {
            $yielded = yield [
                (function () {
                    return yield 'foo';
                })(),
                \React\Promise\resolve('bar'),
                \React\Promise\reject($exception)
            ];
        });
        self::$loop->run();
        $this->assertEquals(['foo', 'bar', $exception], $yielded);
    }
}
