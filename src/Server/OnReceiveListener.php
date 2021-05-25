<?php declare(strict_types=1);

namespace Sergonie\Network\Server;

use Sergonie\Network\Server;

/**
 * This event happens when the new connection comes in.
 */
interface OnReceiveListener extends Listener
{
    /**
     * Handles receive server event.
     *
     * @param Server $server
     * @param Client $client
     * @param string $data
     */
    public function onReceive(Server $server, Client $client, string $data): void;
}
