#!/bin/sh
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
    set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'bin/console' ]; then
    # Install project
    composer install --prefer-dist --no-interaction --optimize-autoloader --no-scripts --quiet
    php bin/console assets:install --no-interaction
    php bin/console cache:warmup --no-interaction --no-optional-warmers

    # Create DB and load data
    until php bin/console doctrine:query:sql "select 1" >/dev/null 2>&1; do
        (echo >&2 "Waiting for MySQL to be ready...")
        sleep 1
    done

#    php bin/console doctrine:migrations:migrate --no-interaction

    # Start Hack (changing permissions after create files on the host)
    SRC=bin/console
    USER=www-data
    GROUP=www-data
    USER_ID=$(stat -c '%u' $SRC)
    GROUP_ID=$(stat -c '%g' $SRC)

    echo $USER_ID >/root/uid
    echo $GROUP_ID >/root/gid
    usermod -u $USER_ID $USER
    groupmod -g $GROUP_ID $GROUP
    chown -R $USER:$GROUP var public vendor
    # End Hack
fi

exec docker-php-entrypoint "$@"
