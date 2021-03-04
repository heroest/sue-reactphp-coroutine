<?php

namespace Sue\Tests\Coroutine;

use Sue\Tests\Coroutine\BaseTestCase;
use function Sue\Coroutine\co;

final class SystemCallTest extends BaseTestCase
{
    public function testSystemCallSleep()
    {
        $time_start = microtime(true);
        $time_end = 0;
        $promise = co(function () use (&$time_end) {
            yield \Sue\Coroutine\SystemCall\sleep(2);
            $time_end = microtime(true);
            return 'foo';
        });
        self::$loop->run();

        $time_used = (float) bcsub($time_end, $time_start, 4);
        $this->assertGreaterThanOrEqual(2, $time_used);
        $this->assertLessThanOrEqual(2.1, $time_used);

        $result = self::unwrapSettledPromise($promise);
        $this->assertEquals('foo', $result);
    }

    public function testSystemCallTimeout()
    {
        $yielded = false;
        $cancelled = false;
        $deferred = new \React\Promise\Deferred(function () use (&$cancelled) {
            $cancelled = true;
        });
        $coroutine_promise = co(function ($promise) use (&$yielded) {
            yield \Sue\Coroutine\SystemCall\timeout(1);
            $yielded = yield $promise;
        }, $deferred->promise());
        self::$loop->addTimer(2, function () use ($deferred) {
            $deferred->resolve('foo');
        });
        self::$loop->run();
        $this->assertEquals(
            new \Sue\Coroutine\CoroutineException('Coroutine is timeout'),
            self::unwrapSettledPromise($coroutine_promise)
        );
        $this->assertEquals(false, $yielded);
        $this->assertEquals(true, $cancelled);
    }

    public function testSystemCallNotTimeout()
    {
        $cancelled = false;
        $deferred = new \React\Promise\Deferred(function () use (&$cancelled) {
            $cancelled = true;
        });
        $coroutine_promise = co(function ($promise) {
            yield \Sue\Coroutine\SystemCall\timeout(2);
            return yield $promise;
        }, $deferred->promise());
        self::$loop->addTimer(1, function () use ($deferred) {
            $deferred->resolve('foo');
        });
        self::$loop->run();
        $this->assertEquals('foo', self::unwrapSettledPromise($coroutine_promise));
        $this->assertEquals(false, $cancelled);
    }

    public function testSystemCallCancel()
    {
        $yielded = false;
        $msg = 'foo';
        $coroutine_promise = co(function ($reason) use (&$yielded) {
            yield \Sue\Coroutine\SystemCall\cancel($reason);
            $yielded = yield 'bar';
        }, $msg);
        self::$loop->run();
        $this->assertEquals(
            new \Sue\Coroutine\CoroutineException($msg),
            self::unwrapSettledPromise($coroutine_promise)
        );
        $this->assertNotEquals('bar', $yielded);
    }

    public function testNestedSystemcallTimeout()
    {
        $child = function () {
            yield \Sue\Coroutine\SystemCall\timeout(2);
            yield \React\Promise\Timer\resolve(3, self::$loop);
        };
        $promise = co(function ($child) {
            yield $child();
        }, $child);
        self::$loop->run();
        $this->assertEquals(new \Sue\Coroutine\CoroutineException('Coroutine is timeout'), self::unwrapSettledPromise($promise));
    }

    public function testNestedSystemcallTimeoutWithHandling()
    {
        $child = function () {
            yield \Sue\Coroutine\SystemCall\timeout(2);
            yield \React\Promise\Timer\resolve(3, self::$loop);
        };
        $throwable = false;
        co(function ($child) use (&$throwable) {
            try {
                yield $child();
            } catch (\Throwable $e) {
                $throwable = $e;
            }
        }, $child);
        self::$loop->run();
        $this->assertEquals(new \Sue\Coroutine\CoroutineException('Coroutine is timeout'), $throwable);
    }

    public function testNestedSystemcallCancel()
    {
        $child = function () {
            yield 'foo';
            yield \Sue\Coroutine\SystemCall\cancel('bar');
        };
        $throwable = false;
        co(function ($child) use (&$throwable) {
            try {
                yield $child();
            } catch (\Throwable $e) {
                $throwable = $e;
            }
        }, $child);
        self::$loop->run();
        $this->assertEquals(new \Sue\Coroutine\CoroutineException('bar'), $throwable);
    }
}
