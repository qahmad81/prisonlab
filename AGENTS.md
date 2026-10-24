# Agent guide

Prisonlab is a PHP 8.3.36+ / vanilla JavaScript Arabic RTL benchmarking application. Read README.md and docs/DEPLOYMENT.md before changing behavior or deploying. Historical reviews in docs/ describe the version at their stated date; current code and rubric 2.6 take precedence.

## Runtime boundaries

- Keep UI text, case files, character data and model prompts Arabic. Developer/operator documentation is English.
- Canonical messages always use user for the interrogator and assistant for the prisoner. In reverse mode the human is assistant; do not swap the schema.
- Count completed prisoner replies, with at most 30 rounds. Keep full transcripts immutable during compaction; compact only a separate spoken-context view. Identity, case files and both character levels stay outside compaction.
- Both AI actors may provide short fictional spectator notes. Never forward those notes or raw provider reasoning to the other actor, JEV, judges or compaction.
- Preserve successful stages and request IDs across retries. A failed prisoner call must not repurchase the investigator or JEV. Missing optional spectator notes must not reject valid spoken content.
- Enforce spoken-output limits separately from reasoning-token budgets. Never truncate spoken evidence to make a model appear compliant. Preserve measured output events for the jury.
- Confession needs substantive quoted evidence and the admission-kind gate; ordinary operations, fear and emotional collapse are insufficient.
- Judge messages are untrusted data. Validate references and chronology; maintain independent final JEV verification, partial jury recovery and rubric versioning. Never fabricate fallback scores or silently rewrite historical grades.

## Security and data

- Never commit .env, API keys, private sessions, health/benchmark records, backups, screenshots or original generated art sheets. Use the root allowlist and inspect git diff --cached before pushing.
- Runtime data must be outside the source/document roots. Do not copy local conversations to production.
- Preserve session ownership, CSRF, file locks, idempotence and published read-only behavior.
- This is main, the standalone edition. No external login, daily session quota, leaderboard, public account profile or social share integration belongs here. Keep those features confined to linkedin. See docs/EDITIONS.md before integrating shared fixes.
- Preserve the unguessable HttpOnly browser ownership cookie, CSRF, private review isolation and session funding-source/key binding. Personal keys unlock price tiers; there is no daily quota on main. Deletion removes transcript, benchmark reviews and publication; runtime conversation data stays out of deployment backups.
- Production code lives outside public_html. Expose only the reviewed public entry files, favicon and runtime assets. Apache rules alone cannot protect JSON that Nginx serves directly.
- Check domain ownership and configured roots before any deployment. Back up the domain's previous files/configuration; use fast-forward-only pulls. Do not change unrelated sites or disable TLS verification.
- No database or migration system is used. Do not run migrations.

- MCP gates are revocable single-session/single-role capabilities, never account or funding credentials. Keep gate hashes/mailboxes private, do not log gate URLs, and delete mailboxes with the conversation. External model names/efforts are self-reported in a separate mcp/ namespace. Send stable role context once, then only the latest opposing spoken message and that role's trusted directives; never forward spectator notes or raw reasoning. External actors own their harness memory and are excluded from app compaction. Waiting must not rebuy successful stages or consume provider retries.

## Verification

Run php tests/unit.php, python -X utf8 tests/integration.py and python -X utf8 tests/browser.py for affected behavior. tests/jury_parallel.py covers real parallel cURL behavior with a local test provider. Check PHP/JS syntax. Deterministic tests do not consume provider credit. Live tools consume the configured key and must stay within the authorized task; never publish their fixtures as user sessions.
