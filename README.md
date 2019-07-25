# NI Direct Drupal 8

Drupal source code for the NIDirect website: https://www.nidirect.gov.uk.

Drupal 8 install based on drupal-composer/drupal-project with that project set at the upstream remote.

## Updating Core

Follow the instructions at: https://github.com/drupal-composer/drupal-project

## Getting started

For local development, please review the [README file for the local development framework](https://github.com/dof-dss/lando-d7-to-d8-migrate).

This document intends to cover an overview of how the site is structured and how it approaches certain Drupal topics.

## Project structure

Some key project directories and/or files:

```
composer.json (defines project dependencies)
composer.lock (what composer install uses when running, ensure this is always in sync with composer.json)
config (configuration management folder)
phpcs.sh (shell script to simplify invocation of PHPCS tool)
vendor (third party dependencies and libraries; sourced by composer)
web (docroot folder)
web/core (Drupal core; don't alter except via composer patches)
web/modules/contrib (community modules; don't alter except via composer patches)
web/modules/custom (custom code; sourced from other repository by composer)
web/modules/migrate/nidirect_migrations (migration modules; sourced from other repository by composer)
web/themes/custom/nidirect (custom site theme)
web/sites/default/settings.php (generated settings file, see note below):
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

All of these tools can be run locally - and you should at least once per feature - and the CI service is present to ensure that even if you do not then you cannot merge your code until any issues are corrected.

## Contribution

All changes **must** be submitted with an appropriate pull request (PR) in GitHub. Direct commits to `master` or `development` are not permitted.

## Configuration management

This project employs a suite of modules to control how site configuration is imported and behaves:

- [config_split](https://www.drupal.org/project/config_split): Allows configuration to be defined per environment. Ie: development modules will be enabled for local work, and remain off/absent from others.
- [config_readonly](https://www.drupal.org/project/config_readonly): Ensures that active configuration cannot be changed by site admins, but blocking certain system forms from saving. A whitelist is available for 'admin content' that is not necessarily tracked in code or is needed to be changed regularly by site admins.
- [config_ignore](https://www.drupal.org/project/config_ignore): Ensures that some site configuration is not overwritten during configuration import during deployments.

TL;DR: Site admins are restricted to make changes to active configuration via the site UI, because it can be lost when syncing with the config kept in code.

## Front-end toolchain

> Details of the front-end toolchain and approach, even if it's just a link to a README.md file in the theme folder/repo.

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
