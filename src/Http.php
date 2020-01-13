<?php

declare(strict_types=1);

namespace Zls\Swoole;

use Cfg;
use Exception;
use Swoole\Http\Request;
use swoole\Http\Response;
use Swoole\Http\Server as httpServer;
use swoole\Server;
use Z;
use Zls;
use Zls_Config;

class Http
{
    use Utils;

    /**
     * @param Request    $request
     * @param Response   $response
     * @param Server     $server
     * @param Zls_Config $zlsConfig
     * @param array      $config
     *
     * @return string
     * @throws Exception
     */
    public function onRequest($request, $response, $server, $zlsConfig, $config = []): string
    {
        Z::di()->bind(SWOOLE_RESPONSE, static function () use ($response) {
            return $response;
        });
        $zCfg = clone $zlsConfig;
        Cfg::set('config', $zCfg);
        $__SERVER = array_change_key_case($request->server, CASE_UPPER);
        $__HEADER = array_change_key_case($request->header, CASE_UPPER);
        $__GET    = $request->get ?? [];
        $__POST   = $request->post ?? [];
        $__FILES  = $request->files ?? [];
        $__COOKIE = $request->cookie ?? [];
        foreach ($__HEADER as $key => $value) {
            $__SERVER['HTTP_' . str_replace('-', '_', $key)] = $value;
        }
        $__SERVER['REMOTE_ADDR'] = Z::arrayGet($__SERVER, 'REMOTE_ADDR', Z::arrayGet($__HEADER, 'REMOTE_ADDR', Z::arrayGet($__HEADER, 'X-REAL-IP')));
        $__SERVER['ZLS_POSTRAW'] = $request->rawContent();
        $pathInfo                = Z::arrayGet($__SERVER, 'PATH_INFO');
        $__SERVER['PATH_INFO']   = $pathInfo;
        if (isset($config['set_properties']['open_http2_protocol']) && !!$config['set_properties']['open_http2_protocol']) {
            $__SERVER['HTTPS'] = 'on';
        }
        $__SERVER['PATH_INFO'] = $pathInfo;
        $_SESSION              = [];
        $arr = [
            'server'  => $__SERVER,
            'get'     => $__GET,
            'post'    => $__POST,
            'files'   => $__FILES ?? [],
            'cookie'  => $__COOKIE ?? [],
            'session' => $_SESSION ?? [],
        ];
        Cfg::setArray($arr);
        $zCfg->setAppDir(ZLS_APP_PATH)->getRequest()->setPathInfo($pathInfo);
        if (Z::arrayGet($config, 'watch') && '1' === Z::arrayGet($__GET, '_reload')) {
            $this->printLog('reload Serve');
            $server->reload();
        }
        ob_start();
        try {
            $zCfg->bootstrap();
            echo Zls::resultException(static function () {
                return Zls::runWeb();
            });
        } catch (SwooleException $e) {
            echo $e->getMessage();
        }
        $content = ob_get_clean();
        Z::defer(function () use ($response) {
            $headers = Cfg::get( 'setHeader', []);
            foreach ($headers as $header) {
                $header = explode(':', $header);
                $k      = array_shift($header);
                $c      = trim(join(':', $header));
                if (!$c) {
                    if (!!preg_match('/HTTP\/1.1 ([\d]{3}) \w+/i', $k, $code) !== false) {
                        $response->status((int) $code[1]);
                    }
                    continue;
                }
                $response->header($k, trim($c));
            }
            $cookies = Cfg::get( 'setCookie', []);
            foreach ($cookies as $cookie) {
                $response->cookie(...$cookie);
            }
        });
        Z::eventEmit(ZLS_PREFIX . 'DEFER');
        Z::resetZls();
        return $content;
    }

    public function verificationCertificate()
    {
    }

    public function newHttpServer($host, $port, array $option = []): array
    {
        $isSsl        = false;
        $serverOption = [];
        if (Z::arrayGet($option, 'open_http2_protocol')) {
            $certFile = Z::arrayGet($option, 'ssl_cert_file');
            $keyFile  = Z::arrayGet($option, 'ssl_key_file');
            if ($certFile && $keyFile && is_file($certFile) && is_file($keyFile)) {
                $serverOption = [SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL];
                $isSsl        = true;
            } else {
                $this->error("ssl certificate does not exist");
                $this->warning("ignore open_http2_protocol option");
            }
        }

        return [new httpServer($host, $port, ...$serverOption), $isSsl];
    }
}
