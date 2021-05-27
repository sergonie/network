<?php

declare(strict_types=1);

namespace Sergonie\Network\Http;

use function Laminas\Diactoros\marshalMethodFromSapi;
use function Laminas\Diactoros\marshalProtocolVersionFromSapi;
use function Laminas\Diactoros\marshalUriFromSapi;
use function Laminas\Diactoros\normalizeServer;
use function Laminas\Diactoros\normalizeUploadedFiles;

class Request extends \Laminas\Diactoros\ServerRequest
{
    public const METHOD_GET = 'GET';
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_CONNECT = 'CONNECT';
    public const METHOD_OPTIONS = 'OPTIONS';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_TRACE = 'TRACE';

    public static function fromSwoole(\Swoole\Http\Request $request): self
    {
        $server = $request->server
            ? normalizeServer(array_change_key_case(
                    $request->server,
                    CASE_UPPER)
            ) : [];

        return new self(
            $server,
            normalizeUploadedFiles($request->files ?? []),
            marshalUriFromSapi($server, $request->header ?? []),
            marshalMethodFromSapi($server),
            new Stream($request),
            $request->header ?? [],
            $request->cookie ?? [],
            $request->get ?? [],
            $request->post ?? [],
            marshalProtocolVersionFromSapi($server)
        );
    }
}
