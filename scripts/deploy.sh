#!/usr/bin/env bash
# Live server deploy script (called by GitHub Actions SSH or manually).
# Preserves: .env, storage/, uploaded assets under public/
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
RUN_MIGRATE="${RUN_MIGRATE:-false}"

cd "$APP_DIR"

pick_php() {
  if [ -n "${PHP_BIN}" ] && command -v "${PHP_BIN}" >/dev/null 2>&1; then
    echo "${PHP_BIN}"
    return 0
  fi
  if [ -n "${PHP_BIN}" ] && [ -x "${PHP_BIN}" ]; then
    echo "${PHP_BIN}"
    return 0
  fi

  local candidates=(
    php8.2 php82
    php8.1 php81
    php8.3 php83
    /usr/bin/php8.2 /usr/bin/php8.1 /usr/bin/php8.3
    /usr/local/bin/php8.2 /usr/local/bin/php8.1 /usr/local/bin/php8.3
    /opt/cpanel/ea-php82/root/usr/bin/php
    /opt/cpanel/ea-php81/root/usr/bin/php
    /opt/cpanel/ea-php83/root/usr/bin/php
    /opt/alt/php82/usr/bin/php
    /opt/alt/php81/usr/bin/php
    /opt/alt/php83/usr/bin/php
  )

  local c ver
  for c in "${candidates[@]}"; do
    if command -v "$c" >/dev/null 2>&1 || [ -x "$c" ]; then
      ver="$("$c" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
      case "$ver" in
        8.1|8.2|8.3)
          echo "$c"
          return 0
          ;;
      esac
    fi
  done

  if command -v php >/dev/null 2>&1; then
    echo "php"
    return 0
  fi

  return 1
}

PHP_BIN="$(pick_php)" || {
  echo "ERROR: no usable PHP binary found"
  exit 1
}

echo "==> Working directory: $(pwd)"
echo "==> Git HEAD: $(git rev-parse --short HEAD)"
echo "==> PHP: $PHP_BIN ($($PHP_BIN -v | head -n1))"

if [ ! -f .env ]; then
  echo "ERROR: .env missing in $APP_DIR — aborting to avoid wiping live config."
  exit 1
fi

export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_DISABLE_XDEBUG_WARN=1

ensure_composer_phar() {
  if [ -f composer.phar ]; then
    return 0
  fi
  echo "==> Downloading composer.phar for $PHP_BIN"
  $PHP_BIN -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  $PHP_BIN composer-setup.php --quiet --filename=composer.phar
  rm -f composer-setup.php
}

run_composer() {
  ensure_composer_phar
  $PHP_BIN composer.phar "$@"
}

if [ -f composer.json ]; then
  echo "==> composer install (no-dev)"
  # Prefer correct PHP; still ignore ext gaps on CLI vs FPM hosts
  run_composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction \
    --ignore-platform-req=ext-curl \
    --ignore-platform-req=ext-gd \
    --ignore-platform-req=ext-zip \
    --ignore-platform-req=ext-intl
fi

if [ ! -f artisan ]; then
  echo "ERROR: artisan not found — is DEPLOY_PATH correct?"
  exit 1
fi

bring_up() {
  echo "==> Maintenance mode OFF"
  $PHP_BIN artisan up || true
}
trap bring_up EXIT

echo "==> Maintenance mode ON"
$PHP_BIN artisan down --retry=60 || true

echo "==> Clear stale caches"
$PHP_BIN artisan config:clear || true
$PHP_BIN artisan route:clear || true
$PHP_BIN artisan view:clear || true
$PHP_BIN artisan cache:clear || true

if [ "$RUN_MIGRATE" = "true" ]; then
  echo "==> Running migrations"
  $PHP_BIN artisan migrate --force --no-interaction
else
  echo "==> Skipping migrations (set RUN_MIGRATE=true / DEPLOY_RUN_MIGRATE secret to enable)"
fi

echo "==> Ensure storage link"
$PHP_BIN artisan storage:link || true

echo "==> Rebuild caches"
$PHP_BIN artisan config:cache
# Some apps have duplicate route names — don't leave site down for that
if ! $PHP_BIN artisan route:cache; then
  echo "==> route:cache failed (duplicate names?) — continuing without route cache"
  $PHP_BIN artisan route:clear || true
fi
$PHP_BIN artisan view:cache || true

echo "==> Deploy OK @ $(git rev-parse --short HEAD)"
trap - EXIT
bring_up
