<?php
/**
 * Swoole_http_request Document
 *
 * @author seekwe <seekwe@gmail.com>
 */
class swoole_http_request {

public $fd = 0;
public $header;
public $server;
public $request;
public $cookie;
public $get;
public $files;
public $post;
public $tmpfiles;

public function rawcontent() {}
public function getData() {}
public function __destruct() {}
}