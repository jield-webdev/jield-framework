<?php

declare(strict_types=1);

namespace Jield\AssetManager\Command;

use Jield\AssetManager\Asset\AssetInterface;
use Jield\AssetManager\Service\AssetManager;
use Jield\Stdlib\Value;
use Override;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function sprintf;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
final class WarmupCommand extends Command
{
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

    /**
     * @throws RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @mago-var mixed $purge */
        $purge = $input->getOption(name: 'purge');
        /** @mago-var mixed $verbose */
        $verbose = $input->getOption(name: 'verbose');

        if ($verbose) {
            $output->writeln(messages: '<info>Start warming up</info>');
        }

        if ($purge) {
            if ($verbose) {
                $output->writeln(messages: '<comment>Purge requested, starting purge</comment>');
            }

            $this->purgeCache(output: $output, verbose: Assert::boolean($verbose));
        }

        if ($verbose) {
            $output->writeln(messages: '<info>Collecting all assets</info>');
        }

        $collection = $this->assetManager->getResolver()->collect();

        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(1)
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

            /** @mago-var mixed $path */
            foreach ($collection as $path) {
                $asset = $this->assetManager->getResolver()->resolve(fileName: Assert::string($path));

                $this->assetManager->getAssetFilterManager()->setFilters(
                    path: Assert::string($path),
                    asset: Assert::isInstanceOf($asset, AssetInterface::class),
                );
                $this->assetManager
                    ->getAssetCacheManager()
                    ->setCache(path: Assert::string($path), asset: Assert::isInstanceOf($asset, AssetInterface::class))
                    ->dump();
            }
        }

        if ($verbose) {
            $output->writeln(messages: '<info>Warming up finished</info>');
        }

        return Command::SUCCESS;
    }

    /**
     * Purges all directories defined as AssetManager cache dir.
     *
     * @throws RuntimeException
     */
    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:no-boolean-flag-parameter(1)
    private function purgeCache(OutputInterface $output, bool $verbose = false): void
    {
        if (!($this->appConfig['asset_manager']['caching'] ?? null)) {
            return;
        }

        /**
         * @mago-var mixed $configName
         * @mago-var mixed $config
         */
        foreach (Assert::isIterable($this->appConfig['asset_manager']['caching']) as $configName => $config) {
            if (!($config['options']['dir'] ?? null)) {
                continue;
            }

            if ($verbose) {
                $output->writeln(messages: sprintf(
                    'Purging %s on "%s"',
                    Value::scalar($configName),
                    Value::scalar($config['options']['dir'] ?? null),
                ));
            }

            /** @mago-var mixed $node */
            $node = $config['options']['dir'] ?? null;

            if ($configName !== 'default') {
                $node = Value::stringify($node) . ('/' . Value::stringify($configName));
            }

            $this->recursiveRemove(node: Assert::string($node), output: $output, verbose: $verbose);
        }
    }

    /**
     * Removes given node from filesystem (recursively).
     *
     * @throws RuntimeException
     */
    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:no-boolean-flag-parameter(1)
    private function recursiveRemove(string $node, OutputInterface $output, bool $verbose = false): void
    {
        if (is_dir(filename: $node)) {
            $objects = scandir(directory: $node);
            if ($objects === false) {
                throw new RuntimeException(sprintf('Cannot read directory "%s".', $node));
            }
            if ($verbose) {
                $output->writeln(messages: sprintf('Found folder %s', $node));
            }
            foreach ($objects as $object) {
                if ($object === '.' || $object === '..') {
                    continue;
                }

                $this->recursiveRemove(node: $node . '/' . $object, output: $output, verbose: $verbose);
            }
            return;
        }
        if (is_file(filename: $node)) {
            //Filetype check (we only want to purge css and js files)
            if (!str_contains(haystack: $node, needle: '.css') && !str_contains(haystack: $node, needle: '.js')) {
                return;
            }
            unlink(filename: $node);
            return;
        }
    }
}
