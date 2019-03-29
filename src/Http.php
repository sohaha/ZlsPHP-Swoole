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
        $_GET    = isset($request->get) ? $request->get : [];
        $_POST   = isset($request->post) ? $request->post : [];
        $_FILES  = isset($request->files) ? $request->files : [];
        $_COOKIE = isset($request->cookie) ? $request->cookie : [];
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
            $this->printLog('重载文件');
            $server->reload();
        }

        Z::setGlobalData([
            'server' => $_SERVER,
            'get' => $_GET,
            'post' => $_POST,
            'files' => isset($_FILES) ? $_FILES : [],
            'cookie' => isset($_COOKIE) ? $_COOKIE : [],
            'session' => isset($_SESSION) ? $_SESSION : []
        ]);

        ob_start();
        try {
            if (z::arrayGet($zlsConfig->getSessionConfig(), 'autostart')) {
                z::sessionStart();
            }
            $zlsConfig->bootstrap();
            Zls::runWeb();
        } catch (SwooleHandler $e) {
            echo $e->getMessage();
        } catch (\Exception $e) {
            $err = (0 == $e->getCode()) ? $e->getMessage() : $this->exceptionHandle($e);
            echo $this->showError($err);
        } catch (\Error $e) {
            $exception = new \Zls_Exception_500($e->getMessage(), 500, 'Error', $e->getFile(), $e->getLine());
            echo $this->exceptionHandle($exception);
        }
        $content = ob_get_contents();
        ob_end_clean();
        z::resetZls();

        return $content ?: ' ';
    }

    private function showError($err = '')
    {
        return Z::config()->getShowError() ? $err : '';
    }

    /**
     * @param \Exception $exception
     * @return mixed|string
     * @throws \Exception
     */
    public function exceptionHandle(\Exception $exception)
    {
        $error  = $exception->getMessage();
        $config = Z::config();
        ini_set('display_errors', '1');
        try {
            if ($exception instanceof \Zls_Exception) {
                $loggerWriters = $config->getLoggerWriters();
                /** @var \Zls_Logger $loggerWriter */
                foreach ($loggerWriters as $loggerWriter) {
                    $loggerWriter->write($exception);
                }
                $handle = $config->getExceptionHandle();
                if ($config->getShowError() || $handle) {
                    if ($handle instanceof \Zls_Exception_Handle) {
                        $error = $handle->handle($exception);
                    } else {
                        $error = $exception->render(null, true);
                    }
                } else {
                    $error = '';
                }
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return $error;
    }
}
