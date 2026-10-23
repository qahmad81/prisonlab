# Implementation and verification — 2026-10-21

Historical record of versions 2.0 and 2.1. Current 2.5 behavior is documented in README.md.

## Version 2.1 after user testing

- Both actor selectors gained paid economy, professional, all and free tabs, combining release recency and seven-day OpenRouter usage, with source metadata and separate sort options.
- Orange warnings reflected measured failures or short published limits, with actual first-byte/request timing and throughput; unmeasured models received no invented metrics.
- Visible conversation notices and a fixed toast replaced hidden notices. Transient calls used three attempts, retrying after 10/20 seconds. Resume investigation preserved completed stages/rounds.
- Thinking was removed from API, private/public displays, investigator requests and compaction. The state atlas was hidden and emotion details rendered in Arabic rather than raw JSON. Later AI-only fictional spectator notes are a separate feature; raw provider reasoning remains hidden.
- Compaction processed spoken text only, preserved identity/case/directives and accumulated turn-linked quoted notes.
- Confession detection became cumulative with decision history and evidence. A failed check after saving a reply could retry independently without buying another answer.
- Detection scoring distinguished using pressure from exposing deception; each observed opportunity required a prisoner source and later investigator response. Untested axes had no invented grade and disclosed coverage/weights.
- Unit/integration/browser tests covered three failed attempts then recovery, 10000/20000ms waits, note isolation, immutable identity, retained memory and rejection of fabricated evidence.
- A bounded real three-judge/JEV test completed. Report contracts clarified that secret-discovery grades remain numeric, while untested axes may be null; zero scores/untested axes do not need evidence proving points never awarded. Positive points still require evidence. HTTP429 and incomplete provider responses were made recoverable with completed stages preserved.

See LAST_SESSION_REVIEW.md for the complete human dialogue reviewed at that time.

## Initial version 2.0 implementation

Initialization, mode selection, ending, archives and publishing were wired. Actor models were fixed per session. Background AI generation originally used Next to reveal saved pairs without regeneration; 2.5 now reveals automatically. Both original modes were capped at 30 prisoner replies, with manual or evidenced-confession ending rather than collapse alone.

Prisoner prompts demanded plausible arguments and alternative explanations within character facts, consistent resistance and JEV-directed human emotion. Investigators were directed to logical, emotional and moral deception supported by evidence. Four separate JEV questions measured fear/anger/trust/exhaustion, saving the directive, target state, portrait and metrics on each reply.

Full archives were separated from compaction context and automatic compaction deferred toward context capacity. Reported cache/cost usage became visible. The original three-judge panel used GPT-4.1 Mini, Gemini 2.5 Flash and Qwen3 235B, with fixed fallbacks for actor conflicts and catalog/request input/output caps of USD10 per million.

Nine-axis judge reports carried literal quotes/turns, then JEV determined final axis scores. Prisoner emotion contributed 30%; investigator scores covered logical, emotional and moral deception. Weights were visible. Invalid quotes/incomplete axes were rejected with one correction. Successful turn/evaluation stages survived provider failures; no scores were invented.

Visit-private and published grids, a shared reader, per-reply portraits/emotions, results and share links were added. Ownership cookies, CSRF, atomic writes, per-session locks, idempotent requests and explicit read-only publishing were implemented. Data moved outside the public root; settings/source/logs were HTTP-blocked. Ownerless legacy records and pre-change backups remained private.

## Verification

1. PHP/JS syntax checks passed.
2. Isolated HTTP tests covered both original modes, 30-round limit, confession, visitor isolation, CSRF, blocked files, non-destructive compaction, duplicates, read-only publication and continuation of partial evaluation.
3. Playwright covered human/AI flows, evaluation/publishing/archives/public reader, 30 generated rounds, four reply metrics, nine axes, 27 fixture evidence records and no JS errors/mobile overflow. The then-manual reveal behavior was tested as historical behavior.
4. A limited real test used Gemini 2.5 Flash Lite, JEV emotions/confession, actual GPT/Gemini/Qwen reports and final JEV1.14. One quotation needed correction before acceptance.
5. DeepSeek V3.2 timed out twice during diagnosis; the configured third judge changed to Qwen. Qwen also exceeded the initial short timeout, then succeeded with faster-provider preference and 180-second judge timeout. Ordinary session retries do not silently replace failed judges.

Live outputs/screenshots are excluded from Git and were never automatically published. The 30-round regression used mock output, not 30 paid rounds.

## Operational limits

AI generation needs an open page; no durable worker exists. Ownership is a visit cookie, not an account/recovery system. Compaction estimates UTF-8 conservatively rather than using a provider-specific tokenizer. Cache behavior remains provider-dependent. Judging can take minutes and must expose recoverable failure. Public hosting needs a real web-server configuration, not the development server. Later jury deadlines/quorum rules supersede the initial three-judge implementation.
