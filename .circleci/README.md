# CircleCI service overview

This service runs a number of discrete testing steps, defined as jobs in `.circleci/config.yml`.

See https://circleci.com/docs/2.0 for configuration file/language specifications.

Drupal config is generally copied into the required locations in the containers for better clarity. Some exceptions exist where `sed` is easier to use, eg: nightwatch config.

Environment variable usage is encouraged to safeguard sensitive values, but usage can be fiddly as interpolation in parts of the config file will work in some parts but not others.

## Running locally

It is possible to debug/run the pipeline locally using the CircleCI Local CLI tool.

See https://circleci.com/docs/2.0/local-cli/ for installation and further details.

TL;DR:

- Install with: `brew install circleci`
- Run individual jobs like this: `circleci local execute --job YOUR_JOB_NAME -e GITHUB_TOKEN=YOUR-TOKEN-VALUE -e DB_NAME=circle_test -e DB_USER=root -e DB_PASS= -e DB_PREFIX= -e DB_HOST=127.0.0.1 -e DB_PORT=3306 -e DB_NAMESPACE=Drupal\\Core\\Database\\Driver\\mysql -e DB_DRIVER=mysql -e CONFIG_SYNC_DIRECTORY=../config/sync`
  - Note how environmental variables from CircleCI are not accessible outside of that platform. You need to pass them in as per above when running locally. That might be tricky if you don't have a certain key value; CircleCI's environment variable browser will not show you the raw value once the variable has been created.
- Features such as parallelism, workspace sharing and directory caching won't work. You may need to adjust your job's execution steps to compensate for this.

## Interaction with other supporting repos/services

This is the primary NIDIrect CI pipeline. Other, supporting repositories will have their own pipelines that provide basic, but helpful, checks to trap simple issues earlier on. For example:

https://github.com/dof-dss/nidirect-site-modules contains code that we want to validate against coding standards and deprecated code. We can't run UI tests or kernel test here because we would need the full set of services and fully working Drupal install to do this.

Updates to supporting repos (new tags) will require a change to the `composer.lock` file in this project, triggering the required full build steps/checks before a release is potentially created and providing a much better chance of spotting issues before they might be deployed.
