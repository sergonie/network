<?php

declare(strict_types=1);

namespace Sergonie\Network\Http;

use DOMDocument;
use Igni\Exception\RuntimeException;
use JsonSerializable;
use Laminas\Diactoros\MessageTrait;
use Laminas\Diactoros\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sergonie\Network\Exception\InvalidArgumentException;
use SimpleXMLElement;

use function is_array;
use function is_string;
use function json_encode;

/**
 * PSR-7 implementation of ResponseInterface.
 * Utilizes zend/diactoros implementation.
 *
 * @see ResponseInterface
 * @package Sergonie\Http
 */
class Response implements ResponseInterface
{
    use MessageTrait;

    public const HTTP_CONTINUE = 100;
    public const HTTP_SWITCHING_PROTOCOLS = 101;
    public const HTTP_PROCESSING = 102;
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_ACCEPTED = 202;
    public const HTTP_NON_AUTHORITATIVE_INFORMATION = 203;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_RESET_CONTENT = 205;
    public const HTTP_PARTIAL_CONTENT = 206;
    public const HTTP_MULTI_STATUS = 207;
    public const HTTP_ALREADY_REPORTED = 208;
    public const HTTP_IM_USED = 226;
    public const HTTP_MULTIPLE_CHOICES = 300;
    public const HTTP_MOVED_PERMANENTLY = 301;
    public const HTTP_FOUND = 302;
    public const HTTP_SEE_OTHER = 303;
    public const HTTP_NOT_MODIFIED = 304;
    public const HTTP_USE_PROXY = 305;
    public const HTTP_RESERVED = 306;
    public const HTTP_TEMPORARY_REDIRECT = 307;
    public const HTTP_PERMANENTLY_REDIRECT = 308;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_PAYMENT_REQUIRED = 402;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_NOT_ACCEPTABLE = 406;
    public const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    public const HTTP_REQUEST_TIMEOUT = 408;
    public const HTTP_CONFLICT = 409;
    public const HTTP_GONE = 410;
    public const HTTP_LENGTH_REQUIRED = 411;
    public const HTTP_PRECONDITION_FAILED = 412;
    public const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
    public const HTTP_REQUEST_URI_TOO_LONG = 414;
    public const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    public const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    public const HTTP_EXPECTATION_FAILED = 417;
    public const HTTP_I_AM_A_TEAPOT = 418;
    public const HTTP_MISDIRECTED_REQUEST = 421;
    public const HTTP_UNPROCESSABLE_ENTITY = 422;
    public const HTTP_LOCKED = 423;
    public const HTTP_FAILED_DEPENDENCY = 424;
    public const HTTP_RESERVED_FOR_WEBDAV_ADVANCED_COLLECTIONS_EXPIRED_PROPOSAL = 425;
    public const HTTP_UPGRADE_REQUIRED = 426;
    public const HTTP_PRECONDITION_REQUIRED = 428;
    public const HTTP_TOO_MANY_REQUESTS = 429;
    public const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    public const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS = 451;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;
    public const HTTP_NOT_IMPLEMENTED = 501;
    public const HTTP_BAD_GATEWAY = 502;
    public const HTTP_SERVICE_UNAVAILABLE = 503;
    public const HTTP_GATEWAY_TIMEOUT = 504;
    public const HTTP_VERSION_NOT_SUPPORTED = 505;
    public const HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL = 506;
    public const HTTP_INSUFFICIENT_STORAGE = 507;
    public const HTTP_LOOP_DETECTED = 508;
    public const HTTP_NOT_EXTENDED = 510;
    public const HTTP_NETWORK_AUTHENTICATION_REQUIRED = 511;

    /**
     * Map of standard HTTP status code/reason phrases
     *
     * @var string[]
     */
    private static array $phrases
        = [
            // INFORMATIONAL CODES
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            // SUCCESS CODES
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-status',
            208 => 'Already Reported',
            // REDIRECTION CODES
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => 'Switch Proxy', // Deprecated
            307 => 'Temporary Redirect',
            // CLIENT ERROR
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Time-out',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Large',
            415 => 'Unsupported Media Property',
            416 => 'Requested range not satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Unordered Collection',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            // SERVER ERROR
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Time-out',
            505 => 'HTTP Version not supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            511 => 'Network Authentication Required',
        ];

    private string $reasonPhrase = '';
    private int $statusCode;
    private bool $complete = false;

