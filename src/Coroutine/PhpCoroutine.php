<?php declare (strict_types = 1);
/*
 * @Author: seekwe
 * @Date:   2019-05-28 15:27:25
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-05-31 16:05:08
 */

namespace Zls\Swoole\Coroutine;

use Swoole\Coroutine as c;
use Swoole\Coroutine\Channel;
use Z;
use Zls\Swoole\Utils;

class PhpCoroutine implements Coroutine
{
    use Utils;
    /** @var Channel $chan */
    private $chan;
    private $sum;
    private $outtime;
    private $data;

    public function __construct($timeout, $sum)
    {

    }

    public static function sleep($time)
    {
        sleep($time);
    }

    public function run(string $name, \Closure $ce)
    {
        $this->data[$name] = $ce();
    }

    public function data(): array
    {
        return $this->data;
    }

    public static function defer(\Closure $ce)
    {
        z::defer($ce);
    }

    public static function go(\Closure $ce)
    {
        $ce();
    }
}
