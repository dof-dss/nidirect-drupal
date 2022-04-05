# filebeat/scripts/cronjob.sh

#!/usr/bin/env bash

# Ship today's latest log entries using Filebeat.
#
# Platform.sh truncates logs in /var/log to 100 MB. Filebeat cannot track
# entries in truncated log files.  (See
# https://www.elastic.co/guide/en/beats/filebeat/8.1/file-log-rotation.html)
# This script, when run via cron, will periodically copy today's latest log
# entries into a log file on a writeable mount. Filebeat can then harvest
# this log file reliably because it is never truncated.

cd $LOGS_MOUNT_PATH

# Create log file for today if it doesn't already exist.
TODAY_DATE=$(date +%Y-%m-%d)
TODAY_LOG_FILE=${TODAY_DATE}-access.log
if [ ! -f ./${TODAY_LOG_FILE} ]; then
    touch ./${TODAY_LOG_FILE}
fi

# Compare latest access log entries for today and add to today's access log.
cat /var/log/access.log | grep $(date +%d/%b/%Y:) > ./latest.log
diff --changed-group-format='%>' --unchanged-group-format='' $TODAY_LOG_FILE latest.log > diff.log
cat diff.log >> $TODAY_LOG_FILE

# Remove temp log files used for diff comparison.
rm latest.log diff.log

# Run filebeat to ship today's log (to remote logging service).
cd /app/.filebeat
./filebeat run --once

# Delete yesterdays log file if it exists.
YESTERDAY_DATE=$(date --date="yesterday" +%Y-%m-%d)
YESTERDAY_LOG_FILE=${YESTERDAY_DATE}-access.log
if [ -f ./${YESTERDAY_LOG_FILE} ]; then
    rm ./${YESTERDAY_LOG_FILE}
fi
