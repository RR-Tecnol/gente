#!/bin/bash
set -e

echo "Deploying application ..."

# Enter maintenance mode
(php artisan down --message 'The app is being (quickly!) updated. Please try again in a minute.') || true
    # Disable proxy
    unset http_proxy
    unset https_proxy
    unset ftp_proxy
    #unset {http,https,ftp}_PROXY

    # Update codebase
    git fetch origin develop
    git reset --hard origin/develop

    # Install dependencies based on lock file
    composer install --no-interaction --prefer-dist --optimize-autoloader
    composer update eltonwebnet/jasper-rdr

    export PATH=$PATH:/home/elton/.nvm/versions/node/v12.18.3/bin
#    npm install --only=prod
    npm install

    npm run production


    # Migrate database
    # php artisan migrate --force

    # Note: If you're using queue workers, this is the place to restart them.
    # ...

    # Clear cache
    #php artisan optimize

    # Reload PHP to update opcache
    #echo "" | sudo -S service php7.3-fpm reload
# Exit maintenance mode
php artisan up

echo "Application deployed!"
