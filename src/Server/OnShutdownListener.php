<?php declare(strict_types=1);

namespace Sergonie\Network\Server;

use Sergonie\Network\Server;

/**
 * The event happens when the server shuts down
 *
 * Before the shutdown happens all the client connections are closed.
 */
interface OnShutdownListener extends Listener
{
    /**
     * Handles server's shutdown event.
     * 
     * @param Server $server
     */
    public function onShutdown(Server $server): void;
}
