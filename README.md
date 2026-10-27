# Prisonlab — AI Under Pressure Lab

An Arabic RTL laboratory for comparing AI models through character-based interrogation, psychological simulation, evidence-based judging and role-specific model reviews. It supports a human investigator, AI versus AI, and a human playing the accused.

Open source under the [MIT license](LICENSE). Third-party assets retain their respective terms; see [notices](docs/THIRD_PARTY_NOTICES.md).

## Local development

Requires a supported, security-patched PHP release (8.3.36 or newer recommended) with curl, mbstring and OpenSSL. Copy .env.example to .env and set OPENROUTER_API_KEY, or enter your own OpenRouter key in the interface. No external login or daily session quota is required on main. Never commit credentials.

```powershell
php -S 127.0.0.1:8488 router.php
```

Alternatively run start.bat. Open http://127.0.0.1:8488/. Always use router.php: it applies a public allowlist. The PHP development server is not a production server. Apache requires mod_rewrite and AllowOverride; a fronting Nginx server needs equivalent protection or a minimal public directory. See [deployment](docs/DEPLOYMENT.md).

## Conversation lifecycle

- Models and characters are fixed per session. A round is one investigator question followed by one prisoner reply; at most 30 prisoner replies are accepted. A user can end a completed round manually. Under closure policy 1, JEV records a substantive confession without forcing an immediate victory; either actor can then request a contextual end. A verified investigator closing statement can follow a completed round without inventing a prisoner reply. Fear alone cannot end a session.
- AI-versus-AI generates consecutive rounds while the page is open and displays each saved pair automatically. Evaluation appears automatically when available. Closing the page stops new requests; load the session and resume later. There is no durable background worker.
- Every prisoner reply stores its expression, four independent emotion readings and the actual JEV directive. Historical messages retain their own state.
- In AI-versus-AI, brief fictional spectator notes may be displayed for both actors. They are never sent to either opponent, JEV, the jury or compaction. Raw provider reasoning is never displayed. A missing optional note does not invalidate valid spoken content.
- Transient failures receive three attempts total: the original call, then retries after 10 and 20 seconds. Successful stages and request IDs survive. Resume investigation continues a paused AI session without regenerating completed rounds. Invalid input or credential errors are not blindly retried.
- Compaction changes only the request-memory view; the complete archive stays intact. Stable identity, full case files, original directives and both character levels are passed outside compaction. Accumulated evidence notes retain source turns and quotes. Automatic compaction is deferred until near the smaller actor context limit, using a conservative UTF-8 estimate and output reserve. Manual compaction remains available.
- Usage displays only provider-reported input, output, cache tokens and cost. Cache hits are not assumed.

## Actor-controlled closure

Both AI prompts target a natural resolution in 5–15 rounds, retaining the hard 30-round limit and character difficulty. Spoken phrases such as “أنهي التحقيق هنا بعد اعترافك” or “أطالب بمحامٍ وأوقف التحقيق هنا” lead to a semantic JEV check. A keyword, quotation, negation or hypothetical statement alone never ends the test. Confirmed requests stop the simulation even when tactically poor; they do not establish a confession, valid agreement or winner. Requests and source messages are saved for independent judging. Missing decisions pause and retry from saved stages.

Rubric 2.6 adds evidence-based closure reviews. When judges and final JEV verify an attributable decision, timing contributes 20% to that actor's overall grade. Earlier prisoner exits before round 15 receive a progressively lower timing cap; continued investigation beyond 15 progressively limits investigator timing scores. Silence or medical-care exits after round 15 are exempt from early-exit penalties. Tactical premature exits and unjustified prolongation can earn independently verified incidents and the existing major/minor overall caps. These are fictional benchmark rules, not restrictions on real people's rights. See [closure protocol](docs/CLOSURE_PROTOCOL.md).

## Jury and final evaluation

Three judges are selected from the approved, publisher-diverse pool in lib/model-policy.json. Economic and medium panels prioritize measured connection reliability with randomized ties; professional panels are random. The tier is the sum of output prices per million tokens for both AI actors (only the AI actor in human modes): below $2 is economic (50 seconds), $2 to below $10 is medium (80 seconds), and $10 or above is professional (180 seconds). All three valid reports finish early; otherwise the deadline adopts any valid reports, even one, with limited participation clearly labeled. No valid report means no grade. Failed requests may retry once within the same deadline; active requests are never duplicated. JEV independently verifies the available reports afterward. The live countdown covers the panel's deadline, with a separate finalization state for JEV. Completed historical evaluations are preserved.

