#!/bin/bash
set -e

echo "Running post-init config sync..."

cd /opt/bitnami/drupal

CONFIG_PATH="/opt/bitnami/drupal/sites/default/files/config/sync/system.site.yml"

if vendor/bin/drush status | grep -q "Drupal bootstrap"; then
  echo "Drupal installed. Applying config sync..."
  
  if [ -f "$CONFIG_PATH" ]; then
    UUID=$(grep uuid "$CONFIG_PATH" | awk '{print $2}')
    CURRENT_UUID=$(vendor/bin/drush config-get system.site uuid | awk '{print $2}')
    
    if [ -n "$UUID" ] && [ "$UUID" != "$CURRENT_UUID" ]; then
      echo "Updating site UUID to match config ($UUID)..."
      vendor/bin/drush config-set system.site uuid "$UUID" -y
    else
      echo "UUID is already correct or missing."
    fi

    echo "Deleting default shortcut entities to avoid conflicts..."
    vendor/bin/drush entity:delete shortcut_set default || true
    vendor/bin/drush entity:delete shortcut || true

    echo "Importing configuration..."
    vendor/bin/drush cim -y
    vendor/bin/drush updb -y
    vendor/bin/drush cr
  else
    echo "Config file not found at $CONFIG_PATH. Skipping UUID fix."
  fi
else
  echo "Drupal not installed yet. Skipping config import."
fi