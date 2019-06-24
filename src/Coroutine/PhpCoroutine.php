<?php declare (strict_types = 1);
/*
 * @Author: seekwe
 * @Date:   2019-05-28 15:27:25
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-06-23 19:25:59
 */

namespace Zls\Swoole\Coroutine;

use Swoole\Coroutine as c;
use Swoole\Coroutine\Channel;
use Z;

class PhpCoroutine extends Coroutine
{
    /** @var Channel $chan */
    private $chan;
    private $sum;
    private $outtime;
    private $data;

    public function __construct($timeout, $sum)
    {}

    public function run($name, \Closure $func)
    {
        $this->data[$name] = ['data' => $func()];
    }

    public function data(): array
    {
        return $this->data;
    }

    public static function defer(\Closure $func)
    {
        z::defer($func);
    }

    public static function go(\Closure $func)
    {
        $func();
    }
}
