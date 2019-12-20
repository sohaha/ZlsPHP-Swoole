<?php
declare(strict_types=1);

namespace Zls\Swoole;

use Swoole\Http\Request;
use swoole\Http\Response;
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
     */
    public function onRequest($request, $response, $server, $zlsConfig, $config = []): string
    {
        Z::di()->bind(SWOOLE_RESPONSE, static function () use ($response) {
            return $response;
        });
        $_SERVER = array_change_key_case($request->server, CASE_UPPER);
        $_HEADER = array_change_key_case($request->header, CASE_UPPER);
        $_GET    = $request->get ?? [];
        $_POST   = $request->post ?? [];
        $_FILES  = $request->files ?? [];
        $_COOKIE = $request->cookie ?? [];
        foreach ($_HEADER as $key => $value) {
            $_SERVER['HTTP_' . str_replace('-', '_', $key)] = $value;
        }
        $_SERVER['REMOTE_ADDR'] = Z::arrayGet($_SERVER, 'REMOTE_ADDR', Z::arrayGet($_HEADER, 'REMOTE_ADDR', Z::arrayGet($_HEADER, 'X-REAL-IP')));
        $_SERVER['ZLS_POSTRAW'] = $request->rawContent();
        $pathInfo               = Z::arrayGet($_SERVER, 'PATH_INFO');
        $_SERVER['PATH_INFO']   = $pathInfo;
        $_SESSION               = [];
        Z::setGlobalData([
            'server'  => $_SERVER,
            'get'     => $_GET,
            'post'    => $_POST,
            'files'   => $_FILES ?? [],
            'cookie'  => $_COOKIE ?? [],
            'session' => $_SESSION ?? [],
        ], ZLS_PREFIX);
        $zlsConfig->setAppDir(ZLS_APP_PATH)->getRequest()->setPathInfo($pathInfo);
        if (Z::arrayGet($config, 'watch') && '1' === Z::arrayGet($_GET, '_reload')) {
            $this->printLog('reload Serve');
            $server->reload();
        }
        Z::setGlobalData(ZLS_PREFIX . 'setHeader', []);
        ob_start();
        try {
            $zlsConfig->bootstrap();
            echo Zls::resultException(static function () {
                return Zls::runWeb();
            });
        } catch (SwooleException $e) {
            echo $e->getMessage();
        }
        $content = ob_get_clean();
        $headers = Z::getGlobalData(ZLS_PREFIX . 'setHeader', []);
        foreach ($headers as $header) {
            $header = explode(':', $header);
            $k      = array_shift($header);
            $c      = join(':', $header);
            $response->header($k, trim($c));
        }
        $cookies = Z::getGlobalData(ZLS_PREFIX . 'setCookie', []);
        foreach ($cookies as $cookie) {
            $response->cookie(...$cookie);
        }
        Z::eventEmit(ZLS_PREFIX . 'DEFER');
        Z::resetZls();

        return $content;
    }
}
