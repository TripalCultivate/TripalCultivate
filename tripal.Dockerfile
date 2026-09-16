ARG drupalversion=11.x-dev
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

## Migrate Chado v1.3 to v1.3.3.013.
RUN service postgresql start \
  && drush trp-install-chado --schema-name=${chadoschema} \
  && drush trp-prep-chado --schema-name=${chadoschema} \
  && drush trp-migrate-chado --schema-name=${chadoschema} \
  && service postgresql stop

RUN service postgresql start \
  && drush tripal:trp-import-types --collection_id=general_chado \
  && drush tripal:trp-import-types --collection_id=germplasm_chado \
  && drush tripal:trp-import-types --collection_id=genomic_chado \
  && drush tripal:trp-import-types --collection_id=genetic_chado \
  && drush cr \
  && service postgresql stop
