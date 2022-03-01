#!/usr/bin/env bash

# Check if filebeat is running and if not, run it
SERVICE="filebeat"

if pgrep -x "$SERVICE" >/dev/null
then
    # Do nothing, service running.
else
    ./filebeat run --once
fi
