# config/filebeat/scripts/install.sh

#!/usr/bin/env bash

TEMP_BEAT_HOME=config/filebeat/build

[ ! -d $TEMP_BEAT_HOME ] && mkdir -p $TEMP_BEAT_HOME
cd $TEMP_BEAT_HOME

# Install Filebeat
curl -L -O https://artifacts.elastic.co/downloads/beats/filebeat/filebeat-8.0.0-linux-x86_64.tar.gz
tar xzvf filebeat-8.0.0-linux-x86_64.tar.gz
rm filebeat-8.0.0-linux-x86_64.tar.gz

# Download the certificate
wget https://raw.githubusercontent.com/logzio/public-certificates/master/AAACertificateServices.crt

mkdir -p filebeat-8.0.0-linux-x86_64/pki/tls/certs
cp AAACertificateServices.crt filebeat-8.0.0-linux-x86_64/pki/tls/certs/
