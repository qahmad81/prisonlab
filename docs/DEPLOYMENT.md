# Deployment and rollback

## Verified domain layout

Production: https://prisonlab.odehit.com/ . Its Hestia Nginx/Apache configuration declares /home/user/web/prisonlab.odehit.com/public_html as the document root. PHP 8.3-FPM has curl/mbstring/OpenSSL and permits the domain private directory through open_basedir.

The Git checkout is /home/user/web/prisonlab.odehit.com/private/app, branch main. Runtime storage is /home/user/web/prisonlab.odehit.com/private/data. Neither is a public document root. There is no database or migration system.

public_html contains three minimal PHP entry wrappers loading private/app, copied favicon/assets and the allowlist .htaccess, alongside the preexisting robots.txt. This keeps private directories inaccessible to the Nginx worker while PHP-FPM can read them. PHP resolves application source paths in the private checkout. Secrets are copied separately to private/app/.env with restricted permissions; LAB_DATA_DIR points to private/data. Never stage, commit or print .env.

## Initial publication

1. Verify the domain's HTTP/HTTPS configuration and resolve its exact root; never infer a path from another site.
2. Back up existing public files and relevant domain/pool configuration under private/backups.
3. Push only the inspected allowlist to a private repository after a staged secret/asset audit.
4. Clone into private/app and run git pull --ff-only origin main. Verify its commit against the reviewed local commit.
5. Transfer .env directly over SCP and set production LAB_DATA_DIR. Restrict source/secret/data permissions. Do not transfer developer sessions, telemetry, benchmark votes, verification artifacts or screenshots.
6. Run bash tools/publish-public.sh with the verified public_html path to publish only reviewed wrappers/assets, preserving robots.txt and backing up the old placeholder. Scope proxy/PHP timeout changes to this domain so long jury calls finish.
7. Validate server configuration before graceful reload. Verify HTTPS, init_data, assets, cookie/CSRF behavior and denial of private paths.

## Subsequent deployment

SSH to the registered server, cd to the exact private/app path, inspect git status, then git pull --ff-only origin main and bash tools/publish-public.sh /home/user/web/prisonlab.odehit.com/public_html. Do not force-reset remote changes or run migrations. Keep .env/private data out of Git and preserve permissions. Source is root-owned and readable by the domain application user; mutable data is owned by that application user.

Nginx may serve static JSON without consulting .htaccess. Source must stay outside public_html. The public publisher refuses unexpected source/target directory layouts and never copies source directories or private files. Never expose models.json, cases, lib, tests, tools, docs, .git, .env, backups or data directly.

## Checks and limits

Run php tests/unit.php, python -X utf8 tests/integration.py, python -X utf8 tests/browser.py and python -X utf8 tests/jury_parallel.py with isolated storage/mock providers. A production functional check must be bounded and must not publish fixtures or copy user history. Live calls consume the configured key. Ownership is currently an anonymous visit cookie, without LinkedIn login or durable workers.

## Rollback

Record the previous commit and backup directory. On initial-deployment failure restore only this domain's prior public files and backed-up configuration. For a regression deploy an explicitly reviewed prior commit while preserving .env/private data. Test configuration before reload; do not modify unrelated domains.
