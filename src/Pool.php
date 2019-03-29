<?php declare(strict_types=1);

namespace Zls\Swoole;

use Swoole\Coroutine\Channel;
use z;

/**
 * 连接池
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2018-09-29 19:36
 */
class Pool
{
    /**
     * @var Channel
     */
    protected $pool;

    /**
     * RedisPool constructor.
     * @param int $size 连接池的尺寸
     */
    public function init($size = 10)
    {
        $this->pool = new Channel($size);
        for ($i = 0; $i < $size; $i++) {
            Z::log($i, 'swoole', true);
            //$redis = new \Swoole\Coroutine\Redis();
            //$res = $redis->connect('127.0.0.1', 6379);
            //if ($res == false)
            //{
            //    throw new \RuntimeException("failed to connect redis server.");
            //}
            //else
            //{
            //    $this->put($redis);
            //}
        }
    }

    public function put($obj)
    {
        $this->pool->push($obj);
    }

    public function get()
    {
        return $this->pool->pop();
    }
}
