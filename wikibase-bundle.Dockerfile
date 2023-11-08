FROM wikibase/wikibase-bundle:1.39.1-wmde.11
COPY LocalSettings.php /var/www/html/LocalSettings.d/LocalSettings.override.php
COPY extensions/JsonConfig /var/www/html/extensions/JsonConfig/
COPY extensions/Kartographer /var/www/html/extensions/Kartographer/
COPY extensions/WikibaseQualityConstraints /var/www/html/extensions/WikibaseQualityConstraints/
COPY extensions/CASAuth /var/www/html/extensions/CASAuth/
COPY extensions/TemplateData /var/www/html/extensions/TemplateData
COPY extensions/TemplateStyles /var/www/html/extensions/TemplateStyles
COPY extensions/SyntaxHighlight_GeSHi /var/www/html/extensions/SyntaxHighlight_GeSHi
COPY extensions/ParserFunctions /var/www/html/extensions/ParserFunctions
COPY extensions/BatchIngestion /var/www/html/extensions/BatchIngestion
COPY extensions/WikibaseSync /var/www/html/extensions/WikibaseSync 
COPY jquery.wikibase.entityselector.js /var/www/html/extensions/Wikibase/view/resources/jquery/wikibase/jquery.wikibase.entityselector.js
COPY jquery.wikibase.entitysearch.js /var/www/html/extensions/Wikibase/repo/resources/jquery.wikibase/jquery.wikibase.entitysearch.js
COPY images /var/www/html/images/
COPY --chown=www-data:www-data jobrunner-entrypoint.sh /var/www/html/maintenance/jobrunner-entrypoint.sh
RUN chmod +x /var/www/html/maintenance/jobrunner-entrypoint.sh
RUN apt update
RUN apt install -y vim
