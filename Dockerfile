ARG drupalversion='10.2.x-dev'
ARG phpversion='8.3'
ARG postgresqlversion='16'
FROM tripalproject/tripaldocker:drupal${drupalversion}-php${phpversion}-pgsql${postgresqlversion}-noChado

ARG chadoschema='testchado'
ARG installTheme=TRUE
WORKDIR /var/www/drupal/web/themes

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

RUN service postgresql start \
  && drush trp-install-chado --schema-name=${chadoschema} \
  && drush trp-prep-chado --schema-name=${chadoschema} \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=general_chado \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=germplasm_chado \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=genomic_chado \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=genetic_chado \
  && drush en trpcultivate --yes \
  && drush cr \
  && service postgresql stop
