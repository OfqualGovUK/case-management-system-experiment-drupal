#!/bin/bash
set -e

echo "[INFO] Starting Drupal entrypoint script"

# Check MySQL readiness
echo "[INFO] Waiting for MySQL to be ready..."
for i in $(seq 1 10); do
    php -r "
    \$dsn = 'mysql:host=${DRUPAL_DATABASE_HOST};port=${DRUPAL_DATABASE_PORT_NUMBER}';
    try {
        new PDO(\$dsn, '${DRUPAL_DATABASE_USER}', '${DRUPAL_DATABASE_PASSWORD}');
        exit(0);
    } catch (PDOException \$e) {
        exit(1);
    }
    " && echo "[INFO] MySQL is ready" && break || echo "[WARN] Attempt $i: MySQL not ready"
    sleep 10
    if [ "$i" -eq 10 ]; then
        echo "[ERROR] MySQL did not become ready in time"
        exit 1
    fi
done

# Check Drupal bootstrap
echo "[INFO] Checking Drupal bootstrap status"
BOOTSTRAP=$(vendor/bin/drush core-status --field=bootstrap || echo "0")
echo "[INFO] Bootstrap status: ${BOOTSTRAP}"

# Install Drupal if not installed
if [ "${BOOTSTRAP}" != "Successful" ]; then
    echo "[INFO] Installing Drupal"
    if [ -d config/sync ] && [ "$(ls -A config/sync)" ]; then
        echo "[INFO] Using existing configuration"
        vendor/bin/drush site:install --existing-config \
        --db-url="mysql://${DRUPAL_DATABASE_USER}:${DRUPAL_DATABASE_PASSWORD}@${DRUPAL_DATABASE_HOST}:${DRUPAL_DATABASE_PORT_NUMBER}/${DRUPAL_DATABASE_NAME}" \
        --account-name="${DRUPAL_USERNAME}" \
        --account-pass="${DRUPAL_PASSWORD}" \
        --account-mail="${DRUPAL_EMAIL}" \
        -y
        echo "[INFO] Drupal installation with existing config completed"
    else
        echo "[ERROR] config/sync directory is missing or empty. Cannot install with existing config."
        exit 1
    fi
else
    echo "[INFO] Drupal already installed"
fi

# Sync Drupal config
if [ -d config/sync ] && [ "$(ls -A config/sync)" ]; then
    echo "[INFO] Syncing Drupal config"
    vendor/bin/drush cim -y || echo "[WARN] Sync failed"
    echo "[INFO] Config sync completed"
else
    echo "[WARN] config/sync missing or empty. Skipping sync."
fi

# Sync latest Drupal user password
echo "[INFO] Syncing password for user: ${DRUPAL_USERNAME}"
vendor/bin/drush user:password "${DRUPAL_USERNAME}" "${DRUPAL_PASSWORD}"
if [ $? -eq 0 ]; then
    echo "[INFO] Password synced for user: ${DRUPAL_USERNAME}"
else
    echo "[ERROR] Password sync failed for user: ${DRUPAL_USERNAME}"
    exit 1
fi

echo "[INFO] Drupal setup complete"