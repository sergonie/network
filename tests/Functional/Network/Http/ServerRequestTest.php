<?php declare(strict_types=1);

namespace Igni\Tests\Functional\Network\Http;

use Sergonie\Network\Http\ServerRequest;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

final class ServerRequestTest extends TestCase
{
    public function testCanInstantiate(): void
    {
        self::assertInstanceOf(ServerRequest::class, new ServerRequest());
    }

    public function testFactoryFromUri(): void
    {
        $request = new ServerRequest('/some/uri');

        self::assertInstanceOf(ServerRequest::class, $request);
        self::assertSame('/some/uri', $request->getUri()->getPath());
        self::assertSame([], $request->getQueryParams());
    }

    public function testFactoryFromEmptySwooleRequest(): void
    {
        $request = ServerRequest::fromSwoole(new \Swoole\Http\Request());

        self::assertInstanceOf(ServerRequest::class, $request);
        self::assertSame('', $request->getUri()->getPath());
        self::assertSame([], $request->getQueryParams());
    }

    public function testFactoryFromSwooleRequest(): void
    {
        $request = new \Swoole\Http\Request();
        $request->header = [
            'Accept-Type' => '*'
        ];
        $request->server = [
            'request_uri' => '/some/uri',
            'query_string' => 'test=1',
            'request_method' => 'POST',
        ];
        $request = ServerRequest::fromSwoole($request);

        self::assertInstanceOf(ServerRequest::class, $request);
        self::assertSame('/some/uri', $request->getUri()->getPath());
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            [
                'REQUEST_URI' => '/some/uri',
                'QUERY_STRING' => 'test=1',
                'REQUEST_METHOD' => 'POST',
            ],
            $request->getServerParams()
        );
        self::assertSame(['test' => '1'], $request->getQueryParams());
    }

    public function testParseJsonBody(): void
    {
        $data = [
            'json' => true,
            'body' => 'cool',
        ];
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $body = json_encode($data);
        $serverRequest = new ServerRequest('some/uri?test=1', 'POST', $body, $headers);

        self::assertSame($data, $serverRequest->getParsedBody());
    }

    public function testParseApplicationFormBody(): void
    {
        $data = [
            'json' => '1',
            'body' => 'cool',
        ];
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $body = http_build_query($data);
        $serverRequest = new ServerRequest('some/uri?test=1', 'POST', $body, $headers);

        self::assertSame($data, $serverRequest->getParsedBody());
    }

    public function testFactoryFromGlobals(): void
    {
        $request = ServerRequest::fromGlobals();
        self::assertInstanceOf(ServerRequest::class, $request);
        self::assertSame('', $request->getUri()->getPath());
        self::assertSame('GET', $request->getMethod());
        self::assertSame($_SERVER, $request->getServerParams());
        self::assertSame([], $request->getUploadedFiles());
        self::assertSame([], $request->getCookieParams());
        self::assertSame([], $request->getAttributes());
        self::assertSame(null, $request->getParsedBody());
    }

    public function testOverrides(): void
    {
        // Uploaded files.
        $request = ServerRequest::fromGlobals();
        $withFiles = $request->withUploadedFiles([Mockery::mock(UploadedFileInterface::class)]);
        self::assertSame([], $request->getUploadedFiles());
        self::assertCount(1, $withFiles->getUploadedFiles());

        // Cookie params
        $withCookies = $request->withCookieParams(['test' => 1]);
        self::assertSame([], $request->getCookieParams());
        self::assertSame(['test' => 1], $withCookies->getCookieParams());

        // Query params.
        $withQuery = $request->withQueryParams(['test' => 1]);
        self::assertSame([], $request->getQueryParams());
        self::assertSame(['test' => 1], $withQuery->getQueryParams());

        // Attributes
        $withAttributes = $request->withAttributes(['test' => 1]);
        self::assertSame([], $request->getAttributes());
        self::assertSame(['test' => 1], $withAttributes->getAttributes());
        self::assertSame(1, $withAttributes->getAttribute('test'));

        // Single Attribute
        $withAttribute = $request->withAttribute('test', 1);
        self::assertSame([], $request->getAttributes());
        self::assertSame(['test' => 1], $withAttribute->getAttributes());
        self::assertSame(1, $withAttribute->getAttribute('test'));
        self::assertSame('default', $withAttribute->getAttribute('testb', 'default'));

        // Without attribute
        $without = $withAttribute->withoutAttribute('test');
        self::assertSame([], $without->getAttributes());
        self::assertSame([], $request->withoutAttribute('test')->getAttributes());
        self::assertSame(['test' => 1], $withAttribute->getAttributes());

        // Parsed body
        $withBody = $request->withParsedBody('test 1');
        self::assertSame(null, $request->getParsedBody());
        self::assertSame('test 1', $withBody->getParsedBody());
    }
}
