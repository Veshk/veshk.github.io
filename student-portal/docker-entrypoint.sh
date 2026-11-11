#!/bin/sh
set -eu

# Each release/container caches its own environment, never an image-build secret.
php artisan config:cache --no-interaction
exec docker-php-entrypoint "$@"
