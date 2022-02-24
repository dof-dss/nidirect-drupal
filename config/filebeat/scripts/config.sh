#!/usr/bin/env bash

# Move filebeat to mount with write access
cd $PLATFORM_HOME
cp -v -r config/filebeat/build/filebeat-8.0.0-linux-x86_64/* filebeat
mkdir filebeat/registry
