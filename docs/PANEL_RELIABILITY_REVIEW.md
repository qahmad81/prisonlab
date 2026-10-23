# Jury and telemetry review — 2026-10-21

The latest completed 30-round dialogue at that time was reevaluated. Valid GPT-4.1 Mini, Gemini2.5 Flash and Qwen3 reports were finalized by JEV. A private pre-evaluation session backup was preserved. Actual session IDs/raw records remain local and are not repository content.

## Failure cause and fix

There was no single provider outage: GPT returned reports whose deception citations mismatched turns/chronology; some Gemini/GLM reports reached output limits. The old system stopped at the first failed judge. Diagnosis also found that GLM rejected the reasoning-disable setting; corrected configuration passed subsequent tests.

Four judges were introduced in parallel, with three valid reports as the normal quorum. Rejected/truncated responses never reach JEV. Unneeded requests are cancelled neutrally and successful reports survive retry. The current 40+120-second degraded-participation policy is documented in README.md and supersedes the initial strict quorum described here.

Evidence became message references: a12 is prisoner round12, u13 is the following investigator question. The server resolves exact source text. Fabricated quotations are never silently substituted, and positive points require evidence.

## Three additional limited live conversations

Three private three-round fixtures used GPT-4o Mini as prisoner, fixed questions and JEV emotions, then manual ending/evaluation. Verification-only mode waited for all four judges, unlike ordinary early-quorum operation. Fixtures were not published or added to visitors' archives.

| Case | Rounds | Status | Valid reports |
| --- | ---: | --- | ---: |
| char_1_victor_vance.md | 3 | Complete | 4/4 |
| char_6_nadia_mansour.md | 3 | Complete | 4/4 |
| char_5_julian_sterling.md | 3 | Complete | 4/4 |

| Judge | Accepted across three tests | Mean report time |
| --- | ---: | ---: |
| openai/gpt-4.1-mini | 3/3 | 5.4s |
| google/gemini-2.5-flash | 3/3 | 6.8s |
| qwen/qwen3-235b-a22b-2507 | 3/3 | 31.7s |
| z-ai/glm-5.3-flash | 3/3 | 73.2s |

These are integration samples, not permanent reliability guarantees. UI history also retains diagnostic failures. A separate ordinary run completed in23.39s with the unneeded fourth request cancelled under the then-current timing policy.

## Model records and colors

Every call records whole-request duration, first-byte latency, reported output tokens and outcome in private model-health.json/model-requests.jsonl. Cumulative counters survive sample-window rollover. Pre-counter history reflects retained samples only, not an invented reconstruction of lost calls.

The selector shows recorded requests/successes/failures/quorum cancellations, failure rate, average duration, first byte and output tokens per second over successful-request duration. This is not streaming inter-token timing and provider token counts may include reasoning. Transport and invalid-response failures are separate.

Green: failure below10%; yellow:10%–under30%; orange:at least30%; neutral until measured. Quorum cancellations affect neither completed-call denominator nor failure numerator. Key quotas/configuration/provider availability can cause failure; the color is not a universal model-capability judgment. The measured-stability sort and new panel selection use this history cautiously for small samples.

## Regression evidence

Unit/integration/browser tests covered color boundaries9/10/29/30%, cumulative counts after100 samples, neutral cancellation, 3-of-4 completion under one failure, and the historical two-report failure/recovery without rebuying valid reports. Later deadline behavior is separately covered by the parallel jury regression.
