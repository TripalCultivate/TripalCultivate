ARG drupalversion=10.3.x-dev
ARG phpversion=8.3
ARG pgsqlversion=16
FROM tripalproject/tripaldocker:drupal${drupalversion}-php${phpversion}-pgsql${pgsqlversion}-noChado

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

COPY . /var/www/drupal/web/modules/contrib/TripalCultivate
WORKDIR /var/www/drupal/web/modules/contrib/TripalCultivate

## Migrate Chado v1.3 to v1.3.3.013 and trick Tripal into supporting it.
RUN service postgresql start \
  && drush trp-install-chado --schema-name=${chadoschema} \
  && echo "SET search_path TO testchado"  > /var/www/drupal/migration.sql \
  && cat /var/www/drupal/web/modules/contrib/TripalCultivate/config/sql/V1.3__to__V1.3.3.013__updates.sql >> /var/www/drupal/migration.sql \
  && drush sql:query --file=/var/www/drupal/migration.sql \
  && cp /var/www/drupal/web/modules/contrib/TripalCultivate/config/sql/chado_schema-1.3.3.013.yml /var/www/drupal/web/modules/contrib/tripal/tripal_chado/chado_schema/chado_schema-1.3.yml \
  && service postgresql stop

RUN service postgresql start \
  && drush trp-prep-chado --schema-name=${chadoschema} \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=general_chado \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=germplasm_chado \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=genomic_chado \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=genetic_chado \
  && drush en trpcultivate --yes \
  && drush tripal:trp-run-jobs --username=drupaladmin \
  && drush cr \
  && service postgresql stop
