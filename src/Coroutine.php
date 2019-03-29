<?php declare(strict_types=1);

namespace Zls\Swoole;

use Swoole\Coroutine as co;
use Swoole\Coroutine\Channel;
use Z;

/**
 * 协程处理
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2018-09-29 16:00
 */
class Coroutine
{
    /** @var Channel $chan */
    private $chan;
    private $sum;
    private $outtime;

    public function __construct(int $outtime = 10, int $sum = 0)
    {
        // todo 如果非协程模式是否要做兼任处理?
        $this->chan    = new Channel($sum);
        $this->outtime = $outtime;
    }

    public function sleep($time)
    {
        co::sleep($time);
    }

    public function run(string $name, callable $cb): void
    {
        ++$this->sum;
        go(function () use ($name, $cb) {
            try {
                $this->chan->push(['name' => $name, 'data' => $cb()]);
            } catch (\Error | \Exception $e) {
                z::log(['协程内出错了', $e->getMessage()], 'SwooleError');
                $this->chan->push(['name' => $name, 'data' => false, 'err' => $e]);
            }
        });
    }

    public function data(): array
    {
        $data = [];
        for ($i = 0; $i < $this->sum; $i++) {
            /** @noinspection PhpMethodParametersCountMismatchInspection */
            $res = $this->chan->pop($this->outtime);
            if (Z::arrayKeyExists('err', $res)) {
                /** @var \Exception $e */
                $e   = $res['err'];
                $err = method_exists($e, 'render') ? $e->render() : $e->getMessage();
                /** @noinspection PhpUnhandledExceptionInspection */
                throw new SwooleHandler($err, 500);
            }
            $data[$res['name']] = $res['data'];
        }

        return $data;
    }
}
