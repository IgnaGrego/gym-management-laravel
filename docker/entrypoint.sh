#!/bin/sh
set -e

echo "Waiting for PostgreSQL..."
until pg_isready -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USERNAME" > /dev/null 2>&1; do
    sleep 1
done

exec "$@"
