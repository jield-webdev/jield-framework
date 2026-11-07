# Continuous integration

[CI and releases](../.github/workflows/release.yml) runs on branch pushes, pull
requests, merge queues, `v*` tags, and manual dispatches. Its `CI` status succeeds
only when every check passes; use that status as the required check in branch
protection. Tag pushes publish a release only after the same gate succeeds.

The workflow runs these checks independently so one failure does not hide
findings from the other tools:

| Check | Scope / command |
| --- | --- |
| PHP compatibility | PHP 8.4 and 8.5; locked Composer install and platform requirements |
| Composer metadata | `composer package:validate`, including lock-file consistency |
| PHP syntax | `composer php-lint` on source, tests, configuration, resources, CLI tools, bootstrap, and CI scripts |
| PHPStan | `composer phpstan`, using level 5 and paths from `phpstan.neon` |
| Coding standards | `composer cs` and `composer cs-test`, both using PSR-12 |
| Mago formatting | `composer mago:format` (read-only check using `mago.toml`) |
| Mago lint | `composer mago:lint` |
| Mago analysis | `composer mago:analyze` on all of `src`, with dependency symbols and PDF defaults loaded |
| Tests | `composer test` and `composer test:consumer` on both PHP versions |
| Dependency security | `composer security:check` audits the lock file for advisories and abandoned packages |
| Workflow correctness | actionlint 1.7.12, including ShellCheck for workflow shell steps |
| Repository integrity | Checks must leave tracked files unchanged |

The shared [setup action](../.github/actions/setup-php/action.yml) installs the
required extensions, including igbinary and Redis 6.3.0, and caches Composer's
download directory. Dependencies are always installed from `composer.lock`;
`vendor` is not cached. Only the test jobs build PDF fonts. PHP 8.6 remains
outside the required matrix until the dependency graph supports it, as described
in the [release policy](releases.md).

## Run locally

Install PHP with the extensions required by Composer and
[Mago 1.59.0](https://mago.carthage.software/1.59.0/en/guide/installation/).
Keep the Mago version in the workflow and `mago.toml` in sync when upgrading.

```sh
composer install
composer check
composer test:consumer
```

`composer release:check` runs the same local quality gate as `composer check`.
The consumer-install check remains separate because it resolves dependencies in
a temporary application and tests the committed distribution archive. Local
Composer check groups stop at their first failure; GitHub runs the tools as
separate steps to report all findings.

PHP syntax checks omit analysis stubs, which intentionally redeclare built-in
symbols. Mago and PHPStan load their configured analysis stubs instead. Mago
checks the full source tree, including new modules, without maintaining a list
of individual module directories. The formatter preserves multiline conditions
and uses multiline empty declarations to agree with the PSR-12 checks.

Compatibility APIs retain their established method counts, properties, parameter
lists, boolean options, and mutually exclusive state-selection branches. These
deliberate exceptions use counted, scoped `@mago-expect` comments with explanations
in the source. Public protocol names and form field identifiers are also marked
where security heuristics mistake them for credentials. Generic return annotations
keep their local variable targets, and navigation result checks retain correlated
field validation for both analyzers. No linter rule is disabled for these cases;
additional findings beyond the expected counts remain visible, and stale
expectations are reported by Mago.

All checks are blocking. Existing lint, formatting, or analysis violations must
be resolved before the `CI` status can pass; no baseline or automatic source
rewrites are applied by this workflow.

Tool references: [Mago's GitHub Actions recipe](https://mago.carthage.software/main/en/recipes/github-actions/),
[PHP setup](https://github.com/shivammathur/setup-php), and
[actionlint usage](https://github.com/rhysd/actionlint/blob/main/docs/usage.md).
