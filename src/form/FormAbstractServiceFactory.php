<?php

declare(strict_types=1);

namespace Jield\Form;

use Jield\Stdlib\Service;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function is_array;
use function is_string;

final class FormAbstractServiceFactory implements AbstractFactoryInterface
{
    private ?array $config = null;

    /** @var string Top-level configuration key indicating forms configuration */
    private string $configKey = 'forms';

    /** @var null|Factory Form factory used to create forms */
    private ?Factory $factory = null;

    /**
     * Create a form (v3)
     *
     * @throws Exception\ExceptionInterface
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): FormInterface {
        $config = $this->getConfig(container: $container);
        $config = Assert::isArray($config[$requestedName]);
        $factory = $this->getFormFactory(container: $container);

        $this->marshalInputFilter(config: $config, container: $container, formFactory: $factory);
        return $factory->createForm(spec: $config);
    }

    /**
     * Can we create the requested service? (v3)
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function canCreate(ContainerInterface $container, string $requestedName): bool
    {
        // avoid infinite loops when looking up config
        if ($requestedName === 'config') {
            return false;
        }

        $config = $this->getConfig(container: $container);
        if (empty($config)) {
            return false;
        }

        return (
            isset($config[$requestedName])
            && is_array(value: $config[$requestedName])
            && !empty($config[$requestedName])
        );
    }

    /**
     * Get forms configuration, if any
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getConfig(ContainerInterface $container): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        if (!$container->has('config')) {
            $this->config = [];
            return $this->config;
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');
        if (!isset($config[$this->configKey]) || !is_array(value: $config[$this->configKey])) {
            $this->config = [];
            return $this->config;
        }

        $this->config = $config[$this->configKey];
        return $this->config;
    }

    /**
     * Retrieve the form factory, creating it if necessary
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getFormFactory(ContainerInterface $container): Factory
    {
        if ($this->factory instanceof Factory) {
            return $this->factory;
        }

        $elements = null;
        if ($container->has(FormElementManager::class)) {
            $elements = Service::get($container, FormElementManager::class);
        }

        $this->factory = new Factory(formElementManager: $elements);
        return $this->factory;
    }

    /**
     * Marshal the input filter into the configuration
     *
     * If an input filter is specified:
     * - if the InputFilterManager is present, checks if it's there; if so,
     *   retrieves it and resets the specification to the instance.
     * - otherwise, pulls the input filter factory from the form factory, and
     *   attaches the FilterManager and ValidatorManager to it.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function marshalInputFilter(array &$config, ContainerInterface $container, Factory $formFactory): void
    {
        if (!isset($config['input_filter'])) {
            return;
        }

        if ($config['input_filter'] instanceof InputFilterInterface) {
            return;
        }

        if (is_string(value: $config['input_filter']) && $container->has(InputFilterPluginManager::class)) {
            $inputFilters = Service::get($container, InputFilterPluginManager::class);
            if ($inputFilters->has($config['input_filter'])) {
                $config['input_filter'] = $inputFilters->get($config['input_filter']);
                return;
            }
        }

        $formFactory->setInputFilterFactory(\Jield\Form\InputFilterFactory::create($container));
    }
}
