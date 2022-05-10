#!/usr/bin/env bash
#
# logging/scripts/shiplog.sh
#
# Ship today's log entries from a specified log to logz.io
# Usage: /bin/bash /app/logging/scripts/shiplog.sh [LOG_NAME] [LOG_PATH] [LOG_DATE_PATTERN] [LOG_TYPE]
#    eg: /bin/bash /app/logging/scripts/shiplog.sh "access" "/var/log/access.log" "$(date +%d/%b/%Y:)" "nginx_access"'

err() {
  echo "[$(date +'%Y-%m-%dT%H:%M:%S%z')]: $*" >&2
}

# LOGZ_TOKEN environment variable is required for this script to run.
if [ -z $LOGZ_TOKEN ]; then
    err "LOGZ_TOKEN is not set"
    exit 1
fi

# Mount for logs must exist or exit script.
if [ -f /app/log ]; then
    cd /app/log
else
    err "Log mount /app/log does not exist"
    exit 1
fi

LOG_NAME=$1
LOG_PATH=$2
LOG_PATTERN=$3
LOG_TYPE=$4

# Script will be creating logs for today and removing yesterday's logs
TODAY_DATE=$(date +%Y-%m-%d)
YESTERDAY_DATE=$(date --date="yesterday" +%Y-%m-%d)

if [ -z $LOG_NAME ] || [ -z $LOG_PATH ] || [ -z $LOG_DATE_PATTERN ] || [ -z $LOG_TYPE ]; then
    err "shiplog called with too few parameters. Expected four."
    echo 'Usage: ./shiplog.sh [LOG_NAME] [LOG_PATH] [LOG_DATE_PATTERN] [LOG_TYPE]'
    echo '   eg: ./shiplog.sh "access" "/var/log/access.log" "$(date +%d/%b/%Y:)" "nginx_access"'
    exit 1
fi

if [ -f $LOG_PATH ]; then

    echo "Shipping today's newest log entries from $LOG_PATH ..."

    # Create today's log file.
    todays_log="${TODAY_DATE}-${LOG_NAME}.log"
    echo "Creating ${todays_log} ..."
    if [ ! -f ./${todays_log} ]; then
        touch ./${todays_log}
        echo "${todays_log} created"
    else
        echo "${todays_log} already exists"
    fi

    # Delete yesterdays log files.
    yesterdays_log="${YESTERDAY_DATE}-${LOG_NAME}.log"

    echo "Deleting ${yesterdays_log} ..."

    if [ -f ./${yesterdays_log} ]; then
        rm ${yesterdays_log}
        echo "${yesterdays_log} deleted"
    else
        echo "${yesterdays_log} does not exist"
    fi

    # Get latest log entries and ship to logz.io.
    echo "Retrieving latest log entries from ${LOG_PATH} and writing to ${todays_log}"
    cat $LOG_PATH | grep $LOG_PATTERN > ./$LOG_NAME-latest.log
    diff --changed-group-format='%>' --unchanged-group-format='' $todays_log $LOG_NAME-latest.log > $LOG_NAME-new.log
    cat $LOG_NAME-new.log >> $todays_log
    echo "Shipping latest log entries from ${todays_log} to Logz.io using cURL"
    http_response=$(curl -T new.log -s -w "%{response_code}" https://listener.logz.io:8022/file_upload/${LOGZ_TOKEN}/${LOG_TYPE})
    exit_code=$?

    # Clean up temporary log files.
    rm $LOG_NAME-latest.log $LOG_NAME-new.log

    if [ "$exit_code" != "0" ]; then
        err "The cURL command failed with: $exit_code"
    elif [ "$http_response" != "200" ]; then
        err "Log shipping failed with: $http_response"
    fi

    if [ "$exit_code" != "0" ] || [ "$http_response" != "200" ]; then
        email = "From: noreply@nidirect.gov.uk\n"
        email += "Subject: Logz upload failed on project "
        email += "${PLATFORM_PROJECT} branch ${PLATFORM_BRANCH}"
        printf $email | /usr/sbin/sendmail eddwebdev@finance-ni.gov.uk
        exit 1
    fi

    echo "Log shipping succeeded with: $http_response"
else
    err "${LOG_PATH} does not exist"
    exit 1
fi

exit 0
