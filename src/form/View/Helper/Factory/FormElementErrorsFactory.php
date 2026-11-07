<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper\Factory;

use Jield\Form\View\Helper\FormElementErrors;
use Psr\Container\ContainerInterface;

use function array_key_exists;
use function is_array;

final class FormElementErrorsFactory
{
    public function __invoke(ContainerInterface $container): FormElementErrors
    {
        $helper = new FormElementErrors();

        if (!$container->has('config')) {
            return $helper;
        }

        $config = $container->get('config');
        if (isset($config['view_helper_config']['form_element_errors'])) {
            $configHelper = $config['view_helper_config']['form_element_errors'];

            // Attributes
            if (isset($configHelper['attributes']) && is_array(value: $configHelper['attributes'])) {
                $helper->setAttributes(attributes: $configHelper['attributes']);
            }

            // Message close string
            if (array_key_exists(key: 'message_close_string', array: $configHelper)) {
                $helper->setMessageCloseString(messageCloseString: $configHelper['message_close_string']);
            }

            // Message open format
            if (array_key_exists(key: 'message_open_format', array: $configHelper)) {
                $helper->setMessageOpenFormat(messageOpenFormat: $configHelper['message_open_format']);
            }

            // Message separator string
            if (array_key_exists(key: 'message_separator_string', array: $configHelper)) {
                $helper->setMessageSeparatorString(messageSeparatorString: $configHelper['message_separator_string']);
            }

            // Translate error messages
            if (array_key_exists(key: 'translate_messages', array: $configHelper)) {
                $helper->setTranslateMessages(flag: $configHelper['translate_messages']);
            }
        }

        return $helper;
    }
}
