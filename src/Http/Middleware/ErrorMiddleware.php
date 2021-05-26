<?php declare(strict_types=1);

namespace Sergonie\Network\Http\Middleware;

use ErrorException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sergonie\Network\Exception\HttpException;
use Sergonie\Network\Http\Response;
use Throwable;

/**
 * Middleware for error handling. If an exception is thrown and not catch during the request cycle,
 * it will appear here. Middleware will catch it and return response with status code (500) and exception message
 * as a body.
 *
 * @package Sergonie\Http\Middleware
 */
final class ErrorMiddleware implements MiddlewareInterface
{
    private $errorHandler;

    /**
     * ErrorMiddleware constructor.
     *
     * @param callable $errorHandler
     */
    public function __construct(callable $errorHandler)
    {
        $this->errorHandler = $errorHandler;
    }

    /**
     * @param  ServerRequestInterface  $request
     * @param  RequestHandlerInterface  $next
     *
     * @return ResponseInterface
     * @throws \ErrorException
     * @see MiddlewareInterface::process
     *
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        $this->setErrorHandler();

        try {
            $response = $next->handle($request);

        } catch (Throwable $exception) {
            $result = ($this->errorHandler)($exception);
            if ($result instanceof Throwable) {
                $exception = $result;
            }

            if ($exception instanceof HttpException) {
                $response = $exception->toResponse();
            } else {
                $response = Response::asText($exception->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $this->restoreErrorHandler();

        return $response;
    }


    private function setErrorHandler(): void
    {
        set_error_handler(/**
         * @throws \ErrorException
         */ static function (int $number, string $message, string $file, int $line) {

            if (!(error_reporting() & $number)) {
                return;
            }

            throw new ErrorException($message, 0, $number, $file, $line);
        });
    }

    private function restoreErrorHandler(): void
    {
        restore_error_handler();
    }
}
