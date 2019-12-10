<?php
/**
 * php crawler
 *
 * @package PHPCrawler
 * @author  fang
 * @date    2019-12-10
 */

namespace PHPCrawler;

use Amp\Artax\Client;
use Amp\Artax\Request;
use Amp\Artax\Response as ArtaxResponse;
use Amp\Loop;
use Amp\Artax\DefaultClient;
use Amp\Artax\HttpException;
use Amp\Promise;
use Symfony\Component\DomCrawler\Crawler;
use Psr\Log\AbstractLogger;

class PHPCrawler {
    const TASK_DRAIN = 'drain';

    private $queue = [];
    /** @var DefaultClient */
    private $httpCli;

    /** @var int times for retry，default 0 */
    private $defaultRetries;
    /** @var string default encoding */
    private $defaultEncoding;
    private $callbacks = [];

    private $maxConnections;

    /** @var bool dom parser */
    private $defaultDomParser = false;

    /** @var AbstractLogger */
    private $logger;
    /** @var int rate limit default 0 no limit */
    private $rateLimit;

    private $lastFetchingTaskTimeMs = 0;
    // how many tasks left
    private $taskCount = 0;
    // how many consumer running
    private $runningTaskCount = 0;
    private $drainEventTriggered = false;

    public function __construct($option = []) {
        // default options
        $this->maxConnections = $option['maxConnections'] ?? 1;
        $this->defaultRetries = $option['retries'] ?? 3;
        $this->defaultEncoding = $option['encoding'] ?? 'utf-8';
        $this->logger = $option['logger'] ?? null;

        // set up customized artax client
        $cli = $option['artaxClient'] ?? new DefaultClient();
        $artaxOpts = $option['artaxOptions'] ?? [];
        if (isset($option['timeout'])) {
            $artaxOpts[Client::OP_TRANSFER_TIMEOUT] = $option['timeout'];
        }
        if (isset($option['headers'])) {
            $artaxOpts[Client::OP_DEFAULT_HEADERS] = $option['headers'];
        }
        if ($artaxOpts) {
            $cli->setOptions($artaxOpts);
        }
        $this->httpCli = $cli;

        // dom解析
        if (!empty($option['domParser'])) {
            $this->defaultDomParser = $option['domParser'];
        }

        $this->rateLimit = $option['rateLimit'] ?? 0;
    }

    /**
     * register callbacks
     *
     * @param string   $taskName event name response|drain
     * @param callable $cb
     */
    public function on(string $taskName, callable $cb) {
        $this->callbacks[$taskName] = $cb;
    }

