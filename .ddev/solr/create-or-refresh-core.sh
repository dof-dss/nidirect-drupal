#!/usr/bin/env bash
# Start the NIDirect core from the same committed configset used by Upsun.
set -euo pipefail

readonly core="nidirect_index"
readonly configset="/solr-configset"
readonly source_conf="${configset}/conf"
readonly target_conf="/var/solr/data/${core}/conf"

mkdir -p /var/solr/data

# DDEV Solr is intentionally unauthenticated. Remove policy retained by an
# earlier local SolrCloud configuration from the disposable Docker volume.
rm -f /var/solr/data/security.json

# Remove only the known cores left by the previous DDEV Solr recipes. They are
# incompatible with this standalone runtime and otherwise fail during startup.
for obsolete_core in dev default_shard1_replica_n1; do
  if [[ -f "/var/solr/data/${obsolete_core}/core.properties" ]]; then
    rm -rf -- "/var/solr/data/${obsolete_core}"
  fi
done

if [[ ! -f "${source_conf}/schema.xml" || ! -f "${source_conf}/solrconfig.xml" ]]; then
  echo "Missing NIDirect configset in ${source_conf}." >&2
  exit 1
fi

precreate-core "$core" "$configset"

# precreate-core leaves an existing persistent core untouched. Refresh its
# configuration from the repository before Solr starts.
if [[ -d "$target_conf" ]]; then
  find "$target_conf" -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +
  cp -a "${source_conf}/." "$target_conf/"
fi

exec solr -f
