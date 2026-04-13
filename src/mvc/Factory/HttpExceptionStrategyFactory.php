<?php

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\Service\HttpViewManagerConfigTrait;
use Jield\Mvc\View\Http\ExceptionStrategy;
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
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): ExceptionStrategy
    {
        $strategy = new ExceptionStrategy();
        $config   = $this->getConfig($container);

        $this->injectDisplayExceptions($strategy, $config);
        $this->injectExceptionTemplate($strategy, $config);

        return $strategy;
    }

    /**
     * Inject strategy with configured display_exceptions flag.
     */
    private function injectDisplayExceptions(ExceptionStrategy $strategy, array $config): void
    {
        $flag = $config['display_exceptions'] ?? false;
        $strategy->setDisplayExceptions($flag);
    }

    /**
     * Inject strategy with configured exception_template
     */
    private function injectExceptionTemplate(ExceptionStrategy $strategy, array $config): void
    {
        $template = $config['exception_template'] ?? 'error';
        $strategy->setExceptionTemplate($template);
    }
}
