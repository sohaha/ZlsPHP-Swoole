<?php declare (strict_types = 1);
/*
 * @Author: seekwe
 * @Date:   2019-05-28 15:27:25
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-06-03 16:31:00
 */

namespace Zls\Swoole\Coroutine;

use Swoole\Coroutine as c;
use Swoole\Coroutine\Channel;
use Z;
use Zls\Swoole\SwooleException;
use Zls\Swoole\Utils;

class SwooleCoroutine implements Coroutine
{
    use Utils;
    /** @var Channel $chan */
    private $chan;
    private $sum;
    private $outtime;
    private $data;

    public function __construct($timeout, $sum)
    {
        $this->chan = new Channel($sum);
        $this->outtime = $timeout;
        $this->data = [];
    }

    public function sleep($time)
    {
        c::sleep($time);
    }

    public function run(string $name, \Closure $cb)
    {
        ++$this->sum;
        $this->data[] = $name;
        go(function () use ($name, $cb) {
            try {
                $res = $this->chan->push(['name' => $name, 'data' => $cb()], $this->outtime);
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
            if ($res === false) {
                break;
            }

            if (Z::arrayKeyExists('err', $res)) {
                z::log($res);
                /** @var \Exception $e */
                $e = $res['err'];
                $err = method_exists($e, 'render') ? $e->render() : $e->getMessage();
                /** @noinspection PhpUnhandledExceptionInspection */
                throw new SwooleException($err, 500, 'Exception', $e->getFile(), $e->getLine());
            } else {

            }
            $data[$res['name']] = ['data' => $res['data'], 'err' => $res['err'], 'time' => time() - $t];
        }
        $keys = array_keys($data);
        $errKey = array_diff($this->data, $keys);
        foreach ($errKey as $v) {
            $data[$v] = ['data' => null, 'err' => 'timeout', 'time' => time() - $t];
        }
        return $data;
    }

    public function defer(\Closure $cb)
    {
        defer($cb);
    }
}
