<?php
include(__DIR__ . '/../vendor/autoload.php');

use PHPCrawler\PHPCrawler;
use PHPCrawler\Response;

$cli = new \Amp\Artax\DefaultClient();
$logger = new Monolog\Logger("example1");
try {
    $logger->pushHandler(new \Monolog\Handler\StreamHandler(STDOUT, \Monolog\Logger::INFO));
} catch (\Exception $e) {
}


$crawler = new PHPCrawler([
    'maxConnections' => 2,
    'domParser' => true,
    'timeout' => 30000,
    'retries' => 0,
    'logger' => $logger,
]);

$crawler->on('response', function (Response $res) use ($cli) {
    /** @var \Amp\Artax\Response $res */
    $res = yield $cli->request("https://www.foxnews.com/politics/lindsey-graham-adam-schiff-is-doing-a-lot-of-damage-to-the-country-and-he-needs-to-stop");
    $body = yield $res->getBody();
    echo "=======> body " . strlen($body) . " bytes \n";
});

$crawler->on('drain', function () {
    echo "[INFO] drain\n";
});

$crawler->queue([
    'uri' => 'https://www.foxnews.com/politics/trump-impeachment-hearing-gohmert-nadler-berke',
    'method' => 'POST',
    'body' => [
        'id' => 123,
    ],
    'headers' => [
        'User-Agent' => 'Opera/9.80 (Macintosh; Intel Mac OS X 10.6.8; U; en) Presto/2.8.131 Version/11.11',
    ]
]);
$crawler->queue('https://www.foxnews.com/politics/pete-buttigieg-mckinsey-consulting-clients-approval');
$crawler->run();