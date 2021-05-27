<?php

declare(strict_types=1);

namespace Sergonie\Network\Server;

use Psr\Log\LoggerInterface;
use Sergonie\Network\Exception\HttpException;
use Sergonie\Network\Http\Request;
use Sergonie\Network\Http\Response;
use Sergonie\Network\Server;
use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\Http\Response as SwooleHttpResponse;
use Swoole\Http\Server as SwooleHttpServer;
use Throwable;

use function explode;
use function gzdeflate;
use function implode;
use function in_array;
use function strtolower;

class HttpServer extends Server
{
    private int $compressionLevel = 0;

    public function __construct(
        Configuration $settings = null,
        LoggerInterface $logger = null,
        HandlerFactory $handlerFactory = null
    ) {
        parent::__construct($settings, $logger, $handlerFactory ?? $this);
    }

    public function createHandler(Configuration $configuration): \Swoole\Server
    {
        $flags = SWOOLE_TCP;
        if ($configuration->isSslEnabled()) {
            $flags |= SWOOLE_SSL;
        }
        //$this->compressionLevel = $settings['compression_level'] ?? 0;

        $handler = new SwooleHttpServer(
            $configuration->getAddress(),
            $configuration->getPort(),
            $configuration->getMode(),
            $flags
        );

        $handler->set($configuration->getSettings());

        return $handler;
    }

    public function addListener(Listener $listener): void
    {
        $this->addListenerByType($listener, OnRequestListener::class);
        parent::addListener($listener);
    }

    protected function createListeners(): void
    {
        $this->createOnRequestListener();
        parent::createListeners();
    }

    protected function createOnRequestListener(): void
    {
        /**  */
        $this->handler->on('Request', function (
            SwooleHttpRequest $request,
            SwooleHttpResponse $response
        ) {
            $psrRequest = Request::fromSwoole($request);
            $psrResponse = Response::empty();
            $queue = clone $this->listeners[OnRequestListener::class];

            try {
                while (!$queue->isEmpty() && $listener = $queue->pop()) {
                    /** @var OnRequestListener $listener */
                    $psrResponse = $listener->onRequest(
                        $this->getClient($request->fd),
                        $psrRequest, $psrResponse
                    );
                }
            } catch (HttpException $exception) {
                $psrResponse = $exception->toResponse();
            } catch (Throwable $throwable) {
                $this->logger->error($throwable->getMessage());
                $psrResponse = Response::empty(
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }

            // Set headers
            foreach ($psrResponse->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $response->header($name, $value);
                }
            }

            // Response body.
            $body = $psrResponse->getBody()->getContents();

            // Status code
            $response->status($psrResponse->getStatusCode());

            // Protect server software header.
            $response->header('software-server', '');
            $response->header('server', '');

            // Support gzip/deflate encoding.
            if ($psrRequest->hasHeader('accept-encoding')) {
                $encoding = explode(',', strtolower(implode(',',
                    $psrRequest->getHeader('accept-encoding'))));

                if (in_array('gzip', $encoding, true)) {
                    $response->header('content-encoding', 'gzip');
                    $body = gzencode($body, $this->compressionLevel);
                } elseif (in_array('deflate', $encoding, true)) {
                    $response->header('content-encoding', 'deflate');
                    $body = gzdeflate($body, $this->compressionLevel);
                }
            }

            $response->end($body);
        });
    }
}
