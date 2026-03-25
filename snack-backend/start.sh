#!/bin/sh
set -e

echo "=== DB ENV CHECK ==="
echo "DB_HOST=${DB_HOST}"
echo "DB_PORT=${DB_PORT}"
echo "DB_DATABASE=${DB_DATABASE}"
echo "MYSQLHOST=${MYSQLHOST}"
echo "MYSQLPORT=${MYSQLPORT}"
echo "MYSQLDATABASE=${MYSQLDATABASE}"
echo "MYSQL_URL=${MYSQL_URL}"
echo "===================="

# Use Railway MySQL vars directly if DB_HOST is not set
if [ -z "$DB_HOST" ] && [ -n "$MYSQLHOST" ]; then
    echo "Using Railway MYSQL* variables..."
    export DB_CONNECTION=mysql
    export DB_HOST="$MYSQLHOST"
    export DB_PORT="${MYSQLPORT:-3306}"
    export DB_DATABASE="${MYSQLDATABASE:-railway}"
    export DB_USERNAME="${MYSQLUSER:-root}"
    export DB_PASSWORD="$MYSQLPASSWORD"
    echo "DB_HOST set to: $DB_HOST"
fi

# Fallback: parse MYSQL_URL
if [ -z "$DB_HOST" ] && [ -n "$MYSQL_URL" ]; then
    echo "Parsing MYSQL_URL..."
    export DB_CONNECTION=mysql
    export DB_HOST=$(echo "$MYSQL_URL" | sed -E 's|mysql://[^@]+@([^:/]+).*|\1|')
    export DB_PORT=$(echo "$MYSQL_URL" | sed -E 's|mysql://[^@]+@[^:]+:([^/]+)/.*|\1|')
    export DB_DATABASE=$(echo "$MYSQL_URL" | sed -E 's|mysql://[^/]+/(.+)|\1|')
    export DB_USERNAME=$(echo "$MYSQL_URL" | sed -E 's|mysql://([^:]+):.*|\1|')
    export DB_PASSWORD=$(echo "$MYSQL_URL" | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')
    echo "DB_HOST set to: $DB_HOST"
fi

if [ -z "$DB_HOST" ]; then
    echo "ERROR: DB_HOST is still empty. Check Railway variable linking."
    exit 1
fi

echo "Running migrations..."
php artisan migrate --force

echo "Starting server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
