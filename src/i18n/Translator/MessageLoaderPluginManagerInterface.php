<?php

declare(strict_types=1);

namespace Jield\I18n\Translator;

use Jield\I18n\Translator\Loader\FileLoaderInterface;
use Jield\I18n\Translator\Loader\RemoteLoaderInterface;
use Laminas\ServiceManager\PluginManagerInterface;

/**
 * @psalm-type InstanceType = RemoteLoaderInterface|FileLoaderInterface
 * @extends PluginManagerInterface<InstanceType>
 */
interface MessageLoaderPluginManagerInterface extends PluginManagerInterface
{
}
