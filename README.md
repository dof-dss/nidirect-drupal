[![CircleCI](https://circleci.com/gh/dof-dss/nidirect-drupal.svg?style=svg)](https://circleci.com/gh/dof-dss/nidirect-drupal)

# NI Direct Drupal

Drupal source code for the NIDirect website: https://www.nidirect.gov.uk.

Drupal project based on `drupal/recommended-project`. The project comprises of a number of repositories and the diagram below outlines the relationships between them.

[Composer](https://getcomposer.org/) is used to define and build the project; see `composer.json` for details.

> This repository corresponds to the 'Drupal project template' element in the diagram.

![NI Direct project components](https://mermaid.ink/img/eyJjb2RlIjoiZ3JhcGggTFJcbnN1YmdyYXBoIE5JIERpcmVjdCBEcnVwYWwgOFxubGFuZG9bXCJMb2NhbCBkZXYgZnJhbWV3b3JrIChMYW5kbylcIl1cbmQ4W0RydXBhbCA4IHByb2plY3QgdGVtcGxhdGVdXG5zdWJncmFwaCBDdXN0b20gY29kZVxuY3VzdG9tW05JRGlyZWN0IGN1c3RvbSBtb2R1bGVzXVxubWlncmF0ZVtOSURpcmVjdCBEOCBtaWdyYXRpb24gbW9kdWxlc11cbmVuZFxuc3ViZ3JhcGggRnJvbnRlbmRcbmJhc2V0aGVtZVtCYXNlIHRoZW1lOiBPcmlnaW5zXVxubmlkaXJlY3R0aGVtZVtOSURpcmVjdCB0aGVtZV1cbmVuZFxuc3ViZ3JhcGggRXh0ZXJuYWxcbmNvbnRyaWJbQ29udHJpYnV0ZWQgRHJ1cGFsIG1vZHVsZXNdXG4zcFtUaGlyZCBwYXJ0eSBsaWJyYXJpZXNdXG5lbmRcbmVuZFxubGFuZG8tLT5kOFxuZDgtLT5jdXN0b21cbmQ4LS0-bWlncmF0ZVxuZDgtLT5iYXNldGhlbWVcbmQ4LS0-bmlkaXJlY3R0aGVtZVxuZDgtLT5jb250cmliXG5kOC0tPjNwXG5cbmNsaWNrIGxhbmRvIFwiaHR0cHM6Ly9naXRodWIuY29tL2RvZi1kc3MvbGFuZG8tZDctdG8tZDgtbWlncmF0ZVwiIFwibGFuZG8tZDctdG8tZDgtbWlncmF0ZVwiXG5jbGljayBkOCBcImh0dHBzOi8vZ2l0aHViLmNvbS9kb2YtZHNzL25pZGlyZWN0LWRydXBhbFwiIFwibmlkaXJlY3QtZHJ1cGFsXCJcbmNsaWNrIGN1c3RvbSBcImh0dHBzOi8vZ2l0aHViLmNvbS9kb2YtZHNzL25pZGlyZWN0LXNpdGUtbW9kdWxlc1wiIFwibmlkaXJlY3Qtc2l0ZS1tb2R1bGVzXCJcbmNsaWNrIG1pZ3JhdGUgXCJodHRwczovL2dpdGh1Yi5jb20vZG9mLWRzcy9uaWRpcmVjdC1kOC1taWctbW9kc1wiIFwibmlkaXJlY3QtZDgtbWlnLW1vZHNcIlxuY2xpY2sgYmFzZXRoZW1lIFwiaHR0cHM6Ly9naXRodWIuY29tL2RvZi1kc3Mvbmljc2RydV9vcmlnaW5zX3RoZW1lXCIgXCJuaWNzZHJ1X29yaWdpbnNfdGhlbWVcIlxuY2xpY2sgbmlkaXJlY3R0aGVtZSBcImh0dHBzOi8vZ2l0aHViLmNvbS9kb2YtZHNzL25pY3NkcnVfbmlkaXJlY3RfdGhlbWVcIiBcIm5pY3NkcnVfbmlkaXJlY3RfdGhlbWVcIiIsIm1lcm1haWQiOnsidGhlbWUiOiJkZWZhdWx0In19 "NI Direct project components")

## Updating Core

Follow the instructions at: https://www.drupal.org/docs/updating-drupal/updating-drupal-core-via-composer

## Getting started

For local development, please review the [README file for the local development framework](https://github.com/dof-dss/lando-d7-to-d8-migrate).

This document intends to cover an overview of how the site is structured and how it approaches certain Drupal topics.

## Project structure

Some key project directories and/or files:

```
└── .circleci/ (Circle CI configuration and supporting files)
├── .platform/ (platform.sh routes and services config)
├── .platform.app.yaml (platform.sh application config)
├── composer.json (defines project dependencies)
├── composer.lock (what composer install uses when running, ensure this is always in sync with composer.json)
├── config/ (configuration management folder)
├── phpcs.sh (shell script to simplify invocation of PHPCS tool)
├── vendor/ (third party dependencies and libraries; sourced by composer)
├── web/ (docroot folder)
├── web/core (Drupal core; don't alter except via composer patches)
├── web/modules/contrib (community modules; don't alter except via composer patches)
├── web/modules/custom (custom code; sourced from other repository by composer)
├── web/modules/origins (common internal custom modules; sourced from other repository by composer)
├── web/modules/migrate/nidirect_migrations (migration modules; sourced from other repository by composer)
├── web/themes/custom/nicsdru_origins_theme (custom base theme)
├── web/themes/custom/nicsdru_nidirect_theme (custom site theme)
├── web/profiles/custom/test_profile (baseline test profile for functional tests)
├── web/sites/default/development.services.yml (generated local services file)
└── web/sites/default/settings.php (generated settings file, see note below):
```

## Drupal settings files

There is a general settings file (`sites/default/settings.php`) that detects and loads more specific settings, depending
on the environment the application is running in.

## Code workflow

Like the popular git-flow workflow, but without the more complex elements:

- `development` bleeding-edge. All feature branches originate from here.
- `main` stable, automatically deployed to platform.sh. Release tags should originate from here.

API keys, auth tokens or other sensitive values *must* be stored as environment variables and never stored in the codebase itself.

## Continuous integration

Automated testing is configured to check:

- Static analysis of custom PHP code against drupal.org coding standards using [phpcs](https://github.com/squizlabs/PHP_CodeSniffer).
- Analysis of custom code for deprecated code using [drupal-check](https://github.com/mglaman/drupal-check).
- Run any defined unit tests via [phpunit](https://phpunit.de/).
- Run functional UI tests using [nightwatch.js](https://nightwatchjs.org).

All of these tools can be run locally with Circle CI.

## Contribution

All changes should be submitted with an appropriate pull request (PR) in GitHub. Direct commits to `main` or `development` are not normally permitted.

## Configuration management

This project employs a suite of modules to control how site configuration is imported and behaves:

- [config_split](https://www.drupal.org/project/config_split): Allows configuration to be defined per environment. Ie: development modules will be enabled for local work, and remain off/absent from others.
- [config_readonly](https://www.drupal.org/project/config_readonly): Ensures that active configuration cannot be changed by site admins, but blocking certain system forms from saving. Exceptions can be defined for 'admin content' that is not necessarily tracked in code or is needed to be changed regularly by site admins.
- [config_ignore](https://www.drupal.org/project/config_ignore): Ensures that some site configuration is not overwritten during configuration import during deployments.
- [multiline_config](https://www.drupal.org/project/multiline_config): Exports text strings as multiline making it easier to read, review and edit configurations such as those exported by the Webforms module.

> TL;DR: Site admins are restricted to make changes to active configuration via the site UI, because it can be lost when syncing with the config kept in code.

### Config split

```
config/
├── development/ [environment specific config for development site]
├── local/ [environment specific config for local development work]
├── production/ [environment specific config for production environment]
└── sync/ [general site config]
```

### Overriding of taxonomy term view pages

This project uses a very customised approach.

When clicking on the 'view' page for a taxonomy term (`/information-and-services/motoring` for example) you see any sub terms from the
'site themes' vocabulary as usual, but you also see links to any node that has 'Motoring' set as its 'Theme/subtheme' or 'Supplementary subtheme'.

This is achieved by merging the results from the 'Articles by Term' view ('All articles by term - embed' display) and the 'Site subtopics' view
('By topic - simple embed' display) which is done in the `nicsdru_nidirect_theme_preprocess_taxonomy_term` function which may be found in the
`nicsdru_nidirect_theme.theme` file.

In addition to this, taxonomy terms may be overridden by 'campaigns', which are nodes of type 'landing page'. The landing page node will replace the taxonomy term that is
selected in the 'Theme/subtheme' field. This is achieved by using the 'Articles by Term' view ('Campaign List - embed' display). Again, processing may be found in the
`nicsdru_nidirect_theme_preprocess_taxonomy_term` function.

### Display of teasers on Landing Pages

In order to add teasers to a landing page you should first select a theme/subtheme and then go in to the layout builder for the node. Once you have added a 'one column' section
you should then be able to add a block. Choose to 'create a custom block' and then choose 'Article Teasers by Topic' from the list of available blocks. A list of teasers will
then appear according to the theme/subtheme selected.

Note that once you have saved the layout builder changes, you may then go back to the article teasers block and click on 'configure' - if you then select 'manually control listing'
you will be able to add/remove teasers and/or control the order.

#### Some key concepts:

> Config blocklist

This refers to configuration that is fully excluded from other environments. An example might be: the devel module; only used for debugging purposes on the local development environment.

> Config graylist

This refers to configuration that is used in more than one environment, but may differ slightly in each case. When config import/export is run, the config filter takes care of merging/splitting as required.

> Active configuration setting

Inside `sites/default/settings.php` you will find these lines:

```
$config['config_split.config_split.local']['status'] = TRUE;
$config['config_split.config_split.development']['status'] = FALSE;
$config['config_split.config_split.production']['status'] = FALSE;
```

These indicate which config_split configuration is active at any given time. *NB: you can only have one active at a time.* The precise setting of these will vary on the environment that hosts the site to ensure that configuration imports use the correct values.

> Importing configuration and structure

When developing it is important to keep the site configuration and structure in sync with the exported config contained under the `config/sync` directory.

Every time you work on a new feature you should perform the following 2 steps

Step 1: Update vendor packages
* `composer install`

Step 2: Update site configuration and structure
* `lando imp` : Imports configuration and structure (blocks)

NOTE: Custom Menu links only applies to links created from the `admin/structure/menu` page. Links created via the node edit screen etc will not be exported as a menu structure item.

> Exporting your work

First, ensure that your active config_split configuration is correct (see above). If you have recently changed it, you will need to run `drush cr` to bring Drupal's service container and caches up to date.

Use Drupal, drush or drupal console to export as usual, e.g.: `drush cex`. Config split integrates with the config filters to correctly set your updated configuration in the correct place providing your initial settings are correct.

When you create new features, you should take care to ensure that configuration is exported/applied to the correct location or your changes may not apply when deploying to other environments.

*Known bug*: When exporting via drush 9.x, you will see this message:

`The .yml files in your export directory (../config/sync) will be deleted and replaced with the active config`

This is the default drush message and can be safely disregarded. You should, of course, always review what the end result looks like and only stage/commit the files that are relevant to your work.

*NB: config_split used to rely on a specific export command (`drush csex`) but this is no longer required now that drush, config_split and config filter all work in tandem.*

To export custom blocks or menus use `/admin/structure/structure-sync/` and more information can be found on the structure_sync module readme.

## Front-end toolchain

See https://github.com/dof-dss/nicsdru_nidirect_theme/blob/main/README.md for full details.

## Deployment

The project is hosted using platform.sh which operates a continuous deployment process. That means:

- Any code merged/pushed to `main` will deploy to production.

### Release naming conventions

The project operates using [semantic versioning](https://semver.org/).

# Licence
Unless stated otherwise, the codebase is released under [the MIT License](http://www.opensource.org/licenses/mit-license.php). This covers both the codebase and any sample code in the documentation.
