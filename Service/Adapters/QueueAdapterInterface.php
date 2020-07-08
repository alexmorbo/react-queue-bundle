<?php

declare(strict_types=1);

namespace Morbo\React\Queue\Service\Adapters;

use React\Promise\PromiseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

interface QueueAdapterInterface
{
    public function __construct(ContainerInterface $container);

    public static function getServiceName(): string;

    public static function getExtensionClass(): string;

    public function send(string $queue, string $value, bool $sendToQueueStart = false): PromiseInterface;

    public function receive(string $queue): PromiseInterface;
}