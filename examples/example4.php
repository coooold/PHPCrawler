<?php
include(__DIR__ . '/../vendor/autoload.php');

use PHPCrawler\PHPCrawler;
use PHPCrawler\Response;

$logger = new Monolog\Logger("example3");
try {
    $logger->pushHandler(new \Monolog\Handler\StreamHandler(STDOUT, \Monolog\Logger::INFO));
} catch (\Exception $e) {
}

$crawler = new PHPCrawler([
    'maxConnections' => 10,
    'rateLimit' => 2,   // req per second
    'domParser' => true,
    'timeout' => 30000,
    'retries' => 3,
    'logger' => $logger,
]);

$crawler->on('response', function (Response $res, PHPCrawler $crawler) {
    if (!$res->success) {
        return;
    }

    if ($res->task['type'] && $res->task['type'] === 'list') {
        $title = $res->dom->filter("title")->html();
        echo ">>> list title: {$title}\n";
        $link = $res->dom->filter(".right a")->first()->attr("href");
        $crawler->queue([
            'uri' => $link,
            'type' => 'article',
        ]);
    } elseif ($res->task['type'] && $res->task['type'] === 'article') {
        $title = $res->dom->filter("title")->html();
        echo ">>> article title: {$title}\n";
    }
});

for ($page = 1; $page <= 10; $page++) {
    $crawler->queue([
        'uri' => "http://www.qbaobei.com/jiaoyu/gshb/List_{$page}.html",
        'type' => 'list',
    ]);
}

$crawler->run();