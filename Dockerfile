ARG drupalversion='10.2.x-dev'
ARG phpversion='8.3'
FROM tripalproject/tripaldocker:drupal${drupalversion}-php${phpversion}-pgsql13-noChado

ARG chadoschema='testchado'
ARG sitename="Tripal Cultivate"

WORKDIR /var/www/drupal/web/themes

## Download the Tripal Cultivate base theme
RUN git clone https://github.com/TripalCultivate/TripalCultivate-Theme.git trpcultivatetheme \
  && service postgresql restart \
  && drush theme:enable trpcultivatetheme --yes \
  && drush config-set system.theme default trpcultivatetheme \
  && drush config:set system.site name "${sitename}" \
  && service postgresql stop

COPY . /var/www/drupal/web/modules/contrib/TripalCultivate
WORKDIR /var/www/drupal/web/modules/contrib/TripalCultivate

RUN service postgresql start \
  && drush trp-install-chado --schema-name=${chadoschema} \
  && drush trp-prep-chado --schema-name=${chadoschema} \
  && drush en trpcultivate --yes \
  && drush cr \
  && service postgresql stop
