<?php

declare(strict_types=1);

namespace Morbo\React\Queue;

use Morbo\React\Queue\DependencyInjection\ReactQueueExtension;
use Morbo\React\Queue\Service\Adapters\AmqpAdapter;
use Morbo\React\Queue\Service\Adapters\RedisAdapter;
use Morbo\React\Queue\Service\Queue;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function React\Promise\all;

class ReactQueueTest extends BaseTestCase
{
    private ContainerBuilder $container;

    public function setUp(): void
    {
        $this->container = new ContainerBuilder();

        $extension = new ReactQueueExtension();
        $extension->load(
            [
                'react' => [
                    'adapters' => [
                        'redis' => [
                            'adapter'       => RedisAdapter::class,
                            'configuration' => [
                                'redis' => [
                                    'dsn' => 'redis://localhost:16379',
                                ],
                            ],
                            'prefix'        => 'redis:',
                        ],
                        'amqp'  => [
                            'adapter'       => AmqpAdapter::class,
                            'configuration' => [
                                'amqp' => [
                                    'host'     => 'localhost',
                                    'port'     => 5672,
                                    'vhost'    => '/',
                                    'user'     => 'guest',
                                    'password' => 'guest',
                                ],
                            ],
                            'prefix'        => 'amqp_',
                        ],
                    ],
                ],
            ],
            $this->container
        );
    }

    public function testDependencyInjection()
    {
        $this->assertTrue($this->container->has('react.queue'), '"react.queue" is loaded');
        $this->assertTrue($this->container->has(Queue::class), '"Queue::class" is loaded');
    }

    public function testAdapters()
    {
        /** @var Queue $queue */
        $queue = $this->container->get('react.queue');

        $promises = [];
        foreach (['amqp', 'redis'] as $adapter) {
            $queueName = 'test_queue';
            $testMessage = uniqid();

            $promises[$adapter] = $queue->$adapter()->receive($queueName)
                ->then(
                    function ($message) use ($testMessage) {
                        $this->assertSame($message, $testMessage);

                        return $message;
                    },
                    fn() => dd(func_get_args(), 'ERR')
                );

            $queue->getLoop()->addTimer(
                2,
                function () use ($queue, $queueName, $testMessage, $adapter) {
                    $queue->$adapter()->send($queueName, $testMessage, true);
                }
            );
        }

        all($promises)->then(
            function (array $result) use ($queue) {
                $queue->getLoop()->stop();

                dump($result);
                $this->assertTrue(true);
            }
        );

        $queue->getLoop()->run();
    }
}
