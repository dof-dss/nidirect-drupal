[![CircleCI](https://circleci.com/gh/dof-dss/nidirect-drupal.svg?style=svg)](https://circleci.com/gh/dof-dss/nidirect-drupal)

# NI Direct Drupal 8

Drupal source code for the NIDirect website: https://www.nidirect.gov.uk.

Drupal 8 project based on `drupal-composer/drupal-project`. The project comprises of a number of disparate repositories and the diagram below outlines the relationships between them.

[Composer](https://getcomposer.org/) is used to define and build the project; see `composer.json` for details.

> This repository corresponds to the 'Drupal 8 project template' element in the diagram.

![NI Direct project components](https://mermaid.ink/img/eyJjb2RlIjoiZ3JhcGggTFJcbnN1YmdyYXBoIE5JIERpcmVjdCBEcnVwYWwgOFxubGFuZG9bXCJMb2NhbCBkZXYgZnJhbWV3b3JrIChMYW5kbylcIl1cbmQ4W0RydXBhbCA4IHByb2plY3QgdGVtcGxhdGVdXG5zdWJncmFwaCBDdXN0b20gY29kZVxuY3VzdG9tW05JRGlyZWN0IGN1c3RvbSBtb2R1bGVzXVxubWlncmF0ZVtOSURpcmVjdCBEOCBtaWdyYXRpb24gbW9kdWxlc11cbmVuZFxuc3ViZ3JhcGggRnJvbnRlbmRcbmJhc2V0aGVtZVtCYXNlIHRoZW1lOiBPcmlnaW5zXVxubmlkaXJlY3R0aGVtZVtOSURpcmVjdCB0aGVtZV1cbmVuZFxuc3ViZ3JhcGggRXh0ZXJuYWxcbmNvbnRyaWJbQ29udHJpYnV0ZWQgRHJ1cGFsIG1vZHVsZXNdXG4zcFtUaGlyZCBwYXJ0eSBsaWJyYXJpZXNdXG5lbmRcbmVuZFxubGFuZG8tLT5kOFxuZDgtLT5jdXN0b21cbmQ4LS0-bWlncmF0ZVxuZDgtLT5iYXNldGhlbWVcbmQ4LS0-bmlkaXJlY3R0aGVtZVxuZDgtLT5jb250cmliXG5kOC0tPjNwXG5cbmNsaWNrIGxhbmRvIFwiaHR0cHM6Ly9naXRodWIuY29tL2RvZi1kc3MvbGFuZG8tZDctdG8tZDgtbWlncmF0ZVwiIFwibGFuZG8tZDctdG8tZDgtbWlncmF0ZVwiXG5jbGljayBkOCBcImh0dHBzOi8vZ2l0aHViLmNvbS9kb2YtZHNzL25pZGlyZWN0LWRydXBhbFwiIFwibmlkaXJlY3QtZHJ1cGFsXCJcbmNsaWNrIGN1c3RvbSBcImh0dHBzOi8vZ2l0aHViLmNvbS9kb2YtZHNzL25pZGlyZWN0LXNpdGUtbW9kdWxlc1wiIFwibmlkaXJlY3Qtc2l0ZS1tb2R1bGVzXCJcbmNsaWNrIG1pZ3JhdGUgXCJodHRwczovL2dpdGh1Yi5jb20vZG9mLWRzcy9uaWRpcmVjdC1kOC1taWctbW9kc1wiIFwibmlkaXJlY3QtZDgtbWlnLW1vZHNcIlxuY2xpY2sgYmFzZXRoZW1lIFwiaHR0cHM6Ly9naXRodWIuY29tL2RvZi1kc3Mvbmljc2RydV9vcmlnaW5zX3RoZW1lXCIgXCJuaWNzZHJ1X29yaWdpbnNfdGhlbWVcIlxuY2xpY2sgbmlkaXJlY3R0aGVtZSBcImh0dHBzOi8vZ2l0aHViLmNvbS9kb2YtZHNzL25pY3NkcnVfbmlkaXJlY3RfdGhlbWVcIiBcIm5pY3NkcnVfbmlkaXJlY3RfdGhlbWVcIiIsIm1lcm1haWQiOnsidGhlbWUiOiJkZWZhdWx0In19 "NI Direct project components")

## Updating Core

Follow the instructions at: https://github.com/drupal-composer/drupal-project

## Getting started

For local development, please review the [README file for the local development framework](https://github.com/dof-dss/lando-d7-to-d8-migrate).

This document intends to cover an overview of how the site is structured and how it approaches certain Drupal topics.

## Project structure

Some key project directories and/or files:

```
├── composer.json (defines project dependencies)
├── composer.lock (what composer install uses when running, ensure this is always in sync with composer.json)
├── config (configuration management folder)
├── phpcs.sh (shell script to simplify invocation of PHPCS tool)
├── vendor (third party dependencies and libraries; sourced by composer)
├── web (docroot folder)
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

## Drupal settings file

Rather than store a per-environment file and symlink/swap in at build time to replace `sites/default/settings.php`, this file is always regenerated using the example settings file provided by Drupal Core itself. Specific feature flags and/or variable values are provided by environment variables to ensure maximum portability for the single file across all environments.

Do not make changes directly to this file where you find it because they will be overwritten whenever the site is deployed or rebuilt.

## Code workflow

Like the popular git-flow workflow, but without the more complex elements:

- `development` bleeding-edge. All feature branches originate from here.
- `master` stable, cut release tags from here.

Preferred feature branch naming convention: `TICKET_REF-short-desc`, for example: `D8NID-123-event-listing`

We highly recommend developers use a tool such as [Talisman](https://github.com/thoughtworks/talisman) to ensure they do not commit potentially sensitive material into the codebase.

API keys, auth tokens or other credentials values *must* be stored as environment variables and never stored in the codebase itself.

## Continuous integration

Automated testing is configured to check:

- Static analysis of custom PHP code against drupal.org coding standards using [phpcs](https://github.com/squizlabs/PHP_CodeSniffer).
- Analysis of custom code for deprecated code using [drupal-check](https://github.com/mglaman/drupal-check).
- Run any defined unit tests via [phpunit](https://phpunit.de/).
- Run functional UI tests using [nightwatch.js](https://nightwatchjs.org).

All of these tools can be run locally with Circle CI present to ensure that even if you do not then you cannot merge your code to production until any failing tests are corrected.

## Contribution

All changes **must** be submitted with an appropriate pull request (PR) in GitHub. Direct commits to `master` or `development` are not permitted.

## Configuration management

This project employs a suite of modules to control how site configuration is imported and behaves:

- [config_split](https://www.drupal.org/project/config_split): Allows configuration to be defined per environment. Ie: development modules will be enabled for local work, and remain off/absent from others.
- [config_readonly](https://www.drupal.org/project/config_readonly): Ensures that active configuration cannot be changed by site admins, but blocking certain system forms from saving. A whitelist is available for 'admin content' that is not necessarily tracked in code or is needed to be changed regularly by site admins.
- [config_ignore](https://www.drupal.org/project/config_ignore): Ensures that some site configuration is not overwritten during configuration import during deployments.

> TL;DR: Site admins are restricted to make changes to active configuration via the site UI, because it can be lost when syncing with the config kept in code.

### Config split

```
config/
├── development/ [environment specific config for development site]
├── local/ [environment specific config for local development work]
├── pre_production/ [environment specific config for pre-production site]
└── sync/ [canonical site config; i.e.: live/production.]
```

*'sync' rather than 'production' is used as this is the default and helps avoid problems with contrib modules and hosting platforms that assume this is always present.*

### Overriding of taxonomy term view pages

This project uses a very customised approach. 

When clicking on the 'view' page for a taxonomy term ('/information-and-services/motoring' for example) you see any sub terms from the 
'site themes' vocabulary as usual, but you also see links to any node that has 'Motoring' set as its 'Theme/subtheme' or 'Supplementary subtheme'.

This is achieved by merging the results from the 'Articles by Term' view ('All articles by term - embed' display) and the 'Site subtopics' view 
('By topic - simple embed' display) which is done in the 'nicsdru_nidirect_theme_preprocess_taxonomy_term' function which may be found in the 
nicsdru_nidirect_theme.theme file.

In addition to this, taxonomy terms may be overridden by 'campaigns', which are nodes of type 'landing page'. The landing page node will replace the taxonomy term that is
selected in the 'Theme/subtheme' field. This is achieved by using the 'Articles by Term' view ('Campaign List - embed' display). Again, processing may be found in the
'nicsdru_nidirect_theme_preprocess_taxonomy_term' function.

#### Some key concepts:

> Config blacklist

This refers to configuration that is fully excluded from other environments. An example might be: the devel module; only used for debugging purposes on the local development environment.

> Config graylist

This refers to configuration that is used in more than one environment, but may differ slightly in each case. When config import/export is run, the config filter takes care of merging/splitting as required.

> Active configuration setting

Inside `sites/default/settings.php` you will find these lines:

```
$config['config_split.config_split.local']['status'] = TRUE;
$config['config_split.config_split.development']['status'] = FALSE;
$config['config_split.config_split.pre-production']['status'] = FALSE;
```

These indicate which config_split configuration is active at any given time. *NB: you can only have one active at a time.* The precise setting of these will, of course, vary on each environment that hosts the site to ensure that configuration imports use the correct values.

> Importing configuration and structure

When developing it is important to keep the site configuration and structure in sync with the exported config contained under the config/sync directory.
Every time you work on a new feature you should perform the following 2 steps

Step 1: Update vendor packages
* composer install

Step 2: Update site configuration and structure

* lando imp : Imports configuration and structure (blocks)
* lando drush cim - Import configuration changes only
* lando drush ib - Import custom blocks
* lando drush im - Import custom menu links

> Exporting your work

First, ensure that your active config_split configuration is correct (see above). If you have recently changed it, you will need to run `drush cr` to bring Drupal's service container and caches up to date.

Use Drupal, drush or drupal console to export as usual, e.g.: `drush cex`. Config split integrates with the config filters to correctly set your updated configuration in the correct place providing your initial settings are correct.

When you create new features, you should take care to ensure that configuration is exported/applied to the correct location or your changes may not apply when deploying to other environments.

*Known bug*: When exporting via drush 9.x, you will see this message:

`The .yml files in your export directory (../config/sync) will be deleted and replaced with the active config`

This is the default drush message and can be safely disregarded. You should, of course, always review what the end result looks like and only stage/commit the files that are relevant to your work.

*NB: config_split used to rely on a specific export command (`drush csex`) but this is no longer required now that drush, config_split and config filter all work in tandem.*

## Front-end toolchain

See https://github.com/dof-dss/nicsdru_nidirect_theme/blob/master/README.md for full details.

## Deployment

> TODO: outline how to release/deploy code for this project.

### Release naming conventions

The project operates using [semantic versioning](https://semver.org/), amended slightly to be more Drupal-centric in structure:

Given the below as a starting point:

```
8.x-Y.Z
```

- Major change (core update): Incremenent `8.x`, for example: `8.x-3.0 > 9.x-1.0`.
- Routine change (new feature): Increment `Y`. For example, `8.x-1.4 > 8.x-2.0` or `8.x-9.0 > 8.x-10.0`.
- Small change: Increment `Z`. For example, `8.x-1.4 > 8.x-1.5` or `8.x-2.9 > 8.x-2.10`.

## Project workflow

The project presently uses a [Lean with Kanban](https://inviqa.com/blog/introduction-lean-kanban-software-development) methodology. At it's core, this emphasises the team to:

- Visualise progress.
- Limit work in progress (WIP).
- Manage the workflow.
- Make policies explicit.
- Implement feedback loops.
- Improve and evolve collaboratively.

New bugs, tasks or features should be added to the [JIRA backlog](https://digitaldevelopment.atlassian.net/browse/D8NID). The backlog is reviewed and prioritised at least every two weeks.

There are limits on WIP - which may change - so pay attention to these.

# Licence
Unless stated otherwise, the codebase is released under [the MIT License](http://www.opensource.org/licenses/mit-license.php). This covers both the codebase and any sample code in the documentation.
