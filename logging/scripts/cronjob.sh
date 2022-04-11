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
TODAY_LOG_FILE=${TODAY_DATE}-access.log
echo "> Creating log file for ${TODAY_DATE} if it does not exist ..."
if [ ! -f ./${TODAY_LOG_FILE} ]; then
    touch ./${TODAY_LOG_FILE}
    echo "${TODAY_LOG_FILE} created"
else
    echo "${TODAY_LOG_FILE} already exists"
fi

# Compare latest access log entries with existing entries for today and add in newest entries.
echo "> Retrieving latest log entries from /var/log/access.log and writing to ${TODAY_LOG_FILE}"
cat /var/log/access.log | grep $(date +%d/%b/%Y:) > ./latest.log
diff --changed-group-format='%>' --unchanged-group-format='' $TODAY_LOG_FILE latest.log > new.log
cat new.log >> $TODAY_LOG_FILE

# Upload new log entries to logz.io using cURL.
echo "> Shipping latest log entries from /var/log/access.log to Logz.io using cURL"
curl -T new.log https://listener.logz.io:8022/file_upload/${LOGZ_TOKEN}/nginx
rm latest.log new.log

# Delete yesterdays log file if it exists.
YESTERDAY_DATE=$(date --date="yesterday" +%Y-%m-%d)
YESTERDAY_LOG_FILE=${YESTERDAY_DATE}-access.log
echo "> Deleting ${YESTERDAY_LOG_FILE} if it exists ..."
if [ -f ./${YESTERDAY_LOG_FILE} ]; then
    rm ${YESTERDAY_LOG_FILE}
    echo "${YESTERDAY_LOG_FILE} deleted"
else
    echo "${YESTERDAY_LOG_FILE} does not exist"
fi

exit 0
