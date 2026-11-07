<?php

declare(strict_types=1);

namespace JieldTest\Release;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReleaseCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/jield-release-test-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/bin', 0700, true);
        copy(dirname(__DIR__, 2) . '/bin/release.php', $this->directory . '/bin/release.php');
        file_put_contents($this->directory . '/checks.php', '<?php exit((int) getenv("JIELD_RELEASE_CHECK_EXIT"));');
        file_put_contents($this->directory . '/CHANGELOG.md', implode("\n", [
            '## [0.1.0-alpha.1]',
            '## [0.2.0-beta.1]',
            '## [0.9.0-RC.1]',
            '## [1.0.0]',
            '## [1.1.0]',
        ]));
        $this->git('init', '--initial-branch=main');
        $this->git('config', 'user.name', 'Release Test');
        $this->git('config', 'user.email', 'release@example.test');
        $this->git('config', 'commit.gpgsign', 'false');
        $this->git('config', 'tag.gpgsign', 'false');
        $this->git('add', '--all');
        $this->git('commit', '--quiet', '--message', 'Release fixture');
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        unset($files);
        rmdir($this->directory);
    }

    public function testAlphaCreatesAnAnnotatedTagOnTheCheckedCommit(): void
    {
        [$status, $output] = $this->release('0.1.0-alpha.1');

        self::assertSame(0, $status, $output);
        self::assertSame('tag', $this->git('cat-file', '-t', 'v0.1.0-alpha.1'));
        self::assertSame($this->git('rev-parse', 'HEAD'), $this->git('rev-parse', 'v0.1.0-alpha.1^{}'));
    }

    public function testDryRunCreatesNoTag(): void
    {
        [$status, $output] = $this->release('0.1.0-alpha.1', dryRun: true);

        self::assertSame(0, $status, $output);
        self::assertStringContainsString('No tag created.', $output);
        self::assertSame('', $this->git('tag', '--list'));
    }

    public function testDirtyWorkingTreeCannotBeReleased(): void
    {
        file_put_contents($this->directory . '/uncommitted.txt', 'uncommitted');
        [$status, $output] = $this->release('0.1.0-alpha.1');

        self::assertSame(1, $status);
        self::assertStringContainsString('working tree must be clean', $output);
        self::assertSame('', $this->git('tag', '--list'));
    }

    public function testFailedQualityChecksCreateNoTag(): void
    {
        [$status, $output] = $this->release('0.1.0-alpha.1', checkStatus: 2);

        self::assertSame(1, $status, $output);
        self::assertSame('', $this->git('tag', '--list'));
    }

    public function testReleasesMustUseMain(): void
    {
        $this->git('checkout', '--quiet', '-b', 'feature');
        [$status, $output] = $this->release('0.1.0-alpha.1');

        self::assertSame(1, $status);
        self::assertStringContainsString('from main', $output);
        self::assertSame('', $this->git('tag', '--list'));
    }

    /** @return iterable<string, array{string, list<string>, string}> */
    public static function skippedPhases(): iterable
    {
        yield 'beta needs alpha' => ['0.2.0-beta.1', [], 'alpha'];
        yield 'RC needs beta' => ['0.9.0-RC.1', ['v0.1.0-alpha.1'], 'beta'];
        yield 'stable needs RC' => ['1.0.0', ['v0.1.0-alpha.1', 'v0.2.0-beta.1'], 'RC'];
    }

    #[DataProvider('skippedPhases')]
    public function testReleasePhasesCannotBeSkipped(string $version, array $tags, string $missingPhase): void
    {
        foreach ($tags as $tag) {
            $this->git('tag', $tag);
        }
        [$status, $output] = $this->release($version);

        self::assertSame(1, $status);
        self::assertStringContainsString('Publish a ' . $missingPhase, $output);
        self::assertSame(implode("\n", $tags), $this->git('tag', '--list'));
    }

    public function testFirstStableMustBeOnePointZero(): void
    {
        $this->addPrereleaseTags();
        [$status, $output] = $this->release('1.1.0');

        self::assertSame(1, $status);
        self::assertStringContainsString('first stable release must be 1.0.0', $output);
    }

    public function testStableCanBeReleasedAfterAllPrereleasePhases(): void
    {
        $this->addPrereleaseTags();
        [$status, $output] = $this->release('1.0.0');

        self::assertSame(0, $status, $output);
        self::assertSame('tag', $this->git('cat-file', '-t', 'v1.0.0'));
    }

    public function testPublishedVersionCannotBeReused(): void
    {
        $this->git('tag', 'v0.1.0-alpha.1');
        [$status, $output] = $this->release('0.1.0-alpha.1');

        self::assertSame(1, $status);
        self::assertStringContainsString('published tags are immutable', $output);
        self::assertSame('commit', $this->git('cat-file', '-t', 'v0.1.0-alpha.1'));
    }

    public function testAnnotatedTagCanBeVerifiedBeforePublication(): void
    {
        [$status, $output] = $this->release('0.1.0-alpha.1');
        self::assertSame(0, $status, $output);
        $tags = $this->git('tag', '--list');
        [$status, $output] = $this->command([PHP_BINARY, 'bin/release.php', '0.1.0-alpha.1', '--verify-tag']);

        self::assertSame(0, $status, $output);
        self::assertStringContainsString('Verified v0.1.0-alpha.1', $output);
        self::assertSame($tags, $this->git('tag', '--list'));
    }

    public function testPublicationCannotBypassThePrereleaseStages(): void
    {
        $this->git('tag', '--annotate', 'v1.0.0', '--message', 'Premature stable');
        [$status, $output] = $this->command([PHP_BINARY, 'bin/release.php', '1.0.0', '--verify-tag']);

        self::assertSame(1, $status);
        self::assertStringContainsString('Publish a alpha', $output);
    }

    public function testPublicationRejectsALightweightTag(): void
    {
        $this->git('tag', 'v0.1.0-alpha.1');
        [$status, $output] = $this->command([PHP_BINARY, 'bin/release.php', '0.1.0-alpha.1', '--verify-tag']);

        self::assertSame(1, $status);
        self::assertStringContainsString('annotated tag', $output);
    }

    public function testPublicationChecksTheTaggedCommit(): void
    {
        [$status, $output] = $this->release('0.1.0-alpha.1');
        self::assertSame(0, $status, $output);
        $this->git('commit', '--quiet', '--allow-empty', '--message', 'Later commit');
        [$status, $output] = $this->command([PHP_BINARY, 'bin/release.php', '0.1.0-alpha.1', '--verify-tag']);

        self::assertSame(1, $status);
        self::assertStringContainsString('tag must point to the checked commit', $output);
    }

    public function testMissingChangelogPreventsRelease(): void
    {
        [$status, $output] = $this->release('0.1.0-alpha.2');

        self::assertSame(1, $status);
        self::assertStringContainsString('Add a CHANGELOG.md entry', $output);
        self::assertSame('', $this->git('tag', '--list'));
    }

    private function addPrereleaseTags(): void
    {
        foreach (['v0.1.0-alpha.1', 'v0.2.0-beta.1', 'v0.9.0-RC.1'] as $tag) {
            $this->git('tag', $tag);
        }
    }

    /** @return array{int, string} */
    private function release(string $version, bool $dryRun = false, int $checkStatus = 0): array
    {
        return $this->command(
            [PHP_BINARY, 'bin/release.php', $version, ...($dryRun ? ['--dry-run'] : [])],
            [
                'COMPOSER_BINARY' => $this->directory . '/checks.php',
                'JIELD_RELEASE_CHECK_EXIT' => (string) $checkStatus,
            ],
        );
    }

    private function git(string ...$arguments): string
    {
        [$status, $output] = $this->command(['git', ...$arguments]);
        self::assertSame(0, $status, $output);
        return trim($output);
    }

    /** @param list<string> $command
     *  @param array<string, string> $environment
     *  @return array{int, string}
     */
    private function command(array $command, array $environment = []): array
    {
        $process = proc_open(
            $command,
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            $this->directory,
            array_merge(getenv(), $environment),
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    }
}
