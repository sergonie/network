<?php declare(strict_types=1);

namespace Sergonie\Network\Http;

use Laminas\Diactoros\RequestTrait;
use Laminas\Diactoros\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Sergonie\Network\Exception\InvalidArgumentException;

/**
 * PSR-7 implementation of RequestInterface.
 * Utilizes zend/diactoros implementation.
 *
 * @see RequestInterface
 * @package Sergonie\Http
 */
class Request implements RequestInterface
{
    use RequestTrait;

    public const METHOD_GET = 'GET';
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_CONNECT = 'CONNECT';
    public const METHOD_OPTIONS = 'OPTIONS';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_TRACE = 'TRACE';

    /** @var StreamInterface */
    private $stream;

    /**
     * @param null|string $uri URI for the request, if any.
     * @param string $method HTTP method for the request, if any.
     * @param string|resource|StreamInterface $body Output body, if any.
     * @param array $headers Headers for the message, if any.
     * @throws InvalidArgumentException for any invalid value.
     */
    public function __construct(?string $uri = null, string $method = self::METHOD_GET, $body = 'php://temp', array $headers = [])
    {
        $this->uri = new Uri($uri ?? '');
        $this->method = $method;
        $this->stream = Stream::create($body, 'wb+');

        $headers['Host'] = $headers['Host'] ?? [$this->getHostFromUri()];
        $this->setHeaders($headers);
    }
}
