<?php
declare (strict_types=1);
/*
 * @Author: seekwe
 * @Date:   2019-05-28 15:27:25
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-06-03 18:03:40
 */

namespace Zls\Swoole;

use Swoole\Coroutine\Channel;
use z;
use Zls\Swoole\Exception\Pool as PoolException;

class Pool
{
    /**
     * @var Channel
     */
    protected $pool;
    /**
     * @var callable
     */
    private $closed;
    /**
     * @var null|callable
     */
    private $detect;
    /**
     * @var int
     */
    private $max;
    /**
     * @var Coroutine\PhpCoroutine|Coroutine\SwooleCoroutine
     */
    private $co;
    /**
     * @var callable
     */
    private $initFn;
    /**
     * @var int
     */
    private $len;
    /**
     * @var int
     */
    private $total = 0;

    /**
     * RedisPool constructor.
     * @param int|array     $size
     * @param callable      $init
     * @param callable      $closed
     * @param callable|null $detect
     */
    public function init($size, callable $init, callable $closed, callable $detect = null)
    {
        $this->len = 0;
        if (is_array($size)) {
            $this->max = z::arrayGet($size, 1);
            $min       = z::arrayGet($size, 0);
        } else {
            $this->max = $size;
            $min       = $size;
        }
        $this->pool   = new Channel($this->max);
        $this->closed = $closed;
        $this->detect = $detect;
        $this->initFn = $init;
        $this->co     = Co::instance();
        for ($i = 0; $i < $min; $i++) {
            $this->co->run((string)$i, function () use ($init, $i) {
                $obj = $init();
                if (!is_null($obj)) {
                    ++$this->len;
                    $this->put($obj);
                }
            });
        }
    }

    public function pool()
    {
        return $this->pool;
    }

    public function put($obj)
    {
        if (!is_null($obj)) {
            ++$this->len;
            if ($this->pool->length() <= $this->max) {
                $this->pool->push($obj);
            } else {
                $closed = $this->closed;
                --$this->len;
                --$this->total;
                if (!!$closed && is_callable($closed)) {
                    $closed($obj);
                }
            }
        }
    }

    public function get($timeout = 0)
    {
        static $i;
        if ($this->pool->length() > 0) {
            $i++;
            z::log("复用: {$i}");
            $obj    = $this->pool->pop($timeout);
            $detect = $this->detect;
            if ($detect && ($obj = $detect($obj, $this->initFn, $this->closed))) {
                return $obj;
            }
        }
        if ($this->max >= $this->pool->length()) {
            ++$this->total;
            $init = $this->initFn;
            $obj  = $init();
            if (!is_null($obj)) {

            }
        } else {
            // ++$this->len;
            $obj = $this->pool->pop($timeout);
        }
        Z::throwIf(!$obj, new PoolException("Pool length <= 0"));

        return $obj;
    }

    public function info()
    {
        return ["len" => $this->len, "total" => $this->total, "length" => $this->pool->length()];
    }
}
