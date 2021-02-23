<?php

include 'vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();
\Sue\Coroutine\CoroutineScheduler::getInstance()->registerLoop($loop);

$deferred = new \React\Promise\Deferred();

$never = new \React\Promise\Deferred(function () {
    echo "never canncller get called\r\n";
    // throw new \Exception('never is canncelled');
});

function child() {
    try {
        $result = yield grandson();
    } catch (Throwable $e) {
        throw $e;
    }
    return $result;
};

function grandson()
{
    yield 3;
    throw new \Exception('grandson problem');
    return 'grandson-done';
}

function fastresult()
{
    return 'fast';
}


$loop->futureTick(function () use ($deferred, $never) {    
    \Sue\Coroutine\co(function ($promise, $never) {
        $result = yield [
            child(),
            child(),
            $promise,
            fastresult(),
            $never->promise()
        ];
        foreach ($result as $value) {
            echo $value . "\r\n";
        }
    }, $deferred->promise(), $never);
});

$loop->addTimer(3, function () use ($deferred) {
    $deferred->reject(new \Exception('fail la'));
});
// $loop->addTimer(1, function () use ($never) {
//     $never->resolve('never finished');
// });
$st = microtime(true);
$loop->run();
echo 'time-used: ' . bcsub(microtime(true), $st, 4);