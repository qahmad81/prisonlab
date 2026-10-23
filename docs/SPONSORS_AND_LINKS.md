# Community funding and shareable views

These features exist only on linkedin. main receives only the shared conversation portrait enlargement (prisoner 120px, investigator 87px). main continues to run independently with its existing private .env site key.

## Credentials and quotas

The vault is LAB_DATA_DIR/sponsor-vault.json, with AES-256-GCM encrypted keys, 12-byte IVs, 16-byte authentication tags and a separate 32-byte sponsor-vault.key. Both files are 0600 inside the 0700 runtime directory, outside source and public roots. Ownership is the PHP application user. Runtime files and keys are never committed, copied to public_html or included in code deployment backups. Do not print or return decrypted credentials, encrypted records or fingerprints. HTTPS key verification uses the existing fixed OpenRouter endpoints.

Sponsorship uses the current authenticated LinkedIn account, not an owner/profile supplied by the browser. A company label can be supplied; its authenticated account owner is always visible separately. It is not a claim of LinkedIn company-page verification. GET lists only public metadata and the viewing account's own remaining count; owners additionally see inactive records and edit/delete controls. A sponsor cannot read private funded transcripts. Up to five active/stored donations can be managed per account. Internal actor price ceilings cover output and conservatively cap input at the same value; owners must also set their actual key-dollar budget in OpenRouter.

Daily quotas count new tests per donor and LinkedIn account, using Asia/Jerusalem dates. Account-file exclusive locks serialize starts across processes. Reservations are written atomically before transcripts; crashes can consume a slot but cannot create a free extra session. Quotas are held separately from the secret vault so its size does not grow per test. Rotation, editing and conversation deletion preserve consumed counts. New-day starts reset the daily ledger. A request ID replays the owner's existing test before any funding change; concurrent starts with the same ID cannot reserve twice.

The session stores a sponsor ID, public owner snapshot, label, initial revision and price ceiling. Every subsequent request resolves that same sponsor; a visitor header or new selection cannot change the payer. Each provider launch checks whether the selected revision is still active. Already-transmitted calls can finish; future stages stop after a revision change until the session is reloaded/resumed using its original donor's current key. A removed or disabled donor never falls through to another key. Lower actor limits apply on resume. Changing the funding selector only affects a new test.

Price zero enforces provider prompt/completion price caps of zero for chat actors. JEV decisions, compaction and the normal price-capped jury still use that donor key, as explicitly requested by the owner. Personal visitor keys remain in sessionStorage and headers only, with their existing fingerprint binding and unlimited site quota. The sponsor picker can override a stored personal key for a new test without exposing it or bypassing donor limits.

## Initial site-key migration

After deploying and verifying the linkedin code, run the CLI-only tools/migrate_site_sponsor.php as the PHP application user, passing the explicitly verified owner's public profile ID. The tool locates that authenticated account record, verifies the legacy OpenRouter key, encrypts it, assigns the default 3/day and USD1/million actor limit, then checks decryption against the original without printing it. The new default stays disabled while existing same-day site reservations are carried over, so migration cannot reset a visitor's consumed quota. Interrupted migrations resume without refunding usage. Re-running is idempotent. It never claims the site key for the first visitor. Do not copy production accounts or conversations into local development.

Only after that succeeds, remove OPENROUTER_API_KEY from production .env through a private atomic edit retaining its permissions. Never copy .env into a deployment backup. The vault's site_initialized marker prevents resurrecting the old env key if the migrated site donor is later removed. Legacy/dev deployments without that marker retain the env fallback. Keep the local shared .env for the standalone main checkout.

For rollback below sponsor-aware code, securely restore the default site key to .env from the vault before reverting, without printing credentials or copying the vault into source. If the donor has intentionally revoked/deleted it, do not restore a revoked key. Prefer a corrective sponsor-aware deployment and preserve private data. No database migration is involved.

## URLs and privacy

- public.php?session_id=... opens only a published test. Private/deleted tests still return 404.
- public.php?view=leaderboard&model=...&role=prisoner|interrogator filters a model role. Case, operator profile, levels, persona and rubric can further scope results.
- public.php?case_file=... shares a fictional case/accused file, even before any tests are published.
- public.php?investigator=... shares a fictional investigator persona.
- public.php?profile=...&tab=investigator|accused|simulations shares the member's public tests by their role. Default is investigator.
- index.php?sponsors=1 opens the sponsor directory; index.php?case_file=... preselects a shared case.

Copy-link controls and filter-address updates make these URLs reusable. Case/model/persona/profile pages have source-generated Open Graph metadata; private session metadata is never emitted. Funding names and actual provider-reported costs accompany published tests. A sponsor identity may remain in a published funding snapshot after deleting the key, without retaining the secret or granting private access.

## Tests

Run tests/unit.php and Python tests/integration.py, tests/browser.py, tests/accounts.py, tests/sponsors.py and tests/community_browser.py. Accounts, sponsors and community browser suites use port 8494 and must run sequentially. Sponsor tests use isolated mock credentials, validate encryption/no response leaks, check foreign-owner rejection, free-actor caps with donor-funded JEV/jury, independent quotas, rotation/revocation, deletion, migration and eight-process contention. Browser tests cover safe labels, cleared key inputs, source selection, profile tabs and stable model/case links. No test consumes paid provider credit.
