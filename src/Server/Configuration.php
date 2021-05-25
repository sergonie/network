<?php

declare(strict_types=1);

namespace Igni\Network\Server;

use Igni\Exception\InvalidArgumentException;
use Igni\Network\Exception\ConfigurationException;

/**
 * Class ServerConfiguration
 *
 * @package Igni\Http\Server
 */
class Configuration
{
    /**
     * Dispatch the connection to the workers in sequence.
     * Recommended for stateless asynchronous server.
     */
    public const DISPATCH_POLLING_MODE = 1;

    /**
     * Dispatch the connection to the worker according to the id number of connection.
     * In this mode, the data from the same connection will be handled by the same worker process.
     * Recommended for stateful server.
     */
    public const DISPATCH_FIXED_MODE = 2;

    /**
     * Dispatch the connection to the unoccupied worker process.
     * Recommended for stateless, synchronous and blocking server.
     */
    public const DISPATCH_PREEMPTIVE_MODE = 3;

    /**
     * Dispatch the connection to the worker according to the ip of client.
     * The dispatch algorithm is ip2long(ClientIP) % worker_num
     */
    public const DISPATCH_IP_MODE = 4;

    /**
     * Used if none provided in the constructor
     */
    public const DEFAULT_ADDRESS = '0.0.0.0';

    /**
     * Used if none provided in the constructor
     */
    public const DEFAULT_PORT = 80;

    protected string $address;

    protected int $port;

    protected int $mode;

    protected array $settings = [];

    protected int $compression_level = 0;

    /**
     * HttpConfiguration constructor.
     *
     * @param  int  $port
     * @param  string  $address
     * @param  int  $mode
     */
    public function __construct(
        int $port = self::DEFAULT_PORT,
        string $address = self::DEFAULT_ADDRESS,
        int $mode = SWOOLE_BASE
    ) {
        $this->address = $address;
        $this->port = $port;
        $this->mode = $mode;
    }

    public function setMode(int $mode): void
    {
        if (in_array($mode, [SWOOLE_PROCESS, SWOOLE_BASE], true)) {
            $this->mode = $mode;
        } else {
            throw new InvalidArgumentException('Invalid value of running mode of the server');
        }
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * Checks if ssl is enabled.
     *
     * @return bool
     */
    public function isSslEnabled(): bool
    {
        return isset($this->settings['ssl_cert_file']);
    }

    /**
     * Enables ssl on the server
     *
     * @param string $certFile
     * @param string $keyFile
     */
    public function enableSsl(string $certFile, string $keyFile): void
    {
        if (!is_readable($certFile)) {
            throw ConfigurationException::forInvalidSslCertFile($certFile);
        }

        if (!is_readable($keyFile)) {
            throw ConfigurationException::forInvalidSslKeyFile($keyFile);
        }

        $this->settings += [
            'ssl_cert_file' => $certFile,
            'ssl_key_file'  => $keyFile,
        ];
    }

    /**
     * Sets response compression level 0 - no compression 9 - high compression
     *
     * @param int $level
     */
    public function setResponseCompression(int $level = 0): void
    {
        if ($level < 0 or $level > 9) {
            throw new InvalidArgumentException('Invalid value of compression level');
        }

        $this->compression_level = $level;
    }

    /**
     * Checks if server is daemonized.
     *
     * @return bool
     */
    public function isDaemonEnabled(): bool
    {
        return isset($this->settings['daemonize']) && $this->settings['daemonize'];
    }

    /**
     * Sets the max tcp connection number of the server.
     *
     * @param int $max
     */
    public function setMaxConnections(int $max = 10000): void
    {
        $this->settings['max_conn'] = $max;
    }

    /**
     * Sets the number of worker processes.
     *
     * @param int $count
     */
    public function setWorkers(int $count = 1): void
    {
        $this->settings['worker_num'] = $count;
    }

    /**
     * Sets the number of requests processed by the worker process before process manager recycles it.
     * Once process is recycled (memory used by process is freed and process is killed) process manger
     * will spawn new worker.
     *
     * @param int $max
     */
    public function setMaxRequests(int $max = 0): void
    {
        $this->settings['max_request'] = $max;
    }

    /**
     * Sets the maximum number of pending connections. This refers to the number of clients
     * that can be waiting to be served. Exceeding this number results in the client getting
     * an error when attempting to connect.
     *
     * @param int $max
     */
    public function setMaximumBacklog(int $max = 0): void
    {
        $this->settings['backlog'] = $max;
    }

    /**
     * Sets dispatch mode for child processes works only if the server is run in process mode.
     *
     * @param int $mode
     */
    public function setDispatchMode(int $mode = self::DISPATCH_FIXED_MODE): void
    {
        $this->settings['dispatch_mode'] = $mode;
    }

    /**
     * Allows server to be run as a background process.
     *
     * @param string $pidFile
     */
    public function enableDaemon(string $pidFile): void
    {
        if (!is_writable($pidFile) && !is_writable(dirname($pidFile))) {
            throw ConfigurationException::forUnavailablePidFile($pidFile);
        }

        $this->settings['daemonize'] = true;
        $this->settings['pid_file'] = $pidFile;
    }

    /**
     * Sets temporary dir for uploaded files
     *
     * @param string $dir
     */
    public function setUploadDir(string $dir): void
    {
        $this->settings['upload_tmp_dir'] = $dir;
    }

    /**
     * Sets the group of worker and task worker process.
     *
     * @param string $group
     */
    public function setOwnerGroup(string $group): void
    {
        $this->settings['group'] = $group;
    }

    /**
     * Redirect the root path of worker process.
     *
     * @param string $dir
     */
    public function setChroot(string $dir): void
    {
        if (!is_dir($dir)) {
            throw ConfigurationException::forInvalidDirectory($dir);
        }

        $this->settings['chroot'] = $dir;
    }

    /**
     * Set the output buffer size in the memory. The default value is 2M.
     * The data to send can't be larger than the $size every request.
     *
     * @param int $size bytes
     */
    public function setBufferOutputSize(int $size = 2 * 1024 * 1024): void
    {
        $this->settings['buffer_output_size'] = $size;
    }
}
