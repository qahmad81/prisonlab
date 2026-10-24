# Standalone deployment

The main branch is a complete standalone application: no external account setup, daily test quota, account leaderboard, public profiles or social sharing integration. The live community website https://prisonlab.odehit.com/ uses the separate linkedin branch. See EDITIONS.md; do not deploy main over that account-enabled site by mistake.

## Runtime

Use patched PHP with curl, mbstring and OpenSSL (8.3.36 or newer). Copy .env.example to a private .env and set an operator OpenRouter key, or use a visitor's key through the interface. The operator-funded model price cap remains; there is no daily test quota. There is no database and no migration system.

For local use, run php -S 127.0.0.1:8488 router.php from the repository. The router enforces a public allowlist. Do not expose PHP's development server to the Internet. Visitor keys stay in browser sessionStorage and request headers. Private history belongs to an unguessable HttpOnly cookie; there is no cross-browser account recovery.

## Production layout

Place source and .env outside the public document root, for example domain/private/app, with LAB_DATA_DIR at domain/private/data. Restrict source to the application group and secrets to the operator/application user; runtime data is mode 0700. The public directory exposes only index.php, api.php, public.php, privacy.php, favicon and reviewed runtime assets. Do not rely on Apache rules when a fronting Nginx can serve JSON directly.

The reviewed tools/publish-public.sh validates the private/app and public_html relationship before writing four PHP wrappers and copying runtime assets. Verify domain ownership/roots before invoking it. Never publish lib, cases, models.json, states.json, tests, docs, tools, .git, .env, private histories, health records or candidate reports. A visitor may explicitly publish a completed session through the application; the source publisher must not copy runtime sessions.

Before updating a standalone installation, record its commit, inspect local changes and back up source/public/configuration without conversation/account/benchmark data. Pull main using fast-forward-only Git. Preserve .env, application ownership and data. Lint PHP and JavaScript, then run the unit, integration, browser, standalone identity and parallel-jury tests against isolated mock providers. No migrations or test fixtures belong on production.

Use valid HTTPS, secure cookies, correct PHP-FPM permissions and timeouts sufficient for the professional panel's 180-second deadline plus JEV verification. Check home, init_data, private-path denial and public gallery after deployment. For rollback, restore the reviewed previous source/public/configuration and recheck runtime configuration; do not replace private user histories with test or developer data.

## MCP endpoint

Expose the reviewed mcp.php wrapper with other public entries. Keep LAB_DATA_DIR/mcp private and excluded from deployment backups. Disable MCP access logging or redact its query arguments; its gate URL is a credential. Retain multiworker PHP-FPM for renewable 15-second polls. See docs/MCP_ACTORS.md.
