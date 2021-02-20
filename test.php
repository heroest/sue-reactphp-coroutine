<?php

include 'vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();
\Sue\Coroutine\CoroutineScheduler::getInstance()->registerLoop($loop);

$deferred = new \React\Promise\Deferred();

function child() {
    try {
        $result = yield grandson();
    } catch (Throwable $e) {
        echo 'c: ' . $e;
    }
    
    return $result;
};

function grandson()
{
    yield 3;    
    return 'grandson-done';
}

$loop->futureTick(function () use ($deferred) {
    \Sue\Coroutine\co(function ($promise) {
        $group = [
            child(),
            child()
        ];
        $result = yield $group;
        echo "done\r\n";
    }, $deferred->promise())
    ->otherwise(function ($error) {
        echo $error;
    });
});

// $loop->addTimer(5, function () use ($deferred) {
//     $deferred->resolve('okay');
// });
$st = microtime(true);
$loop->run();
echo 'time-used: ' . bcsub(microtime(true), $st, 4);