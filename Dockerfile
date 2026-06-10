ARG drupalversion=11.x-dev
ARG phpversion=8.5
ARG postgresqlversion=18
ARG installTheme
ARG buildplatform='linux/amd64'
FROM --platform=${buildplatform} knowpulse/tripalcultivate-tripal:${installTheme}drupal${drupalversion}-php${phpversion}-pgsql${postgresqlversion}

COPY . /var/www/drupal/web/modules/contrib/TripalCultivate
WORKDIR /var/www/drupal/web/modules/contrib/TripalCultivate

RUN rm ./phpunit.xml
RUN bash /var/www/drupal/web/modules/contrib/tripal/set_phpunit_config.sh

RUN service postgresql start \
  && drush en trpcultivate --yes \
  && drush tripal:trp-run-jobs --username=drupaladmin \
  && drush cr \
  && service postgresql stop
