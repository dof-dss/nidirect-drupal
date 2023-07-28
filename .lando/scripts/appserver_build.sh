#!/usr/bin/env bash

# Variables to indicate key settings files or directories for Drupal.
DRUPAL_REPO_URL=git@github.com:dof-dss/nidirect-drupal.git

DRUPAL_ROOT=/app/web
DRUPAL_SETTINGS_FILE=$DRUPAL_ROOT/sites/default/settings.php
DRUPAL_SERVICES_FILE=$DRUPAL_ROOT/sites/default/services.yml
DRUPAL_CUSTOM_CODE=$DRUPAL_ROOT/modules/custom
DRUPAL_TEST_PROFILE=$DRUPAL_ROOT/profiles/custom/test_profile

# Semaphore files to control whether we need to trigger an install
# of supporting software or config files.
CKEDITOR_PATCHED=/etc/CKEDITOR_PATCHED

# Update APT cache and install Vim.
apt update
apt install -y vim

# Create export directories for config and data.
if [ ! -d "/app/.lando/exports" ]; then
  echo "Creating export directories"
  mkdir -p /app/.lando/exports/config && mkdir /app/.lando/exports/data
fi

composer install

# Create Drupal public files directory and set IO permissions.
if [ ! -d "/app/web/sites/default/files" ]; then
  echo "Creating public Drupal files directory"
  mkdir -p /app/web/sites/default/files
  chmod -R 755 /app/web/sites/default/files
fi

# Create Drupal private file directory above web root.
if [ ! -d "/app/.lando/private" ]; then
  echo "Creating private Drupal files directory"
  mkdir -p /app/.lando/private
fi

# Set local environment settings.php file.
echo "Creating settings.local.php file using our Lando copy"
chmod -R ug+rw $DRUPAL_ROOT/sites/default

# Copy default services config and replace key values for local development.
cp -v /app/config/drupal.settings.php $DRUPAL_ROOT/sites/default/settings.local.php
cp -v /app/config/drupal.services.yml $DRUPAL_SERVICES_FILE

echo "Copying Redis service overrides"
cp -v /app/config/redis.services.yml $DRUPAL_ROOT/sites/default/redis.services.yml

if [ ! -f "$CKEDITOR_PATCHED" ]; then
  # Replace vanilla CKEditor config with a custom one to fix the click/drag bug with embedded entities.
  echo "Replace vanilla CKEditor config with a custom one to fix the click/drag bug with embedded entities"
  git clone https://github.com/dof-dss/ckeditor4-fix-widget-dnd.git /tmp/ckeditor4-fix-widget-dnd
  rm -rf $DRUPAL_ROOT/core/assets/vendor/ckeditor
  mv -v /tmp/ckeditor4-fix-widget-dnd/build/ckeditor $DRUPAL_ROOT/core/assets/vendor/ckeditor
  rm -rf /tmp/ckeditor4-fix-widget-dnd

  touch $CKEDITOR_PATCHED
fi

# Create .env starting point
if [ ! -f "/app/.env" ]; then
  echo "Creating .env file from example file..."
  cp -v /app/.env.example /app/.env
  echo "NB: to view/complete relevant sections of your .env file use this command:"
  echo "platform ssh -e main 'env | sort'"
fi
