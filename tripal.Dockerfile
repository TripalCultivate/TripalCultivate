ARG drupalversion=11.3.x-dev
ARG phpversion=8.5
ARG postgresqlversion=18
ARG buildplatform='linux/amd64'
FROM --platform=${buildplatform} tripalproject/tripaldocker:drupal${drupalversion}-php${phpversion}-pgsql${postgresqlversion}-noChado

ARG chadoschema='testchado'
ARG installTheme=TRUE
WORKDIR /var/www/drupal/web/themes

## TEMPORARY!!!
## Extend loading time to "fix" max execution time error.
RUN echo "ini_set('max_execution_time', 0);" >> /var/www/drupal/web/sites/default/settings.php

## Download the Tripal Cultivate base theme
RUN service postgresql restart \
  && if [ "$installTheme" = "TRUE" ]; then \
  git clone https://github.com/TripalCultivate/TripalCultivate-Theme.git trpcultivatetheme \
  && mv trpcultivatetheme/trpcultivatetheme_companion /var/www/drupal/web/modules/contrib/trpcultivatetheme_companion \
  && drush pm:install trpcultivatetheme_companion --yes \
  && drush theme:enable trpcultivatetheme --yes \
  && drush config-set system.theme default trpcultivatetheme; fi \
  && export DRUPALVERSION=`drush core:status --field=drupal-version` \
  && export PHPVERSION=`drush core:status --field=php-version` \
  && drush config:set system.site name "Tripal Cultivate Docker" \
  && drush config:set system.site slogan "Drupal $DRUPALVERSION PHP$PHPVERSION" \
  && service postgresql stop

COPY config/sql/V1.3__to__V1.3.3.013__updates.sql /V1.3__to__V1.3.3.013__updates.sql
COPY config/sql/chado_schema-1.3.3.013.yml /var/www/drupal/web/modules/contrib/tripal/tripal_chado/chado_schema/chado_schema-1.3.yml

## Migrate Chado v1.3 to v1.3.3.013 and trick Tripal into supporting it.
RUN service postgresql start \
  && drush trp-install-chado --schema-name=${chadoschema} \
  && echo "SET search_path TO testchado"  > /var/www/drupal/migration.sql \
  && cat /V1.3__to__V1.3.3.013__updates.sql >> /var/www/drupal/migration.sql \
  && drush sql:query --file=/var/www/drupal/migration.sql \
  && service postgresql stop

RUN service postgresql start \
  && drush trp-prep-chado --schema-name=${chadoschema} \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=general_chado \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=germplasm_chado \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=genomic_chado \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=genetic_chado \
  && drush cr \
  && service postgresql stop

RUN service postgresql start \
  && cd /var/www/drupal \
  && composer require tripal/tripal:4.x-dev tripal/tripal_devtools --dev --with-all-dependencies \
  && drush en tripal_devtools --yes \
  && service postgresql stop
