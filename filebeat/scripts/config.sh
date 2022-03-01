#!/usr/bin/env bash

# Move filebeat to mount with write access
cd $PLATFORM_HOME
cp -r filebeat/build/filebeat-8.0.0-linux-x86_64/* .filebeat/
cp filebeat/filebeat.yml .filebeat/
mkdir .filebeat/registry
