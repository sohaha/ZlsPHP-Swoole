<?php
namespace Zls\Swoole\Command;

use Z;

class Swoole extends \Zls\Command\Command
{
    public function execute($args)
    {
        $active = z::arrayGet($args, 2);
        if (class_exists('swoole_http_server')) {
            if (method_exists($this, $active)) {
                $this->$active($args);
            } else {
                $this->error('Warning: unknown method');
            }
        } else {
            $this->error('Warning: swoole not found !\nPlease install: https://wiki.swoole.com/wiki/page/6.html');
        }
    }
    
    public function reload()
    {
        $SwooleMain = z::extension('Swoole\Main');
 
        $this->printStrN($SwooleMain->reload());
    }
        public function restart()
    {
        $SwooleMain = z::extension('Swoole\Main');
 
        $this->printStrN($SwooleMain->restart());
    }
    
    public function server()
    {
        /**
            * @var \Zls\Swoole\Main $SwooleMain
            */
        $SwooleMain = z::extension('Swoole\Main');
        $SwooleMain->start();
    }
    public function options()
    {
        return ['--force, -F' => ' Overwrite old config file'];
    }
    public function example()
    {
    }
    /**
* 命令介绍
* @return string
*/
    public function description()
    {
        return 'Start Swolole';
    }
    public function handle()
    {
        return true;
    }
}
