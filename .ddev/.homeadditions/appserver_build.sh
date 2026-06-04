#!/usr/bin/env bash
APP_ROOT=/var/www/html
DRUPAL_ROOT=$APP_ROOT/web

# If we don't have a Drupal install, download it.
if [ ! -d "$DRUPAL_ROOT/core" ]; then
  echo "Installing Drupal"
  export COMPOSER_PROCESS_TIMEOUT=600
  composer install
fi

# Create Drupal public files directory and set IO permissions.
if [ ! -d "$DRUPAL_ROOT/files" ]; then
  echo "Creating public Drupal files directory"
  mkdir -p $DRUPAL_ROOT/files
  chmod -R 0775 $DRUPAL_ROOT/files
fi

# Create Drupal private file directory above web root.
if [ ! -d "$APP_ROOT/private" ]; then
  echo "Creating private Drupal files directory"
  mkdir -p $APP_ROOT/private
fi

if [ ! -d $DRUPAL_ROOT/sites/default/settings.local.php ]; then
  echo "Creating local Drupal settings and developent services files"
  cp -v $APP_ROOT/.ddev/homeadditions/config/drupal.settings.php $DRUPAL_ROOT/sites/default/settings.local.php
  cp -v $APP_ROOT/.ddev/homeadditions/config/drupal.services.yml $DRUPAL_ROOT/sites/local.development.services.yml
fi

# Set Simple test variables and put PHPUnit config in place.
if [ ! -f "${DRUPAL_ROOT}/core/phpunit.xml" ]; then
  echo "Adding localised PHPUnit config to Drupal webroot"
  cp $DRUPAL_ROOT/core/phpunit.xml.dist $DRUPAL_ROOT/core/phpunit.xml
  # Fix bootstrap path
  sed -i -e "s|bootstrap=\"tests/bootstrap.php\"|bootstrap=\"${DRUPAL_ROOT}/core/tests/bootstrap.php\"|g" $DRUPAL_ROOT/core/phpunit.xml
  # Inject database params for kernel tests.
  sed -i -e "s|name=\"SIMPLETEST_DB\" value=\"\"|name=\"SIMPLETEST_DB\" value=\"${DB_DRIVER}://${DB_USER}:${DB_PASS}@${DB_HOST}/${DB_NAME}\"|g" $DRUPAL_ROOT/core/phpunit.xml
  # Uncomment option to switch off Symfony deprecatons helper (we use drupal-check for this).
  sed -i -e "s|<!-- <env name=\"SYMFONY_DEPRECATIONS_HELPER\" value=\"disabled\"/> -->|<env name=\"SYMFONY_DEPRECATIONS_HELPER\" value=\"disabled\"/>|g" $DRUPAL_ROOT/core/phpunit.xml
  # Set the base URL for kernel tests.
  sed -i -e "s|name=\"SIMPLETEST_BASE_URL\" value=\"\"|name=\"SIMPLETEST_BASE_URL\" value=\"http:\/\/${DDEV_PROJECT}.ddev.site\"|g" $DRUPAL_ROOT/core/phpunit.xml
fi
