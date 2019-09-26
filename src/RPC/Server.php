<?php
declare (strict_types=1);

namespace Zls\Swoole\RPC;

use swoole\Server as swooleServer;
use Z;
use Zls\Swoole\Utils;

class Server
{
    use Utils;
    /** @var swooleServer|\swoole_http_server|\swoole_websocket_server $server */
    private $server;

    public function __construct($server, $config)
    {
        /** @var $server swooleServer */
        $this->server = $server;
        $server->set([
            'open_http_protocol' => false,
        ]);
        $unpack = z::arrayGet($config, 'rpc_server.unpack');
        if (!is_callable($unpack)) {
            $unpack = static function ($data) {
                /** @noinspection PhpComposerExtensionStubsInspection */
                return @json_decode($data, true);
            };
        }
        $pack = z::arrayGet($config, 'rpc_server.pack');
        if (!is_callable($pack)) {
            $pack = static function ($data) {
                /** @noinspection PhpComposerExtensionStubsInspection */
                return @json_encode($data, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES);
            };
        }
        $app    = Z::arrayGet($config, 'rpc_server.method', []);
        $appKey = array_keys($app);
        $server->on('receive', static function (swooleServer $serv, $fd, $from_id, $data) use ($app, $appKey, $pack, $unpack) {
            $result = $err = null;
            $id     = 0;
            $info   = $unpack($data);
            if ((bool)$info && is_array($info)) {
                $id     = z::arrayGet($info, 'id');
                $method = z::arrayGet($info, 'method');
                $params = z::arrayGet($info, 'params.0');
                if (in_array($method, $appKey, true)) {
                    $_method   = $app[$method];
                    $appMethod = $_method[1];
                    $factory   = Z::factory($_method[0]);
                    try {
                        $result = $factory->$appMethod($params, $id);
                    } catch (\Throwable $e) {
                        $err = 'server error: ' . $e->getMessage();
                    }
                } else {
                    $err = 'method does not exist';
                }
            } else {
                $err = 'unpacking data error';
            }
            try {
                $resultData = $pack(['error' => $err, 'result' => $result, 'id' => $id]);
                $serv->send($fd, $resultData);
            } catch (\Throwable $e) {
                $serv->close($fd);
            }
        });
    }

    public function instance()
    {
        return $this->server;
    }

}
