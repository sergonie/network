<?php declare(strict_types=1);

namespace Igni\Network\Server;

use Igni\Network\Exception\ClientException;
use Swoole\Server as SwooleServer;

class Client
{
    private int $id;
    private SwooleServer $handler;

    public function __construct(SwooleServer $handler, int $id)
    {
        $this->handler = $handler;
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getInfo(): ClientInfo
    {
        return new ClientInfo($this->handler->getClientInfo($this->id));
    }

    public function isActive(): bool
    {
        return $this->handler->exist($this->id);
    }

    public function pause(): void
    {
        $this->handler->pause($this->id);
    }

    public function resume(): void
    {
        $this->handler->resume($this->id);
    }

    /**
     * @param  string|null  $data
     */
    public function send(string $data = null): void
    {
        if (!$this->handler->send($this->id, $data)) {
            throw ClientException::forSendFailure($this, $data);
        }
    }

    public function protect(): void
    {
        $this->handler->protect($this->id);
    }

    public function wait(string $data = null): void
    {
        if (!$this->handler->sendwait($this->id, $data)) {
            throw ClientException::forWaitFailure($this);
        }
    }

    public function confirm(): void
    {
        $this->handler->confirm($this->id);
    }

    public function close(): void
    {
        $this->handler->close($this->id);
    }

    public function __toString(): string
    {
        return self::class . "[{$this->id}]";
    }
}
