<?php declare (strict_types = 1);
/*
 * @Author: seekwe
 * @Date:   2019-05-28 15:27:25
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-05-31 16:05:11
 */

namespace Zls\Swoole\Coroutine;

interface Coroutine
{
    public function __construct($timeout, $sum);
    public static function sleep($time);
    public function run(string $name, \Closure $ce);
    public function data();
    public static function defer(\Closure $ce);
    public static function go(\Closure $ce);
}
