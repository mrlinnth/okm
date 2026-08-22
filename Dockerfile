FROM serversideup/php:8.5-cli AS cli

USER root
RUN install-php-extensions intl pcov
USER www-data

FROM serversideup/php:8.5-fpm-nginx AS web

USER root
RUN install-php-extensions intl
USER www-data
