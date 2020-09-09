#!/bin/bash
if [ ! $MIGRATE_ENABLED == 1 ]; then
  exit 0
fi

PLATFORM_LEGACY_PROJECT_ID=$1
PLATFORM_LEGACY_BRANCH=$2

if [ -f /app/imports/nidirectd7.sql.gz ]; then
  rm /app/imports/nidirectd7.sql.gz
fi

platform db:dump -p $PLATFORM_LEGACY_PROJECT_ID -e $PLATFORM_LEGACY_BRANCH --gzip -f /app/imports/nidirectd7.sql.gz
platform mount:download $PLATFORM_LEGACY_PROJECT_ID -e $PLATFORM_LEGACY_BRANCH -m "/public_html/sites/default/files" --exclude "css*" --exclude "js*" --exclude="status_check*" --target /app/imports/files/sites/default/files/ -y
