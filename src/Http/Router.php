<?php declare(strict_types=1);

namespace Sergonie\Network\Http;

/**
 * Responsible for aggregating routes and forwarding request between framework and application layer.
 *
 * @package Sergonie\Network\Http
 */
interface Router
{
    public function add(Route $route): void;
    public function find(string $method, string $path): Route;
}
