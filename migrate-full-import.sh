#!/bin/bash

# ABOUT
# This is a selective rollback + import script that assumes a starting point of all generic
# D7 upgrade migrations having been done/refined, then custom migrations already imported at
# least once before. This script rolls back all the key migrations, in the order required,
#
# Migrate top-ups aren't quite so useful for us here, because source content removals
# are not also kept in sync with the D8 site, so a full rollback + re-import is safest for
# data integrity.
#
# This process can take a LONG time to run (hours) so please be patient.

if [ $LANDO="ON" ]; then
  DRUPAL_ROOT=/app/drupal8/web
else
  DRUPAL_ROOT=/app/web
fi

# Option to import all migrations.
# Typically web want to preserve certain content types such as landing pages from begin overwritten.
if [ "$1" == "-a" ] || [ "$1" == "--all" ]; then
 MIGRATE_ALL=true;
fi

# Enable migrate booster module
drush mbe

##### RESET ######
# Reset all migrations.
for migration_id in `drush migrate:status --format=csv | grep Importing | awk -F ',' '{print $2}'`; do
  drush migrate:reset $migration_id
done

##### ROLLBACK ######

# Import URL aliases and redirects
drush migrate:rollback --group=migrate_drupal_7_link

# Rollback book migration.
drush migrate:rollback nidirect_book

# Rollback all node migrations.
for type in driving_instructor application article external_link gp_practice health_condition news page publication; do
  drush migrate:rollback --group=migrate_nidirect_node_$type
done

if [ $MIGRATE_ALL ]; then
  drush migrate:rollback --group=migrate_nidirect_node_landing_page
  drush migrate:rollback --group=migrate_nidirect_node_nidirect_contact
  drush migrate:rollback --group=migrate_nidirect_node_contact
fi

# Rollback GP entities.
drush migrate:rollback --group=migrate_nidirect_entity_gp
# Rollback media entities
drush migrate:rollback --group=migrate_drupal_7_file

##### PRE, IMPORT AND POST MIGRATE ######

# Import any new users
drush migrate:import upgrade_d7_user

# Import any new taxonomy terms, with dependencies.
drush migrate:import --group=migrate_drupal_7_taxo --force --execute-dependencies

# Import files.
drush migrate:import upgrade_d7_file --force --execute-dependencies
# Import media entities
drush migrate:import --group=migrate_drupal_7_file --force

# Import file images
drush migrate:import upgrade_d7_file_image

# Import URL aliases and redirects
drush migrate:import --group=migrate_drupal_7_link --force

# Import GP entities.
drush migrate:import --group=migrate_nidirect_entity_gp --force

# Import all node migrations.
for type in driving_instructor application article external_link gp_practice health_condition news page publication; do
  drush migrate:import --group=migrate_nidirect_node_$type --force --execute-dependencies
done

if [ $MIGRATE_ALL ]; then
  drush migrate:import --group=migrate_nidirect_node_landing_page --force
  drush migrate:import --group=migrate_nidirect_node_nidirect_contact --force
  drush migrate:import --group=migrate_nidirect_node_contact --force
fi

# Import book
drush migrate:import nidirect_book --force

# Clear caches and re-index Solr.
drush cr
drush sapi-c && drush sapi-r && drush sapi-i
