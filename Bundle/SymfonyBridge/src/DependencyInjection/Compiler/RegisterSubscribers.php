<?php

namespace SimpleBus\SymfonyBridge\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RegisterSubscribers implements CompilerPassInterface
{
    use CollectServices;

    private $callableServiceId;
    private $serviceLocatorId;
    private $tag;
    private $keyAttribute;

    /**
     * @param string $callableServiceId The service id of the MessageSubscriberCollection
     * @param string $serviceLocatorId  The service id of the ServiceLocator
     * @param string $tag               The tag name of message subscriber services
     * @param string $keyAttribute      The name of the tag attribute that contains the name of the subscriber
     */
    public function __construct($callableServiceId, $serviceLocatorId, $tag, $keyAttribute)
    {
        $this->callableServiceId = $callableServiceId;
        $this->serviceLocatorId = $serviceLocatorId;
        $this->tag = $tag;
        $this->keyAttribute = $keyAttribute;
    }

    /**
     * Search for message subscriber services and provide them as a constructor argument to the message subscriber
     * collection service.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has($this->callableServiceId)) {
            return;
        }

        if (!$container->has($this->serviceLocatorId)) {
            return;
        }

        $callableDefinition = $container->findDefinition($this->callableServiceId);
        $serviceLocatorDefinition = $container->findDefinition($this->serviceLocatorId);

        $handlers = array();
        $services = array();

        $this->collectServiceIds(
            $container,
            $this->tag,
            $this->keyAttribute,
            function ($key, $serviceId, array $tagAttributes) use (&$handlers, &$services) {
                if (isset($tagAttributes['method'])) {
                    // Symfony 3.3 supports services by classname. This interferes with `is_callable`
                    // in `ServiceLocatorAwareCallableResolver`
                    $callable = [
                        'serviceId' => $serviceId,
                        'method'    => $tagAttributes['method'],
                    ];
                } else {
                    $callable = $serviceId;
                }

                $handlers[ltrim($key, '\\')][] = $callable;
                $services[$serviceId] = new Reference($serviceId);
            }
        );

        $callableDefinition->replaceArgument(0, $handlers);
        $serviceLocatorDefinition->replaceArgument(0, $services);
    }
}
