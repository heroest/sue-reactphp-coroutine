<?php

namespace Sue\Tests\Coroutine;

use Sue\Tests\Coroutine\BaseTestCase;

use function React\Promise\reject;
use function React\Promise\resolve;
use function Sue\Coroutine\co;

final class PromiseCancelTset extends BaseTestCase
{
    public function testCoroutinePromiseCancel()
    {
        $deferred_cancelled = false;
        $deferred = new \React\Promise\Deferred(function () use (&$deferred_cancelled) {
            $deferred_cancelled = true;
            throw new \Exception('bar');
        });
        /** @var \React\Promise\CancellablePromiseInterface $coroutine_promise */
        $coroutine_promise = co(function ($promise) {
            yield $promise;
            return 'foo';
        }, $deferred->promise());
        self::$loop->addTimer(0.1, function () use ($coroutine_promise) {
            $coroutine_promise->cancel();
        });
        self::$loop->run();
        $result = self::unwrapSettledPromise($coroutine_promise);
        $this->assertEquals(new \Sue\Coroutine\CoroutineException('Coroutine is canncelled by promise cancel'), $result);
        $this->assertEquals(true, $deferred_cancelled);
    }

    public function testAwaitPromiseCancel()
    {
        $promise_cancelled = false;
        $deferred = new \React\Promise\Deferred(function () use (&$promise_cancelled) {
            $promise_cancelled = true;
        });

        /** @var \React\Promise\CancellablePromiseInterface $coroutine_promise */
        $coroutine_promise = co(function ($promise) {
            yield [
                reject(new \Exception('foo')),
                resolve('bar'),
                $promise
            ];
        }, $deferred->promise());

        self::$loop->addTimer(0.5, function () use ($coroutine_promise) {
            $coroutine_promise->cancel();
        });
        self::$loop->run();
        $result = self::unwrapSettledPromise($coroutine_promise);
        $this->assertEquals(new \Sue\Coroutine\CoroutineException('Coroutine is canncelled by promise cancel'), $result);
        $this->assertEquals(true, $promise_cancelled);
    }
}