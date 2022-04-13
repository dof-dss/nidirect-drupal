#!/usr/bin/env bash

# logging/scripts/cronjob.sh
#
# Ship today's latest log entries using cURL.
#
# Platform.sh truncates logs in /var/log to 100 MB.
# This script, when run via cron, will periodically copy today's latest log
# entries into a log file on a writeable mount and upload newest log entries
# to a logging service.
echo "Log shipping cronjob started ..."

cd $LOGS_MOUNT_PATH || exit

# Create log file for today if it doesn't already exist.
TODAY_DATE=$(date +%Y-%m-%d)
TODAY_ACCESS_LOG=${TODAY_DATE}-access.log
TODAY_DRUPAL_LOG=${TODAY_DATE}-drupal.log

echo "> Creating log files for ${TODAY_DATE} ..."

if [ ! -f ./${TODAY_ACCESS_LOG} ]; then
    touch ./${TODAY_ACCESS_LOG}
    echo "${TODAY_ACCESS_LOG} created"
else
    echo "${TODAY_ACCESS_LOG} already exists"
fi

if [ ! -f ./${TODAY_DRUPAL_LOG} ]; then
    touch ./${TODAY_DRUPAL_LOG}
    echo "${TODAY_DRUPAL_LOG} created"
else
    echo "${TODAY_DRUPAL_LOG} already exists"
fi

# Compare latest access log entries with existing entries for today and add in newest entries.
echo "> Retrieving latest log entries from /var/log/access.log and writing to ${TODAY_ACCESS_LOG}"
cat /var/log/access.log | grep $(date +%d/%b/%Y:) > ./access-latest.log
diff --changed-group-format='%>' --unchanged-group-format='' $TODAY_ACCESS_LOG access-latest.log > access-new.log
cat access-new.log >> $TODAY_ACCESS_LOG

# Compare latest drupal log entries with existing entries for today and add in newest entries.
echo "> Retrieving latest log entries from /app/log/drupal.log and writing to ${TODAY_DRUPAL_LOG}"
cat /app/log/drupal.log | grep $(date "+%d/%b/%Y - %H:%M") > ./drupal-latest.log
diff --changed-group-format='%>' --unchanged-group-format='' $TODAY_ACCESS_LOG drupal-latest.log > drupal-new.log
cat drupal-new.log >> $TODAY_ACCESS_LOG

# Upload new access and drupal log entries to logz.io using cURL.
echo "> Shipping latest log entries from /var/log/access.log to Logz.io using cURL"
curl -T access-new.log https://listener.logz.io:8022/file_upload/${LOGZ_TOKEN}/nginx
curl -T drupal-new.log https://listener.logz.io:8022/file_upload/${LOGZ_TOKEN}/http-bulk

# Clean up temporary log files.
rm access-latest.log access-new.log drupal-latest.log drupal-new.log

# Delete yesterdays log files.
YESTERDAY_DATE=$(date --date="yesterday" +%Y-%m-%d)
YESTERDAY_ACCESS_LOG=${YESTERDAY_DATE}-access.log
YESTERDAY_DRUPAL_LOG=${YESTERDAY_DATE}-drupal.log

echo "> Deleting yesterday's log files ..."

if [ -f ./${YESTERDAY_ACCESS_LOG} ]; then
    rm ${YESTERDAY_ACCESS_LOG}
    echo "${YESTERDAY_ACCESS_LOG} deleted"
else
    echo "${YESTERDAY_ACCESS_LOG} does not exist"
fi

if [ -f ./${YESTERDAY_DRUPAL_LOG} ]; then
    rm ${YESTERDAY_DRUPAL_LOG}
    echo "${YESTERDAY_DRUPAL_LOG} deleted"
else
    echo "${YESTERDAY_DRUPAL_LOG} does not exist"
fi

exit 0
