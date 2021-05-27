<?php declare(strict_types=1);

namespace Sergonie\Tests\Functional\Network\Server;

use Closure;
use Sergonie\Network\Http\Response;
use Sergonie\Network\Http\Stream;
use Sergonie\Network\Server\Client;
use Sergonie\Network\Server\Configuration;
use Sergonie\Network\Server\HandlerFactory;
use Sergonie\Network\Server\HttpServer;
use Sergonie\Network\Server\OnRequestListener;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\Http\Response as SwooleHttpResponse;
use Swoole\Server;

final class HttpServerTest extends TestCase
{
    public function testCanInstantiate(): void
    {
        self::assertInstanceOf(HttpServer::class, new HttpServer());
        self::assertInstanceOf(HttpServer::class, new HttpServer(new Configuration()));
        self::assertInstanceOf(HttpServer::class, new HttpServer(new Configuration(), new NullLogger()));
        self::assertInstanceOf(HttpServer::class, new HttpServer(new Configuration(), new NullLogger(), Mockery::mock(HandlerFactory::class)));
    }

    public function testOnRequestListener(): void
    {
        $server = $this->mockServer($listeners);
        $onRequest = Mockery::mock(OnRequestListener::class);
        $onRequest
            ->shouldReceive('onRequest')
            ->withArgs(function(Client $client, ServerRequestInterface $request, ResponseInterface $response) {
                self::assertSame(1, $client->getId());
                self::assertSame(204, $response->getStatusCode());
                return true;
            })
            ->andReturn(Response::asText('Test 1'));
        $server->addListener($onRequest);
        $server->start();

        $swooleRequest = Mockery::mock(SwooleHttpRequest::class);
        $swooleRequest->fd = 1;
        $swooleResponse = Mockery::mock(SwooleHttpResponse::class);
        $swooleResponse->shouldReceive('header');
        $swooleResponse->shouldReceive('status')
            ->withArgs([200]);
        $swooleResponse->shouldReceive('end')
            ->withArgs(['Test 1']);

        $swoole = Mockery::mock(Server::class);

        $listeners['Connect']($swoole, 1);
        $listeners['Request']($swooleRequest, $swooleResponse);
        $listeners['Close']($swoole, 1);
    }

    public function testGzipSupport(): void
    {
        $server = $this->mockServer($listeners);

        $content = Mockery::mock(Stream::class);
        $content
            ->shouldReceive('getContents')
            ->andReturn('test gzip');

        $psrResponse = Mockery::mock(ResponseInterface::class);
        $psrResponse
            ->shouldReceive('getHeaders')
            ->andReturn([]);
        $psrResponse
            ->shouldReceive('getBody')
            ->andReturn($content);
        $psrResponse
            ->shouldReceive('getStatusCode')
            ->andReturn(200);

        $onRequestMock = Mockery::mock(OnRequestListener::class);
        $onRequestMock
            ->shouldReceive('onRequest')
            ->andReturn($psrResponse);

        $server->addListener($onRequestMock);
        $server->start();

        $swooleRequestMock = Mockery::mock(SwooleHttpRequest::class);
        $swooleRequestMock->fd = 1;
        $swooleRequestMock->header = ['accept-encoding' => 'gzip, deflate'];

        $swooleResponseMock = Mockery::mock(SwooleHttpResponse::class);
        $swooleResponseMock
            ->shouldReceive('status')
            ->withArgs([200]);
        $swooleResponseMock
            ->shouldReceive('header');
        $swooleResponseMock
            ->shouldReceive('end')
            ->withArgs(function(string $result) {
                self::assertSame(gzencode('test gzip', 0), $result);
                return true;
            });

        $swoole = Mockery::mock(Server::class);

        $listeners['Connect']($swoole, 1);
        $listeners['Request']($swooleRequestMock, $swooleResponseMock);
        $listeners['Close']($swoole, 1);
    }

    private function mockHandlerFactory(Configuration $configuration, &$listeners = []): HandlerFactory
    {
        $handler = Mockery::mock(Server::class);
        $handler->shouldReceive('on')
            ->withArgs(function(string $type, Closure $listener) use(&$listeners) {
                $listeners[$type] = $listener;
                return true;
            });
        $handler->shouldReceive('start');
        $handler->shouldReceive('shutdown');

        $handlerFactory = Mockery::mock(HandlerFactory::class);
        $handlerFactory
            ->shouldReceive('createHandler')
            ->with($configuration)
            ->andReturn($handler);

        return $handlerFactory;
    }

    private function mockServer(&$listeners = []): HttpServer
    {
        $configuration = new Configuration();
        $handlerFactory = $this->mockHandlerFactory($configuration, $listeners);

        return new HttpServer($configuration, new NullLogger(), $handlerFactory);
    }
}
