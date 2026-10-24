# Deployment and rollback

## Verified domain layout

Production: https://prisonlab.odehit.com/ . Its Hestia Nginx/Apache configuration declares /home/user/web/prisonlab.odehit.com/public_html as the document root. PHP 8.3-FPM has curl/mbstring/OpenSSL and permits the domain private directory through open_basedir.

The Git checkout is /home/user/web/prisonlab.odehit.com/private/app, branch linkedin. Runtime storage is /home/user/web/prisonlab.odehit.com/private/data. Neither is a public document root. There is no database or migration system.

public_html contains six minimal PHP entry wrappers (index.php, api.php, public.php, privacy.php, auth.php and mcp.php) loading private/app, copied favicon/assets and the allowlist .htaccess, alongside the preexisting robots.txt. This keeps private directories inaccessible to the Nginx worker while PHP-FPM can read them. PHP resolves application source paths in the private checkout. Secrets are copied separately to private/app/.env with restricted permissions; LAB_DATA_DIR points to private/data. Never stage, commit or print .env.

MCP mailbox data belongs under private/data/mcp and is excluded from code backups. The MCP endpoint accepts role-specific bearer capabilities, including query URLs for harness compatibility: disable its access logging or redact its query arguments in both this domain's Nginx and Apache logs. Keep multiworker PHP-FPM enabled for renewable 15-second polls. See docs/MCP_ACTORS.md. No database migration is involved.

## Initial publication

1. Verify the domain's HTTP/HTTPS configuration and resolve its exact root; never infer a path from another site.
2. Back up existing public files and relevant domain/pool configuration under private/backups.
3. Push only the inspected allowlist to the public MIT-licensed repository after a staged secret/asset audit.
4. Clone into private/app and run git pull --ff-only origin linkedin. Verify its commit against the reviewed local commit.
5. Transfer .env directly over SCP and set production LAB_DATA_DIR. Restrict source/secret/data permissions. Do not transfer developer sessions, telemetry, benchmark votes, verification artifacts or screenshots.
6. Run bash tools/publish-public.sh with the verified public_html path to publish only reviewed wrappers/assets, preserving robots.txt and backing up the old placeholder. Scope proxy/PHP timeout changes to this domain so long jury calls finish.
7. Validate server configuration before graceful reload. Verify HTTPS, init_data, assets, cookie/CSRF behavior and denial of private paths.

## Subsequent deployment

SSH to the registered server, cd to the exact private/app path, inspect git status, then git pull --ff-only origin linkedin and bash tools/publish-public.sh /home/user/web/prisonlab.odehit.com/public_html. Do not force-reset remote changes or run migrations. Keep .env/private data out of Git and preserve permissions. Source is root-owned and readable by the domain application user; mutable data is owned by that application user.

Nginx may serve static JSON without consulting .htaccess. Source must stay outside public_html. The public publisher refuses unexpected source/target directory layouts and never copies source directories or private files. Never expose models.json, cases, lib, tests, tools, docs, .git, .env, backups or data directly.

## Checks and limits

Run php tests/unit.php, python -X utf8 tests/integration.py, python -X utf8 tests/browser.py, python -X utf8 tests/jury_parallel.py, python -X utf8 tests/accounts.py and python -X utf8 tests/community_browser.py with isolated storage/mock providers. A production functional check must be bounded and must not publish fixtures or copy user history. Live calls consume the configured key. New conversation ownership requires LinkedIn accounts; no durable background worker is used.

Before enabling account login, set APP_BASE_URL=https://prisonlab.odehit.com and LINKEDIN_REDIRECT_URI=https://prisonlab.odehit.com/auth.php?action=callback, with the matching client ID/secret in the private server .env. Authorize exactly that redirect in LinkedIn; the loopback redirect is for local development only. Complete isolated mock OAuth tests before deployment, then verify real OAuth on production with the owner's browser (owner's explicit preference after the interrupted local check). Preserve quota/account/session data and exclude it from routine code-deployment backups so deleting a conversation removes the application's stored content rather than leaving untracked archive copies. Do not transfer local account records to production.

## Rollback

Record the previous commit and backup directory. On initial-deployment failure restore only this domain's prior public files and backed-up configuration. For a regression deploy an explicitly reviewed prior commit while preserving .env/private data. Test configuration before reload; do not modify unrelated domains.
