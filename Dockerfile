ARG drupalversion=11.2.x-dev
ARG phpversion=8.4
ARG pgsqlversion=17
ARG installTheme
FROM knowpulse/tripalcultivate-tripal:${installTheme}drupal${drupalversion}-php${phpversion}-pgsql${pgsqlversion}

COPY docker/* /var/www/drupal
WORKDIR /var/www/drupal/
RUN composer config --no-plugins allow-plugins.cweagans/composer-patches true \
  && composer require 'drupal/markup:^2.0' 'cweagans/composer-patches' \
  && composer config extra.patches-file composer.patches.json \
  && composer install

COPY . /var/www/drupal/web/modules/contrib/TripalCultivate
WORKDIR /var/www/drupal/web/modules/contrib/TripalCultivate

RUN service postgresql start \
  && drush en trpcultivate markup --yes \
  && drush tripal:trp-run-jobs --username=drupaladmin \
  && drush cr \
  && service postgresql stop
