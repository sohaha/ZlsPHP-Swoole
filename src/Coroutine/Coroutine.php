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
    public function sleep($time);
    public function run(string $name, \Closure $cb);
    public function data();
    public function defer(\Closure $cb);
}
