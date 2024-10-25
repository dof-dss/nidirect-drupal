[![CircleCI](https://circleci.com/gh/dof-dss/nidirect-drupal.svg?style=svg)](https://circleci.com/gh/dof-dss/nidirect-drupal)

# NI Direct Drupal

Drupal source code for the NIDirect website: https://www.nidirect.gov.uk.

Drupal project based on `drupal/recommended-project`. The project comprises of a number of repositories and the diagram below outlines the relationships between them.

[Composer](https://getcomposer.org/) is used to define and build the project; see `composer.json` for details.

Repository links for custom modules and themes:

- https://github.com/dof-dss/nidirect-site-modules
- https://github.com/dof-dss/nicsdru_origins_modules
- https://github.com/dof-dss/nicsdru_origins_theme
- https://github.com/dof-dss/nicsdru_nidirect_theme

## Getting started

### Pre-requisites

1. Rancher Desktop [https://docs.rancherdesktop.io/(https://docs.rancherdesktop.io/)]
   - Install notes: https://ddev.readthedocs.io/en/stable/users/install/docker-installation/#rancher-desktop
   - Rancher is an cross-platform Open Source container management tool, as an alternative to Docker Desktop.
   - **Make sure you turn off Kubernetes support or it can block port 80 and 443 causing DDEV to fail to start.**
2. DDEV [https://ddev.com/get-started/](https://ddev.com/get-started/)
   - The project uses a Mutagen (a file sync agent) performance profile for all platforms. It installs itself into each project, as needed.
3. Platform CLI tool and access [https://docs.platform.sh/development/cli.html](https://docs.platform.sh/development/cli.html)
   - You will need to request access to the projects you work on.
   - Once granted, log in with `platform login -f` to renew any local SSH certs.
4. Environment variables
   - You need to populate the sensitive values in `.ddev/.env` as part of the build process.
   - **Ask the team for how best to obtain these.**
5. Platform SH API Token
   - You will need to generate an API Token for DDev. This can be found on platform.sh -> My Profile -> API Tokens
   - Assign the token to the PLATFORMSH_CLI_TOKEN key of your .env file.
6. Install DBeaver or SequelAce (if you haven't done so already)
   - DBeaver (MacOS for Apple Silicon) https://dbeaver.io/download/
   - Sequel Ace https://sequel-ace.com

### Next installation/import steps

- Clone this repo and change directory into it.
- `mv <path-to-dot-env-file> .ddev/.env`
- `ddev start`. This will run composer inside the container for you, avoiding host-level inconsistencies.
- Verify env vars have taken effect with: `ddev exec "env | sort""`
- Run `ddev pull nidirect` which will import the database and run config import.
- To inspect the databases you can use either `ddev dbeaver` or `ddev sequelace` depending on the DBMS you have installed.
- Copy your `~/.composer/auth.json` to `.ddev/homeadditions/.composer` to allow git operations inside the container using `ddev composer`

## Troubleshooting

- Can't connect to the database
  - Ensure your .env file values are valid and in effect. See https://ddev.readthedocs.io/en/stable/users/extend/customization-extendibility/#environment-variables-for-containers-and-services for details.
  - Ensure your databases have fully imported.
- Port 443 or 80 is blocked.
  - Make sure Rancher Desktop's Kubernetes option is disabled.
  - Run `lando poweroff` to ensure any legacy applications are not listening on the ports.
  - https://ddev.readthedocs.io/en/stable/users/usage/troubleshooting/#web-server-ports-already-occupied

## Updating Core

Follow the instructions at: https://www.drupal.org/docs/updating-drupal/updating-drupal-core-via-composer

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

All of these tools can be run locally with Circle CI.

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

> Active configuration setting

Inside settings files such as `sites/default/local.settings.php` you will find lines such as these:

```
$config['config_split.config_split.local']['status'] = TRUE;
$config['config_split.config_split.development']['status'] = FALSE;
$config['config_split.config_split.production']['status'] = FALSE;
```

These indicate which config_split configuration is active at any given time. *NB: you can only have one active at a time.* The precise setting of these will vary on the environment that hosts the site to ensure that configuration imports use the correct values.

## Front-end toolchain

See https://github.com/dof-dss/nicsdru_nidirect_theme/blob/main/README.md for full details.

## Deployment

The project is hosted using platform.sh which operates a continuous deployment process. That means:

- Any code merged/pushed to `main` will deploy to production.

### Release naming conventions

The project operates using [semantic versioning](https://semver.org/).

## Contribution

> Contributors to repositories hosted in dof-dss are expected to follow the Contributor Covenant Code of Conduct, and those working within Government are also expected to follow the Northern Civil Service Code of Ethics and Civil Service Code. For details see https://github.com/dof-dss/contributor-code-of-conduct

All changes should be submitted with an appropriate pull request (PR) in GitHub. Direct commits to `main` or `development` are not normally permitted.

# Licence
Unless stated otherwise, the codebase is released under [the MIT License](http://www.opensource.org/licenses/mit-license.php). This covers both the codebase and any sample code in the documentation.
