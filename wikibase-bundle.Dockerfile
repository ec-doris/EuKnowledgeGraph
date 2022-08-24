FROM wikibase/wikibase-bundle:1.35.5-wmde.3
# Copy composer executable to the wikibase image from the composer image
COPY --from=composer:2.1.4 /usr/bin/composer /usr/bin/composer
# Unzip needs to be installed so composer can use it instead of building dependencies from source
# As per: https://stackoverflow.com/a/60421549/5495682
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev \
    unzip
RUN docker-php-ext-install zip
COPY LocalSettings.php /var/www/html/LocalSettings.d/LocalSettings.override.php
COPY extensions/JsonConfig /var/www/html/extensions/JsonConfig/
COPY extensions/Kartographer /var/www/html/extensions/Kartographer/
COPY extensions/WikibaseQualityConstraints /var/www/html/extensions/WikibaseQualityConstraints/
COPY extensions/CASAuth /var/www/html/extensions/CASAuth/
COPY extensions/AWS /var/www/html/extensions/AWS/
COPY images /var/www/html/images/
# COPY composer.local.json /var/www/html/composer.local.json
# RUN ( cd /var/www/html/extensions/AWS/ && composer install --no-dev )
RUN ( cd /var/www/html && composer update )
# RUN rm composer.lock

COPY --chown=www-data:www-data jobrunner-entrypoint.sh /var/www/html/maintenance/jobrunner-entrypoint.sh
RUN chmod +x /var/www/html/maintenance/jobrunner-entrypoint.sh
