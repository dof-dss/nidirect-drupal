#!/usr/bin/env bash

# filebeat/scripts/cronjob.sh
#
# Ship today's latest log entries using Filebeat.
#
# Platform.sh truncates logs in /var/log to 100 MB. Filebeat cannot track
# entries in truncated log files.  (See
# https://www.elastic.co/guide/en/beats/filebeat/8.1/file-log-rotation.html)
# This script, when run via cron, will periodically copy today's latest log
# entries into a log file on a writeable mount. Filebeat can then harvest
# this log file reliably because it is never truncated.
echo "Filebeat log shipping cronjob started ..."

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

# Run filebeat to ship today's log (to remote logging service).
#echo "> Running filebeat --once ..."
#cd /app/.filebeat || exit
#./filebeat run --once

#echo "Filebeat log shipping complete"

exit 0
