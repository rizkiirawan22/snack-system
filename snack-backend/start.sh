#!/bin/sh
set -e

# If MYSQL_URL is set but DB_HOST is not, parse MYSQL_URL
if [ -n "$MYSQL_URL" ] && [ -z "$DB_HOST" ]; then
    echo "Parsing MYSQL_URL for DB config..."
    # mysql://user:password@host:port/database
    DB_USERNAME=$(echo "$MYSQL_URL" | sed -E 's|mysql://([^:]+):.*|\1|')
    DB_PASSWORD=$(echo "$MYSQL_URL" | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')
    DB_HOST=$(echo "$MYSQL_URL" | sed -E 's|mysql://[^@]+@([^:/]+).*|\1|')
    DB_PORT=$(echo "$MYSQL_URL" | sed -E 's|mysql://[^@]+@[^:]+:([^/]+)/.*|\1|')
    DB_DATABASE=$(echo "$MYSQL_URL" | sed -E 's|mysql://[^/]+/(.+)|\1|')

    export DB_CONNECTION=mysql
    export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
    echo "DB_HOST=$DB_HOST DB_PORT=$DB_PORT DB_DATABASE=$DB_DATABASE"
fi

echo "Running migrations..."
php artisan migrate --force

echo "Starting server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
