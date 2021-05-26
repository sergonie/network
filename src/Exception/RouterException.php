<?php declare(strict_types=1);

namespace Sergonie\Network\Exception;

use Igni\Exception\RuntimeException;
use Psr\Http\Message\ResponseInterface;
use Sergonie\Network\Http\Response;
use Sergonie\Network\Http\Route;
use Sergonie\Network\Http\Router;

class RouterException extends RuntimeException implements HttpException
{
    private int $httpStatus;

    public static function noRouteMatchesRequestedUri(string $uri, string $method): self
    {
        $exception = new self("No route matches requested uri: $method `$uri`.");
        $exception->httpStatus = 404;
        return $exception;
    }

    public static function methodNotAllowed(
        string $uri,
        array $allowedMethods
    ): self {
        $exception = new self(
            sprintf(
                'This uri `%s` allows only %s http methods.',
                $uri, implode(', ', $allowedMethods)
            ));
        $exception->httpStatus = 405;

        return $exception;
    }

    public static function invalidRoute($given): self
    {

        $exception = new self(sprintf(
            '%s::addRoute() - passed value must be instance of %s, %s given.',
            Router::class,
            Route::class,
            get_class($given)
        ));
        $exception->httpStatus = 500;

        return $exception;
    }

    public function toResponse(): ResponseInterface
    {
        return Response::asText($this->getMessage(), $this->httpStatus);
    }
}
