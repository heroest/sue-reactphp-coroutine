# sue-reactphp-coroutine

Coroutine-framework-for-ReactPHP

## What is ReactPHP?

[ReactPHP](https://reactphp.org/) is a low-level library for event-driven programming in PHP. At its core is an event loop, on top of which it provides low-level utilities, such as: Streams abstraction, async DNS resolver, network client/server, HTTP client/server and interaction with processes. Third-party libraries can use these components to create async network clients/servers and more.

**Table of Contents**
* [Quickstart example](#quickstart-example)
* [Methods](#methods)
  * [\Sue\Coroutine\bootstrap](#\Sue\Coroutine\bootstrap)
  * [\Sue\Coroutine\co](#\Sue\Coroutine\co)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## quickstart-example

```php

$loop = \React\EventLoop\Factory::create();
$deferred = new \React\Promise\Deferred();
$loop->addTimer(3, function () use ($deferred) {
    $deferred->resolve('foo');
})

//1. Use React Promise
$deferred->promise()->then(function ($value) {
    echo "promise value: {$value}\r\n";
});

//2. use coroutine function co()
\Sue\Coroutine\bootstrap($loop);
\Sue\Coroutine\co(function ($promise) {
    echo "start waiting promise to be resolved\r\n";
    $value = yield $promise
    echo "promise value: {$value}\r\n"
}, $deferred->promise());

$loop->run();

```

## methods

## \Sue\Coroutine\bootstrap
Before start with executing code in coroutine, you need to attach coroutine scheduler to EventLoop:
```php
$loop = \React\EventLoop\Factory::create();
\Sue\Coroutine\bootstrap($loop);
```

## \Sue\Coroutine\co
```co()``` is used to execute a callable as coroutine. you can start a coroutine like example below:
```php
$loop = \React\EventLoop\Factory::create();
\Sue\Coroutine\bootstrap($loop);
$callable = function ($worker_id) {
    $count = 3;
    while ($count--) {
        echo "{$worker_id}: " . yield $count . "\r\n";
    }
};
\Sue\Coroutine\co($callable, 'foo');
\Sue\Coroutine\co($callable, 'bar');
$loop->run();
/** expect out:
 foo: 2
 bar: 2
 foo: 1
 bar: 1
 foo: 0
 bar: 0
**/
```

## Install


## Tests
You need to clone this project from [git](https://github.com/heroest/sue-reactphp-coroutine) and then use composer to install all the dependencies
```bash
$ composer install
$ vendor/bin/phpunit
```

## License

MIT, see [LICENSE file](LICENSE).