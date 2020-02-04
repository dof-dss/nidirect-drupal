#!/usr/bin/env bash

DRUPAL_DEPLOY_PATH=$1
# We only care about our custom code folder and custom theme folder.
PHPCS_CHECK_DIR=$2

# Dependencies are added with composer. Shouldn't be using a global install even if available.
PHPCS_PATH="${DRUPAL_DEPLOY_PATH}/vendor/bin/phpcs"
PHPCBF_PATH="${DRUPAL_DEPLOY_PATH}/vendor/bin/phpcbf"

# Define extensions we're interested in checking.
PHPCS_EXTENSIONS="php,inc,module,theme"

# Exclude some fussier/less valuable sniffs.
DRUPAL_EXCLUDED_SNIFFS=(
    Drupal.Commenting.DocComment
    Drupal.Commenting.ClassComment
)

DRUPAL_PRACTICE_EXCLUDED_SNIFFS=(
  DrupalPractice.Objects.StrictSchemaDisabled
)

# Comma separated list of npm or non-PHP related FE toolchain directories we want to ignore.
#IGNORE="${DRUPAL_DEPLOY_PATH}/web/themes/custom/THEMENAME/node_modules"

echo "Running coding standard checks in ${PHPCS_CHECK_DIR}"

# Configure PHPCS.
${PHPCS_PATH} --config-set installed_paths ${DRUPAL_DEPLOY_PATH}/vendor/drupal/coder/coder_sniffer

EXCLUDE=$(IFS=, ; echo "${DRUPAL_EXCLUDED_SNIFFS[*]}")
${PHPCS_PATH} -nq --standard=Drupal --extensions=${PHPCS_EXTENSIONS} --exclude=${EXCLUDE} --ignore=${IGNORE} ${PHPCS_CHECK_DIR}
if [ $? != 0 ]
then
    exit 1
fi

# Run Drupal best practice checks too.
EXCLUDE=$(IFS=, ; echo "${DRUPAL_PRACTICE_EXCLUDED_SNIFFS[*]}")
${PHPCS_PATH} -nq --standard=DrupalPractice --extensions=${PHPCS_EXTENSIONS} --exclude=${EXCLUDE} --ignore=${IGNORE} ${PHPCS_CHECK_DIR}
if [ $? != 0 ]
then
    exit 1
fi