    /**
     * trigger an event
     *
     * @param string $eventName event name response|drain
     * @param array  $options   function parameters
     *
     * @return Promise
     */
    private function trigger(string $eventName, array $options = []): Promise {
        return \Amp\call(function () use ($eventName, $options) {
            $cb = $this->callbacks[$eventName] ?? null;
            if (!$cb) return null;
            try {
                return \call_user_func_array($cb, $options);
            } catch (\Exception $e) {
                $this->logger->warning('error', ['message' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * fetch next task from queue
     * success false|array()
     */
    private function fetchNextTask(): Promise {
        return \Amp\call(function () {
            // $this->logger->info("fetch next task", ['task count' => $this->taskCount, 'queue size' => count($this->queue)]);
            if ($this->taskCount === 0) {
                return self::TASK_DRAIN;
            }

            if (!$this->queue) {
                return false;
            }
            $task = array_pop($this->queue);

            if ($this->rateLimit) {
                $now = microtime(true) * 1000;
                $lastTime = $this->lastFetchingTaskTimeMs;
                $spanMs = 1000 / $this->rateLimit;
                $delayMs = $lastTime + $spanMs - $now;

                if ($delayMs > 0) {
                    $this->lastFetchingTaskTimeMs = $now + $delayMs;
                    // $this->logger->info("delayed for {$delayMs} ms");
                    yield \Amp\delay($delayMs);
                } else {
                    $this->lastFetchingTaskTimeMs = $now;
                }
            }

            return $task;
        });
    }

    /**
     * start a queue consumer
     */
    private function startQueueConsumer() {
        Loop::defer(function () {
            $task = yield $this->fetchNextTask();
            if ($task === self::TASK_DRAIN) {
                if (!$this->drainEventTriggered) {
                    $this->trigger(Event::DRAIN);
                    $this->logger->info("drain event triggered");
                    $this->drainEventTriggered = true;
                }
                $this->logger->info("consumer exit");
                return;
            }

            if (!$task) {
                // when no task found, wait for 100ms
                yield \Amp\delay(100);
            } else {
                try {
                    $uri = $task['uri'];
                    $this->logger && $this->logger->info("start fetching uri", ['uri' => $uri]);

                    $req = $this->buildRequest($task);
                    $opt = [];
                    if (isset($task['timeout'])) {
                        $opt[Client::OP_TRANSFER_TIMEOUT] = $task['timeout'];
                    }

                    /** @var ArtaxResponse $httpResp */
                    $httpResp = yield $this->httpCli->request($req, $opt);
                    $crawlerResp = new Response();
                    $crawlerResp->success = true;
                    $crawlerResp->headers = $httpResp->getHeaders();
                    $crawlerResp->status = $httpResp->getStatus();
                    $crawlerResp->body = yield $httpResp->getBody();
                    if ($task['encoding'] !== 'utf-8') {
                        $crawlerResp->body = mb_convert_encoding($crawlerResp->body, 'utf-8', $task['encoding']);
                    }
                    if ($task['domParser']) {
                        $crawlerResp->dom = new Crawler($crawlerResp->body);
                    }
                    $crawlerResp->rawResponse = $httpResp;
                    $crawlerResp->task = $task;

                    yield $this->trigger(Event::RESPONSE, [
                        $crawlerResp,
                        $this
                    ]);
                    $this->taskCount--;
                } catch (HttpException $e) {
                    $this->logger && $this->logger->warning("http error: " . $e->getMessage(), ["uri" => $uri, "task" => $task]);

                    if ($task['retries'] > 0) {     // retry for failure
                        echo "[WARNING] retry left for {$task['retries']} times $uri\n";
                        $task['retries']--;
                        $this->queue($task);
                    } else { // failed for all retries
                        $crawlerResp = new Response();
                        $crawlerResp->success = false;
                        $crawlerResp->error = $e->getMessage();
                        $crawlerResp->task = $task;

                        yield $this->trigger(Event::RESPONSE, [
                            $crawlerResp,
                            $this
                        ]);
                        $this->taskCount--;
                    }
                }
            }

            // start next queue consumer after a task is done
            Loop::defer(function () {
                $this->startQueueConsumer();
            });
        });
    }

    /**
     * push a task into the queue
     *
     * @param mixed $task string|array
     */
    public function queue($task) {
        $this->taskCount++;
        if (is_string($task)) {
            $task = [
                'uri' => $task,
            ];
        }

        $task['retries'] = $task['retries'] ?? $this->defaultRetries;
        $task['encoding'] = $task['encoding'] ?? $this->defaultEncoding;
        $task['domParser'] = $task['domParser'] ?? $this->defaultDomParser;

        $this->queue[] = $task;
        $this->logger && $this->logger->info("push to queue", ['task' => $task]);
    }

    /**
     * start amp event loop
     */
    public function run() {
        Loop::run(function () {
            // start maxConnections consumers
            for ($i = 0; $i < $this->maxConnections; $i++) {
                $this->logger && $this->logger->info('start a new consumer');
                $this->runningTaskCount++;
                $this->startQueueConsumer();
            }
        });
    }

    /**
     * build an artax request
     *
     * @param $task
     *
     * @return Request
     */
    private function buildRequest($task): Request {
        $uri = $task['uri'];
        $req = new Request($uri);
        if (isset($task['method'])) {
            $req = $req->withMethod($task['method']);
        }
        if (isset($task['headers'])) {
            $req = $req->withHeaders($task['headers']);
        }
        if (isset($task['body'])) {
            if (is_array($task['body'])) {
                // if application/json specified
                if (false !== strpos($req->getHeader('Content-Type'), 'application/json')) {
                    $body = json_encode($task['body']);
                } else {
                    $req = $req->withHeader('Content-Type', 'application/x-www-form-urlencoded');
                    $body = http_build_query($task['body']);
                }
            } else {
                $body = $task['body'];
            }

            $req = $req->withBody($body);
        }

        return $req;
    }
}
