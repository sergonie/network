<?php

declare(strict_types=1);

namespace Sergonie\Tests\Functional\Network\Http;

use Sergonie\Network\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testCanInstantiate(): void
    {
        self::assertInstanceOf(Request::class, new Request());
    }

    public function testFactoryFromUri(): void
    {
        $request = new Request([], [], '/some/uri');

        self::assertInstanceOf(Request::class, $request);
        self::assertSame('/some/uri', $request->getUri()->getPath());
        self::assertSame([], $request->getQueryParams());
    }

    public function testFactoryFromEmptySwooleRequest(): void
    {
        $request = Request::fromSwoole(new \Swoole\Http\Request());

        self::assertInstanceOf(Request::class, $request);
        self::assertSame('/', $request->getUri()->getPath());
        self::assertSame([], $request->getQueryParams());
    }

    public function testFactoryFromSwooleRequest(): void
    {
        $request = new \Swoole\Http\Request();
        $request->get = ['test' => 1];
        $request->header = [
            'Accept-Type' => '*',
        ];
        $request->server = [
            'REQUEST_URI'    => '/some/uri',
            'QUERY_STRING'   => 'test=1',
            'REQUEST_METHOD' => 'POST',
        ];
        $request = Request::fromSwoole($request);

        self::assertInstanceOf(Request::class, $request);
        self::assertSame('/some/uri', $request->getUri()->getPath());
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            [
                'REQUEST_URI'    => '/some/uri',
                'QUERY_STRING'   => 'test=1',
                'REQUEST_METHOD' => 'POST',
            ],
            $request->getServerParams()
        );
        self::assertSame(['test' => 1], $request->getQueryParams());
    }

//    public function testParseJsonBody(): void
//    {
//        $data = [
//            'json' => true,
//            'body' => 'cool',
//        ];
//
//        $swoole_request = new \Swoole\Http\Request();
//        $swoole_request->post = json_encode($data);
//        $swoole_request->header = [
//            'Content-Type' => 'application/json',
//        ];
//
//        $serverRequest = Request::fromSwoole($swoole_request);
//        self::assertSame($data, $serverRequest->getParsedBody());
//    }

    public function testGetParsedBody(): void
    {
        $swoole_request = new \Swoole\Http\Request();
        $swoole_request->post = [
            'json' => '1',
            'body' => 'cool',
        ];
        $swoole_request->header = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $serverRequest = Request::fromSwoole($swoole_request);

        self::assertSame($swoole_request->post, $serverRequest->getParsedBody());
    }

//    public function testFactoryFromGlobals(): void
//    {
//        $request = Request::fromGlobals();
//        self::assertInstanceOf(Request::class, $request);
//        self::assertSame('', $request->getUri()->getPath());
//        self::assertSame('GET', $request->getMethod());
//        self::assertSame($_SERVER, $request->getServerParams());
//        self::assertSame([], $request->getUploadedFiles());
//        self::assertSame([], $request->getCookieParams());
//        self::assertSame([], $request->getAttributes());
//        self::assertSame(null, $request->getParsedBody());
//    }
//
//    public function testOverrides(): void
//    {
//        // Uploaded files.
//        $request = Request::fromGlobals();
//        $withFiles
//            = $request->withUploadedFiles([Mockery::mock(UploadedFileInterface::class)]);
//        self::assertSame([], $request->getUploadedFiles());
//        self::assertCount(1, $withFiles->getUploadedFiles());
//
//        // Cookie params
//        $withCookies = $request->withCookieParams(['test' => 1]);
//        self::assertSame([], $request->getCookieParams());
//        self::assertSame(['test' => 1], $withCookies->getCookieParams());
//
//        // Query params.
//        $withQuery = $request->withQueryParams(['test' => 1]);
//        self::assertSame([], $request->getQueryParams());
//        self::assertSame(['test' => 1], $withQuery->getQueryParams());
//
//        // Attributes
//        $withAttributes = $request->withAttributes(['test' => 1]);
//        self::assertSame([], $request->getAttributes());
//        self::assertSame(['test' => 1], $withAttributes->getAttributes());
//        self::assertSame(1, $withAttributes->getAttribute('test'));
//
//        // Single Attribute
//        $withAttribute = $request->withAttribute('test', 1);
//        self::assertSame([], $request->getAttributes());
//        self::assertSame(['test' => 1], $withAttribute->getAttributes());
//        self::assertSame(1, $withAttribute->getAttribute('test'));
//        self::assertSame('default',
//            $withAttribute->getAttribute('testb', 'default'));
//
//        // Without attribute
//        $without = $withAttribute->withoutAttribute('test');
//        self::assertSame([], $without->getAttributes());
//        self::assertSame([],
//            $request->withoutAttribute('test')->getAttributes());
//        self::assertSame(['test' => 1], $withAttribute->getAttributes());
//
//        // Parsed body
//        $withBody = $request->withParsedBody('test 1');
//        self::assertSame(null, $request->getParsedBody());
//        self::assertSame('test 1', $withBody->getParsedBody());
//    }
}
