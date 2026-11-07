# Jield Framework

A modular PHP framework built on Laminas, Doctrine, and Twig.

The first planned release is **0.1.0-alpha.1**, packaging the current `main`.
The release path is 0.x alpha → 0.x beta → 0.x RC → **1.0.0**. Prerelease APIs
and configuration can change; see [the release policy](docs/releases.md) and
[changelog](CHANGELOG.md).

## Install in another project

The current dependency stack supports PHP 8.4 and 8.5. Install the extensions
required by Composer, including `igbinary` and `redis` (^6.0).

Until the repository is registered on Packagist, add its VCS URL to the **consuming application's root**`composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/jield-webdev/jield-framework"
    }
  ],
  "minimum-stability": "dev",
  "prefer-stable": true,
  "require": {
    "jield/framework": "0.1.0-alpha.1",
    "laminas/laminas-stdlib": "4.0.x-dev as 3.21.0"
  }
}
```

Use the actual repository URL if the repository is hosted elsewhere. This works
once `v0.1.0-alpha.1` has been pushed to that repository. For a private repository,
configure Composer's authentication separately.

```sh
composer update
```

In an existing project with a lock file, limit the update to these packages with
`composer update jield/framework laminas/laminas-stdlib --with-all-dependencies`.

The alpha needs development versions of several Laminas packages and Azure Blob
Storage, plus Twig 4 alpha. The consuming application must allow these versions:
a dependency's `minimum-stability` is ignored. The Stdlib alias must also be
repeated in the application because inline aliases are root-only. An `@alpha`
flag on `jield/framework` alone does not allow its development dependencies.
`prefer-stable` keeps other packages on stable versions where constraints permit.
Commit the application's lock file so deployments use the tested references.

After Packagist registration, remove the VCS entry and use the same requirements.
For deliberate updates within the first alpha series, replace the exact framework
constraint with `~0.1.0@alpha`. To test the moving development branch, use
`dev-main`; its Composer branch alias is `0.1.x-dev`.

When **another library** depends on this alpha, its final consuming application
must carry the repository, stability configuration, and Stdlib alias too.
Composer does not inherit those root settings from libraries.

## Application bootstrap and PDF fonts

Load the framework bootstrap before creating the application. It loads Composer
and registers the current Laminas compatibility adapters:

```php
require __DIR__ . '/vendor/jield/framework/autoload.php';
```

Composer does not execute scripts belonging to dependencies. Applications using
PDF rendering should add these root scripts (adjust `vendor/` if customized):

```json
{
  "scripts": {
    "pdf-fonts": "@php vendor/jield/framework/bin/build-pdf-fonts.php",
    "post-install-cmd": [
      "@pdf-fonts"
    ],
    "post-update-cmd": [
      "@pdf-fonts"
    ]
  }
}
```

Run `composer pdf-fonts` once after adding the scripts. Font preparation downloads
upstream font sources on its first run; later runs reuse generated fonts.

## Development and releases

```sh
composer install
composer release:check
```

`composer.lock` records the dependencies tested by CI and releases; update it
deliberately during development. It does not constrain dependencies in consuming
projects and is excluded from distribution archives.

After committing release changes on `main`:

```sh
composer release -- 0.1.0-alpha.1 --dry-run
composer release -- 0.1.0-alpha.1
git push origin refs/tags/v0.1.0-alpha.1
```

The release command validates the version, phase order, changelog, clean `main`,
and quality checks before creating an annotated local tag. Publishing and the
1.0.0 acceptance criteria are described in [the release guide](docs/releases.md).

Jield BV