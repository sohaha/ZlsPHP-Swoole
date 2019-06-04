<?php declare (strict_types = 1);
/*
 * @Author: seekwe
 * @Date:   2019-05-31 12:59:44
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-06-04 17:18:28
 */

namespace Zls\Swoole\RPC;

use swoole_client;
use Z;

class Client
{

    private static $clients = [];

    public static function call(\swoole_client $client, $method, $params, $id = -1)
    {
        $recv = null;
        $data = ["method" => $method, "params" => [$params], "id" => $id];
        /** @noinspection PhpComposerExtensionStubsInspection */
        if ($rs = $client->send(@json_encode($data, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES))) {
            if ($recv = $client->recv()) {
                /** @noinspection PhpComposerExtensionStubsInspection */
                $recv = @json_decode($recv, true);
                if (z::arrayGet($recv, 'error') == null) {
                    return $recv['result'];
                }
            }
        }

        return false;
    }

    public static function init(string $name, array $option)
    {
        if (!isset(self::$clients[$name])) {
            self::$clients[$name] = $option;
        }

        return self::$clients[$name];
    }

    public static function get($name, $timeout = null)
    {
        $call = null;
        if (isset(self::$clients[$name])) {
            $option = self::$clients[$name];
            $addr = explode(':', $option['addr']);
            if (is_null($timeout)) {
                $timeout = $option['timeout'];
            }
            $client = new swoole_client(SWOOLE_TCP | SWOOLE_KEEP);
            $ip = z::arrayGet($addr, 0, "");
            $port = (int) z::arrayGet($addr, 1, 0);
            if ($client->connect($ip, $port, $timeout)) {
                $call = function ($method, $params, $id = -1) use ($client) {
                    return z::tap(self::call($client, $method, $params, $id), function () use ($client) {
                        $client->close();
                    });
                };
            }
        }

        return $call;
    }
}
