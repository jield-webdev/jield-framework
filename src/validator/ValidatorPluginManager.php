<?php

declare(strict_types=1);

namespace Jield\Validator;

use Closure;
use Laminas\ServiceManager\AbstractSingleInstancePluginManager;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorInterface;
use Laminas\Validator\ValidatorPluginManagerAwareInterface;
use Psr\Container\ContainerInterface;

use function array_replace_recursive;

/**
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 * @extends AbstractSingleInstancePluginManager<ValidatorInterface>
 */
final class ValidatorPluginManager extends AbstractSingleInstancePluginManager
{
    private \Laminas\Validator\ValidatorPluginManager $nativePlugins;

    private const array DEFAULT_CONFIGURATION = [
        'factories' => [
            IsFloat::class => InvokableFactory::class,
        ],
        'aliases' => [
            'isFloat' => IsFloat::class,
        ],
    ];

    /**
     * Whether to share by default; default to false
     */
    protected bool $sharedByDefault = false;

    protected string $instanceOf = ValidatorInterface::class;

    /**
     * @param ServiceManagerConfiguration $config
     */
    public function __construct(ContainerInterface $creationContext, array $config = [])
    {
        /** @var ServiceManagerConfiguration $config */
        $config = array_replace_recursive(self::DEFAULT_CONFIGURATION, \Jield\Stdlib\Value::stringKeyedArray($config));
        $this->nativePlugins = new \Laminas\Validator\ValidatorPluginManager($creationContext, $config);
        parent::__construct($creationContext, $config);

        $this->configure(['initializers' => [Closure::fromCallable([$this, 'injectValidatorPluginManager'])]]);
    }

    /** @internal */
    protected function injectValidatorPluginManager(ContainerInterface $container, object $validator): void
    {
        if (!$validator instanceof ValidatorPluginManagerAwareInterface) {
            return;
        }

        $validator->setValidatorPluginManager($this->nativePlugins);
    }
}
