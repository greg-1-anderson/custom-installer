Composer Custom Type Installer
==============================
Adds a root-level custom [type](https://getcomposer.org/doc/04-schema.md#type) installer path to composer.json. Any custom type can be used to define a path the [type](https://getcomposer.org/doc/04-schema.md#type) should be installed in.

Based on [davidbarratt/custom-installer](https://github.com/davidbarratt/custom-installer).

## Installation
Simply require this library in your composer.json file. Typically this will be added as a dependency of the custom [type](https://getcomposer.org/doc/04-schema.md#type) to ensure that the library is loaded before the library that needs it. However, this can be added to the root composer.json, as long as it goes before any library that needs it.
```json
{
    "require": {
        "greg-1-anderson/custom-installer": "dev-exclusions"
    }
}
```

## Usage
The added parameter(s) are only allowed on the root to avoid conflicts between multiple libraries. This also prevents a project owner from having a directory accidentally wiped out by a library.

#### custom-installer (root-level)
You may use [Composer Installer](https://github.com/composer/installers) type [installation paths](https://github.com/composer/installers#custom-install-paths) with the variables `{$name}`, `{$vendor}`, and `{$type}`. Each package will go in it’s respective folder in the order in which they are installed.

Any composer type that has an entry in the extra "custom-installer" field
may optionally also have an entry in the "merge-exclusions" field.  The
exclusions list all directories that should be left untouched when
installing or removing the package.  This allows for composer-managed
folders to reside in directories that are physically inside the
installation directory of a composer package, without being logically
part of that package (e.g. Drupal modules, themes and core/vendor
directories).

```json
{
    "extra": {
        "custom-installer": {
            "drupal-core": "web/",
            "drupal-site": "web/sites/{$name}/",
            "random-type": "custom/{$type}/{$vendor}/{$name}/"
        },
        "merge-exclusions": {
            "drupal-core": [
                "modules",
                "themes",
                "profiles",
                "core/vendor"
            ]
        }
    }
}
```
