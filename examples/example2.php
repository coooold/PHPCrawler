<?php
include(__DIR__ . '/../vendor/autoload.php');

use PHPCrawler\PHPCrawler;
use PHPCrawler\Response;
use Symfony\Component\DomCrawler\Crawler;

$cli = new \Amp\Artax\DefaultClient();
$logger = new Monolog\Logger("example2");
try {
    $logger->pushHandler(new \Monolog\Handler\StreamHandler(STDOUT, \Monolog\Logger::INFO));
} catch (\Exception $e) {
}

$crawler = new PHPCrawler([
    'maxConnections' => 2,
    'domParser' => true,
    'timeout' => 30000,
    'retries' => 3,
    'logger' => $logger,
    'artaxClient' => $cli,
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