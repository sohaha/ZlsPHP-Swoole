<?php

namespace Zls\Swoole;

/**
 * Zls
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2018-01-29 14:13
 */
use Z;

class WebSocket
{
    /**
     * @var \Business\Swoole\WebSocker $BusinessSwooleWebSocker
     */
    private $BusinessSwooleWebSocker;

    public function __construct()
    {
        $this->BusinessSwooleWebSocker = z::business('Swoole\WebSocker');
    }

    /**
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $this->BusinessSwooleWebSocker->$name(...$arguments);
    }

    /**
     * 执行控制器API
     * @param $controller
     * @param $method
     * @return string
     */
    private function getController($controller, $method)
    {
        $controllerObject = Z::factory(\Zls::getConfig()->getControllerDirName() . '_' . $controller);
        if (method_exists($controllerObject, 'before')) {
            $controllerObject->before($method, $controller);
        }
        $_method = \Zls::getConfig()->getMethodPrefix() . $method;
        $content = $controllerObject->$_method();

        return $this->json($content, $controller, $method);
    }
}
