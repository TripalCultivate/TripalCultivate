ARG drupalversion='10.2.x-dev'
ARG phpversion='8.3'
FROM tripalproject/tripaldocker:drupal${drupalversion}-php${phpversion}-pgsql13-noChado

ARG chadoschema='testchado'
COPY . /var/www/drupal/web/modules/contrib/TripalCultivate

WORKDIR /var/www/drupal/web/modules/contrib/TripalCultivate

RUN service postgresql restart \
  && drush trp-install-chado --schema-name=${chadoschema} \
  && drush trp-prep-chado --schema-name=${chadoschema} \
  && drush en trpcultivate --yes \
  && drush cr
