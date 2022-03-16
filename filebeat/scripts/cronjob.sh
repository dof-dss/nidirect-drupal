# filebeat/scripts/cronjob.sh

#!/usr/bin/env bash

# Run filebeat if not running already.
if ! pgrep -x "filebeat" >/dev/null; then
  cd /app/.filebeat;
  ./filebeat run &>/dev/null & disown;
fi
