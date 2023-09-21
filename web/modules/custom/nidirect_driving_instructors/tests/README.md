# Driving Instructor tests

## Kernel tests
Kernel tests load a subset of the Driving instructor bundle configuration based on the exported site configuration
within /config/sync.

There are notable exceptions, we only use the field and storage configuration for the fields that we are interested in
testing against. We also remove some 3rd party configuration from the node definition itself (metatags, menu
settings) as we don't test against these, and it this speeds up the test execution.
