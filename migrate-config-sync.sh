#!/bin/bash
if [ $LANDO ]; then
  DRUPAL_ROOT=/app/drupal8/web
else
  DRUPAL_ROOT=/app/web
fi

echo "Refreshing database migrate config for global upgrade migrations..."
drush config-import --source=$DRUPAL_ROOT/modules/migrate/nidirect-migrations/migrate_nidirect_global/config/install --partial -y

echo "Refreshing database migrate config for users..."
drush config-import --source=$DRUPAL_ROOT/modules/migrate/nidirect-migrations/migrate_nidirect_user/config/install --partial -y

echo "Refreshing database migrate config for taxonomy..."
drush config-import --source=$DRUPAL_ROOT/modules/migrate/nidirect-migrations/migrate_nidirect_taxo/config/install --partial -y

echo "Refreshing database migrate config for files..."
drush config-import --source=$DRUPAL_ROOT/modules/migrate/nidirect-migrations/migrate_nidirect_file/config/install --partial -y

# For each, one perform a partial import task to sync the module's config with our active db config.
for module in `drush pml | grep Enabled | grep -oE "(migrate_nidirect_node_\w+)"`; do
  echo "Refreshing database migrate config for $module..."
  drush config-import --source=$DRUPAL_ROOT/modules/migrate/nidirect-migrations/migrate_nidirect_node/$module/config/install --partial -y
done

echo "Refreshing database migrate config for link..."
drush config-import --source=$DRUPAL_ROOT/modules/migrate/nidirect-migrations/migrate_nidirect_link/config/install --partial -y
