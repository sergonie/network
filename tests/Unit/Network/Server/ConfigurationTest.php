<?php declare(strict_types=1);

namespace Igni\Tests\Unit\Network\Server;

use Igni\Network\Exception\ConfigurationException;
use Igni\Network\Server\Configuration;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testCanInstantiate(): void
    {
        $config = new Configuration();
        self::assertSame(
            Configuration::DEFAULT_PORT,
            $config->getPort()
        );
        self::assertSame(
            Configuration::DEFAULT_ADDRESS,
            $config->getAddress()
        );
    }

    public function testEnableSsl(): void
    {
        $config = new Configuration();
        $config->enableSsl(
            FIXTURES_DIR . '/bob.crt',
            FIXTURES_DIR . '/bob.key'
        );

        self::assertTrue($config->isSslEnabled());
    }

    public function testEnableSslWithInvalidCertFile(): void
    {
        $this->expectException(ConfigurationException::class);
        $config = new Configuration();
        $config->enableSsl('invalid', FIXTURES_DIR . '/bob.key');
    }

    public function testEnableSslWithInvalidKeyFile(): void
    {
        $this->expectException(ConfigurationException::class);
        $config = new Configuration();
        $config->enableSsl(FIXTURES_DIR . '/bob.crt', 'invalid');
    }

    public function testEnableDaemon(): void
    {
        $config = new Configuration();
        $config->enableDaemon(FIXTURES_DIR . '/file.pid');

        self::assertTrue($config->isDaemonEnabled());
        self::assertSame(
            [
                'daemonize' => true,
                'pid_file' => FIXTURES_DIR . '/file.pid',
            ],
            $config->getSettings()
        );
    }

    public function testSetMaxConnections(): void
    {
        $config = new Configuration();
        $config->setMaxConnections(10);
        self::assertSame(
            [
                'max_conn' => 10,
            ],
            $config->getSettings()
        );
    }

    public function testSetWorkers(): void
    {
        $config = new Configuration();
        $config->setWorkers(10);
        self::assertSame(
            [
                'worker_num' => 10,
            ],
            $config->getSettings()
        );
    }

    public function testSetMaxRequests(): void
    {
        $config = new Configuration();
        $config->setMaxRequests(10);
        self::assertSame(
            [
                'max_request' => 10,
            ],
            $config->getSettings()
        );
    }

    public function testSetMaximumBacklog(): void
    {
        $config = new Configuration();
        $config->setMaximumBacklog(10);
        self::assertSame(
            [
                'backlog' => 10,
            ],
            $config->getSettings()
        );
    }

    public function testSetDispatchMode(): void
    {
        $config = new Configuration();
        $config->setDispatchMode(Configuration::DISPATCH_FIXED_MODE);
        self::assertSame(
            [
                'dispatch_mode' => 2,
            ],
            $config->getSettings()
        );
    }

    public function testSetChroot(): void
    {
        $config = new Configuration();
        $config->setChroot(__DIR__);
        self::assertSame(
            [
                'chroot' => __DIR__,
            ],
            $config->getSettings()
        );
    }

    public function testSetOwnerGroup(): void
    {
        $config = new Configuration();
        $config->setOwnerGroup('test');
        self::assertSame(
            [
                'group' => 'test',
            ],
            $config->getSettings()
        );
    }

    public function testSetUploadDir(): void
    {
        $config = new Configuration();
        $config->setUploadDir(__DIR__);
        self::assertSame(
            [
                'upload_tmp_dir' => __DIR__,
            ],
            $config->getSettings()
        );
    }

    public function testSetBufferOutputSize(): void
    {
        $config = new Configuration();
        $config->setBufferOutputSize(10);
        self::assertSame(
            [
                'buffer_output_size' => 10,
            ],
            $config->getSettings()
        );
    }
}
