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