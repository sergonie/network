<?php declare(strict_types=1);

namespace Sergonie\Tests\Functional\Network\Http\Middleware;

use Igni\Exception\RuntimeException;
use Sergonie\Network\Exception\MiddlewareException;
use Sergonie\Network\Http\Middleware\CallableMiddleware;
use Sergonie\Network\Http\ServerRequest;
use Sergonie\Tests\Fixtures\CustomHttpException;
use Sergonie\Network\Http\Middleware\ErrorMiddleware;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CallableMiddlewareTest extends TestCase
{
    public function testCanInstantiate(): void
    {
        $middleware = new CallableMiddleware(function() {});
        self::assertInstanceOf(CallableMiddleware::class, $middleware);
    }

    public function testNegativeUsageCase(): void
    {
        $this->expectException(MiddlewareException::class);
        $middleware = new CallableMiddleware(function() {});
        $middleware->process(
            Mockery::mock(ServerRequestInterface::class),
            Mockery::mock(RequestHandlerInterface::class)
        );
    }

    public function testPositiveUsageCase(): void
    {
        $middleware = new CallableMiddleware(function(ServerRequestInterface $request, RequestHandlerInterface $next) {
            return $next->handle($request);
        });

        $requestHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return Mockery::mock(ResponseInterface::class);
            }
        };

        $results = $middleware->process(Mockery::mock(ServerRequestInterface::class), $requestHandler);

        $this->assertInstanceOf(ResponseInterface::class, $results);
    }
}
