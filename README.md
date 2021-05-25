# Network package

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](./LICENSE)
[![Build Status](https://travis-ci.org/igniphp/network.svg?branch=master)](https://travis-ci.org/igniphp/network)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/igniphp/network/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/igniphp/network/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/igniphp/network/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/igniphp/network/?branch=master)

## Requirements

- PHP 7.4 or better
- Swoole extension is required for network server to work

## Installation

Linux users:

```
pecl install swoole
composer install sergonie/network
```

Mac users with homebrew:

```
brew install swoole
composer install sergionie/network
```
or:
```
brew install homebrew/php/php71-swoole
composer install sergonie/network
```


## Basic Usage

```php
<?php
// Autoloader.
require_once __DIR__ . '/vendor/autoload.php';

// Create server instance.
$server = new \Sergonie\Network\Server();
$server->start();
```

### Listeners

Sergonie http server uses event-driven model that makes it easy to scale and extend.

There are five type of events available, each of them extends `Sergonie\Network\Server\Listener` interface:

 - `Sergonie\Network\Server\OnStartListener` fired when server starts
 - `Sergonie\Network\Server\OnStopListener` fired when server stops
 - `Sergonie\Network\Server\OnConnectListener` fired when new client connects to the server
 - `Sergonie\Network\Server\OnCloseListener` fired when connection with the client is closed
 - `Sergonie\Network\Server\OnRequestListener` fired when new request is dispatched
 
 ```php
 <?php
 // Autoloader.
 require_once __DIR__ . '/vendor/autoload.php';
 
 use Sergonie\Network\Server\Client;
 use Sergonie\Network\Server\OnRequestListener;
 use Psr\Http\Message\ServerRequestInterface;
 use Psr\Http\Message\ResponseInterface;
 use Sergonie\Network\Http\Stream;
 
 // Create server instance.
 $server = new \Sergonie\Network\Server();
 
 // Each request will retrieve 'Hello' response
 $server->addListener(new class implements OnRequestListener {
     public function onRequest(Client $client, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        return $response->withBody(Stream::fromString("Hello world"));
     }
 });
 $server->start();
 ```

### Configuration

Server can be easily configured with `Sergonie\Network\Server\Configuration` class.

Please consider following example:
```php
<?php
// Autoloader.
require_once __DIR__ . '/vendor/autoload.php';

// Listen on localhost at port 80.
$configuration = new \Sergonie\Network\Server\Configuration('0.0.0.0', 80);

// Create server instance.
$server = new \Sergonie\Network\Server($configuration);
$server->start();
```

##### Enabling ssl support
```php
<?php
// Autoloader.
require_once __DIR__ . '/vendor/autoload.php';

$configuration = new \Sergonie\Network\Server\Configuration();
$configuration->enableSsl($certFile, $keyFile);

// Create server instance.
$server = new \Sergonie\Network\Server($configuration);
$server->start();
```

##### Running server as a daemon
```php
<?php
// Autoloader.
require_once __DIR__ . '/vendor/autoload.php';

$configuration = new \Sergonie\Network\Server\Configuration();
$configuration->enableDaemon($pidFile);

// Create server instance.
$server = new \Sergonie\Network\Server($configuration);
$server->start();
```
More examples can be found in the `./examples/` directory.
