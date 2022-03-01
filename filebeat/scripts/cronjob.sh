#!/usr/bin/env bash

# Run filebeat if not running already.
if ! pgrep -x "filebeat" >/dev/null; then
  cd /app/.filebeat;
  ./filebeat run --once &>/dev/null & disown;
fi
