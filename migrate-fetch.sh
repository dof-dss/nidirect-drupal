#!/usr/bin/bash
curl -u ${AZURE_HTTP_USER}:${AZURE_HTTP_PASS} -X GET -o /app/imports/nidirectd7.sql.gz "http://40.127.96.215/files/nidirect.sql.gz"
curl -u ${AZURE_HTTP_USER}:${AZURE_HTTP_PASS} -X GET -o /app/imports/nidirect_files.tar "http://40.127.96.215/files/nidirect_files_change.tar"
