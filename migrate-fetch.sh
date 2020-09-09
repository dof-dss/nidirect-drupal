#!/bin/bash
if [ ! $MIGRATE_ENABLED == 1 ]; then
  exit 0
fi

PLATFORM_LEGACY_PROJECT_ID=$1
PLATFORM_LEGACY_BRANCH=$2

platform db:dump -p $PLATFORM_LEGACY_PROJECT_ID -e $PLATFORM_LEGACY_BRANCH --gzip -f /app/imports/nidirectd7.sql.gz --exclude-table=cache,cache_admin_menu,cache_block,cache_bootstrap,cache_features,cache_feeds_http,cache_field,cache_filter,cache_form,cache_image,cache_libraries,cache_menu,cache_metatag,cache_oembed,cache_page,cache_panels,cache_path,cache_rules,cache_school_closures,cache_search_api_solr,cache_token,cache_ultimate_cron,cache_update,cache_variable,cache_views,cache_views_data,search_index,search_dataset,search_node_links,search_total,watchdog,google_analytics_node_stats,history,queue -y
platform mount:download -p $PLATFORM_LEGACY_PROJECT_ID -e $PLATFORM_LEGACY_BRANCH -m "/public_html/sites/default/files" --exclude "css*" --exclude "js*" --exclude="status_check*" --target /app/imports/files/sites/default/files/ -y
