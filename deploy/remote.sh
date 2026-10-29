#!/usr/bin/env bash
# Runs on the Hostinger server after the code is uploaded.
#
# Usage: remote.sh <app dir> <public domain>
# Environment: DB_NAME, DB_USER, DB_PASS, DB_HOST (optional; SQLite is used
# when they are empty), SITE_MAIL.
set -euo pipefail

APP="$1"
DOMAIN="$2"
PRIVATE="$APP/private"
cd "$APP"

# Hostinger's default CLI PHP can be older than the web PHP. Use 8.3 or newer.
PHP=php
for candidate in /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php /usr/bin/php8.4 /usr/bin/php8.3; do
  if [ -x "$candidate" ]; then PHP="$candidate"; break; fi
done
if ! "$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
  echo "PHP 8.3 or newer is needed on the command line. Found: $("$PHP" -v | head -1)" >&2
  exit 1
fi
echo "Using $("$PHP" -v | head -1)"
DRUSH="$PHP vendor/bin/drush.php --root=$APP/web --uri=https://$DOMAIN -y"

mkdir -p "$PRIVATE/files" web/sites/default/files
chmod 750 "$PRIVATE"

# Secrets file: written once, then kept.
if [ ! -f "$PRIVATE/secrets.php" ]; then
  SALT=$("$PHP" -r 'echo bin2hex(random_bytes(32));')
  if [ -n "${DB_NAME:-}" ]; then
    DB_PHP="\$databases['default']['default'] = ['driver' => 'mysql', 'database' => '${DB_NAME}', 'username' => '${DB_USER}', 'password' => '${DB_PASS}', 'host' => '${DB_HOST:-localhost}', 'port' => '3306', 'prefix' => '', 'collation' => 'utf8mb4_general_ci'];"
  else
    VERSION=$("$PHP" -r 'echo (new PDO("sqlite::memory:"))->query("select sqlite_version()")->fetchColumn();')
    if ! "$PHP" -r "exit(version_compare('$VERSION', '3.45.0', '>=') ? 0 : 1);"; then
      echo "SQLite $VERSION is too old for Drupal 11 (needs 3.45). Create a MySQL database in hPanel and add DB_NAME, DB_USER and DB_PASS secrets." >&2
      exit 1
    fi
    DB_PHP="\$databases['default']['default'] = ['driver' => 'sqlite', 'database' => '$PRIVATE/goingsolo.sqlite', 'prefix' => '', 'namespace' => 'Drupal\\\\sqlite\\\\Driver\\\\Database\\\\sqlite', 'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/'];"
  fi
  cat > "$PRIVATE/secrets.php" <<PHP
<?php
$DB_PHP
\$settings['hash_salt'] = '$SALT';
\$settings['trusted_host_patterns'] = ['^' . preg_quote('$DOMAIN') . '\$', '^www\.' . preg_quote('$DOMAIN') . '\$'];
PHP
  chmod 640 "$PRIVATE/secrets.php"
  echo "Wrote $PRIVATE/secrets.php"
fi

chmod u+w web/sites/default 2>/dev/null || true
chmod u+w web/sites/default/settings.php 2>/dev/null || true
cp deploy/settings.php web/sites/default/settings.php

if ! $DRUSH status --field=bootstrap 2>/dev/null | grep -q Successful; then
  echo "Installing Drupal..."
  $DRUSH site:install goingsolo --site-name="goingsolo.ie" --site-mail="${SITE_MAIL:-hello@goingsolo.ie}" --account-name=admin --account-mail="${SITE_MAIL:-hello@goingsolo.ie}" --account-pass="$("$PHP" -r 'echo bin2hex(random_bytes(12));')"
  $DRUSH pm:install goingsolo_commerce goingsolo_seo
  $DRUSH pathauto:aliases-generate create all || true
  $DRUSH simple-sitemap:generate || true
  echo "ONE-TIME ADMIN LOGIN:"
  $DRUSH user:login --name=admin
else
  echo "Updating Drupal..."
  $DRUSH updatedb
  $DRUSH cache:rebuild
fi
$DRUSH cron || true
chmod 444 web/sites/default/settings.php
chmod 555 web/sites/default
echo "Done: https://$DOMAIN"
