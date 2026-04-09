<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Jield\Mvc\View\Http\ExceptionStrategy;
use Laminas\ServiceManager\Factory\FactoryInterface;

class HttpExceptionStrategyFactory implements FactoryInterface
{
    use HttpViewManagerConfigTrait;

    /**
     * @param  string $requestedName
     * @param  null|array $options
     * @return ExceptionStrategy
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        $strategy = new ExceptionStrategy();
        $config   = $this->getConfig(container: $container);

        $this->injectDisplayExceptions(strategy: $strategy, config: $config);
        $this->injectExceptionTemplate(strategy: $strategy, config: $config);

        return $strategy;
    }

    /**
     * Inject strategy with configured display_exceptions flag.
     */
    private function injectDisplayExceptions(ExceptionStrategy $strategy, array $config): void
    {
        $flag = $config['display_exceptions'] ?? false;
        $strategy->setDisplayExceptions(displayExceptions: $flag);
    }

    /**
     * Inject strategy with configured exception_template
     */
    private function injectExceptionTemplate(ExceptionStrategy $strategy, array $config): void
    {
        $template = $config['exception_template'] ?? 'error';
        $strategy->setExceptionTemplate(exceptionTemplate: $template);
    }
}