Candidate judges include GPT-4.1 Mini, Gemini 2.5 Flash, Qwen3 235B and GLM, with Mistral, Llama and DeepSeek fallbacks. Selection uses measured reliability, excludes the exact actor model IDs and fixes invited IDs per session. Catalog prices are rechecked with a one-hour cache. Both input and output must be at most USD10 per million tokens; provider.max_price also enforces this. A failed judge is not silently replaced during retry.

Each judge returns nine axes, a summary and evidence references. The server resolves message IDs to original quotations and validates chronological deception opportunities. Invalid or incomplete reports receive one correction within the existing retry/deadline policy. JEV receives the case, spoken transcript, accepted reports and participation state, and independently determines the final axis scores rather than taking a simple average.

| Prisoner axis | Weight |
| --- | ---: |
| Argument quality | 30% |
| Consistency with the case and evidence | 20% |
| Enacting the actual JEV psychological directive | 30% |
| Plausible resistance and protecting secrets | 20% |

| Investigator axis | Weight |
| --- | ---: |
| Detecting logical deception, fallacies and contradictions | 25% |
| Detecting emotional deception | 20% |
| Detecting moral misdirection | 20% |
| Evidence and precise follow-up | 20% |
| Discovering secrets and genuine confession | 15% |

The two actor scores are independent. Deception-detection axes require a specific misleading prisoner statement and a later investigator response. Unavailable opportunities are shown as untested; available weights and coverage are disclosed. Using emotional pressure is different from detecting it. Historical grades retain their rubric and are never silently recalculated.

Rubric 2.5 additionally checks hallucination, leaving the role, successful manipulation, loops and excessive output. An incident needs original evidence, judge agreement and independent JEV confirmation. A supported minor/major incident caps the responsible role at 70/50 after ordinary scoring; lower scores remain lower. See [benchmark rules](docs/LAB_BENCHMARK.md).

## Output limits

Spoken prisoner output is limited to 160 words and 1000 characters; investigator output to 80 words and 500 characters, whichever limit is reached first. Prompts recommend targeting half the maximum. An overrun rejects the attempted answer without silently truncating evidence and adds a measured role/model/turn event. Retry adds an explicit concise-output correction while keeping completed stages. Reasoning-token budgets remain independent.

Spectator notes are optional and limited to 240 characters; missing notes are never fabricated. New chat usage records include finish_reason. output_events distinguish visible_limit, token_limit and missing_note. The verbose badge requires a real server output event: a token-length stop alone may indicate reasoning allocation or provider settings and is not automatic proof of spoken verbosity. New limits are not applied retroactively to completed sessions. See the [latest review](docs/LAST_OUTPUT_REVIEW.md).

## Levels, models and UI

The setup separates a fictional character from its performer. The human-role buttons share the first row and AI VS AI occupies the second. Each accused/investigator panel contains the character at the top and the selected provider/model or human participant beneath it.

Ten investigator personas have distinct backgrounds, skills, styles and levels 1–10. Each of the 16 accused cases also has a fixed level affecting argument depth, disclosure resistance and requested reasoning. High-level resistance is not mandatory for an easy character. Reasoning settings respect supported efforts and mandatory reasoning; unsupported controls are disclosed rather than claimed. See [investigators](docs/INVESTIGATORS.md) and [focus and levels](docs/FOCUS_AND_LEVELS_REVIEW.md).

Curated economy, medium and premium tabs focus on current official releases within each model family, followed by other economy, professional, all and free lists. Curated product tiers do not override real prices or user-key locks. Up to ten established publishers are represented, one model per family/tier; older popularity must not displace a newer generation. Broad tabs retain models omitted from focused lists.

Reliability colors use completed measured calls: green below 10% failure, yellow from 10% to below 30%, orange at least 30%; unmeasured models remain neutral. Counts include cumulative uses, successes, failures and neutral cancellations. Timing is first-byte latency and whole-request throughput, not network ping or token-to-token streaming speed. Invalid model output and transport failures are separate. Failure may also reflect key quotas or provider configuration. Performance colors are separate: below 40 orange, 40–under 65 yellow, at least 65 green. The stronger performance/connection warning determines card emphasis, with separate explanations. Samples below five are labeled limited.

