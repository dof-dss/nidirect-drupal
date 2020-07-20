#!/usr/bin/bash
curl -u ${AZURE_HTTP_USER}:${AZURE_HTTP_PASS} -X GET -o /app/imports/nidirectd7.sql.gz "{AZURE_HOST}/files/nidirect.sql.gz"
curl -u ${AZURE_HTTP_USER}:${AZURE_HTTP_PASS} -X GET -o /app/imports/nidirect_files.tar "{AZURE_HOST}/files/nidirect_files_change.tar"
