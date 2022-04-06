#!/usr/bin/env bash

# filebeat/scripts/install.sh

TEMP_BEAT_HOME=filebeat/build

[ ! -d $TEMP_BEAT_HOME ] && mkdir -p $TEMP_BEAT_HOME
cd $TEMP_BEAT_HOME

echo "Created ${TEMP_BEAT_HOME}"

# Install Filebeat
curl -L -O https://artifacts.elastic.co/downloads/beats/filebeat/filebeat-8.0.0-linux-x86_64.tar.gz
tar xzvf filebeat-8.0.0-linux-x86_64.tar.gz
rm filebeat-8.0.0-linux-x86_64.tar.gz

echo "Downloaded Filebeat"

# Download the certificate
curl https://raw.githubusercontent.com/logzio/public-certificates/master/AAACertificateServices.crt --create-dirs -o pki/tls/certs/COMODORSADomainValidationSecureServerCA.crt

echo "Downloaded Logz.io certificate"
