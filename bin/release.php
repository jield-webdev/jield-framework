<?php

declare(strict_types=1);

// Argument arrays keep version and path values out of shell command parsing.
$run = static function (array $command, bool $capture = false): string {
    $pipes = [];
    $process = proc_open($command, [STDIN, $capture ? ['pipe', 'w'] : STDOUT, STDERR], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start ' . $command[0]);
    }

    $output = '';
    if ($capture) {
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
    }

    if (proc_close($process) !== 0) {
        throw new RuntimeException('Command failed: ' . implode(' ', $command));
    }

    return trim($output);
};

try {
    chdir(dirname(__DIR__));
    $arguments = array_slice($argv, 1);
    $dryRun = in_array('--dry-run', $arguments, true);
    $verifyTag = in_array('--verify-tag', $arguments, true);
    $arguments = array_values(array_filter(
        $arguments,
        static fn (string $value): bool => !in_array($value, ['--dry-run', '--verify-tag'], true),
    ));
    if (count($arguments) !== 1) {
        throw new RuntimeException('Usage: composer release -- VERSION [--dry-run | --verify-tag]');
    }

    $version = preg_replace('/^v/', '', $arguments[0]);
    $number = '(?:0|[1-9][0-9]*)';
    $pattern = '/^(?:0\.' . $number . '\.' . $number . '-(alpha|beta|RC)\.[1-9][0-9]*'
        . '|[1-9][0-9]*\.' . $number . '\.' . $number . ')$/D';
    if (!preg_match($pattern, $version, $match)) {
        throw new RuntimeException(
            'Use 0.x.y-alpha.N, 0.x.y-beta.N, 0.x.y-RC.N, then 1.0.0 or a later stable version.',
        );
    }

    $tag = 'v' . $version;
    $phase = $match[1] ?? 'stable';
    if ($phase === '') {
        $phase = 'stable';
    }
    if (!$verifyTag && $run(['git', 'branch', '--show-current'], true) !== 'main') {
        throw new RuntimeException('Releases must be made from main.');
    }
    if ($run(['git', 'status', '--porcelain'], true) !== '') {
        throw new RuntimeException('Commit all release changes first; the working tree must be clean.');
    }
    $commit = $run(['git', 'rev-parse', 'HEAD'], true);
    if ($verifyTag) {
        if ($run(['git', 'cat-file', '-t', 'refs/tags/' . $tag], true) !== 'tag') {
            throw new RuntimeException('Releases require an annotated tag.');
        }
        if ($run(['git', 'rev-parse', 'refs/tags/' . $tag . '^{}'], true) !== $commit) {
            throw new RuntimeException('The release tag must point to the checked commit.');
        }
    }

    $tags = array_filter(explode("\n", $run(['git', 'tag', '--list'], true)));
    $phases = [];
    $hasStable = false;
    foreach ($tags as $existingTag) {
        if ($verifyTag && $existingTag === $tag) {
            continue;
        }
        $existingVersion = preg_replace('/^v/', '', $existingTag);
        $existingPattern = '/^' . $number . '\.' . $number . '\.' . $number
            . '(?:-(alpha|beta|RC)\.[1-9][0-9]*)?$/D';
        if (!preg_match($existingPattern, $existingVersion, $existingMatch)) {
            continue;
        }
        if (version_compare($version, $existingVersion, '<=')) {
            throw new RuntimeException(
                'The release must be newer than ' . $existingTag . '; published tags are immutable.',
            );
        }
        if (isset($existingMatch[1])) {
            $phases[] = $existingMatch[1];
        } else {
            $hasStable = true;
        }
    }

    $requiredPhases = match ($phase) {
        'beta' => ['alpha'],
        'RC' => ['alpha', 'beta'],
        'stable' => ['alpha', 'beta', 'RC'],
        default => [],
    };
    foreach ($requiredPhases as $requiredPhase) {
        if (!in_array($requiredPhase, $phases, true)) {
            throw new RuntimeException('Publish a ' . $requiredPhase . ' before releasing a ' . $phase . '.');
        }
    }
    if ($phase === 'stable' && !$hasStable && $version !== '1.0.0') {
        throw new RuntimeException('The first stable release must be 1.0.0.');
    }
    $changelogHeading = '/^## \[' . preg_quote($version, '/') . '\](?:\s+[^\r\n]+)?\r?$/m';
    if (!preg_match($changelogHeading, file_get_contents('CHANGELOG.md'))) {
        throw new RuntimeException('Add a CHANGELOG.md entry for ' . $version . ' before releasing.');
    }
    if ($verifyTag) {
        fwrite(STDOUT, 'Verified ' . $tag . ' at ' . $commit . ".\n");
        exit(0);
    }

    $composer = getenv('COMPOSER_BINARY');
    $command = $composer ? [PHP_BINARY, $composer] : ['composer'];
    $run([...$command, 'release:check', '--no-interaction']);
    if ($run(['git', 'status', '--porcelain'], true) !== '' || $run(['git', 'rev-parse', 'HEAD'], true) !== $commit) {
        throw new RuntimeException(
            'Release checks changed the working tree or HEAD; review and commit the changes first.',
        );
    }

    if ($dryRun) {
        fwrite(STDOUT, 'Ready to tag ' . $tag . ' at ' . $commit . ". No tag created.\n");
        exit(0);
    }

    $run(['git', 'tag', '--annotate', $tag, $commit, '--message', 'Release ' . $version]);
    fwrite(STDOUT, 'Created ' . $tag . ' at ' . $commit . ".\n");
    fwrite(STDOUT, 'Publish with: git push origin refs/tags/' . $tag . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
