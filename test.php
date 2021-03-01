<?php

include 'vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();
\Sue\Coroutine\bindLoop($loop);

$deferred = new \React\Promise\Deferred();

$never = new \React\Promise\Deferred(function () {
    echo "never canncller get called\r\n";
    // throw new \Exception('never is canncelled');
});

// set_error_handler(function ($error_no, $error_str, $error_file, $error_line) {
//     throw new ErrorException($error_str, $error_no, E_USER_ERROR, $error_file, $error_line);
// });

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
    return 'grandson-done';
}

function fastresult()
{
    return 'fast';
}


$loop->futureTick(function () use ($deferred, $never) {    
    \Sue\Coroutine\co(function () {
        // try {
             // yield \Sue\Coroutine\SystemCall\timeout(0.1);
            $c = 1/0;
            yield $c;
        // } catch (\Throwable $e) {
        //     echo 'inside - ' . $e;
        // }
       
    }, $deferred->promise(), $never)
    ->otherwise(function ($error) {
        echo 'catched: ' . $error;
    });
});

// $loop->addTimer(3, function () use ($deferred) {
//     $deferred->reject(new \Exception('fail la'));
// });
// $loop->addTimer(1, function () use ($never) {
//     $never->resolve('never finished');
// });
$st = microtime(true);
$loop->run();
echo 'time-used: ' . bcsub(microtime(true), $st, 4);