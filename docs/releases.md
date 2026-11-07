# Composer release strategy

## Versions and milestones

Git tags are the source of package versions. Do not add a `version` field to
`composer.json`. Keep `dev-main` aliased to the current development line
(`0.1.x-dev` initially), and update that alias when development moves to a new
minor line. A branch alias remains a development version, never an alpha release.

| Phase | Example tag | Acceptance criteria |
| --- | --- | --- |
| Alpha | `v0.1.0-alpha.1`, `v0.1.0-alpha.2` | Current main is installable in another project; lint, static analysis, tests, and the consumer installation pass. APIs can change. |
| Beta | `v0.2.0-beta.1` | Intended 1.0 features are complete, migration instructions are documented, and representative applications exercise the integrations. |
| RC | `v0.9.0-RC.1` | Public API and configuration are frozen, regressions are fixed, and deployments in consuming applications pass. Only release fixes follow. |
| Stable | `v1.0.0` | RC acceptance is complete, the documented supported PHP matrix passes, and applications can install without development dependency overrides. |

The minor numbers after the initial alpha are examples; select a new minor for
breaking changes and keep the release sequence increasing. Increment the suffix
number for another candidate of the same version and phase. All 0.x releases
have an explicit `alpha`, `beta`, or `RC` suffix: Composer treats an unsuffixed
0.x tag as stable. Use exact pins in applications during evaluation, and include
upgrade notes for breaking changes in the changelog.

The release tool requires alpha before beta, beta before RC, and all three
before the first stable release, which must be 1.0.0. It rejects duplicate and
older versions. Never move or delete a published tag; issue a new candidate.
For stable tags, CI's consumer installation uses default stable minimum
stability and omits the Stdlib override, so development dependency workarounds
cannot silently carry over into 1.0.0.

## Prepare and publish

1. Work on `main`. Review the changelog entry, dependency changes, and application
   upgrade notes. For the first alpha, release the current implementation plus
   this packaging setup as `0.1.0-alpha.1`.
2. Update dependencies deliberately in a development change with `composer
   update`, test them, and commit `composer.lock`. CI and releases use `composer
   install` with that lock; they never update dependencies while publishing.
3. Commit all changes, push `main`, and wait for CI, including its consumer install
   on PHP 8.4 and 8.5. Fetch tags and fast-forward local `main` before releasing.
4. Run `composer release -- 0.1.0-alpha.1 --dry-run` and then `composer release --
   0.1.0-alpha.1`. Each command checks publishable Composer metadata, PHP syntax,
   PHPStan, and PHPUnit. The real run creates an annotated `v0.1.0-alpha.1` tag
   on the checked commit. The working tree must stay clean throughout.
5. Push only that tag: `git push origin refs/tags/v0.1.0-alpha.1`. The GitHub
   release workflow reruns the checks and consumer install, verifies the
   annotated tag and phase order with `--verify-tag`, then creates a
   GitHub prerelease for a suffixed tag or a stable release for 1.x and later.
   GitHub's generated source archives preserve runtime source, configuration,
   resources, bootstrap, and the PDF font builder; development tools and tests
   are excluded through `.gitattributes`.
6. Verify the version is visible in the configured Composer registry. Install
   `jield/framework:0.1.0-alpha.1` in a consuming application using the
   [README configuration](../README.md), then commit that application's lock.
   Deploy applications with `composer install --no-dev --prefer-dist
   --optimize-autoloader --no-interaction`, rather than updating on the server.

`composer package:validate` uses `--strict --no-check-all`: the latter permits the
existing exact Stdlib compatibility alias. Schema, publishability, and lock-file
checks still run. Existing coding-style commands remain available separately;
they are not part of the initial alpha release gate.

## Registry setup

For public distribution, submit the repository URL once at
[Packagist](https://packagist.org/packages/submit) as `jield/framework`, then
enable its [GitHub update integration](https://packagist.org/about#how-to-update-packages).
Pushed tags are indexed automatically; Composer obtains the version from each
tag's `composer.json`. GitHub Releases provide release notes but are not required
by Composer. A tag may be indexed before the GitHub release workflow finishes,
so the checks on `main` and the local release command must pass before pushing.

A private Git repository can be consumed directly using a root `vcs` repository
entry and Composer authentication, or indexed by the team's private Composer
registry. Do not submit private source to public Packagist. Registry credentials
belong in Composer authentication or CI secrets, never in this repository.

This checkout initially has no Git remote and no tags. The `homepage` currently
points to `https://github.com/jield-webdev/jield-framework`; confirm the real
repository and configure `origin` before pushing. Registry registration is a
separate one-time operation; adding metadata locally does not publish a package.

## Work required before 1.0.0

- Replace development-only Laminas and Azure Blob Storage constraints with
  supported tagged releases, and migrate Twig to a stable supported version.
- Remove `4.0.x-dev as 3.21.0` once the Laminas dependency graph supports the
  actual Stdlib major. Verify installation in an application with default stable
  minimum stability, without an inline alias.
- Test all advertised PHP versions. The current dependencies support PHP 8.4.1
  and 8.5; the framework's existing 8.6 constraint is forward-looking and some
  dependencies currently exclude 8.6. Add 8.6 to required CI only when the whole
  dependency graph supports it, or narrow the advertised constraint before 1.0.
- Exercise authentication, MVC, forms, Doctrine migrations, search/messenger,
  templates, exports, and PDF rendering in real applications; document their
  configuration and migration requirements.
- Confirm license notices for bundled code and resources and publish supported
  upgrade, compatibility, and maintenance policies. After 1.0.0, use patch
  releases for compatible fixes, minor releases for compatible additions, and
  major releases for breaking changes.

Composer references: [library versioning and distribution](https://getcomposer.org/doc/02-libraries.md),
[root-only settings](https://getcomposer.org/doc/04-schema.md#root-package),
and [inline aliases](https://getcomposer.org/doc/articles/aliases.md#require-inline-alias).