    /**
     * @param  string|resource|StreamInterface  $body  Stream identifier and/or
     *     actual stream resource
     * @param  int  $status  Status code for the response, if any.
     * @param  array  $headers  Headers for the response, if any.
     *
     * @throws \InvalidArgumentException on any invalid element.
     */
    public function __construct(
        $body = '',
        int $status = self::HTTP_OK,
        array $headers = []
    ) {
        $this->statusCode = $status;
        $this->reasonPhrase = self::$phrases[$this->statusCode];
        $this->setHeaders($headers);

        $this->stream = $this->createStream($body);
    }

    protected function createStream($stream): StreamInterface
    {
        $factory = new StreamFactory();

        if (is_string($stream)
            && (empty($stream) || strpos('php://', $stream) !== 0)
        ) {
            return $factory->createStream($stream);
        }

        if (is_resource($stream)) {
            return $factory->createStreamFromResource($stream);
        }

        if ($stream instanceof StreamInterface) {
            return $stream;
        }

        if (is_file($stream)) {
            return $factory->createStreamFromFile($stream);
        }

        throw new InvalidArgumentException(
            'Stream must be a string stream resource identifier, '
            .'an actual stream resource, '
            .'or a Psr\Http\Message\StreamInterface implementation'
        );
    }

    /**
     * Writes content to the response body
     *
     * @param  string  $body
     *
     * @return $this
     */
    public function write(string $body): self
    {
        if ($this->complete) {
            throw new RuntimeException('Cannot write to the response, response is already completed.');
        }

        $this->getBody()->write($body);

        return $this;
    }

    /**
     * Ends and closes response.
     *
     * @return $this
     */
    public function end(): self
    {
        if ($this->complete) {
            return $this;
        }

        $this->complete = true;

        return $this;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase()
    {
        if (!$this->reasonPhrase && isset(self::$phrases[$this->statusCode])) {
            $this->reasonPhrase = self::$phrases[$this->statusCode];
        }

        return $this->reasonPhrase;
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase;
        return $new;
    }

    /**
     * Factories response instance from json data.
     *
     * @param  array|JsonSerializable  $data
     * @param  int  $status
     * @param  array  $headers
     *
     * @return Response
     * @throws InvalidArgumentException
     */
    public static function asJson(
        $data,
        int $status = self::HTTP_OK,
        array $headers = []
    ): self
    {
        if (!is_array($data) && !$data instanceof JsonSerializable) {
            throw new InvalidArgumentException('Invalid $data provided, method expects array or instance of \JsonSerializable.');
        }

        $headers['Content-Type'] = 'application/json; charset=utf-8';

        return new Response(json_encode(
            $data,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        ), $status, $headers);
    }

    /**
     * Factories response instance from text.
     *
     * @param  string  $text
     * @param  int  $status
     * @param  array  $headers
     *
     * @return Response
     */
    public static function asText(
        string $text,
        int $status = self::HTTP_OK,
        array $headers = []
    ): self {
        $headers['Content-Type'] = 'text/plain; charset=utf-8';
        return new Response($text, $status, $headers);
    }

    /**
     * Factories response from html text.
     *
     * @param  string  $html
     * @param  int  $status
     * @param  array  $headers
     *
     * @return Response
     */
    public static function asHtml(
        string $html,
        int $status = self::HTTP_OK,
        array $headers = []
    ): self {
        $headers['Content-Type'] = 'text/html; charset=utf-8';

        return new Response($html, $status, $headers);
    }

    /**
     * Factories xml response.
     *
     * @param  SimpleXMLElement|DOMDocument|string  $data
     * @param  int  $status
     * @param  array  $headers
     *
     * @return Response
     * @throws InvalidArgumentException
     */
    public static function asXml(
        $data,
        int $status = self::HTTP_OK,
        array $headers = []
    ): self {
        if ($data instanceof SimpleXMLElement) {
            $body = $data->asXML();
        } elseif ($data instanceof DOMDocument) {
            $body = $data->saveXML();
        } elseif (is_string($data)) {
            $body = $data;
        } else {
            throw new InvalidArgumentException('Invalid $data provided, method expects valid string or instance of \SimpleXMLElement, \DOMDocument');
        }

        $headers['Content-Type'] = 'text/xml; charset=utf-8';

        return new Response($body, $status, $headers);
    }

    /**
     * Factories empty response.
     *
     * @param  int  $status
     * @param  array  $headers
     *
     * @return Response
     */
    public static function empty(
        int $status = self::HTTP_NO_CONTENT,
        array $headers = []
    ): self {
        return new Response('', $status, $headers);
    }
}
