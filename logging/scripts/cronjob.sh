#!/usr/bin/env bash

# logging/scripts/cronjob.sh
#
# Ship today's latest log entries using cURL.
#
# Platform.sh truncates logs in /var/log to 100 MB.
# This script, when run via cron, will periodically copy today's latest log
# entries into a log file on a writeable mount and upload newest log entries
# to a logging service.

# LOGZ_TOKEN environment variable is required for this script to run.
if [ -z $LOGZ_TOKEN ]; then
    echo "LOGZ_TOKEN is not set"
    exit 1
fi

echo "Log shipping cronjob started ..."

# Mount for logs must exist or exit script.
cd /app/log || exit

# Script will be creating logs for today and removing yesterday's logs
TODAY_DATE=$(date +%Y-%m-%d)
YESTERDAY_DATE=$(date --date="yesterday" +%Y-%m-%d)

# Ship access.log created by nginx.

# Create log file to keep track of today's access log entries.
TODAY_ACCESS_LOG=${TODAY_DATE}-access.log

echo "> Creating ${TODAY_ACCESS_LOG} ..."

if [ ! -f ./${TODAY_ACCESS_LOG} ]; then
    touch ./${TODAY_ACCESS_LOG}
    echo "${TODAY_ACCESS_LOG} created"
else
    echo "${TODAY_ACCESS_LOG} already exists"
fi

# Get latest access log entries and ship to logz.io.
echo "> Retrieving latest log entries from /var/log/access.log and writing to ${TODAY_ACCESS_LOG}"
cat /var/log/access.log | grep $(date +%d/%b/%Y:) > ./access-latest.log
diff --changed-group-format='%>' --unchanged-group-format='' $TODAY_ACCESS_LOG access-latest.log > access-new.log
cat access-new.log >> $TODAY_ACCESS_LOG
echo "> Shipping latest log entries from /var/log/access.log to Logz.io using cURL"
curl -T access-new.log https://listener.logz.io:8022/file_upload/${LOGZ_TOKEN}/nginx_access

# Clean up temporary log files.
rm access-latest.log access-new.log

# Delete yesterdays log files.
YESTERDAY_ACCESS_LOG=${YESTERDAY_DATE}-access.log

echo "> Deleting yesterday's log files ..."

if [ -f ./${YESTERDAY_ACCESS_LOG} ]; then
    rm ${YESTERDAY_ACCESS_LOG}
    echo "${YESTERDAY_ACCESS_LOG} deleted"
else
    echo "${YESTERDAY_ACCESS_LOG} does not exist"
fi

# Ship drupal.log created by filelog module if present.
if [ -f /app/log/drupal.log ]; then

    # Create log file to keep track of today's drupal log entries.
    TODAY_DRUPAL_LOG=${TODAY_DATE}-drupal.log

    echo "> Creating ${TODAY_DRUPAL_LOG} ..."

    if [ ! -f ./${TODAY_DRUPAL_LOG} ]; then
        touch ./${TODAY_DRUPAL_LOG}
        echo "${TODAY_DRUPAL_LOG} created"
    else
        echo "${TODAY_DRUPAL_LOG} already exists"
    fi

    # Get latest drupal log entries and ship logz.io.
    echo "> Retrieving latest log entries from /app/log/drupal.log and writing to ${TODAY_DRUPAL_LOG}"
    cat /app/log/drupal.log | grep "$(date +'%a, %d/%m/%Y -')" > ./drupal-latest.log
    diff --changed-group-format='%>' --unchanged-group-format='' $TODAY_DRUPAL_LOG drupal-latest.log > drupal-new.log
    cat drupal-new.log >> $TODAY_DRUPAL_LOG
    echo "> Shipping latest drupal log entries to Logz.io using cURL"
    curl -T drupal-new.log https://listener.logz.io:8022/file_upload/${LOGZ_TOKEN}/drupal

    # Clean up temporary log files.
    rm drupal-latest.log drupal-new.log

    # Delete yesterdays log files.
    YESTERDAY_DRUPAL_LOG=${YESTERDAY_DATE}-drupal.log

    echo "> Deleting yesterday's drupal log ..."

    if [ -f ./${YESTERDAY_DRUPAL_LOG} ]; then
        rm ${YESTERDAY_DRUPAL_LOG}
        echo "${YESTERDAY_DRUPAL_LOG} deleted"
    else
        echo "${YESTERDAY_DRUPAL_LOG} does not exist"
    fi
fi

exit 0
