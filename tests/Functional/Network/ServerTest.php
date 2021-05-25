<?php declare(strict_types=1);

namespace Igni\Tests\Functional\Network;

use Closure;
use Sergonie\Network\Exception\ClientException;
use Sergonie\Network\Exception\ServerException;
use Sergonie\Network\Server;
use Sergonie\Network\Server\Client;
use Sergonie\Network\Server\Configuration;
use Sergonie\Network\Server\HandlerFactory;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ServerTest extends TestCase
{
    public function testCanInstantiate(): void
    {
        self::assertInstanceOf(Server::class, new Server());
        self::assertInstanceOf(Server::class, new Server(new Configuration()));
        self::assertInstanceOf(Server::class, new Server(new Configuration(), new NullLogger()));
        self::assertInstanceOf(Server::class, new Server(new Configuration(), new NullLogger(), Mockery::mock(HandlerFactory::class)));
    }

    protected function assertStartAndStop(Configuration $configuration): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $handlerFactory = $this->mockHandlerFactory($configuration, $listeners);
        $expected_listeners = ['Connect', 'Close', 'Shutdown', 'Receive'];
        if ($configuration->getMode() === SWOOLE_PROCESS) {
            $expected_listeners[] = 'Start';
        }

        /** @var Server|Mockery\MockInterface $server */
        $server = new Server($configuration, $logger, $handlerFactory);
        $server->start();
        self::assertSame($expected_listeners, array_keys($listeners));
        self::assertTrue($server->isRunning());
        $server->stop();
        self::assertFalse($server->isRunning());
    }

    public function testStartAndStop(): void
    {
        $this->assertStartAndStop(new Configuration());
        $this->assertStartAndStop(new Configuration());
    }

    public function testGetConfig(): void
    {
        $server = new Server();
        self::assertInstanceOf(Configuration::class, $server->getConfiguration());

        $configuration = new Configuration();
        $server = new Server($configuration);
        self::assertSame($configuration, $server->getConfiguration());
    }

    public function testFailureOnGetStats(): void
    {
        $this->expectException(ServerException::class);
        $server = new Server();
        $server->getServerStats();
    }

    public function testClientCreation(): void
    {
        $swoole = Mockery::mock(\Swoole\Server::class);

        $server = $this->mockServer($listeners);
        $server->start();

        $listeners['Connect']($swoole, 1);
        $client = $server->getClient(1);
        self::assertInstanceOf(Client::class, $client);
        $listeners['Close']($swoole, 1);

        $this->expectException(ClientException::class);
        $server->getClient(1);
    }

    public function testOnStartListener(): void
    {
        $conf = new Configuration();
        $conf->setMode(SWOOLE_PROCESS);

        $server = $this->mockServer($listeners, $conf);
        $onStart = Mockery::mock(Server\OnStartListener::class);
        $onStart
            ->shouldReceive('onStart')
            ->withArgs(function(Server $passed) use ($server) {
                self::assertSame($server, $passed);
                return true;
            });
        $server->addListener($onStart);
        $server->start();

        $listeners['Start']();
    }

    public function testOnReceiveListener(): void
    {
        $onReceive = Mockery::mock(Server\OnReceiveListener::class);
        $onReceive
            ->shouldReceive('onReceive')
            ->withArgs(function(Server $server, Client $client, string $data) {
                self::assertSame('Test', $data);
                self::assertSame(1, $client->getId());

                return true;
            });

        /** @var Server|Mockery\MockInterface $server */
        $server = $this->mockServer($listeners);
        $server->addListener($onReceive);
        $server->start();

        $swoole = Mockery::mock(\Swoole\Server::class);
        $listeners['Connect']($swoole, 1);
        $listeners['Receive']($swoole, 1, 0, 'Test');
    }

    public function testOnConnectListener(): void
    {
        $server = $this->mockServer($listeners);
        $onConnect = Mockery::mock(Server\OnConnectListener::class);
        $onConnect
            ->shouldReceive('onConnect')
            ->withArgs(function(Server $passed, Client $client) use ($server) {
                self::assertSame(1, $client->getId());
                self::assertSame($server, $passed);
                return true;
            });
        $server->addListener($onConnect);
        $server->start();

        $swoole = Mockery::mock(\Swoole\Server::class);
        $listeners['Connect']($swoole, 1);
    }

    public function testOnShutdownListener(): void
    {
        $server = $this->mockServer($listeners);
        $onShutdown = Mockery::mock(Server\OnShutdownListener::class);
        $onShutdown
            ->shouldReceive('onShutdown')
            ->withArgs(function(Server $passed) use ($server) {
                self::assertSame($server, $passed);
                return true;
            });
        $server->addListener($onShutdown);
        $server->start();

        $listeners['Shutdown']();
    }

    public function testOnCloseListener(): void
    {
        $server = $this->mockServer($listeners);
        $onClose = Mockery::mock(Server\OnCloseListener::class);
        $onClose
            ->shouldReceive('onClose')
            ->withArgs(function(Server $passed, Client $client) use ($server) {
                self::assertSame($server, $passed);
                self::assertSame(1, $client->getId());
                return true;
            });
        $server->addListener($onClose);
        $server->start();

        $swoole = Mockery::mock(\Swoole\Server::class);
        $listeners['Connect']($swoole, 1);
        $listeners['Close']($swoole, 1);
    }

    public function testAddingListeners(): void
    {
        $onStart = Mockery::mock(Server\OnStartListener::class);
        $server = $this->mockServer($listeners);
        $server->addListener($onStart);
        self::assertTrue($server->hasListener($onStart));

        $onShutdown = Mockery::mock(Server\OnShutdownListener::class);
        $server = $this->mockServer($listeners);
        $server->addListener($onShutdown);
        self::assertTrue($server->hasListener($onShutdown));
        self::assertFalse($server->hasListener($onStart));

        $onClose = Mockery::mock(Server\OnCloseListener::class);
        $server = $this->mockServer($listeners);
        $server->addListener($onClose);
        self::assertTrue($server->hasListener($onClose));

        $onConnect = Mockery::mock(Server\OnConnectListener::class);
        $server = $this->mockServer($listeners);
        $server->addListener($onConnect);
        self::assertTrue($server->hasListener($onConnect));

        $onReceive = Mockery::mock(Server\OnReceiveListener::class);
        $server = $this->mockServer($listeners);
        $server->addListener($onReceive);
        self::assertTrue($server->hasListener($onReceive));
    }

    public function testStartWithSsl(): void
    {
        $settings = new Configuration();
        $settings->enableSsl(FIXTURES_DIR . '/bob.crt', FIXTURES_DIR . '/bob.key');
        $handlerFactoryMock = Mockery::mock(HandlerFactory::class);

        $handlerMock = Mockery::mock(\Swoole\Server::class);
        $handlerMock->shouldReceive('start');
        $handlerMock->shouldReceive('set')
            ->withArgs(function (array $config) {
                self::assertSame(
                    [
                        'address' => '0.0.0.0',
                        'port' => 80,
                        'ssl_cert_file' => 'a',
                        'ssl_key_file' => 'b',
                    ],
                    $config
                );

                return true;
            });
        $handlerMock->shouldReceive('on');

        $handlerFactoryMock->shouldReceive('createHandler')
            ->andReturn($handlerMock);

        $server = new Server($settings, new NullLogger(), $handlerFactoryMock);
        self::assertNull($server->start());
    }

    private function mockHandlerFactory(Configuration $configuration, &$listeners = []): HandlerFactory
    {
        $handler = Mockery::mock(\Swoole\Server::class);
        $handler->shouldReceive('on')
            ->withArgs(function(string $type, Closure $listener) use(&$listeners) {
                $listeners[$type] = $listener;
                return true;
            });
        $handler->shouldReceive('start');
        $handler->shouldReceive('shutdown');

        $handlerFactory = Mockery::mock(HandlerFactory::class);
        $handlerFactory
            ->shouldReceive('createHandler')
            ->with($configuration)
            ->andReturn($handler);

        return $handlerFactory;
    }

    private function mockServer(&$listeners = [], ?Configuration $configuration = null): Server
    {
        $configuration = $configuration ?? new Configuration();
        $handlerFactory = $this->mockHandlerFactory($configuration, $listeners);

        return new Server($configuration, new NullLogger(), $handlerFactory);
    }
}
