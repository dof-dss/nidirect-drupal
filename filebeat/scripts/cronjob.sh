#!/usr/bin/env bash

# Run filebeat if not running already.
if [ ! "$(pgrep -x filebeat)" ]; then
    cd /app/.filebeat
    ./filebeat run --once
fi
