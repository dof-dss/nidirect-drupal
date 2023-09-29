[![CircleCI](https://circleci.com/gh/dof-dss/nicsdru_nidirect_theme.svg?style=svg)](https://circleci.com/gh/dof-dss/nicsdru_nidirect_theme)

# nicsdru_nidirect_theme
This repository contains code for nidirect Drupal 8 site theme.

## Table of contents

- [Quick start](#quick-start)
- [What's included](#whats-included)
- [Bugs and feature requests](#bugs-and-feature-requests)
- [Using the NPM scripts](#using-the-npm-scripts)
- [Theme hooks](#theme-hooks)
- [Helpers](#helpers)
- [Sub-theming](#sub-theming)
- [Documentation](#documentation)

## Quick start
- `composer config repositories.repo-name vcs git@github.com:dof-dss/nicsdru_nidirect_theme.git`
- `composer require dof-dss/nicsdru_nidirect_theme:dev-`
- in terminal cd to the directory (`[DRUPAL_ROOT]/themes/custom/nicsdru_nidirect_theme`) and run `npm install`

## What's included

The kit uses [NPM scripts](https://docs.npmjs.com/misc/scripts) and packages to create tooling to:

* write and compile scss to css
* apply appropriate prefixing for css rules
* write and uglify js
* detect features the user’s browser has to offer (via Modernizr)
* code linting
* optimise and compress images for the web
* living style guide for the code via kss

Within the download you'll find the following directories and files, logically grouping common assets, you'll see something like this:

```
nicsdru_origins_theme/
└── config/
└── css/
└── docs/
└── inc/
└── js/
└── src/
│   ├── images/
│   ├── js/
│   ├── scss/
│   ├── styleguide/
└── templates/
└── .gitignore
└── composer.json
└── mkdocs.yml
└── nicsdru_nidirect_theme.breakpoints.yml
└── nicsdru_nidirect_theme.info.yml
└── nicsdru_nidirect_theme.layouts.yml
└── nicsdru_nidirect_theme.libraries.yml
└── nicsdru_nidirect_theme.theme
└── package.json
└── README.md
└── screenshot.png
```

## Using the NPM scripts
The `package.json` includes the following commands and tasks:

| Task | Description |
| --- | --- |
| `npm run build` | `npm run build` compiles files into their build directories ready for use in a production environment. **Uses [Sass](https://sass-lang.com/), [Autoprefixer][autoprefixer], [Modernizr](https://modernizr.com) and [UglifyJS](https://github.com/mishoo/UglifyJS2).** |
| `npm run watch` | Watches for changes to scss and js files & compiles them for development purposes.|
| `npm run lint`  | Will run all `.scss` and `.js` files through their respective linting tools - [eslint]() & [sass-lint](https://github.com/sasstools/sass-lint) |
| `npm run lint-scss`  | Will run all `.scss` files through [sass-lint](https://github.com/sasstools/sass-lint). The command can also accept a parameter to lint an individual file. To do this `npm run lint-scss -- scssfile:name-of-file` where `name-of-file` is the file you want to lint relative to the package.json for the project. |

Run `npm run` to see all the npm scripts.

## Theme hooks

Common theme hooks such as hook_preprocess, hook_alter should not be added to
the `.theme` file but to their respective include file under the /inc directory
There are currently hook files for:
- alter
- preprocess
- process
- theme

## Utility classes

The src/LayoutClasses.php class provides static constants for commonly used
CSS class groups.

The src/Helpers.php class includes commonly reused or helpful functions.
The 'blockContent' method will load a block and return the viewBuilder render
array.


## Documentation

### Page title handling

Drupal normally adds the label or title variable to the `page.twig.html` template, and where this needs to appear in
a separate location for other content variants it requires the use of a secondary template variable + preprocessing
to show/hide the correct value.

Drupal 8 introduces a page title block that is rendered in a given template with the `{{ drupal_block('page_title_block', wrapper=false) }}`
Twig extension to render Drupal blocks in-situ. This approach offers maximum flexibility and moves any conditional
logic from preprocess hooks into the block configuration itself.

[autoprefixer]: https://github.com/postcss/autoprefixer


