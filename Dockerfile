FROM crofqappdev1.azurecr.io/ofqual/drupal-bitnami:latest

USER root

COPY composer.json composer.lock /opt/bitnami/drupal/
RUN cd /opt/bitnami/drupal && composer install --no-dev --no-interaction

COPY config /opt/bitnami/drupal/sites/default/files/config/
COPY web/sites/default/settings.php /opt/bitnami/drupal/sites/default/settings.php
COPY web/modules/custom /opt/bitnami/drupal/modules/custom/
COPY web/themes/custom /opt/bitnami/drupal/themes/custom/

COPY config.sh /docker-entrypoint-init.d/config-sync.sh
RUN chmod +x /docker-entrypoint-init.d/config-sync.sh

RUN chown -R 1001:0 /opt/bitnami/drupal
USER 1001