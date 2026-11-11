#!/bin/sh
set -eu

# The application owns its database without PostgreSQL superuser permissions.
psql --username "$POSTGRES_USER" --dbname postgres \
    --set=ON_ERROR_STOP=1 \
    --set=app_user="$DB_USERNAME" \
    --set=app_password="$DB_PASSWORD" \
    --set=app_database="$DB_DATABASE" <<'SQL'
CREATE ROLE :"app_user" LOGIN PASSWORD :'app_password';
CREATE DATABASE :"app_database" OWNER :"app_user";
SQL
