#!/bin/sh
set -e

echo "=== DB ENV CHECK ==="
echo "DB_HOST=${DB_HOST}"
echo "MYSQLHOST=${MYSQLHOST}"
echo "MYSQL_URL=${MYSQL_URL}"
echo "MYSQL_PUBLIC_URL=${MYSQL_PUBLIC_URL}"
echo "===================="

# Priority 1: DB_HOST already set
if [ -n "$DB_HOST" ]; then
    echo "Using existing DB_HOST: $DB_HOST"

# Priority 2: MYSQLHOST injected from Railway
elif [ -n "$MYSQLHOST" ]; then
    echo "Using MYSQLHOST..."
    export DB_CONNECTION=mysql
    export DB_HOST="$MYSQLHOST"
    export DB_PORT="${MYSQLPORT:-3306}"
    export DB_DATABASE="${MYSQLDATABASE:-railway}"
    export DB_USERNAME="${MYSQLUSER:-root}"
    export DB_PASSWORD="$MYSQLPASSWORD"

# Priority 3: Parse MYSQL_URL (private)
elif [ -n "$MYSQL_URL" ]; then
    echo "Parsing MYSQL_URL..."
    export DB_CONNECTION=mysql
    export DB_HOST=$(echo "$MYSQL_URL" | sed -E 's|mysql://[^@]+@([^:/]+).*|\1|')
    export DB_PORT=$(echo "$MYSQL_URL" | sed -E 's|mysql://[^@]+@[^:]+:([^/]+)/.*|\1|')
    export DB_DATABASE=$(echo "$MYSQL_URL" | sed -E 's|mysql://[^/]+/(.+)|\1|')
    export DB_USERNAME=$(echo "$MYSQL_URL" | sed -E 's|mysql://([^:]+):.*|\1|')
    export DB_PASSWORD=$(echo "$MYSQL_URL" | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')

# Priority 4: Parse MYSQL_PUBLIC_URL (fallback)
elif [ -n "$MYSQL_PUBLIC_URL" ]; then
    echo "Parsing MYSQL_PUBLIC_URL as fallback..."
    export DB_CONNECTION=mysql
    export DB_HOST=$(echo "$MYSQL_PUBLIC_URL" | sed -E 's|mysql://[^@]+@([^:/]+).*|\1|')
    export DB_PORT=$(echo "$MYSQL_PUBLIC_URL" | sed -E 's|mysql://[^@]+@[^:]+:([^/]+)/.*|\1|')
    export DB_DATABASE=$(echo "$MYSQL_PUBLIC_URL" | sed -E 's|mysql://[^/]+/(.+)|\1|')
    export DB_USERNAME=$(echo "$MYSQL_PUBLIC_URL" | sed -E 's|mysql://([^:]+):.*|\1|')
    export DB_PASSWORD=$(echo "$MYSQL_PUBLIC_URL" | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')

else
    echo "ERROR: No DB configuration found."
    exit 1
fi

echo "Resolved DB_HOST=$DB_HOST DB_PORT=$DB_PORT DB_DATABASE=$DB_DATABASE"

echo "Running migrations..."
php artisan migrate --force

echo "Starting server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
