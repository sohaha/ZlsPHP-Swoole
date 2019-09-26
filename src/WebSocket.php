<?php
declare (strict_types=1);
/*
 * @Author: seekwe
 * @Date:   2019-05-28 15:27:25
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-05-31 16:05:51
 */

namespace Zls\Swoole;

use Z;

class WebSocket
{
    public function __construct()
    {
    }

    /**
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
    }

    /**
     * 执行控制器API.
     * @param $controller
     * @param $method
     * @return string
     */
    // private function getController($controller, $method)
    // {
    //     $controllerObject = Z::factory(\Zls::getConfig()->getControllerDirName() . '_' . $controller);
    //     if (method_exists($controllerObject, 'before')) {
    //         $controllerObject->before($method, $controller);
    //     }
    //     $_method = \Zls::getConfig()->getMethodPrefix() . $method;
    //     $content = $controllerObject->$_method();
    //
    //     return $this->json($content, $controller, $method);
    // }
}
