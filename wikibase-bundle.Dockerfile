FROM wikibase/wikibase-bundle:1.35.5-wmde.3
COPY LocalSettings.php /var/www/html/LocalSettings.d/LocalSettings.override.php
COPY extensions/JsonConfig /var/www/html/extensions/JsonConfig/
COPY extensions/Kartographer /var/www/html/extensions/Kartographer/
COPY extensions/WikibaseQualityConstraints /var/www/html/extensions/WikibaseQualityConstraints/
COPY extensions/CASAuth /var/www/html/extensions/CASAuth/
COPY images /var/www/html/images/
COPY --chown=www-data:www-data jobrunner-entrypoint.sh /var/www/html/maintenance/jobrunner-entrypoint.sh
RUN chmod +x /var/www/html/maintenance/jobrunner-entrypoint.sh
