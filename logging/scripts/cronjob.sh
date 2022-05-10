#!/usr/bin/env bash
#
# logging/scripts/cronjob.sh
#
# Calls shiplog.sh to ship latest log entries from various logs
# to logz.io.

# /bin/bash /app/logging/scripts/shiplog.sh [LOG_NAME] [LOG_PATH] [LOG_DATE_PATTERN] [LOG_TYPE]

/bin/bash /app/logging/scripts/shiplog.sh "access" "/var/log/access.log" "$(date +%d/%b/%Y:)" "nginx_access"
/bin/bash /app/logging/scripts/shiplog.sh "drupal" "/app/log/drupal.log" "$(date +'%a, %d/%m/%Y -')" "drupal"
