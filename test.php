<?php

require 'vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();
\Sue\Coroutine\bootstrap($loop);

function child()
{
    yield new \React\Promise\Promise(function ($resolve, $reject) {});
}

function parent()
{
    echo "started\r\n";
    yield \Sue\Coroutine\SystemCall\timeout(2);
    yield child();
}

\Sue\Coroutine\co('parent');
$loop->run();
echo "done\r\n";