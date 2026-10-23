# Agent guide

Prisonlab is a PHP 8.1+ / vanilla JavaScript Arabic RTL benchmarking application. Read README.md and docs/DEPLOYMENT.md before changing behavior or deploying. Historical reviews in docs/ describe the version at their stated date; current code and rubric 2.5 take precedence.

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
- LinkedIn is a future integration. participant_profile is currently null; never treat client-supplied names as authenticated identity.
- Production code lives outside public_html. Expose only the reviewed public entry files, favicon and runtime assets. Apache rules alone cannot protect JSON that Nginx serves directly.
- Check domain ownership and configured roots before any deployment. Back up the domain's previous files/configuration; use fast-forward-only pulls. Do not change unrelated sites or disable TLS verification.
- No database or migration system is used. Do not run migrations.

## Verification

Run php tests/unit.php, python -X utf8 tests/integration.py and python -X utf8 tests/browser.py for affected behavior. tests/jury_parallel.py covers real parallel cURL behavior with a local test provider. Check PHP/JS syntax. Deterministic tests do not consume provider credit. Live tools consume the configured key and must stay within the authorized task; never publish their fixtures as user sessions.
