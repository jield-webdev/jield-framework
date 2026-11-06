<?php

declare(strict_types=1);

namespace Jield\AssetManager\Command;

use Jield\AssetManager\Service\AssetManager;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

final class WarmupCommand extends Command
{
    /** @var string */
    protected static string $defaultName = 'assetmanager:warmup';

    public function __construct(
        private readonly AssetManager $assetManager,
        private readonly array $appConfig,
    ) {
        parent::__construct(name: self::$defaultName);
    }

    #[Override]
    protected function configure(): void
    {
        $this->setName(name: self::$defaultName);

        $this->addOption(name: 'purge', shortcut: 'p', mode: InputOption::VALUE_NONE, description: 'Purge index');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var mixed $purge */
        $purge = $input->getOption(name: 'purge');
        /** @var mixed $verbose */
        $verbose = $input->getOption(name: 'verbose');

        if ($verbose) {
            $output->writeln(messages: '<info>Start warming up</info>');
        }

        if ($purge) {
            if ($verbose) {
                $output->writeln(messages: '<comment>Purge requested, starting purge</comment>');
            }

            $this->purgeCache(output: $output, verbose: \Jield\Stdlib\Value::bool($verbose));
        }

        if ($verbose) {
            $output->writeln(messages: '<info>Collecting all assets</info>');
        }

        $collection = $this->assetManager->getResolver()->collect();

        if (count(value: $collection) === 0) {
            if ($verbose) {
                $output->writeln(messages: '<comment>No assets found</comment>');
            }
        } else {
            if ($verbose) {
                $output->writeln(messages: sprintf(
                    '<comment>Collected %d assets, warming up</comment>',
                    count(value: $collection),
                ));
            }

            /** @var mixed $path */
            foreach ($collection as $path) {
                $asset = $this->assetManager->getResolver()->resolve(fileName: \Jield\Stdlib\Value::string($path));

                $this->assetManager->getAssetFilterManager()->setFilters(path: \Jield\Stdlib\Value::string($path), asset: \Jield\Stdlib\Value::instance($asset, \Jield\AssetManager\Asset\AssetInterface::class));
                $this->assetManager->getAssetCacheManager()->setCache(path: \Jield\Stdlib\Value::string($path), asset: \Jield\Stdlib\Value::instance($asset, \Jield\AssetManager\Asset\AssetInterface::class))->dump();
            }
        }

        if ($verbose) {
            $output->writeln(messages: '<info>Warming up finished</info>');
        }

        return Command::SUCCESS;
    }

    /**
     * Purges all directories defined as AssetManager cache dir.
     */
    private function purgeCache(OutputInterface $output, bool $verbose = false): void
    {
        if (empty($this->appConfig['asset_manager']['caching'])) {
            return;
        }

        /** @var mixed $configName
         * @var mixed $config
         */
        foreach (\Jield\Stdlib\Value::iterable($this->appConfig['asset_manager']['caching']) as $configName => $config) {
            if (empty($config['options']['dir'])) {
                continue;
            }

            if ($verbose) {
                $output->writeln(messages: sprintf('Purging %s on "%s"', \Jield\Stdlib\Value::scalar($configName), \Jield\Stdlib\Value::scalar($config['options']['dir'])));
            }

            /** @var mixed $node */
            $node = $config['options']['dir'];

            if ($configName !== 'default') {
                $node = \Jield\Stdlib\Value::stringify($node) . ('/' . \Jield\Stdlib\Value::stringify($configName));
            }

            $this->recursiveRemove(node: \Jield\Stdlib\Value::string($node), output: $output, verbose: $verbose);
        }
    }

    /**
     * Removes given node from filesystem (recursively).
     */
    private function recursiveRemove(string $node, OutputInterface $output, bool $verbose = false): void
    {
        if (is_dir(filename: $node)) {
            $objects = scandir(directory: $node);

            if ($verbose) {
                $output->writeln(messages: sprintf('Found folder %s', $node));
            }

            foreach ($objects as $object) {
                if ($object === '.' || $object === '..') {
                    continue;
                }

                $this->recursiveRemove(node: $node . '/' . $object, output: $output, verbose: $verbose);
            }
        } elseif (is_file(filename: $node)) {
            //Filetype check (we only want to purge css and js files)
            if (!str_contains(haystack: $node, needle: '.css') && !str_contains(haystack: $node, needle: '.js')) {
                return;
            }

            unlink(filename: $node);
        }
    }
}
