ARG drupalversion='10.2.x-dev'
ARG phpversion='8.3'
FROM tripalproject/tripaldocker:drupal${drupalversion}-php${phpversion}-pgsql13-noChado

ARG chadoschema='testchado'
COPY . /var/www/drupal/web/modules/contrib/TripalCultivate


WORKDIR /var/www/drupal/web/themes

## Download the Tripal Cultivate base theme
RUN git clone https://github.com/TripalCultivate/TripalCultivate-Theme.git trpcultivatetheme

WORKDIR /var/www/drupal/web/modules/contrib/TripalCultivate

RUN service postgresql restart \
  && drush trp-install-chado --schema-name=${chadoschema} \
  && drush trp-prep-chado --schema-name=${chadoschema} \
  && drush en trpcultivate --yes \
  && drush theme:enable trpcultivatetheme --yes \
  && drush config-set system.theme default trpcultivatetheme \
  && drush tripal:trp-run-jobs --username=drupaladmin \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=general_chado \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=trpcultivate_experiments \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=trpcultivate_samples \
  && drush cr
