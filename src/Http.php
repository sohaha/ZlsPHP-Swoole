<?php declare(strict_types=1);

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
     * @noinspection PhpUndefinedClassInspection
     * @param \swoole_http_request  $request
     * @param \swoole_http_response $response
     * @param \Zls_Config           $zlsConfig
     * @param array                 $config
     * @return string
     * @throws \Exception
     */
    public function onRequest($request, $response, $zlsConfig, $config = [])
    {
        z::resetZls();
        z::di()->bind('SwooleResponse', function () use ($response) {
            return $response;
        });
        /** @noinspection PhpUndefinedFieldInspection */
        $_SERVER = $request->server;
        /** @noinspection PhpUndefinedFieldInspection */
        $_HEADER = $request->header;
        $_GET = isset($request->get) ? $request->get : [];
        $_POST = isset($request->post) ? $request->post : [];
        $_FILES = isset($request->files) ? $request->files : [];
        $_COOKIE = isset($request->cookie) ? $request->cookie : [];
        $_SERVER['HTTP_ORIGIN'] = z::arrayGet($_HEADER, 'origin');
        $_SERVER['HTTP_HOST'] = z::arrayGet($_HEADER, 'host');
        $_SERVER['REMOTE_ADDR'] = z::arrayGet($_SERVER, 'remote_addr', z::arrayGet($_HEADER, 'remote_addr', z::arrayGet($_HEADER, 'x-real-ip')));
        $_SERVER['HTTP_X_FORWARDED_FOR'] = z::arrayGet($_HEADER, 'x-forwarded-for');
        $_SERVER['HTTP_USER_AGENT'] = z::arrayGet($_HEADER, 'user-agent');
        /** @noinspection PhpUndefinedMethodInspection */
        $_SERVER['ZLS_POSTRAW'] = $request->rawContent();
        $pathInfo = z::arrayGet($_SERVER, 'path_info');
        $_SERVER['PATH_INFO'] = $pathInfo;
        $_SESSION = [];
        /** @noinspection PhpUndefinedMethodInspection */
        $zlsConfig->setAppDir(ZLS_APP_PATH)->getRequest()->setPathInfo($pathInfo);
        if (z::arrayGet($config, 'watch') && '1' === z::arrayGet($_GET, '_reload')) {
            $this->printLog('重载文件');
            /** @noinspection PhpUndefinedMethodInspection */
            z::swoole()->reload();
        }
        ob_start();
        try {
            //开启session
            if (z::arrayGet($zlsConfig->getSessionConfig(), 'autostart')) {
                z::sessionStart();
            }
            $zlsConfig->bootstrap();
            Zls::runWeb();
        } catch (\Exception $e) {
            if (0 == $e->getCode()) {
                echo $e->getMessage();
            } else {
                echo $this->exceptionHandle($e);
            }
        } catch (\Error $e) {
            echo $this->exceptionHandle(new \Zls_Exception_500($e->getMessage(), 500, 'Error', $e->getFile(), $e->getLine()));
        }
        $content = ob_get_contents();
        ob_end_clean();
        z::di()->remove('SwooleResponse');

        return $content ?: ' ';
    }

    /**
     * @param \Exception $exception
     * @return mixed|string
     * @throws \Exception
     */
    public function exceptionHandle(\Exception $exception)
    {
        $error = $exception->getMessage();
        $config = \Z::config();
        ini_set('display_errors', '1');
        if ($exception instanceof \Zls_Exception) {
            $loggerWriters = $config->getLoggerWriters();
            /** @var \Zls_Logger $loggerWriter */
            foreach ($loggerWriters as $loggerWriter) {
                $loggerWriter->write($exception);
            }
            if ($config->getShowError()) {
                $error = $exception->render(false, true);
            }
        } else {
            $error = '';
        }

        return $error;
    }

    public function onClose($server, $fd, $reactorId)
    {
        Z::di()->remove('SwooleResponse');
    }
}
