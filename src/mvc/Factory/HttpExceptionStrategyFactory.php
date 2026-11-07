<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use ArrayAccess;
use Jield\Mvc\Service\HttpViewManagerConfigTrait;
use Jield\Mvc\View\Http\ExceptionStrategy;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class HttpExceptionStrategyFactory implements FactoryInterface
{
    use HttpViewManagerConfigTrait;

    /**
     * @param  string $requestedName
     * @param  null|array $options
     * @return ExceptionStrategy
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ExceptionStrategy {
        $strategy = new ExceptionStrategy();
        $config = $this->getConfig($container);

        $this->injectDisplayExceptions($strategy, $config);
        $this->injectExceptionTemplate($strategy, $config);

        return $strategy;
    }

    /**
     * Inject strategy with configured display_exceptions flag.
     */
    private function injectDisplayExceptions(ExceptionStrategy $strategy, array|ArrayAccess $config): void
    {
        /** @var mixed $flag */
        $flag = $config['display_exceptions'] ?? false;
        $strategy->setDisplayExceptions(Value::bool($flag));
    }

    /**
     * Inject strategy with configured exception_template
     */
    private function injectExceptionTemplate(ExceptionStrategy $strategy, array|ArrayAccess $config): void
    {
        /** @var mixed $template */
        $template = $config['exception_template'] ?? 'error';
        $strategy->setExceptionTemplate(Value::string($template));
    }
}
