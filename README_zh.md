# PHPCrawler

最厉害的PHP抓取框架

功能:

 * 自动使用 Symfony\Component\DomCrawler 解析dom节点 
 * 并行请求，自动重试
 * 控制请求速度
 * 强制转换utf8-编码
 * PHP 7.2以上可用
 
感谢以下项目：
 - [Amp](https://amphp.org/) a non-blocking concurrency framework for PHP
 - [Artax](https://amphp.org/artax/) An Asynchronous HTTP Client for PHP
 - [Node crawler](https://github.com/bda-research/node-crawler) Most powerful, popular and production crawling/scraping package for Node

node-crawler真的很好用，PHPCrawler项目尽可能与node-crawler保持一致。

# 目录
- [Get started](#get-started)
  * [Install](#install)
  * [Basic usage](#basic-usage)
  * [Slow down](#slow-down)
  * [Custom parameters](#custom-parameters)
  * [Raw body](#raw-body)
- [Events](#events)
  * [Event: response](#eventresponse)
  * [Event: drain](#eventdrain)
- [Advanced](#advanced)
  * [Encoding](#encoding)
  * [Logger](#logger)
  * [Coroutine](#logger)
- [Other](#other)
  * [API reference](/docs/api.md)
  * [Configuration](/docs/configuration.md)
- [Work with DomParser](#work-with-domparser)

# Get started

## Install

```sh
$ composer require "coooold/crawler"
```

## Basic usage

```PHP
use PHPCrawler\PHPCrawler;
use PHPCrawler\Response;
use Symfony\Component\DomCrawler\Crawler;

$logger = new Monolog\Logger("fox");
try {
    $logger->pushHandler(new \Monolog\Handler\StreamHandler(STDOUT, \Monolog\Logger::INFO));
} catch (\Exception $e) {
}

$crawler = new PHPCrawler([
    'maxConnections' => 2,
    'domParser' => true,
    'timeout' => 3000,
    'retries' => 3,
    'logger' => $logger,
]);

$crawler->on('response', function (Response $res) use ($cli) {
    if (!$res->success) {
        return;
    }

    $title = $res->dom->filter("title")->html();
    echo ">>> title: {$title}\n";
    $res->dom
        ->filter('.related-item a')
        ->each(function (Crawler $crawler) {
            echo ">>> links: ", $crawler->text(), "\n";
        });
});

$crawler->queue('https://www.foxnews.com/');
$crawler->run();
```

## Slow down
Use `rateLimit` to slow down when you are visiting web sites.

```PHP
$crawler = new PHPCrawler([
    'maxConnections' => 10,
    'rateLimit' => 2,   // reqs per second
    'domParser' => true,
    'timeout' => 30000,
    'retries' => 3,
    'logger' => $logger,
]);

for ($page = 1; $page <= 100; $page++) {
    $crawler->queue([
        'uri' => "http://www.qbaobei.com/jiaoyu/gshb/List_{$page}.html",
        'type' => 'list',
    ]);
}

$crawler->run(); //between two tasks, avarage time gap is 1000 / 2 (ms)
```

## Custom parameters

Sometimes you have to access variables from previous request/response session, what should you do is passing parameters as same as options:

```PHP
$crawler->queue([
    'uri' => 'http://www.google.com',
    'parameter1' => 'value1',
    'parameter2' => 'value2',
])
```

then access them in callback via `$res->task['parameter1']`, `$res->task['parameter2']` ...

## Raw body

If you are downloading files like image, pdf, word etc, you have to save the raw response body which means Crawler shouldn't convert it to string. To make it happen, you need to set encoding to null

```PHP
$crawler = new PHPCrawler([
    'maxConnections' => 10,
    'rateLimit' => 2,   // req per second
    'domParser' => false,
    'timeout' => 30000,
    'retries' => 3,
    'logger' => $logger,
]);

$crawler->on('response', function (Response $res, PHPCrawler $crawler) {
    if (!$res->success) {
        return;
    }

    echo "write ".$res->task['fileName']."\n";
    file_put_contents($res->task['fileName'], $res->body);
});

$crawler->queue([
    'uri' => "http://www.gutenberg.org/ebooks/60881.txt.utf-8",
    'fileName' => '60881.txt',
]);

$crawler->queue([
    'uri' => "http://www.gutenberg.org/ebooks/60882.txt.utf-8",
    'fileName' => '60882.txt',
]);

$crawler->queue([
    'uri' => "http://www.gutenberg.org/ebooks/60883.txt.utf-8",
    'fileName' => '60883.txt',
]);

$crawler->run();
```

## Events

### Event::RESPONSE
Triggered when a request is done.

```PHP
$crawler->on('response', function (Response $res, PHPCrawler $crawler) {
    if (!$res->success) {
        return;
    }
});
```

### Event::DRAIN
Triggered when queue is empty.

```PHP
$crawler->on('drain', function () {
    echo "queue is drained\n";
});
```

## Advanced

### Encoding

HTTP body will be converted to utf-8 from the default encoding.

```PHP
$crawler = new PHPCrawler([
    'encoding' => 'gbk,
]);
```

### Logger

A PSR logger instance could be used.

```PHP
$logger = new Monolog\Logger("fox");
$logger->pushHandler(new \Monolog\Handler\StreamHandler(STDOUT, \Monolog\Logger::INFO));

$crawler = new PHPCrawler([
    'logger' => $logger,
]);
```

See [Monolog Reference](https://github.com/Seldaek/monolog).

### Coroutine
PHPCrawler, based on amp non-blocking concurrency framework, could work with coroutines, ensuring excellent performance.
[Amp async packages](https://amphp.org/packages) should be used in callbacks, that is to say, neither php native mysql client nor php native file io is not recommended.
The keyword yield like await in ES6, introduced the non-blocking io.

```PHP
$crawler->on('response', function (Response $res) use ($cli) {
    /** @var \Amp\Artax\Response $res */
    $res = yield $cli->request("https://www.foxnews.com/politics/lindsey-graham-adam-schiff-is-doing-a-lot-of-damage-to-the-country-and-he-needs-to-stop");
    $body = yield $res->getBody();
    echo "=======> body " . strlen($body) . " bytes \n";
});
```

## Work with DomParser

[Symfony\Component\DomCrawler](https://packagist.org/packages/symfony/dom-crawler) is a handy tool for crawling pages. 
Response::dom will be injected with an instance of Symfony\Component\DomCrawler\Crawler.

```PHP
$crawler->on('response', function (Response $res) use ($cli) {
    if (!$res->success) {
        return;
    }

    $title = $res->dom->filter("title")->html();
    echo ">>> title: {$title}\n";
    $res->dom
        ->filter('.related-item a')
        ->each(function (Crawler $crawler) {
            echo ">>> links: ", $crawler->text(), "\n";
        });
});
```

See [DomCrawler Reference](https://symfony.com/doc/current/components/dom_crawler.html).

## Other

[API reference](/docs/api.md)

[Configuration](/docs/configuration.md)
