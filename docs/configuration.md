# Configuration

## PHPCrawler default configuration

+ *maxConnections* int default 1, http concurrency
+ *domParser* boolean default false, Symfony\Component\DomCrawler will be injected when true
+ *retries* int default 3, retry when http failure
+ *encoding* string default 'utf-8', HTTP body will be converted to utf-8 from the default encoding
+ *logger* Psr\Log\AbstractLogger default null
+ *artaxClient* Amp\Artax\Client default Amp\Artax\DefaultClient, customized client could be used instead of the default one
+ *artaxOptions* array, options passed to artax client. See [Amp\Artax\Client](https://github.com/amphp/artax/blob/master/lib/Client.php) OP_* constants
+ *timeout* int, http transfer timeout
+ *headers* array, default headers passed to artax client
+ *rateLimit* int default 0 (no limit), requests per second limitation

```PHP
new PHPCrawler([
     'maxConnections' => 2,
     'domParser' => true,
     'timeout' => 30000,
     'retries' => 0,
     'logger' => $logger,
 ]);
```

## Per task configuration

+ *uri* string
+ *method* string GET|POST|PUT|... default GET, http method
+ *body* string, content transferred with a http request 
+ *domParser* boolean default false, Symfony\Component\DomCrawler will be injected when true
+ *retries* int default 3, retry when http failure
+ *encoding* string default 'utf-8', HTTP body will be converted to utf-8 from the default encoding
+ *timeout* int, http transfer timeout
+ *headers* array, default headers passed to artax client

```PHP
$crawler->queue([
    'uri' => 'https://www.foxnews.com/politics/trump-impeachment-hearing-gohmert-nadler-berke',
    'method' => 'POST',
    'body' => [
        'id' => 123,
    ],
    'headers' => [
        'User-Agent' => 'Opera/9.80 (Macintosh; Intel Mac OS X 10.6.8; U; en) Presto/2.8.131 Version/11.11',
        'Content-Type' => 'application/x-www-form-urlencoded',  // or application/json
    ]
]);
```