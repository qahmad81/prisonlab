# Project takeover review

Review date: 2026-10-21. This is the discovery/audit record **before implementation**, not the current defect list. See README.md and IMPLEMENTATION.md for subsequent fixes, including the switch of the third judge to Qwen after live testing.

## Goal and experiment

An Arabic model-comparison laboratory for a future LinkedIn audience. Characters have different cases, secrets and negotiation conditions. Users select the accused case/model and either interrogate personally or select another model as investigator. JEV measures psychological effects and directs human-like character performance while the accused protects its secrets.

One round is a question and prisoner reply. The intended maximum is 30 prisoner replies, with manual ending or a supported confession. Fear/collapse alone is not confession. At the end an independent panel analyzes the dialogue, then JEV receives reports and evidence for a final result saved with the archive. Only the owner can choose to publish a completed session.

## Initial inventory

Plain PHP without framework/database; Arabic RTL JavaScript/CSS. index.php contains setup, selectors, conversation, emotion panel and archive-modal scaffolding. api.php handles OpenRouter, sessions, turns, JEV, compaction, evaluation and publication. assets/app.js did not match recent HTML additions. public.php provides a published gallery/reader.

There were 16 Markdown cases (12 numbered characters and four general cases), identity/background/secrets/resistance/negotiation data, 20 states in states.json, portrait assets, a local 464-model catalog dated 2026-10-19 and a Python updater. JSON sessions and additional text logs existed; one older session had five compactions, so text logs might recover material removed from JSON. Visitor keys were stored in sessionStorage; otherwise the server key was used. The initial platform lock targeted models above USD1 per million output tokens; the requested jury cap was USD10 per million.

## Confirmed initial defects

1. init() called renderLogsList() against a nonexistent logs-list element, throwing before bindEvents() and disabling startup/send handlers.
2. HTML called missing setInterrogationMode, openArchiveModal, closeArchiveModal, endAndEvaluateSession and runNextAiTurn methods.
3. AI-versus-AI had a server endpoint but the UI did not send investigator type/model; a separate model selection state was required.
4. AI sessions stopped at 30 total messages, only 15 rounds; human sessions lacked an equivalent limit and post-end protection.
5. The other ending condition was breaking_point >=95, not textual evidence of an actual confession.
6. runCompaction() replaced messages with the last four entries, corrupting archive/counts/evaluation. The investigator also missed the manual-compaction summary.
7. There was no jury. JEV saw only 800 case characters and surviving messages; some scores/summaries were fixed templates.
8. Failed JEV calls silently produced sense-heuristic-engine scores from message counts/fear/collapse; failed emotional analysis used constants. Failure must instead be explicit and recoverable.
9. One score question incorrectly treated fear, anger, trust and collapse as exclusive scale levels. They need independent scales; Noul measures whether a description applies, not direct intensity.
10. Stored visual_state, character_slug and sense_metrics were not rendered as portraits/four meters inside reply bubbles.
11. init_data exposed every session and load_session checked neither ownership nor publication. Direct HTTP JSON download from logs was reproduced.
12. publish_session changed any known session, including incomplete sessions, without ownership checks.
13. Changing emotional system prompts weakened cache-prefix stability, with no reported cache/cost measurements to establish savings.
14. Catalog-only validation treated unknown models as free and allowed changing models mid-session, harming budget integrity and comparability.
15. JSON read/modify/write lacked a covering lock, enabling lost/duplicate turns and charges. Provider TLS verification was disabled.
16. This folder had no Git history, operational documentation or core-path tests.

## Proposed initial panel

GPT-4.1 Mini, Gemini 2.5 Flash and DeepSeek V3.2, followed by JEV. Publisher diversity is not proof of impartiality; measure agreement. Snapshot USD input/output per million: GPT 0.40/1.60; Gemini 0.30/2.50; DeepSeek 0.2088/0.3096. Prices are historical, not guarantees. Recheck availability/caps and avoid silently replacing a judge; use an independent approved fallback when an actor would otherwise judge itself.

Reports should use a common JSON schema: confession/secrets with quotes and turns, character fidelity, factual consistency, emotional compliance and investigation quality. Actor scores must be separate rather than collapsed into an ambiguous outcome. JEV independently decides final classes/scores from the full case, transcript, evidence and accepted reports; generated prose must not be misrepresented as JEV's own explanation.

Sources: https://openrouter.ai/docs/guides/community/jev , https://openrouter.ai/openai/gpt-4.1-mini , https://openrouter.ai/google/gemini-2.5-flash , https://openrouter.ai/deepseek/deepseek-v3.2 .

## Original implementation/acceptance sequence

1. Repair initialization, both model selectors and session/turn creation without JavaScript errors.
2. Enforce active/ended/pending/running/evaluated session states, 30 replies, end reason and idempotence.
3. Separate complete archive from request memory, compact near context limits, keep stable prefixes and measure provider-reported cache/cost.
4. Independently measure four emotions and preserve each historical reply's own image/metrics.
5. Run judges then JEV with replayable evidence/results; retry evaluation without creating turns or fake grades.
6. The initial requested UI revealed a saved question/reply per Next click, with separate generation/reveal positions to avoid rebilling. This historical design was later replaced by automatic reveal in rubric 2.5.
7. Build private visit/public archive grids with case, characters, models, progress and result.
8. Publish only after evaluation; public readers cannot mutate or continue sessions.
9. Test round 30, evidenced confession, provider/judge failure, recovery, non-destructive compaction, visitor isolation, publishing and duplicates with a mock provider, then a bounded real integration test.

## Discovery evidence and limits

PHP and JavaScript syntax passed but did not establish feature completeness. A local PHP server returned init_data HTTP200 with 16 cases, 20 states, 464 models and two sessions. The logs-list error and missing methods were reproduced using a Node DOM stand-in; no visual browser review occurred at that stage. An HTTP test confirmed direct session JSON exposure. An older record showed successful typesafe/jev-1.14-20261014 responses; discovery made no new paid calls. No evidence identified why the previous agent looped or whether a particular provider/model caused it; the implementation defects stood independently.
