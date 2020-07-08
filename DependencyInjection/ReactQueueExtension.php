<?php

declare(strict_types=1);

namespace Morbo\React\Queue\DependencyInjection;

use Morbo\React\Loop\DependencyInjection\ReactLoopExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ReactQueueExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (! $container->has('react.loop')) {
            $extension = new ReactLoopExtension();
            $extension->load([], $container);
        }

        $container->setParameter('react.queue.adapters', $config['adapters']);
        foreach ($config['adapters'] as $adapter => $adapterData) {
            $adapterClass = $adapterData['adapter'];
            $serviceName = $adapterClass::getServiceName();
            if (! $container->has($serviceName)) {
                $extName = $adapterClass::getExtensionClass();
                $extension = new $extName();
                $extension->load(['react' => $adapterData['configuration']], $container);
            }
        }

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yml');
    }
}