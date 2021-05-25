<?php declare(strict_types=1);

namespace Sergonie\Network\Exception;

use Psr\Http\Message\ResponseInterface;

interface HttpException extends NetworkException
{
    public function toResponse(): ResponseInterface;
}
