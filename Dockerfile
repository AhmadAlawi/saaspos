# Generic per-tenant deploy image for the SaaS provisioning pipeline (SaaS
# conversion plan, Phase 3). Reference template every new customer's
# instance builds from — Dockerfile.meccamall stays as the pinned image for
# that one existing production instance, unaffected by this file.
#
# Multi-stage: webdevops/php-nginx (the runtime base — bundles nginx +
# php-fpm + supervisord in one container, which is what a single-port
# Railway service needs) has no Node.js at all, so the Vite asset build
# happens in a separate node stage and only its *output* (public/build)
# gets copied into the final image — no npm/node in the runtime image.

FROM node:24-alpine AS assets
WORKDIR /app
COPY . .
RUN npm ci
RUN npm run build

FROM webdevops/php-nginx:8.3

ENV WEB_DOCUMENT_ROOT=/app/public \
    PHP_MEMORY_LIMIT=512M \
    PHP_MAX_EXECUTION_TIME=120 \
    PHP_DISPLAY_ERRORS=0 \
    PHP_POST_MAX_SIZE=64M \
    PHP_UPLOAD_MAX_FILESIZE=64M

WORKDIR /app

# ffmpeg — RTSP-to-HLS live camera streaming (beta). See
# App\Services\Cameras\HlsStreamManager. `-c:v copy` in that service
# avoids re-encoding, so this only needs to exist, not be fast.
RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Dependencies first (better layer caching on rebuilds).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan config:clear \
    && chown -R application:application /app \
    && chmod -R 775 storage bootstrap/cache

# Laravel's scheduler as its own long-running supervisord process
# (`schedule:work` checks every minute internally) rather than relying on
# this image's undocumented cron.d behavior — same supervisor.d pattern
# the image already uses for nginx.conf/php-fpm.conf.
COPY docker/supervisor-scheduler.conf /opt/docker/etc/supervisor.d/laravel-scheduler.conf

# The Expo mobile app's API (public/mobile-api/index.php) is a separate
# front controller, deliberately never wired through the main app's
# bootstrap — nginx has to route /mobile-api/* to it explicitly.
COPY docker/mobile-api-vhost.conf /opt/docker/etc/nginx/vhost.common.d/10-mobile-api.conf

# public/documentation/ is a real static directory (index.html + future
# sub-pages) — without an explicit `index` directive for it, nginx's default
# location block treats a bare directory match as "found" by try_files
# before ever trying index.html, and serves a 403 (autoindex off) instead.
COPY docker/documentation-vhost.conf /opt/docker/etc/nginx/vhost.common.d/11-documentation.conf

# Explicit gzip — see docker/gzip.conf for why this isn't left to the
# base image's defaults. Biggest lever available for slow/metered
# store connections without touching app code.
COPY docker/gzip.conf /opt/docker/etc/nginx/vhost.common.d/05-gzip.conf

# Live camera streaming (beta) — MIME types + caching for the HLS
# playlists/segments HlsStreamManager writes to storage/app/public/hls/.
COPY docker/hls.conf /opt/docker/etc/nginx/vhost.common.d/15-hls.conf

# Runs migrations + tenant:provision on every deploy, then hands off to the
# image's normal supervisord entrypoint (nginx + php-fpm + scheduler).
COPY docker-entrypoint.sh /opt/docker/provision/entrypoint.d/30-tenant.sh
RUN chmod +x /opt/docker/provision/entrypoint.d/30-tenant.sh

EXPOSE 80
