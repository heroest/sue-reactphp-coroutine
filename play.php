<?php

include 'vendor/autoload.php';

$d1 = new \React\Promise\Deferred(function () {
    echo "d1 canceller called\r\n";
});

$d2 = new \React\Promise\Deferred(function () {
    echo "d2 cannel called\r\n";
});

$d2->resolve($d1->promise());
$d2->promise()->cancel();