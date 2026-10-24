# External actors over MCP

The standalone and LinkedIn editions support session-scoped external actors. JEV, confession/closure checks, context compaction for the remaining OpenRouter actor, and independent judging still use the test's existing OpenRouter funding binding. Account authentication and quotas in the community edition are unchanged.

## Operator workflow

1. Choose the human/AI role arrangement. Open either AI model picker and choose **MCP** at the top.
2. Enter a model/agent name and the thinking effort actually configured in its harness. These are self-reported, not remotely verified. Configure one or both AI roles this way.
3. Start the test. Expand its MCP gate panel, and copy each role's private HTTP MCP URL into the appropriate harness. The URL is a bearer capability; never publish it or put it in a shared configuration/repository. It expires after 24 hours. Renewing a gate invalidates the previous URL while retaining its current task. Revocation pauses that role until a new gate is created.
4. Tell the agent to call `prisonlab_wait`, enact its fictional assigned character in Arabic, and send the requested JSON object with `prisonlab_reply`. It should keep calling wait after each response until `ended`.
5. Keep the owner's browser page open. It drives paid JEV/provider/jury stages and reveals saved rounds automatically. Closing it pauses progression, without losing a task or submitted response. Reopen the private session and resume; renew the URL if the harness no longer has it. Gate secrets are not recoverable from the stored hash. The UI masks them and retains generated URLs only in page memory.

The UI provides **stop waiting**, resume, and explicit end/evaluate controls. A waiting external actor does not count as a failed provider retry. A complete prisoner response is still one round, with the existing 30-round ceiling and contextual 5–15-round closure policy.

Do not launch a second agent against the same role: the first accepted response to a task wins. Identical response replays are idempotent; conflicting answers and old/wrong-role task IDs are rejected. Local model costs/tokens cannot be measured by the server and are never fabricated. All verified provider charges remain visible separately. External model IDs use the `mcp/` namespace, separate from authenticated OpenRouter model IDs; self-reported labels must not be treated as provider attestations. The jury tier counts the site's price for external actor calls as zero, while including any OpenRouter actor's actual catalog output price.

## Transport and tools

`mcp.php` implements the stateless JSON response form of Streamable HTTP with JSON-RPC 2.0. It supports the initialize-era MCP revisions `2025-03-26`, `2025-06-18`, and `2025-11-25`, `initialize`, initialized/cancelled notifications, `ping`, `tools/list`, and `tools/call`. It does not expose sampling, arbitrary HTTP requests, filesystem access, account administration, publication, funding credentials or judge controls. Unsupported GET streams and DELETE return 405. A newer auto-negotiating client can fall back to the initialize-era protocol; this is not an implementation of the 2026 discovery-era wire protocol.

Authentication is the role-specific capability in `?gate=...`; `Authorization: Bearer ...` can carry that same complete gate value instead, avoiding query credentials in client/proxy logs. No browser login cookies or CSRF credentials need to be copied to the harness. Browser actions creating/revoking a gate still require the session owner's authenticated cookie and CSRF token. Origin headers, when present, must match the configured application origin. The endpoint never follows a caller-supplied URL or initiates calls to a local provider. Only the harness connects outward to PrisonLab.

- `prisonlab_wait({"wait_seconds":15})`: waits up to 15 seconds without holding the conversation lock. Repeat on `waiting`. Returns `task` with `id`, role, round and messages, or `ended`. Revocation/expiration is checked throughout waiting.
- `prisonlab_reply({"task_id":"...","output":{"question":"...","expression":"serious"}})`: investigator output. A prisoner instead sends `{"reply":"...","state_id":"calm"}`. `inner_thought` is an optional short fictional spectator note, never raw chain of thought. Only these fields are accepted into the dialogue pipeline. The normal measured spoken-output limits and jury penalties still apply. An overrun causes a new corrective task, without repeating completed investigator/JEV stages.

The first task contains the stable role, character, case, output rules and initial context. Subsequent tasks send only the latest opposing spoken message, the role's current trusted directives/counter, and any output correction. A prisoner receives its current JEV directive; the investigator never receives that private directive or spectator notes. No raw provider reasoning or opponent spectator notes go to either gate, JEV, judges or compaction. The harness maintains its own growing history; there is no app-driven compaction for an external actor. A remaining OpenRouter actor retains normal independent context management. Both-external sessions disable compaction entirely. Do not restart the harness mid-test unless it can restore its own context.

## Optional stdio adapter

For a harness that accepts only a local stdio MCP server, run `python /absolute/path/to/tools/mcp_stdio.py` with `PRISONLAB_MCP_URL` set in that process's private environment to the copied gate URL. No third-party package is required. The adapter forwards JSON-RPC lines to the remote endpoint and writes only JSON-RPC responses to stdout. It requires HTTPS, except loopback HTTP for isolated development, refuses redirects, and never prints credentials in errors. Store neither the gate nor environment in a public MCP configuration.

## Storage, deployment and recovery

Private per-session/per-role mailbox JSON files live under `LAB_DATA_DIR/mcp`, outside the source and document roots, with 0700 directory/0600 file permissions. They contain only a gate hash, expiry, connection time and the current role task/response. Stable lock files are never unlinked while waiters might use them. Conversation deletion erases mailbox contents and files in addition to the transcript, reviews and publication. Publishing prevents any further gate access. Neither gate secrets nor mailbox data appear in public session/leaderboard payloads. Mailboxes must be excluded from deployment backups along with all account/session data. Query-string gate URLs are credentials: production domain access logs must omit MCP query strings, or disable MCP endpoint access logging. Errors must not log request bodies or raw URLs.

The public publisher/router allowlist includes `mcp.php`; implementation files and mailbox JSON remain private. Production uses multiple PHP-FPM workers so an agent's 15-second long poll does not block the browser making progress. PHP's development server is single-threaded: use `wait_seconds:0` with a client-side 15-second retry for local manual testing, or run a multiworker local server. No background paid worker or database migration is introduced.

Verification: `php tests/unit.php`, `python -X utf8 tests/integration.py`, `python -X utf8 tests/browser.py`, `python -X utf8 tests/mcp.py`, and `python -X utf8 tests/mcp_browser.py`. Community/account suites also run for the LinkedIn edition. These use isolated storage/mock providers. Never copy test mailboxes or local transcripts to production.

Protocol reference: [official MCP 2025-11-25 HTTP transport](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/main/docs/specification/2025-11-25/basic/transports.mdx).
