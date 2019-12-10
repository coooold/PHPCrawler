<?php

namespace PHPCrawler;

use Amp\Artax\Response as ArtaxResponse;
use Symfony\Component\DomCrawler\Crawler;

class Response {
    public $success = false;
    public $headers = [];
    public $body = '';
    public $status = 0;
    /**
     * @var ArtaxResponse
     */
    public $rawResponse;
    /**
     * @var string 错误信息
     */
    public $error;
    /**
     * @var array 创建时候用的参数
     */
    public $task;

    /**
     * @var Crawler
     */
    public $dom;
}