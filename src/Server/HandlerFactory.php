<?php
declare(strict_types=1);

namespace Sergonie\Network\Server;

use Swoole\Server;

interface HandlerFactory
{
    public function createHandler(Configuration $configuration): Server;
}
