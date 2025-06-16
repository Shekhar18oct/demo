# Git Deploy

Git Deploy lets you develop on a live site and still satisfy version
requirements and get accurate results from the update status system. This makes
it easier to contribute to the projects you use.

Version information is added automatically when the Drupal packaging system
creates a release. If you check out a contributed project from the Drupal
repository with Git, it should not have any version information. Git Deploy gets
the missing version information from the project's Git log.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/git_deploy).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/git_deploy).


## Requirements

This module requires the following:

- Access to the git command.
- The ability for PHP to execute shell commands.


## Alternatives

[Drush 8](https://docs.drush.org/en/8.x/): Can automatically add version
information to projects you check out with Git. This is helpful if you need Git
to get a specific dev release rather than to contribute to development. A
project’s dev release changes whenever a maintainer updates its branch. To
enforce consistency among sites using a dev release, you can lock them to the
same release by checking out a specific Git commit.

- The `drush pm-download` command with options `--package-handler=git_drupalorg
  --gitinfofile` performs a Git clone and checkout and adds version information
  to the project info file.
- The drush make command automatically adds version information to the project
  info file without additional options.

[Composer Deploy](https://www.drupal.org/project/composer_deploy): Gets version
information from Composer metadata for projects installed with Composer.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

The module has no menu or modifiable settings. There is no configuration. When
enabled, the module will prevent unsupported version warnings due to missing
version info in Drupal contrib projects checked out with Git and will show valid
versions for those projects on the available updates page.
