<?php
include(__DIR__ . '/../vendor/autoload.php');

use PHPCrawler\PHPCrawler;
use PHPCrawler\Response;
use Symfony\Component\DomCrawler\Crawler;

$logger = new Monolog\Logger("example3");
try {
    $logger->pushHandler(new \Monolog\Handler\StreamHandler(STDOUT, \Monolog\Logger::INFO));
} catch (\Exception $e) {
}

$crawler = new PHPCrawler([
    'maxConnections' => 1,
    'rateLimit' => 2,   // req per second
    'domParser' => true,
    'timeout' => 30000,
    'retries' => 3,
    'logger' => $logger,
]);

$crawler->on('response', function (Response $res) {
    if (!$res->success) {
        return;
    }

    echo "done\n";
});

$crawler->queue('https://m.sohu.com/');
$crawler->queue('https://m.sohu.com/');
$crawler->run();