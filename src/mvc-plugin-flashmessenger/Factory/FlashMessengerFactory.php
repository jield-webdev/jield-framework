<?php

declare(strict_types=1);

namespace Jield\Mvc\Plugin\FlashMessenger\Factory;

use Jield\Mvc\Controller\PluginManager;
use Jield\Mvc\Plugin\FlashMessenger\View\Helper\FlashMessenger;
use Jield\Mvc\Plugin\FlashMessenger\View\Helper\FlashMessengerNamespace;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class FlashMessengerFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): FlashMessenger {
        $controllerPluginManager = Service::get($container, PluginManager::class, 'ControllerPluginManager');
        /** @var \Jield\Mvc\Plugin\FlashMessenger\FlashMessenger $flashMessenger */
        $flashMessenger = $controllerPluginManager->get('flashmessenger');

        /** @mago-var mixed $config */
        $config = $container->get('config');
        if (($config['view_helper_config']['flashmessenger'] ?? null) !== null) {
            $configHelper = Value::toArray($config['view_helper_config']['flashmessenger']);
            return $this->createHelperWithActualConfig(flashMessenger: $flashMessenger, configHelper: $configHelper);
        }

        return new FlashMessenger();
    }

    private function createHelperWithActualConfig(
        \Jield\Mvc\Plugin\FlashMessenger\FlashMessenger $flashMessenger,
        array $configHelper,
    ): FlashMessenger {
        $namespaces = [];
        /**
         * @var array<string, mixed> $arrProperties {
         * classes?:string, message_open_format?:string, message_close_string?:string, message_separator_string?:string
         * }
         */
        foreach ($configHelper as $configNamespace => $arrProperties) {
            $namespace = new FlashMessengerNamespace(
                name: (string) $configNamespace,
                classes: ($arrProperties['classes'] ?? null) !== null ? (string) $arrProperties['classes'] : '',
            );
            if (($arrProperties['message_open_format'] ?? null) !== null) {
                $namespace->setMessageOpenFormat(messageOpenFormat: (string) $arrProperties['message_open_format']);
            }
            if (($arrProperties['message_separator_string'] ?? null) !== null) {
                $namespace->setMessageSeparatorString(
                    messageSeparatorString: (string) $arrProperties['message_separator_string'],
                );
            }
            if (($arrProperties['message_close_string'] ?? null) !== null) {
                $namespace->setMessageCloseString(messageCloseString: (string) $arrProperties['message_close_string']);
            }
            $namespaces[$namespace->getName()] = $namespace;
        }

        $helper = new FlashMessenger(namespaces: $namespaces);
        $helper->setPluginFlashMessenger(pluginFlashMessenger: $flashMessenger);
        return $helper;
    }
}
