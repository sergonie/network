<?php declare(strict_types=1);

namespace Sergonie\Network\Server;

use Sergonie\Network\Server;

/**
 * The event happens when the server starts.
 */
interface OnStartListener extends Listener
{
    /**
     * Handles server's start event.
     *
     * @param Server $server
     */
    public function onStart(Server $server): void;
}
