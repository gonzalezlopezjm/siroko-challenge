#!/bin/bash
set -e

echo "==> Installing Composer dependencies..."
# --no-scripts evita que Composer ejecute cache:clear/assets:install
# antes de que la base de datos esté disponible (causa timeout).
# El warmup lo hacemos nosotros a continuación, con DB ya lista.
composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

echo "==> Waiting for database..."
sleep 2   # margen para que Docker resuelva el hostname en la red interna
until php -r "
    \$p    = parse_url(getenv('DATABASE_URL'));
    \$host = \$p['host'];
    \$port = \$p['port'] ?? 5432;
    \$db   = ltrim(\$p['path'], '/');
    \$db   = explode('?', \$db)[0];
    new PDO(\"pgsql:host=\$host;port=\$port;dbname=\$db\", \$p['user'], \$p['pass']);
" 2>/dev/null; do
  echo "  DB not ready, retrying..."
  sleep 2
done
echo "  DB ready."

echo "==> Running Doctrine migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "==> Warming up cache..."
php bin/console cache:warmup --no-interaction

echo "==> Ensuring Qdrant collection exists..."
php bin/console app:qdrant:ensure-collection --no-interaction || true

echo "==> Loading product fixtures (skips existing)..."
php bin/console app:fixtures:load --no-interaction || true

if [ -n "${OPENAI_API_KEY:-}" ]; then
    echo "==> Indexing products in Qdrant..."
    php bin/console app:search:reindex --no-interaction || true
else
    echo "==> OPENAI_API_KEY not set — skipping Qdrant indexing."
fi

echo "==> Seeding Grafana metrics (skip if already populated)..."
php bin/console app:metrics:seed --skip-if-exists --no-interaction || true

echo "==> Creating demo orders and emails (skip if already present)..."
php bin/console app:demo:setup --no-interaction || true

echo "==> Starting supervisord (php-fpm + messenger worker)..."
exec "$@"
