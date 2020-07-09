<?php

declare(strict_types=1);

namespace Morbo\React\Queue\Service\Adapters;

use Clue\React\Redis\Client;
use Morbo\React\Redis\DependencyInjection\ReactRedisExtension;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RedisAdapter extends AbstractAdapter
{
    protected static $service = 'react.redis';

    protected static $extension = ReactRedisExtension::class;

    private Client $client;

    private string $prefix = '';

    private LoopInterface $loop;

    public function __construct(ContainerInterface $container, string $prefix = '')
    {
        $this->prefix = $prefix;
        $this->client = $container->get('react.redis')->getClient();
        $this->loop = $container->get('react.loop')->getLoop();
    }

    private function fixKey(string $key, $remove = false): string
    {
        return !$remove ? $this->prefix.$key : ltrim($key, $this->prefix);
    }

    public function send(string $queue, string $value, bool $sendToQueueStart = false): PromiseInterface
    {
        $cmd = $sendToQueueStart ? 'rpush' : 'lpush';

        return $this->client->$cmd($this->fixKey($queue), $value);
    }

    public function receive(string $queue): PromiseInterface
    {
        $deferred = new Deferred();

        $block = false;
        $this->loop->addPeriodicTimer(
            0,
            function (TimerInterface $timer) use ($queue, $deferred, &$block) {
                if (!$block) {
                    $block = true;
                    $this->client->rpop($this->fixKey($queue))
                        ->then(
                            function (?string $result) use ($timer, $deferred, &$block) {
                                if (!is_null($result)) {
                                    $this->loop->cancelTimer($timer);
                                    $deferred->resolve($result);
                                }
                                $block = false;
                            }
                        );
                }
            }
        );

        return $deferred->promise();
    }
}