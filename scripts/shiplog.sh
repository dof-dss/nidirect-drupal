#!/usr/bin/env bash
#
# logging/scripts/shiplog.sh
#
# Ship today's log entries from a specified log to logz.io
# Usage: /bin/bash /app/logging/scripts/shiplog.sh [LOG_NAME] [LOG_PATH] [LOG_DATE_PATTERN] [LOG_TYPE]
#    eg: /bin/bash /app/logging/scripts/shiplog.sh "access" "/var/log/access.log" "$(date +%d/%b/%Y:)" "nginx_access"'

echo "Shipping log ..."

# LOGZ_TOKEN environment variable is required for this script to run.
if [ -z "$LOGZ_TOKEN" ]; then
    echo "LOGZ_TOKEN is not set - exiting"
    exit 1
fi

# Set LOGZ_URL if environment variable is not set.
if [ -z "$LOGZ_URL" ]; then
    LOGZ_URL="https://listener-uk.logz.io:8022"
fi

# Mount for logs must exist or exit script.
cd /app/log || exit

LOG_NAME=$1
LOG_PATH=$2
LOG_DATE_PATTERN=$3
LOG_TYPE=$4

# Script will be creating logs for today and removing yesterday's logs
TODAY_DATE=$(date +%Y-%m-%d)
YESTERDAY_DATE=$(date --date="yesterday" +%Y-%m-%d)

# Check 4 arguments passed.
if [ $# -ne 4 ]; then
    echo "shiplog called with incorrect number of arguments. Expected 4, got $#."
    echo 'Usage: ./shiplog.sh [LOG_NAME] [LOG_PATH] [LOG_DATE_PATTERN] [LOG_TYPE]'
    echo '   eg: ./shiplog.sh "access" "/var/log/access.log" "$(date +%d/%b/%Y:)" "nginx_access"'
    exit 1
fi

if [ -f "$LOG_PATH" ]; then

    echo "Shipping today's newest log entries from $LOG_PATH ..."

    # Create today's log file.
    todays_log="${TODAY_DATE}-${LOG_NAME}.log"
    echo "Creating ${todays_log} ..."
    if [ ! -f ./"${todays_log}" ]; then
        touch ./"${todays_log}"
        echo "${todays_log} created"
    else
        echo "${todays_log} already exists"
    fi

    # Delete yesterdays log files.
    yesterdays_log="${YESTERDAY_DATE}-${LOG_NAME}.log"

    echo "Deleting ${yesterdays_log} ..."

    if [ -f ./"${yesterdays_log}" ]; then
        rm "${yesterdays_log}"
        echo "${yesterdays_log} deleted"
    else
        echo "${yesterdays_log} does not exist"
    fi

    # Get latest log entries and ship to logz.io.
    echo "Retrieving latest log entries from ${LOG_PATH} and writing to ${todays_log}"
    cat "$LOG_PATH" | grep "$LOG_DATE_PATTERN" > ./"$LOG_NAME"-latest.log
    diff --changed-group-format='%>' --unchanged-group-format='' "$todays_log" "$LOG_NAME-latest.log" > "$LOG_NAME-new.log"
    cat "$LOG_NAME-new.log" >> "$todays_log"
    echo "Shipping latest log entries from ${todays_log} to Logz.io using cURL"
    http_response=$(curl -T "$LOG_NAME-new.log" -s -w "%{response_code}" $LOGZ_URL/file_upload/"${LOGZ_TOKEN}"/"${LOG_TYPE}")
    exit_code=$?

    # Clean up temporary log files.
    rm "$LOG_NAME-latest.log" "$LOG_NAME-new.log"

    if [ "$exit_code" != "0" ]; then
        echo "The cURL command failed with: $exit_code"
    elif [ "$http_response" != "200" ]; then
        echo "Log shipping failed with: $http_response"
    fi

    if [ "$exit_code" != "0" ] || [ "$http_response" != "200" ]; then
        exit 1
    fi

    echo "Log shipping succeeded with: $http_response"
else
    echo "${LOG_PATH} does not exist"
    exit 1
fi

exit 0
