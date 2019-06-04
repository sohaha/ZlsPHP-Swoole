<?php declare (strict_types = 1);

namespace Zls\Swoole;

use Z;
use Zls;

/*
 * Zls
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2017-08-28 18:07
 */

class Http
{
    use Utils;

    /**
     * @param \swoole_http_request  $request
     * @param \swoole_http_response $response
     * @param \swoole_server        $server
     * @param \Zls_Config           $zlsConfig
     * @param array                 $config
     * @return string
     * @throws \Exception
     */
    public function onRequest($request, $response, $server, $zlsConfig, $config = [])
    {
        z::resetZls();
        z::di()->bind('SwooleResponse', function () use ($response) {
            return $response;
        });
        /** @noinspection PhpUndefinedFieldInspection */
        $_SERVER = array_change_key_case($request->server, CASE_UPPER);
        /** @noinspection PhpUndefinedFieldInspection */
        $_HEADER = array_change_key_case($request->header, CASE_UPPER);
        $_GET = isset($request->get) ? $request->get : [];
        $_POST = isset($request->post) ? $request->post : [];
        $_FILES = isset($request->files) ? $request->files : [];
        $_COOKIE = isset($request->cookie) ? $request->cookie : [];
        foreach ($_HEADER as $key => $value) {
            $_SERVER['HTTP_' . str_replace('-', '_', $key)] = $value;
        }
        $_SERVER['REMOTE_ADDR'] = z::arrayGet($_SERVER, 'REMOTE_ADDR', z::arrayGet($_HEADER, 'REMOTE_ADDR', z::arrayGet($_HEADER, 'X-REAL-IP')));
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        $_SERVER['ZLS_POSTRAW'] = $request->rawContent();
        $pathInfo = z::arrayGet($_SERVER, 'PATH_INFO');
        $_SERVER['PATH_INFO'] = $pathInfo;
        $_SESSION = [];
        /** @noinspection PhpUndefinedMethodInspection */
        $zlsConfig->setAppDir(ZLS_APP_PATH)->getRequest()->setPathInfo($pathInfo);
        if (z::arrayGet($config, 'watch') && '1' === z::arrayGet($_GET, '_reload')) {
            $this->printLog('reload Serve');
            $server->reload();
        }
        Z::setGlobalData([
            'server' => $_SERVER,
            'get' => $_GET,
            'post' => $_POST,
            'files' => isset($_FILES) ? $_FILES : [],
            'cookie' => isset($_COOKIE) ? $_COOKIE : [],
            'session' => isset($_SESSION) ? $_SESSION : [],
        ]);
        ob_start();
        try {
            if (z::arrayGet($zlsConfig->getSessionConfig(), 'autostart')) {
                z::sessionStart();
            }
            $zlsConfig->bootstrap();
            echo Zls::runWeb();
        } catch (SwooleException $e) {
            echo $e->getMessage();
        }
        $content = ob_get_contents();
        ob_end_clean();
        Z::eventEmit('ZLS_DEFER');
        z::resetZls();

        return $content ?: ' ';
    }

}
