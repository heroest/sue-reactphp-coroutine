# sue-reactphp-coroutine

Coroutine-framework-for-ReactPHP

## What is ReactPHP?

[ReactPHP](https://reactphp.org/) is a low-level library for event-driven programming in PHP. At its core is an event loop, on top of which it provides low-level utilities, such as: Streams abstraction, async DNS resolver, network client/server, HTTP client/server and interaction with processes. Third-party libraries can use these components to create async network clients/servers and more.

**Table of Contents**
* [Quickstart example](#quickstart-example)
* [Methods](#methods)
  * [LoopInterface](#loopinterface)
  * [LoopInterface](#loopinterface)
* [Install](#install)
* [Tests](#tests)
* [License](#license)
* [More](#more)

## quickstart-example

```php

$loop = React\EventLoop\Factory::create();
$deferred = new Deferred();
$loop->addTimer(3, function () use ($deferred) {
    $deferred->resolve('job done');
})

//1. Use React Promise
$deferred->promise()->then(function ($value) {
    echo "promise value: {$value}\r\n";
});

//2. use coroutine function co()
\Sue\Coroutine\bindLoop($loop);
\Sue\Coroutine\co(function ($promise) {
    echo "start waiting promise to be resolved\r\n";
    $value = yield $promise
    echo "promise value: {$value}\r\n"
}, $deferred->promise());

$loop->run();

```
