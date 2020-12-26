<?php declare(strict_types=1);

namespace Igni\Network\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Swoole\Http\Request as SwooleHttRequest;
use Throwable;

use function Laminas\Diactoros\marshalHeadersFromSapi;
use function Laminas\Diactoros\normalizeUploadedFiles;

class ServerRequest extends Request implements ServerRequestInterface
{
    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var array
     */
    private $cookieParams = [];

    /**
     * @var null|array|object
     */
    private $parsedBody;

    /**
     * @var array
     */
    private $queryParams = [];

    /**
     * @var array
     */
    private $serverParams;

    /**
     * @var array
     */
    private $uploadedFiles;


    /**
     * Server request constructor.
     *
     * @param array $serverParams Server parameters, typically from $_SERVER
     * @param array $uploadedFiles Upload file information, a tree of UploadedFiles
     * @param null|string $uri URI for the request, if any.
     * @param null|string $method HTTP method for the request, if any.
     * @param string|resource|StreamInterface $body Messages body, if any.
     * @param array $headers Headers for the message, if any.
     * @throws \InvalidArgumentException for any invalid value.
     */
    public function __construct(
        string $uri = null,
        string $method = self::METHOD_GET,
        $body = 'php://input',
        array $headers = [],
        array $uploadedFiles = [],
        array $serverParams = []
    ) {
        parent::__construct($uri, $method, $body, $headers);
        $this->validateUploadedFiles($uploadedFiles);
        $this->serverParams  = $serverParams;
        $this->uploadedFiles = $uploadedFiles;
        parse_str($this->getUri()->getQuery(), $this->queryParams);
        $this->parseBody();
    }

    private function parseBody(): void
    {
        $contentType = $this->getHeader('Content-Type')[0] ?? '';

        $body = (string) $this->getBody();

        switch (strtolower($contentType)) {
            case 'application/json':
                $this->parsedBody = json_decode($body, true);
                return;
            case 'application/x-www-form-urlencoded':
                 parse_str($body, $this->parsedBody);
                 return;
            case 'application/xml':
                $this->parsedBody = simplexml_load_string($body);
                return;
            case 'text/csv':
                $this->parsedBody = str_getcsv($body);
                return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * {@inheritdoc}
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        $this->validateUploadedFiles($uploadedFiles);
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withCookieParams(array $cookies)
    {
        $new = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withQueryParams(array $query)
    {
        $new = clone $this;
        $new->queryParams = $query;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * {@inheritdoc}
     */
    public function withParsedBody($data)
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($attribute, $default = null)
    {
        if (! isset($this->attributes[$attribute])) {
            return $default;
        }

        return $this->attributes[$attribute];
    }

    /**
     * {@inheritdoc}
     */
    public function withAttribute($attribute, $value)
    {
        $new = clone $this;
        $new->attributes[$attribute] = $value;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutAttribute($attribute)
    {
        if (!isset($this->attributes[$attribute])) {
            return clone $this;
        }

        $new = clone $this;
        unset($new->attributes[$attribute]);
        return $new;
    }

    /**
     * Sets request attributes
     *
     * This method returns a new instance.
     *
     * @param array $attributes
     * @return self
     */
    public function withAttributes(array $attributes): ServerRequest
    {
        $new = clone $this;
        $new->attributes = $attributes;
        return $new;
    }

    /**
     * Recursively validate the structure in an uploaded files array.
     *
     * @param array $uploadedFiles
     * @throws \InvalidArgumentException if any leaf is not an UploadedFileInterface instance.
     */
    private function validateUploadedFiles(array $uploadedFiles): void
    {
        foreach ($uploadedFiles as $file) {
            if (is_array($file)) {
                $this->validateUploadedFiles($file);
                continue;
            }

            if (! $file instanceof UploadedFileInterface) {
                throw new \InvalidArgumentException('Invalid leaf in uploaded files structure');
            }
        }
    }

    public static function fromGlobals(): ServerRequest
    {
        $instance = new self(
            $_SERVER['REQUEST_URI'] ?? '',
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'php://input',
            marshalHeadersFromSapi($_SERVER),
            normalizeUploadedFiles($_FILES),
            $_SERVER
        );

        return $instance;
    }

    /**
     * @param SwooleHttRequest $request
     * @return ServerRequest
     */
    public static function fromSwoole(SwooleHttRequest $request): ServerRequest
    {
        $serverParams  = $request->server ?? [];
        if (isset($request->server['query_string'])) {
            $uri = $request->server['request_uri'] . '?' . $request->server['query_string'];
        } else {
            $uri = $request->server['request_uri'];
        }

        // Normalize server params
        $serverParams = array_change_key_case($serverParams, CASE_UPPER);

        // Normalize headers
        $headers = [];
        if ($request->header) {
            foreach ($request->header as $name => $value) {
                if (!isset($headers[$name])) {
                    $headers[$name] = [];
                }
                array_push($headers[$name], $value);
            }
        }

        try {
            $body = $request->rawContent();
        } catch (Throwable $throwable) {
            $body = '';
        }

        if (!$body) {
            $body = '';
        }

        return new ServerRequest(
            $uri,
            $request->server['request_method'] ?? 'GET',
            $body,
            $headers,
            normalizeUploadedFiles($request->files ?? []) ?? [],
            $serverParams
        );
    }
}
