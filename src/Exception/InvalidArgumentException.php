<?php declare(strict_types=1);

namespace Sergonie\Network\Exception;

use Igni\Exception\InvalidArgumentException as IgniExceptionInvalidArgumentException;

class InvalidArgumentException extends IgniExceptionInvalidArgumentException implements NetworkException
{
}
