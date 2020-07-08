<?php

declare(strict_types=1);

namespace Morbo\React\Queue\Service\Adapters;

use Bunny\Async\Client;
use Bunny\Channel;
use Bunny\Message;
use Morbo\React\Amqp\DependencyInjection\ReactAmqpExtension;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function React\Promise\all;

class AmqpAdapter extends AbstractAdapter
{
    protected static $service = 'react.amqp';

    protected static $extension = ReactAmqpExtension::class;

    private Client $client;

    private string $prefix = '';

    private ?PromiseInterface $connectionPromise = null;

    private array $queueDeclaredPromises = [];

    private Channel $channel;

    private const LIFO = '_LIFO';

    public function __construct(ContainerInterface $container, string $prefix = '')
    {
        $this->prefix = $prefix;
        $this->client = $container->get('react.amqp')->getClient();
    }

    private function fixKey(string $key, $remove = false): string
    {
        return !$remove ? $this->prefix.$key : ltrim($key, $this->prefix);
    }

    private function ready(): PromiseInterface
    {
        if (is_null($this->connectionPromise)) {
            $this->connectionPromise = $this->client->connect()
                ->then(
                    function (Client $client) {
                        $this->client = $client;

                        return $client->channel()->then(
                            fn(Channel $channel) => $this->channel = $channel
                        );
                    },
                )->then(
                    function () {
                        return $this->channel->qos(0, 1);
                    }
                );
        }

        return $this->connectionPromise;
    }

    public function declareQueue(string $queueName): PromiseInterface
    {
        if (!isset($this->queueDeclaredPromises[$queueName])) {
            $this->queueDeclaredPromises[$queueName] = $this->ready()
                ->then(
                    function () use ($queueName) {
                        return $this->channel->queueDeclare(
                            $queueName,
                            false,
                            true,
                            false,
                            false
                        );
                    }
                );
        }

        return $this->queueDeclaredPromises[$queueName];
    }

    public function send(string $queue, string $value, bool $sendToQueueStart = false): PromiseInterface
    {
        if ($sendToQueueStart) {
            $queue .= self::LIFO;
        }

        $queue = $this->fixKey($queue);

        return $this->declareQueue($queue)
            ->then(
                fn() => $this->channel->publish(
                    $value,
                    [],
                    '',
                    $queue
                )
            );
    }

    public function receive(string $queue): PromiseInterface
    {
        $queue = $this->fixKey($queue);

        return all(
            [
                $this->declareQueue($queue.self::LIFO),
                $this->declareQueue($queue),
            ]
        )
            ->then(
                function () use ($queue) {
                    $deferred = new Deferred();

                    $this->channel->consume(
                        function (Message $message, Channel $channel, Client $client) use ($deferred) {
                            $deferred->resolve($message);
                        },
                        $queue.self::LIFO
                    );

                    $this->channel->consume(
                        function (Message $message, Channel $channel, Client $client) use ($deferred) {
                            $deferred->resolve($message);
                        },
                        $queue
                    );

                    return $deferred->promise();
                },
            )->then(
                function (Message $message) {
                    return $this->channel->ack($message)->then(
                        fn() => $message->content,
                    );
                }
            );
    }
}