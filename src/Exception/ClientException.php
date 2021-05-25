<?php declare(strict_types=1);

namespace Sergonie\Network\Exception;

use Sergonie\Network\Server\Client;

class ClientException extends ServerException implements NetworkException
{
    public static function forSendFailure(Client $client, string $data): self
    {
        $dataLength = strlen($data);
        return new self("Could not send data[{$dataLength}] to {$client}");
    }

    public static function forWaitFailure(Client $client): self
    {
        return new self("Could not send wait signal to {$client}");
    }

    public static function forInactiveClient(int $clientId): self
    {
        return new self("Client with id {$clientId} was not connected or is already disconnected.");
    }
}

