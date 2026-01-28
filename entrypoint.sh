#!/bin/sh
set -eu

DRUSH_BIN="${DRUSH_BIN:-vendor/bin/drush}"
CONFIG_SYNC_DIR="${CONFIG_SYNC_DIR:-config/sync}"
MYSQL_READY_MAX_RETRIES="${MYSQL_READY_MAX_RETRIES:-10}"
MYSQL_READY_SLEEP_SECONDS="${MYSQL_READY_SLEEP_SECONDS:-5}"

log()  { printf '[%s] %s\n' "$(date '+%Y-%m-%dT%H:%M:%S%z')" "$*"; }
fail() { log "[ERROR] $*"; exit 1; }

trap 'rc=$?; if [ "$rc" -ne 0 ]; then fail "Script failed (exit $rc)"; fi' EXIT

drush() {
  "$DRUSH_BIN" "$@"
}

log "[INFO] Starting Drupal entrypoint script"

# Check MySQL readiness
log "[INFO] Waiting for MySQL to be ready..."
for i in $(seq 1 ${MYSQL_READY_MAX_RETRIES}); do
  php -r "
  \$dsn = 'mysql:host=${DRUPAL_DATABASE_HOST};port=${DRUPAL_DATABASE_PORT_NUMBER}';
  try {
      new PDO(\$dsn, '${DRUPAL_DATABASE_USER}', '${DRUPAL_DATABASE_PASSWORD}', [PDO::ATTR_TIMEOUT => 2]);
      exit(0);
  } catch (PDOException \$e) {
      exit(1);
  }
  " && { log "[INFO] MySQL is accepting connections"; break; }

  log "[WARN] Attempt $i: MySQL not ready yet"
  sleep ${MYSQL_READY_SLEEP_SECONDS}
  if [ "$i" -eq ${MYSQL_READY_MAX_RETRIES} ]; then
    fail "MySQL did not become ready in time"
  fi
done

# Clear all caches to rebuild hook definitions
log "[INFO] Clearing all caches to rebuild module hooks"
drush cr || log "[WARN] Initial cache clear had issues"

# Install Drupal if not installed
echo "[INFO] Checking Drupal bootstrap status"
BOOTSTRAP="$(drush core:status --fields=bootstrap --format=string || true)"
echo "[INFO] Bootstrap status: '${BOOTSTRAP:-N/A}'"

CONFIG_PRESENT=false
if [ -d "${CONFIG_SYNC_DIR}" ] && [ "$(ls -A "${CONFIG_SYNC_DIR}")" ]; then
  CONFIG_PRESENT=true
fi

if [ "${BOOTSTRAP}" != "Successful" ]; then
  log "[INFO] Drupal not installed. Proceeding with installation"

  if [ "${CONFIG_PRESENT}" = "true" ]; then
    log "[INFO] Installing with existing configuration from '${CONFIG_SYNC_DIR}'"
    drush site:install --existing-config \
      --db-url="mysql://${DRUPAL_DATABASE_USER}:${DRUPAL_DATABASE_PASSWORD}@${DRUPAL_DATABASE_HOST}:${DRUPAL_DATABASE_PORT_NUMBER}/${DRUPAL_DATABASE_NAME}" \
      --account-name="${DRUPAL_USERNAME}" \
      --account-pass="${DRUPAL_PASSWORD}" \
      --account-mail="${DRUPAL_EMAIL}" \
      -y
    log "[INFO] Installation completed"

    drush updatedb -y
    drush config:import -y || log "[WARN] Config import had issues"
    drush cr
  else
    fail "Config directory '${CONFIG_SYNC_DIR}' is missing or empty. Cannot install with existing config."
  fi
else
  log "[INFO] Drupal already installed - applying updates & config"
  drush updatedb -y
  if [ "${CONFIG_PRESENT}" = "true" ]; then
    drush config:import -y || log "[WARN] Config import had issues"
  else
    log "[WARN] '${CONFIG_SYNC_DIR}' missing or empty. Skipping config import."
  fi
  drush cr
fi

# Sync latest Drupal user password
log "[INFO] Syncing password for user '${DRUPAL_USERNAME}'"
if drush user:password "${DRUPAL_USERNAME}" "${DRUPAL_PASSWORD}"; then
  log "[INFO] Password synced for user '${DRUPAL_USERNAME}'"
else
  fail "Password sync failed for user '${DRUPAL_USERNAME}'"
fi

log "[INFO] Drupal setup complete"