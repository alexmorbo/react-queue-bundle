<?php

namespace Morbo\React\Queue\Service\Adapters;

abstract class AbstractAdapter implements QueueAdapterInterface
{
    protected static $service = '';

    protected static $extension = '';

    public static function getServiceName(): string
    {
        return static::$service;
    }

    public static function getExtensionClass(): string
    {
        return static::$extension;
    }
}