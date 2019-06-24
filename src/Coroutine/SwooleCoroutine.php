<?php declare (strict_types = 1);
/*
 * @Author: seekwe
 * @Date:   2019-05-28 15:27:25
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-06-23 19:27:01
 */

namespace Zls\Swoole\Coroutine;

use Swoole\Coroutine as c;
use Swoole\Coroutine\Channel;
use Z;
use Zls\Swoole\SwooleException;

class SwooleCoroutine extends Coroutine
{
    /** @var Channel $chan */
    private $chan;
    private $sum = 0;
    private $outtime;
    private $data;

    public function __construct($timeout, $sum)
    {
        $this->chan = new Channel($sum);
        $this->outtime = $timeout;
        $this->data = [];
    }

    public static function sleep($time)
    {
        c::sleep($time);
    }

    /**
     * 执行一个协程任务
     * @param string|\Closure $name
     * @param \Closure $func
     * @return void
     */
    public function run($name, \Closure $func)
    {
        if (is_callable($name)) {
            $func = $name;
            $name = null;
        }
        if (!$name) {
            $name = (string) $this->sum;
        }
        ++$this->sum;
        $this->data[] = $name;
        self::go(function () use ($name, $func) {
            try {
                $res = $this->chan->push(['name' => $name, 'data' => $func()], $this->outtime);
            } catch (\Zls_Exception_Exit $e) {
                $res = $this->chan->push(['name' => $name, 'data' => $e->getMessage()], $this->outtime);
            } catch (\Error | \Exception $e) {
                $this->errorLog('CoroutineError', $e->getMessage());
                $res = $this->chan->push(['name' => $name, 'data' => false, 'err' => $e], $this->outtime);
            }
            if ($res === false) {
                z::log("Channel full: " . $this->chan->errCode);
            }
        });
    }

    public function data(): array
    {
        $data = [];
        for ($i = 0; $i < $this->sum; $i++) {
            $t = time();
            /** @noinspection PhpMethodParametersCountMismatchInspection */
            $res = $this->chan->pop($this->outtime);
            $err = null;
            if ($res === false) {
                break;
            }
            if (Z::arrayKeyExists('err', $res)) {
                Z::log($res, "swoole/err");
                /** @var \Exception $e */
                $e = $res['err'];
                $err = method_exists($e, 'render') ? $e->render() : $e->getMessage();
                /** @noinspection PhpUnhandledExceptionInspection */
                throw new SwooleException($err, 500, 'Exception', $e->getFile(), $e->getLine());
            } else {}
            $data[$res['name']] = ['data' => $res['data'], 'err' => $err, 'time' => time() - $t];
        }
        $keys = array_keys($data);
        $errKey = array_diff($this->data, $keys);
        foreach ($errKey as $v) {
            $data[$v] = ['data' => null, 'err' => 'timeout', 'time' => time() - $t];
        }
        return $data;
    }

    public static function defer(\Closure $func)
    {
        defer($func);
    }

    public static function go(\Closure $func)
    {
        go($func);
    }
}
