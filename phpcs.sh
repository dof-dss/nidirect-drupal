#!/usr/bin/env bash

# Require all arguments
if [ "$#" -ne 3 ]; then
  echo "Usage: $0 <DRUPAL_DEPLOY_PATH> <PHPCS_CHECK_DIR> <IGNORE>"
  echo "  DRUPAL_DEPLOY_PATH: Path to Drupal deployment (e.g., /var/www/deploy)"
  echo "  PHPCS_CHECK_DIR: Directory to check (e.g., web/modules/custom)"
  echo "  IGNORE: Comma-separated directories to ignore, relative to DRUPAL_DEPLOY_PATH or absolute (e.g., web/themes/custom/mytheme/node_modules,web/modules/custom/my_module/tests)"
  exit 1
fi

DRUPAL_DEPLOY_PATH=$1
PHPCS_CHECK_DIR=$2
IGNORE=$3

# Split IGNORE into array, prepend DRUPAL_DEPLOY_PATH to each if not absolute, then join back.
IFS=',' read -ra IGNORE_ITEMS <<< "$IGNORE"
IGNORE_PATHS=""
for item in "${IGNORE_ITEMS[@]}"; do
    # Trim whitespace
    trimmed=$(echo "$item" | xargs)
    # If path is not absolute, prepend DRUPAL_DEPLOY_PATH
    if [[ "$trimmed" = /* ]]; then
        fullpath="$trimmed"
    else
        fullpath="${DRUPAL_DEPLOY_PATH}/${trimmed}"
    fi
    # Comma separate, no trailing comma
    if [ -z "$IGNORE_PATHS" ]; then
        IGNORE_PATHS="$fullpath"
    else
        IGNORE_PATHS="$IGNORE_PATHS,$fullpath"
    fi
done

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

echo "----------------------------------------------------------------------"
echo ">>> Running coding standard checks in: ${PHPCS_CHECK_DIR}"
echo ">>> Ignoring directories: ${IGNORE}"
echo "----------------------------------------------------------------------"

# Configure PHPCS.
${PHPCS_PATH} --config-set installed_paths ${DRUPAL_DEPLOY_PATH}/vendor/drupal/coder/coder_sniffer,${DRUPAL_DEPLOY_PATH}/vendor/slevomat/coding-standard

EXCLUDE=$(IFS=, ; echo "${DRUPAL_EXCLUDED_SNIFFS[*]}")
${PHPCS_PATH} -nq --standard=Drupal --extensions=${PHPCS_EXTENSIONS} --exclude=${EXCLUDE} --ignore=${IGNORE} ${PHPCS_CHECK_DIR}
if [ $? != 0 ]
then
    echo "🚫 Drupal coding standards checks failed, see above for details 🚫"
    exit 1
fi

# Run Drupal best practice checks too.
EXCLUDE=$(IFS=, ; echo "${DRUPAL_PRACTICE_EXCLUDED_SNIFFS[*]}")
${PHPCS_PATH} -nq --standard=DrupalPractice --extensions=${PHPCS_EXTENSIONS} --exclude=${EXCLUDE} --ignore=${IGNORE} ${PHPCS_CHECK_DIR}
if [ $? != 0 ]
then
    echo "🚫 Drupal best practice checks failed, see above for details 🚫"
    exit 1
fi

## Make it clearer when the script succeeds.
echo "LGTM ✅"
