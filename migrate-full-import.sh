#!/bin/bash
if [ $LANDO="ON" ]; then
  DRUPAL_ROOT=/app/drupal8/web
else
  DRUPAL_ROOT=/app/web
fi

##### RESET ######
# Reset all migrations.
for migration_id in `drush migrate:status --format=csv | grep Importing | awk -F ',' '{print $2}'`; do
  drush migrate:reset $migration_id
done

##### ROLLBACK ######

# Rollback book migration.
drush migrate:rollback nidirect_book

# Rollback all node migrations.
for type in driving_instructor application article external_link gp_practice health_condition landing_page news nidirect_contact contact page publication link; do
  drush migrate:rollback --group=migrate_nidirect_node_$type
done

# Rollback GP entities.
drush migrate:rollback --group=migrate_nidirect_entity_gp
# Rollback media entities
drush migrate:rollback --group=migrate_drupal_7_file

##### PRE, IMPORT AND POST MIGRATE ######

# Execute pre-migration commands with drupal console.
cd $DRUPAL_ROOT
drupal nidirect:migrate:pre
drupal nidirect:migrate:pre:feature_nodes

# Import any new users
drush migrate:import upgrade_d7_user

# Import any new taxonomy terms, with dependencies.
drush migrate:import --group=migrate_drupal_7_taxo --execute-dependencies

# Import files.
drush migrate:import upgrade_d7_file --execute-dependencies
# Import media entities
drush migrate:import --group=migrate_drupal_7_file

# Import GP entities.
drush migrate:import --group=migrate_nidirect_entity_gp

# Import all node migrations.
for type in driving_instructor application article external_link gp_practice health_condition landing_page news nidirect_contact contact page publication link; do
  drush migrate:import --group=migrate_nidirect_node_$type --execute-dependencies
done

# Import book
drush migrate:import nidirect_book

# Execute post-migration commands with drupal console.
cd $DRUPAL_ROOT
drupal nidirect:migrate:post
drupal nidirect:migrate:post:feature_nodes
