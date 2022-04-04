# filebeat/scripts/cronjob.sh

#!/usr/bin/env bash

cd /app/log/
TODAY_DATE=$(date +%Y-%m-%d)
YESTERDAY_DATE=$(date --date="yesterday" +%Y-%m-%d)

# Delete yesterdays log file if it exists.
YESTERDAY_LOG_FILE=${YESTERDAY_DATE}-access.log
if [ -f ./${YESTERDAY_LOG_FILE} ]; then
    rm ./${YESTERDAY_LOG_FILE}
fi

# Create log file for today if it doesn't already exist.
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
