#!/bin/bash
SERVICE="filebeat"
if pgrep -x "$SERVICE" >/dev/null
then
    # Do nothing, service running.
else
    ./filebeat run -c ~/filebeat.yml --once
fi
