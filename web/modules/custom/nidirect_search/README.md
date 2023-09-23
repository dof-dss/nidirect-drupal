# Solr configuration and query pipeline

The search_api_solr module provides a default bundle of config files which can
be used that provide a decent approximation of configuration needed to match
up to the fields defined in the UI.

NIDirect makes some notable changes to these files, which will need to be
evaluated and checked with each re-export of the config files to ensure
they're not lost.

# Fulltext fields

Search API produces two Solr fields in `schema_extra_types.xml`: `text_und`
and `text_en`. For all intents and purposes, their configuration should be
identical and should not need to differ unless the site becomes multilingual.

There are other field types including ngram and edgestring variants. These
are not presently used but could be if a specific use case demands it. ngram
is useful for tokenising small chunks of input values, edgegram does the same
but from the front or back of a string. Generally speaking, whitespace
tokenising with stemming is a good, efficient baseline for fuzzy searches.

Search API defines a `title_fulltext` aggregate field on the indexes which is
in addition to the basic `title` field, which is defined as a string and does
not permit more than very basic search tasks to take place.

Breaking down the `text_und` field, we can see two important stanzas for
index types and the analyzers within them. We define an analyzer for
tokenising the field in the index as well as the same for tokenising query
terms.

See comments below for further detail.

```
<fieldType name="text_und" class="solr.TextField" positionIncrementGap="100">
  <analyzer type="index">
<!-- charFilter runs before a tokenizer, irrespective of the order you put it in this schema -->
    <charFilter class="solr.MappingCharFilterFactory" mapping="accents_und.txt"/>
<!-- See https://lucene.apache.org/solr/guide/7_7/tokenizers.html for a good overview of how different tokenizers operate -->
    <tokenizer class="solr.WhitespaceTokenizerFactory"/>
<!-- Our filters then pass tokens down a chain of sequential processing to tidy up or further fragment or stem the input tokens -->
<!-- See https://lucene.apache.org/solr/guide/7_7/filter-descriptions.html for all filters and options -->
    <filter class="solr.StopFilterFactory"
            ignoreCase="true"
            words="stopwords_und.txt"/>
    <filter class="solr.WordDelimiterGraphFilterFactory"
            catenateNumbers="1"
            generateNumberParts="1"
            protected="protwords_und.txt"
            splitOnCaseChange="0"
            generateWordParts="1"
            preserveOriginal="1"
            catenateAll="0"
            catenateWords="1"/>
    <filter class="solr.FlattenGraphFilterFactory"/> <!-- NB: required on index analyzers after graph filters -->
    <filter class="solr.LengthFilterFactory" min="2" max="100"/>
    <filter class="solr.LowerCaseFilterFactory"/>
    <filter class="solr.SnowballPorterFilterFactory" language="English" protected="protwords_und.txt"/>
    <filter class="solr.RemoveDuplicatesTokenFilterFactory"/>
  </analyzer>
  <analyzer type="query">
    <charFilter class="solr.MappingCharFilterFactory" mapping="accents_und.txt"/>
    <tokenizer class="solr.WhitespaceTokenizerFactory"/>
    <filter class="solr.SynonymGraphFilterFactory"
            synonyms="synonyms_und.txt"
            expand="true"
            ignoreCase="true"/>
    <filter class="solr.FlattenGraphFilterFactory"/> <!-- NB: required on index analyzers after graph filters -->
    <filter class="solr.StopFilterFactory"
            ignoreCase="true"
            words="stopwords_und.txt"/>
    <filter class="solr.WordDelimiterGraphFilterFactory"
            catenateNumbers="1"
            generateNumberParts="1"
            protected="protwords_und.txt"
            splitOnCaseChange="0"
            generateWordParts="1"
            preserveOriginal="1"
            catenateAll="0"
            catenateWords="1"/>
    <filter class="solr.FlattenGraphFilterFactory"/> <!-- required on index analyzers after graph filters -->
    <filter class="solr.LengthFilterFactory" min="2" max="100"/>
    <filter class="solr.LowerCaseFilterFactory"/>
    <filter class="solr.SnowballPorterFilterFactory" language="English" protected="protwords_und.txt"/>
    <filter class="solr.RemoveDuplicatesTokenFilterFactory"/>
  </analyzer>
</fieldType>
```

# Updating the module or Solr config

1. Validate the inbound changes and ensure the modifications before are not
accidentally overwritten.
2. Apply the changes in two places: Lando and Platform.sh.

- [Lando](https://github.com/dof-dss/lando-d7-to-d8-migrate):
`config/solr/7.x/default`
- [Platform.sh](https://github.com/dof-dss/nidirect-drupal):
`.platform/solr_config`

# Debugging

Lando will tell you how you can access the web UI for Solr using `lando info`,
eg:

```
{ service: 'solr',
    urls: [ 'http://localhost:32855' ],
    type: 'solr',
    healthy: true,
    core: 'default',
    internal_connection: { host: 'solr', port: '8983' },
    external_connection: { host: '127.0.0.1', port: '32855' },
    healthcheck: 'curl http://localhost:8983/solr/default/admin/ping',
    config: { dir: 'config/solr/7.x/default' },
    version: '7',
    meUser: 'solr',
    hasCerts: false,
    hostnames: [ 'solr.nidirectd8.internal' ]
},
```

## Query screen

Open your browser and you can access to raw Solr query interface, eg:
`http://localhost:32855/solr/#/default/query`

See https://lucene.apache.org/solr/guide/7_7/query-screen.html for full
details.

## Analysis screen

The 'Analysis' page at `http://localhost:32855/solr/#/default/analysis` allows
you to evaluate how your current Solr config is able to tokenise and parse
values for either the index or the query input. This is extremely useful as it
shows you the sequence of each step in the analyzer as well as the state of
the input value and any matched tokens.

See https://lucene.apache.org/solr/guide/7_7/analysis-screen.html for full
details.

## Drupal

Enable `search_api_solr_devel` and it will print extensive request/response
output in the Drupal messages area. This is verbose and can be a little flaky
with dynamic page cache enabled but it can be very useful to validate outbound
queries and responses.

## Updated config

Changes to the Solr config XML files need a service reload to take effect.
While it's possible to restart the Java application container inside the
docker container, it's far faster to rebuild the Solr service image:
`lando rebuild -s solr -y`. This usually takes 10-15 seconds to perform.

If you need to re-index your content then there is a quick command chain.
The example below will:

- Rebuild the Solr service and start it with the latest config.
- Drush will clear the named Solr index (omit for all indexes), mark all
content for re-indexing and then index it.
- Drush then clears the Drupal render cache (omit if you're not evaluating
search results from Drupal).

`lando rebuild -s solr -y && lando drush sapi-c contacts && lando drush sapi-r
contacts && lando drush sapi-i contacts && lando drush cc render`
