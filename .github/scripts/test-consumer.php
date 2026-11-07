<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$directory = sys_get_temp_dir() . '/jield-consumer-' . bin2hex(random_bytes(6));
mkdir($directory, 0700, true);

/** @param list<string> $command */
$run = static function (array $command, string $cwd): void {
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $cwd);
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Command failed: ' . implode(' ', $command));
    }
};

try {
    $archive = $directory . '/framework.zip';
    $run(['git', 'archive', '--format=zip', '--output=' . $archive, 'HEAD'], $root);
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) {
        throw new RuntimeException('Could not open the distribution archive.');
    }
    $runtimeFiles = [
        'composer.json',
        'autoload.php',
        'src/test/Util/ModuleLoader.php',
        'config/asset-manager/module.config.php',
        'resources/pdf/LICENSE.TXT',
        'bin/build-pdf-fonts.php',
    ];
    foreach ($runtimeFiles as $path) {
        if ($zip->locateName($path) === false) {
            throw new RuntimeException('The archive is missing runtime file ' . $path);
        }
    }
    foreach (['.github/', 'test/', 'analysis/', 'composer.lock', 'bin/release.php'] as $path) {
        if ($zip->locateName($path) !== false) {
            throw new RuntimeException('The archive contains development file ' . $path);
        }
    }
    $package = json_decode($zip->getFromName('composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $zip->close();
    if (isset($package['version'])) {
        throw new RuntimeException('Package versions must come from tags, not composer.json.');
    }
    // Use the release tag in CI; test untagged commits as an unpublished alpha.
    $version = getenv('GITHUB_REF_TYPE') === 'tag'
        ? preg_replace('/^v/', '', getenv('GITHUB_REF_NAME'))
        : '0.1.0-alpha.1';
    $stable = !str_contains($version, '-');
    $package['version'] = $version;
    $package['dist'] = ['type' => 'zip', 'url' => $archive];
    $consumer = [
        'name' => 'jield/framework-consumer-test',
        'repositories' => [['type' => 'package', 'package' => $package]],
        'prefer-stable' => true,
        'require' => ['jield/framework' => $version],
        'scripts' => [
            'post-update-cmd' => ['@php vendor/jield/framework/bin/build-pdf-fonts.php'],
        ],
    ];
    if (!$stable) {
        $consumer['minimum-stability'] = 'dev';
        $consumer['require']['laminas/laminas-stdlib'] = $package['require']['laminas/laminas-stdlib'];
    }
    file_put_contents(
        $directory . '/composer.json',
        json_encode($consumer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
    );
    $composer = getenv('COMPOSER_BINARY');
    $command = $composer ? [PHP_BINARY, $composer] : ['composer'];
    $run(
        [...$command, 'update', '--no-dev', '--prefer-dist', '--no-interaction', '--no-progress', '--no-plugins'],
        $directory,
    );
    copy(__DIR__ . '/verify-consumer.php', $directory . '/verify.php');
    $run([PHP_BINARY, 'verify.php', $version], $directory);
    fwrite(STDOUT, "Distribution archive installed and consumer checks passed.\n");

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($directory);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\nConsumer workspace retained at " . $directory . "\n");
    exit(1);
}
