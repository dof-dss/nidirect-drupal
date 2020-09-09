#!/bin/bash
if [ ! $MIGRATE_ENABLED == 1 ]; then
  exit 0
fi

echo "Restore /app/imports/nidirectd7.sql.gz into drupal7db database..."
gunzip /app/imports/nidirectd7.sql.gz
drush -r /app/web sqlc --database=drupal7db < /app/imports/nidirectd7.sql
gzip /app/imports/nidirectd7.sql && mv /app/imports/nidirectd7.sql.gz /app/imports/nidirectd7.last.sql.gz
echo ".... DONE"

echo "Extract file assets into migrate-import directory..."
cd /app/imports
tar -xf nidirect_files.tar
rsync -avzh /app/imports/opt/rh/httpd24/root/var/www/html/drupal_sites/nidirect/files/ /app/imports/files/sites/default/files/
rm -rf /app/imports/opt
echo ".... DONE"
