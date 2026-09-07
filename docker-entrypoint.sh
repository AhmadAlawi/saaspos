#!/bin/bash
# Generic per-tenant entrypoint for the SaaS provisioning pipeline (SaaS
# conversion plan, Phase 3). Runs once per container start, before
# supervisord hands off to nginx/php-fpm/scheduler (webdevops'
# entrypoint.d convention) — same shape as docker-entrypoint-meccamall.sh,
# but drives `tenant:provision` (headless, env-driven, empty-tenant) instead
# of `mecca:provision` (one specific customer's real production data).
set -e

cd /app

# The deploy volume mounts at /app/storage as an EMPTY directory, overlaying
# whatever storage/ tree existed in the built image — Laravel's required
# subfolders have to be recreated here on every boot, not just once.
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/testing \
         storage/app/public \
         storage/logs
chown -R application:application storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# public/ is baked into the image, not the volume, so a symlink created at
# build time would still dangle once storage/app/public gets wiped/recreated
# above — recreate it every boot instead. Idempotent: storage:link just
# reports "already exists" and exits 0 if it's already there.
echo "[entrypoint] linking storage"
php artisan storage:link || true

if [ -z "${APP_KEY:-}" ]; then
    echo "[entrypoint] no APP_KEY set — generating one"
    php artisan key:generate --force
fi

echo "[entrypoint] running migrations"
php artisan migrate --force

echo "[entrypoint] provisioning tenant (no-op once already done)"
php artisan tenant:provision || echo "[entrypoint] tenant:provision failed — app still boots, check logs"

echo "[entrypoint] caching config/views"
php artisan config:cache
php artisan view:cache
# Deliberately NOT running route:cache: it writes bootstrap/cache/routes-v7.php,
# which public/mobile-api/index.php's separate Application instance ALSO reads
# by virtue of sharing the same basePath — so a cached main-app route file gets
# picked up there instead of routes/mobile-api.php ever being parsed, breaking
# every mobile-api request (wrong middleware, "route not found" for real ones).