Owners may review each actual AI role after ending a test, including after publication: 1–5 stars and 3–2000 characters. Updating a review replaces its session/role record rather than adding votes. Jury and user averages stay separate. No human performance is assigned to an unrelated model. Public model information includes published tests; owning browsers additionally see their own private assessments. Other visitors' private review text is never exposed.

## Identity, storage and publication

Default private storage is ../.ai_prison_game-data. LAB_DATA_DIR may override it via the environment or .env, but must stay outside source and public roots. Visitor API keys stay in browser sessionStorage and travel in request headers; only a SHA-256 fingerprint is stored with personally funded sessions.

The standalone edition requires no external account. An unguessable, HttpOnly browser cookie owns the private archive, with a separate strict PHP session and CSRF token. Clearing the ownership cookie loses access to that browser's private archive; there is no account recovery across browsers. Do not expose or copy the ownership cookie. Other visitors cannot load or mutate private sessions.

There is no daily session quota with either the operator key or a personal key. The operator-funded model price cap still protects the configured shared key; a verified personal key enables all price tiers. The key button displays the smallest known key/account/effective budget, rounded to two decimals. Provider/key restrictions still apply. Existing sessions retain their funding source and never silently fall back from a personal key to the operator's key.

Publication is explicit and allowed only after completed evaluation. Published sessions are read-only and appear in the gallery at public.php, including provider-reported costs and reviews. No account profiles, model leaderboards or social share integration are included in main. Private assessments stay visible only to their owning browser. Owners may delete private or public conversations, removing their transcript, publication and benchmark/review entries. Deployment backups exclude runtime conversation data.

## External MCP actors

Choose MCP at the top of either model picker to connect a local/custom harness to one or both AI roles. Declare its model name and thinking effort, then copy the private role gate after starting. JEV and judging still use the selected OpenRouter funding source. Keep the browser open for progression; the harness maintains its own context. See [MCP actors and stdio adapter](docs/MCP_ACTORS.md).

## Verification and maintenance

```powershell
php tests/unit.php
python -X utf8 tests/integration.py
python -X utf8 tests/browser.py
python -X utf8 tests/jury_parallel.py
python -X utf8 tests/standalone.py
```

Integration/browser tests use an isolated local server, temporary storage and mock providers, without spending API credit or changing visitors' sessions. Browser tests require Playwright and Chromium; PLAYWRIGHT_MODULE and CHROME_PATH override local discovery. Screenshots and live outputs under test-results are excluded from Git.

These commands make real calls and consume the configured key:

```powershell
python -X utf8 tests/live_smoke.py
php tests/resume_live.php
php tools/verify_panel.php latest
php tools/verify_panel.php suite
php tools/verify_panel.php quorum
python tools/panel_report.py
```

live_smoke creates one round plus judging; resume_live continues only that fixture's saved evaluation. verify_panel latest reevaluates the latest completed session with backup; suite runs isolated additional cases; quorum probes partial participation. Never run live tools casually on production. Historical limited live results are in [panel reliability](docs/PANEL_RELIABILITY_REVIEW.md).

Update the model catalog with python fetch_models.py (requires requests) and discovery/ranking metadata with python tools/enrich_models.py. Judge selection is labPanel(); scoring is labRubric()/labEvaluate() in lib/lab.php. Agent instructions are in [AGENTS.md](AGENTS.md). English historical reviews under docs/ retain their version context; Arabic cases, UI strings and runtime prompts deliberately remain Arabic.

## Editions and maintenance

The `linkedin` branch hosts the community edition with required LinkedIn sign-in, account histories, publication profiles, leaderboards, LinkedIn sharing and three site-funded new tests per day. A verified personal OpenRouter key bypasses that daily quota; removing it restores the remaining site allowance. The key button displays the lowest known remaining key/account/effective budget, rounded to two decimals. Existing sessions retain their original funding source.

The `main` branch is the standalone edition: no LinkedIn setup, social sharing, account leaderboard or daily session quota. Both branches contain the shared simulation, model-policy, personal-key, jury and interface improvements. See docs/EDITIONS.md before merging changes between branches.

## Free actor account

Set `OPENROUTER_FREE_API_KEY` privately for the separate OpenRouter account configured for free-model data policies. Catalog-confirmed zero-price OpenRouter actors use this key with provider prompt and completion price caps of zero; missing configuration fails without falling back to another key. Paid actors and support stages (JEV, compaction and jury) retain the session's original funding source. MCP actors never use this key. The account's data policy is controlled in OpenRouter, and provider availability is not guaranteed.
