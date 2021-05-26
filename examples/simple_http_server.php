<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Sergonie\Network\Server\Configuration;
use Sergonie\Network\Server\HttpServer;
use Sergonie\Network\Server\OnRequestListener;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sergonie\Network\Server\Client;
use Sergonie\Network\Http\Stream;

$configuration = new Configuration(8080);
$server = new HttpServer($configuration);
$server->addListener(new class implements OnRequestListener {
    public function onRequest(Client $client, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $response->withBody(Stream::fromString("Hello from http server"));
    }
});
$server->start();
