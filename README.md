# ![Mascot](https://user-images.githubusercontent.com/2371345/67424058-caac1a00-f5ab-11e9-99a2-c9360370391f.png) Migrating to Islandora 8 using CSVs

[![Contribution Guidelines](http://img.shields.io/badge/CONTRIBUTING-Guidelines-blue.svg)](./CONTRIBUTING.md)
[![LICENSE](https://img.shields.io/badge/license-GPLv2-blue.svg?style=flat-square)](./LICENSE)

## Introduction

This repository, __migrate_islandora_csv__, provides [a tutorial](TUTORIAL.md) that will introduce you to using the Drupal 8 Migrate tools to create Islandora content in 8. Whether you will eventually use CSVs or other sources (such as XML or directly from a 7.x Islandora) this tutorial should be useful as it covers the basics and mechanics of migration.

This repository is also a Drupal Feature that, when enabled as a module, will create three example migrations ready for you to use with the Migrate API. Each migration comes from one of the files in the `config/install` folder. We'll walk through them in detail below.

This repository also contains a `data` folder containing a CSV and sample images, as a convenience so that the accompanying files are easily available on the Drupal server running the migration. (This is not the recommended method for making files available to Drupal in a real migration.)

When you are ready to create your actual migrations, this repository can function as a template for you to create the yml files defining your own migrations.

## Requirements

This module requires the following modules:

* [islandora/islandora_defaults](https://github.com/Islandora/islandora_defaults)
* [drupal/migrate_source_csv](https://www.drupal.org/project/migrate_source_csv)

## Installation

From your `islandora-playbook` directory, issue the following commands to enable this module:
- `vagrant ssh` to open a shell in your Islandora instance.
- `cd /var/www/html/drupal/web/modules/contrib` to get to your modules directory.
- `git clone https://github.com/dannylamb/migrate_islandora_csv` to clone down the repository from GitHub.
- `drush en -y migrate_islandora_csv` to enable the module, installing the migrations as configuration.

Optionally, flush the cache (`drush cr`), so the migrations become visible in the GUI at Manage > Structure > Migrations > migrate_islandora_csv (http://localhost:8000/admin/structure/migrate/manage/migrate_islandora_csv/migrations)

Now lets go migrate some files.

Cautionary sidenote: as you saw, you can still `git clone` into the modules directory, but if you're installing a custom module that's intended to stay installed for the long term (unlike a migration feature, which you should probably uninstall and delete when you're done with it) then you may want to check with your devops folks and use Composer instead. However, using Git directly allows you to be more flexible when iterating and testing.

## Configuration

No configuration page is provided.

This module uses Features, which is an easy way to ship and install Drupal configuration. To make changes, edit the configuration files in this module and use Features to import those changes. There is a walkthrough in the "Configuration" section of the [Migrate 7.x to 8](https://github.com/Islandora-Devops/migrate_7x_claw) tutorial. 

## Documentation

Further documentation for this module is available on the [Islandora 8 documentation site](https://islandora.github.io/documentation/).

## Troubleshooting/Issues

Having problems or solved a problem? Check out the Islandora google groups for a solution.

* [Islandora Group](https://groups.google.com/forum/?hl=en&fromgroups#!forum/islandora)
* [Islandora Dev Group](https://groups.google.com/forum/?hl=en&fromgroups#!forum/islandora-dev)

## Maintainers/Sponsors

Current maintainers:

* [Danny Lamb](https://github.com/dannylamb)

## Development

If you would like to contribute, please get involved by attending our weekly [Tech Call](https://github.com/Islandora/documentation/wiki#islandora-8-tech-calls). We love to hear from you!

If you would like to contribute code to the project, you need to be covered by an Islandora Foundation [Contributor License Agreement](http://islandora.ca/sites/default/files/islandora_cla.pdf) or [Corporate Contributor License Agreement](http://islandora.ca/sites/default/files/islandora_ccla.pdf). Please see the [Contributors](http://islandora.ca/resources/contributors) pages on Islandora.ca for more information.

We recommend using the [islandora-playbook](https://github.com/Islandora-Devops/islandora-playbook) to get started.

## License

[GPLv2](./LICENSE).
