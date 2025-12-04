#!/bin/bash
set -e

DB_HOST="${DRUPAL_DATABASE_HOST}"
DB_PORT="${DRUPAL_DATABASE_PORT_NUMBER}"
DB_USER="${DRUPAL_DATABASE_USER}"
DB_PASS="${DRUPAL_DATABASE_PASSWORD}"
DB_NAME="${DRUPAL_DATABASE_NAME}"

MAX_RETRIES="${MAX_RETRIES:-60}"
RETRY_INTERVAL="${RETRY_INTERVAL:-10}"

echo "[INFO] Starting Drupal entrypoint script"

# Check MySQL readiness
echo "[INFO] Waiting for MySQL to be ready..."
for i in $(seq 1 "${MAX_RETRIES}"); do
    php -r "
    \$dsn = 'mysql:host=${DB_HOST};port=${DB_PORT}';
    try {
        new PDO(\$dsn, '${DB_USER}', '${DB_PASS}');
        exit(0);
    } catch (PDOException \$e) {
        exit(1);
    }
    " && echo "[INFO] MySQL is ready" && break || echo "[WARN] Attempt $i: MySQL not ready"
    sleep "${RETRY_INTERVAL}"
    if [ "$i" -eq "${MAX_RETRIES}" ]; then
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
            --db-url="mysql://${DB_USER}:${DB_PASS}@${DB_HOST}:${DB_PORT}/${DB_NAME}" -y
        echo "[INFO] Drupal installation with existing config completed"

        echo "[INFO] Updating password for user: ${DRUPAL_USERNAME}"
        vendor/bin/drush user:password "${DRUPAL_USERNAME}" "${DRUPAL_PASSWORD}"
        if [ $? -eq 0 ]; then
            echo "[SUCCESS] Password updated for user: ${DRUPAL_USERNAME}"
        else
            echo "[ERROR] Failed to update password for user: ${DRUPAL_USERNAME}"
            exit 1
        fi
    else
        echo "[ERROR] config/sync directory is missing or empty. Cannot install with existing config."
        exit 1
    fi
else
    echo "[INFO] Drupal already installed"
fi

# Import configuration if exists
if [ -d config/sync ] && [ "$(ls -A config/sync)" ]; then
    echo "[INFO] Importing Drupal configuration"
    vendor/bin/drush cim -y || echo "[WARN] Config import failed"
    echo "[INFO] Configuration import completed"
else
    echo "[WARN] config/sync directory is missing or empty. Skipping config import."
fi

echo "[INFO] Drupal setup complete"