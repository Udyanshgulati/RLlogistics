# Schermo storefront: WordPress + WooCommerce + Brandhouse/Schermo themes. Deployed by Coolify from this directory.
FROM wordpress:7.1.4-php8.3-apache

ARG WOOCOMMERCE_VERSION=11.2.1
ARG WOOCOMMERCE_SHA256=5317434cb381b5fdc7c1dd6ee258324f08c390976aaf8d1199f2a0e9a3ab8586
ARG WPCLI_VERSION=2.12.0
ARG WPCLI_SHA256=ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c

RUN set -eux; \
    apt-get update; apt-get install -y --no-install-recommends unzip mariadb-client; rm -rf /var/lib/apt/lists/*; \
    curl -fsSL -o /tmp/wc.zip "https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip"; \
    echo "${WOOCOMMERCE_SHA256}  /tmp/wc.zip" | sha256sum -c -; \
    mkdir -p /opt/schermo/plugins; unzip -q /tmp/wc.zip -d /opt/schermo/plugins; rm /tmp/wc.zip; \
    curl -fsSL -o /usr/local/bin/wp "https://github.com/wp-cli/wp-cli/releases/download/v${WPCLI_VERSION}/wp-cli-${WPCLI_VERSION}.phar"; \
    echo "${WPCLI_SHA256}  /usr/local/bin/wp" | sha256sum -c -; \
    chmod +x /usr/local/bin/wp

# Themes live in the image and are re-synced into the persistent volume on every start, so a redeploy ships theme changes.
COPY themes /opt/schermo/themes
COPY docker/php.ini /usr/local/etc/php/conf.d/schermo.ini
COPY docker/start.sh docker/setup.sh docker/seed-demo.php /opt/schermo/
RUN chmod +x /opt/schermo/start.sh /opt/schermo/setup.sh

ENTRYPOINT ["/opt/schermo/start.sh"]
CMD ["apache2-foreground"]
