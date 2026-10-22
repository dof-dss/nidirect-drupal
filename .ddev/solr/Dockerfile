ARG SOLR_BASE_IMAGE="solr:9.9"
FROM $SOLR_BASE_IMAGE

# Enable handling of X-Forwarded headers for the DDEV router.
USER root
RUN sed -i '/<!-- Uncomment to enable handling of X-Forwarded- style headers/,/-->/c\<Call name="addCustomizer"><Arg><New class="org.eclipse.jetty.server.ForwardedRequestCustomizer"/></Arg></Call>' /opt/solr/server/etc/jetty.xml
USER solr
