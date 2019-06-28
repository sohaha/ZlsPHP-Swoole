<?php
declare (strict_types=1);
/*
 * @Author: seekwe
 * @Date:   2019-05-28 15:27:25
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-05-31 16:01:00
 */

namespace Zls\Swoole;

use z;
use Zls;
use Zls\Command\Utils as CommandUtils;
use Zls\Action\FileUp;

trait Utils
{
    use CommandUtils;

    public function printLog($msg, $color = '')
    {
        $this->printStr('[ Swoole ]', 'blue', '');
        $this->printStr(': ');
        $this->printStrN($msg, $color);
    }

    public function log(...$_)
    {
        z::log($_, 'swoole');
    }

    public function errorLog(...$_)
    {
        z::log($_, 'swoole/err');
    }

    public function fileMIME($filename)
    {
        $file   = fopen($filename, "rb");
        $bytes4 = fread($file, 4);
        fclose($file);
        $strInfo  = @unpack("C4chars", $bytes4);
        $typeCode = dechex($strInfo['chars1']) .
            dechex($strInfo['chars2']) .
            dechex($strInfo['chars3']) .
            dechex($strInfo['chars4']);
        switch ($typeCode) {
            case "ffd8ffe0":
            case "ffd8ffe1":
            case "ffd8ffe2":
                $type = 'image/jpeg;';
                break;
            case "89504e47":
                $type = 'image/png;';
                break;
            case "3c737667":
                $type = 'image/svg+xml;';
                break;
            case "47494638":
                $type = 'image/gif;';
                break;
            case "504B0304":
                $type = 'application/zip;';
                break;
            case "25504446":
                $type = 'application/pdf;';
                break;
            case "5A5753":
                $type = 'application/swf;';
                break;
            case "3c3f786d":
                $type = 'application/xml;';
                break;
            case "3c68746d":
            case "3c21444f":
                $type = 'text/html;';
                break;
            case "0000":
                $type = 'text/plain;';
                break;
            case "2166756e":
            case "2f2a2aa":
                $type = 'application/javascript;';
                break;
            case "68746d6c":
                $type = 'text/css;';
                break;
            default:
                $type = 'application/octet-stream;' . $typeCode;
                break;
        }

        return $type;
        // $finfo = \finfo_open(FILEINFO_MIME);
        // return Z::tap(\finfo_file($finfo, $file), function () use ($finfo) {
        //     \finfo_close($finfo);
        // });
    }
}
