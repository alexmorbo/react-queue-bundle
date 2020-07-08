<?php

declare(strict_types=1);

namespace Morbo\React\Queue\Service;

use Morbo\React\Loop\Service\Loop;
use Morbo\React\Loop\Service\LoopAwareTrait;
use Morbo\React\Queue\Service\Adapters\QueueAdapterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @method redis()
 * @method amqp()
 */
class Queue
{
    use LoopAwareTrait;

    protected ContainerInterface $container;

    private array $adapters = [];

    public function __construct(ContainerInterface $container, Loop $loop)
    {
        $this->container = $container;
        $this->loop = $loop->getLoop();

        $this->loadAdapters();
    }

    private function loadAdapters(): void
    {
        $adapters = $this->container->getParameter('react.queue.adapters');

        foreach ($adapters as $adapter => $adapterData) {
            $adapterClass = $adapterData['adapter'];

            $this->adapters[$adapter] = new $adapterClass($this->container, $adapterData['prefix']);
        }
    }

    public function __call($name, $args): ?QueueAdapterInterface
    {
        return $this->adapters[$name] ?? null;
    }
}