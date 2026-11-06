<?php

declare(strict_types=1);

namespace Jield\Mvc\Plugin\FlashMessenger\Factory;

use Jield\Mvc\Plugin\FlashMessenger\View\Helper\FlashMessenger;
use Jield\Mvc\Plugin\FlashMessenger\View\Helper\FlashMessengerNamespace;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class FlashMessengerFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): FlashMessenger {
        $controllerPluginManager = \Jield\Stdlib\Service::get($container, \Jield\Mvc\Controller\PluginManager::class, 'ControllerPluginManager');
        /** @var \Jield\Mvc\Plugin\FlashMessenger\FlashMessenger $flashMessenger */
        $flashMessenger = $controllerPluginManager->get('flashmessenger');

        /** @var mixed $config */
        $config = $container->get('config');
        if (isset($config['view_helper_config']['flashmessenger'])) {
            $configHelper = \Jield\Stdlib\Value::toArray($config['view_helper_config']['flashmessenger']);
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
                classes: isset($arrProperties['classes']) ? (string) $arrProperties['classes'] : '',
            );
            if (isset($arrProperties['message_open_format'])) {
                $namespace->setMessageOpenFormat(messageOpenFormat: (string) $arrProperties['message_open_format']);
            }
            if (isset($arrProperties['message_separator_string'])) {
                $namespace->setMessageSeparatorString(
                    messageSeparatorString: (string) $arrProperties['message_separator_string'],
                );
            }
            if (isset($arrProperties['message_close_string'])) {
                $namespace->setMessageCloseString(messageCloseString: (string) $arrProperties['message_close_string']);
            }
            $namespaces[$namespace->getName()] = $namespace;
        }

        $helper = new FlashMessenger(namespaces: $namespaces);
        $helper->setPluginFlashMessenger(pluginFlashMessenger: $flashMessenger);
        return $helper;
    }
}
