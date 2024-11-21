ARG drupalversion=10.3.x-dev
ARG phpversion=8.3
ARG pgsqlversion=16
ARG installTheme
FROM knowpulse/tripalcultivate-tripal:${installTheme}drupal${drupalversion}-php${phpversion}-pgsql${pgsqlversion}

COPY . /var/www/drupal/web/modules/contrib/TripalCultivate
WORKDIR /var/www/drupal/web/modules/contrib/TripalCultivate

RUN service postgresql start \
  && drush en trpcultivate --yes \
  && drush tripal:trp-run-jobs --username=drupaladmin \
  && drush cr \
  && service postgresql stop
