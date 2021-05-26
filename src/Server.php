<?php
declare(strict_types=1);

namespace Sergonie\Network;

use Igni\Exception\RuntimeException;
use Psr\Log\LoggerInterface;
use Sergonie\Network\Exception\ClientException;
use Sergonie\Network\Exception\ServerException;
use Sergonie\Network\Server\Client;
use Sergonie\Network\Server\Configuration;
use Sergonie\Network\Server\HandlerFactory;
use Sergonie\Network\Server\Listener;
use Sergonie\Network\Server\LogWriter;
use Sergonie\Network\Server\OnCloseListener;
use Sergonie\Network\Server\OnConnectListener;
use Sergonie\Network\Server\OnReceiveListener;
use Sergonie\Network\Server\OnShutdownListener;
use Sergonie\Network\Server\OnStartListener;
use Sergonie\Network\Server\ServerStats;
use SplQueue;
use Swoole\Server as SwooleServer;

use function extension_loaded;

/**
 * Http server implementation based on swoole extension.
 *
 * @package Sergonie\Http
 */
class Server implements HandlerFactory
{
    private const SWOOLE_EXT_NAME = 'swoole';

    protected ?SwooleServer $handler = null;
    protected Configuration $configuration;
    protected LoggerInterface $logger;
    protected HandlerFactory $handlerFactory;

    /* @var SplQueue[] */
    protected array $listeners = [];

    /** @var Client[] */
    private array $clients = [];

    private bool $running = false;

    public function __construct(
        Configuration $settings = null,
        LoggerInterface $logger = null,
        HandlerFactory $handlerFactory = null
    ) {
        if (!extension_loaded(self::SWOOLE_EXT_NAME)) {
            throw new RuntimeException('Swoole extenstion is missing, please install it and try again.');
        }

        $this->handlerFactory = $handlerFactory ?? $this;
        $this->configuration = $settings ?? new Configuration();
        $this->logger = new LogWriter($logger);
    }

    /**
     * Return server configuration.
     *
     * @return Configuration
     */
    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * @param  int $id
     *
     * @return Client
     * @throws ClientException if client was not found
     */
    public function getClient(int $id): Client
    {
        if (!isset($this->clients[$id])) {
            throw ClientException::forInactiveClient($id);
        }
        return $this->clients[$id];
    }

    /**
     * Adds listener that is attached to server once it is run.
     *
     * @param  Listener  $listener
     */
    public function addListener(Listener $listener): void
    {
        $this->addListenerByType($listener, OnStartListener::class);
        $this->addListenerByType($listener, OnCloseListener::class);
        $this->addListenerByType($listener, OnConnectListener::class);
        $this->addListenerByType($listener, OnShutdownListener::class);
        $this->addListenerByType($listener, OnReceiveListener::class);
    }

    protected function addListenerByType(Listener $listener, string $type): void
    {
        if ($listener instanceof $type) {
            if (!isset($this->listeners[$type])) {
                $this->listeners[$type] = new SplQueue();
            }
            $this->listeners[$type]->push($listener);
        }
    }

    /**
     * Checks if listener exists.
     *
     * @param  Listener  $listener
     *
     * @return bool
     */
    public function hasListener(Listener $listener): bool
    {
        foreach ($this->listeners as $listenerCollection) {
            foreach ($listenerCollection as $current) {
                if ($current === $listener) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns information about server.
     *
     * @return ServerStats
     */
    public function getServerStats(): ServerStats
    {
        if (!$this->running) {
            throw ServerException::forMethodCallOnIdleServer(__METHOD__);
        }
        return new ServerStats($this->handler->stats());
    }

    public function createHandler(Configuration $configuration): SwooleServer
    {
        $flags = SWOOLE_TCP;
        if ($configuration->isSslEnabled()) {
            $flags |= SWOOLE_SSL;
        }

        $handler = new SwooleServer(
            $configuration->getAddress(),
            $configuration->getPort(),
            $configuration->getMode(),
            $flags
        );

        $handler->set($configuration->getSettings());

        return $handler;
    }

    public function start(): void
    {
        $this->addListener($this->logger);
        $this->handler
            = $this->handlerFactory->createHandler($this->configuration);
        $this->createListeners();
        $this->handler->start();
        $this->running = true;
    }

    public function stop(): void
    {
        if ($this->handler !== null) {
            $this->handler->shutdown();
            $this->handler = null;
        }
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    protected function createListeners(): void
    {
        $this->createOnConnectListener();
        $this->createOnCloseListener();
        $this->createOnShutdownListener();

        if ($this->configuration->getMode() === SWOOLE_PROCESS) {
            $this->createOnStartListener();
        }

        $this->createOnReceiveListener();
    }

    private function createClient($handler, int $clientId): void
    {
        $this->clients[$clientId] = new Client($handler, $clientId);
    }

    private function destroyClient(int $clientId): void
    {
        unset($this->clients[$clientId]);
    }

    protected function createOnConnectListener(): void
    {
        $this->handler->on('Connect', function ($handler, int $clientId) {
            $this->createClient($handler, $clientId);

            if (!isset($this->listeners[OnConnectListener::class])) {
                return;
            }

            $queue = clone $this->listeners[OnConnectListener::class];
            while (!$queue->isEmpty() && $listener = $queue->pop()) {
                /** @var OnConnectListener $listener */
                $listener->onConnect($this, $this->getClient($clientId));
            }
        });
    }

    protected function createOnCloseListener(): void
    {
        $this->handler->on('Close', function ($handler, int $clientId) {
            if (isset($this->listeners[OnCloseListener::class])) {
                $queue = clone $this->listeners[OnCloseListener::class];
                while (!$queue->isEmpty() && $listener = $queue->pop()) {
                    /** @var OnCloseListener $listener */
                    $listener->onClose($this, $this->getClient($clientId));
                }
            }

            $this->destroyClient($clientId);
        });
    }

    protected function createOnShutdownListener(): void
    {
        $this->handler->on('Shutdown', function () {
            if (!isset($this->listeners[OnShutdownListener::class])) {
                return;
            }

            $queue = clone $this->listeners[OnShutdownListener::class];
            while (!$queue->isEmpty() && $listener = $queue->pop()) {
                /** @var OnShutdownListener $listener */
                $listener->onShutdown($this);
            }

            $this->clients = [];
        });
    }

    protected function createOnStartListener(): void
    {
        $this->handler->on('Start', function () {
            if (!isset($this->listeners[OnStartListener::class])) {
                return;
            }

            $queue = clone $this->listeners[OnStartListener::class];
            while (!$queue->isEmpty() && $listener = $queue->pop()) {
                /** @var OnStartListener $listener */
                $listener->onStart($this);
            }
        });
    }

    protected function createOnReceiveListener(): void
    {
        $this->handler->on('Receive',
            function ($handler, int $clientId, int $fromId, string $data) {
                if (!isset($this->listeners[OnReceiveListener::class])) {
                    return;
                }

                $queue = clone $this->listeners[OnReceiveListener::class];
                while (!$queue->isEmpty() && $listener = $queue->pop()) {
                    /** @var OnReceiveListener $listener */
                    $listener->onReceive($this, $this->getClient($clientId),
                        $data);
                }
            });
    }
}
