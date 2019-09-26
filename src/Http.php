<?php
declare(strict_types=1);

namespace Zls\Swoole;

use Exception;
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
     * @throws Exception
     */
    public function onRequest($request, $response, $server, $zlsConfig, $config = []): string
    {
        z::di()->bind('SwooleResponse', static function () use ($response) {
            return $response;
        });
        /** @noinspection PhpUndefinedFieldInspection */
        $_SERVER = array_change_key_case($request->server, CASE_UPPER);
        /** @noinspection PhpUndefinedFieldInspection */
        $_HEADER = array_change_key_case($request->header, CASE_UPPER);
        $_GET    = $request->get ?? [];
        $_POST   = $request->post ?? [];
        $_FILES  = $request->files ?? [];
        $_COOKIE = $request->cookie ?? [];
        foreach ($_HEADER as $key => $value) {
            $_SERVER['HTTP_' . str_replace('-', '_', $key)] = $value;
        }
        $_SERVER['REMOTE_ADDR'] = z::arrayGet($_SERVER, 'REMOTE_ADDR', z::arrayGet($_HEADER, 'REMOTE_ADDR', z::arrayGet($_HEADER, 'X-REAL-IP')));
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        $_SERVER['ZLS_POSTRAW'] = $request->rawContent();
        $pathInfo               = z::arrayGet($_SERVER, 'PATH_INFO');
        $_SERVER['PATH_INFO']   = $pathInfo;
        $_SESSION               = [];
        /** @noinspection PhpUndefinedMethodInspection */
        $zlsConfig->setAppDir(ZLS_APP_PATH)->getRequest()->setPathInfo($pathInfo);
        if (z::arrayGet($config, 'watch') && '1' === z::arrayGet($_GET, '_reload')) {
            $this->printLog('reload Serve');
            $server->reload();
        }
        Z::setGlobalData([
            'server'  => $_SERVER,
            'get'     => $_GET,
            'post'    => $_POST,
            'files'   => $_FILES ?? [],
            'cookie'  => $_COOKIE ?? [],
            'session' => $_SESSION ?? [],
        ]);
        ob_start();
        try {
            if (z::arrayGet($zlsConfig->getSessionConfig(), 'autostart')) {
                z::sessionStart();
            }
            $zlsConfig->bootstrap();
            echo Zls::resultException(static function () {
                return Zls::runWeb();
            });
        } catch (SwooleException $e) {
            echo $e->getMessage();
        }
        $content = ob_get_clean();
        Z::eventEmit('ZLS_DEFER');
        Z::resetZls();

        return $content;
    }
}
