<?php declare (strict_types=1);
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

    public static function call(string $clientName, string $method, $params, $id = -1)
    {
        /** @var \swoole_client $client */
        if (!$client = self::get($clientName)) {
            return [null, "Client connection failed"];
        }
        $data = ["method" => $method, "params" => [$params], "id" => $id];
        /** @noinspection PhpComposerExtensionStubsInspection */
        if ($rs = $client->send(@json_encode($data, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES))) {
            if ($recv = $client->recv()) {
                /** @noinspection PhpComposerExtensionStubsInspection */
                if ($recv = @json_decode($recv, true)) {
                    return Z::tap([$recv['result'], $recv['error']], function () use ($client) {
                        $client->close();
                    });
                } else {
                    return [null, "Data format error, non-json format"];
                }
            }
        }

        return [null, "Failed to send"];
    }


    public static function init(string $name, array $option)
    {
        if (!isset(self::$clients[$name])) {
            self::$clients[$name] = $option;
        }

        return self::$clients[$name];
    }

    public static function get($clientName, $timeout = null)
    {
        if (isset(self::$clients[$clientName])) {
            $option = self::$clients[$clientName];
            $addr   = explode(':', $option['addr']);
            if (is_null($timeout)) {
                $timeout = $option['timeout'];
            }
            $client = new swoole_client(SWOOLE_TCP | SWOOLE_KEEP);
            $ip     = z::arrayGet($addr, 0, "");
            $port   = (int)z::arrayGet($addr, 1, 0);
            if ($client->connect($ip, $port, $timeout)) {
                return $client;
            }
        }

        return false;
    }
}
