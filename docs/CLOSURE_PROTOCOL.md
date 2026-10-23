# Contextual actor termination — rubric 2.6

## Lifecycle and prompt rules

New sessions freeze closure policy 1. Unfinished sessions acquire it on their next actor turn, with an explicit activation round; completed evaluations remain immutable. The investigator, prisoner, jury and final JEV receive consistent rules. Stable role instructions stay outside compaction; the changing round count is appended after the growing history to preserve its cache prefix.

5–15 completed rounds is a natural-resolution target, not a forced deadline or minimum. A genuine solution before five is valid, and unresolved cases may continue to the hard limit of 30. Character-level resistance and emotional simulation remain binding. A detected substantive confession is still recorded, but under this policy actors have the opportunity to acknowledge the result and choose a timely close instead of receiving an automatic win.

An Arabic spoken cue triggers a separate JEV choice over the actor's own current text and spoken history. JEV distinguishes current intent from quotations, denials, hypotheticals and future promises. It classifies intent, not whether the claimed confession/agreement is true. Valid classes are investigator confession/agreement/closure/withdrawal and prisoner counsel/silence/medical/withdrawal. Stop decisions retain the original quote, source message ID, role, turn and actual completed-round count. They cannot be injected through spectator notes or provider reasoning.

A confirmed stop is honored even if it is a poor tactic. There is no automatic winner. An investigator closing statement after at least one completed round is saved as a final canonical user message, without purchasing or fabricating an additional prisoner answer. In the first round the pair completes before semantic closure review. A prisoner stop follows their completed answer. Reverse mode can close directly after the AI's final statement without requiring an extra human answer. Evaluation accepts the original final investigator message as evidence without counting it as a prisoner reply.

Successful intent checks are cached by immutable source ID. Failed checks retain the generated question, completed replies and funding source; retries do not regenerate saved stages. Existing owner locks, request-ID idempotence, CSRF and read-only publication protections still apply. Manual end remains available. The existing retry-confession API also resumes an interrupted stop check.

## Attribution and scoring

Judges submit closure_review for each AI role with observed, score, source message IDs and an explanation. The server resolves quotations from the transcript, requires actor-owned evidence and rejects invented or inapplicable decisions. A user's manual End button does not become an AI exit. Continued AI investigation beyond 15 must be reviewed. At least two judges agree when available; with one valid panel report, that report supplies the candidate. Final JEV independently decides applicability and assigns a timing score.

For an applicable decision, the role score becomes 80% ordinary nine-axis role score plus 20% timing score, before incident caps. Timing is never fabricated when evidence or final JEV is unavailable. A prisoner exit before round 15 caps the timing component at round/15 × 100 (rounded); later early exits are less severe. If the AI investigator continues beyond 15 completed rounds, timing is capped at 70 in round 16, then five points less per additional completed round, down to zero. A justified quick solution can earn a strong timing score; an unsubstantiated claim of confession or agreement cannot.

Silence and medical-care exits strictly AFTER round 15 (round 16 onward) cannot receive an early-exit incident or timing penalty for that choice. This does not erase independently proven hallucination, inconsistency or other misconduct. Counsel requests remain subject to contextual evaluation. These are fictional simulation performance rules, not an assessment or restriction of actual legal or medical rights.

New incident types premature_exit and prolonged_interrogation require original evidence, judge agreement and independent final JEV confirmation. Premature_exit requires the actual recorded actor termination and its source message. Prolonged_interrogation applies only to an AI investigator with evidence after 15 completed rounds. Existing major/minor total-score caps (50/70) apply. Adjusted total scores and incidents flow into the model's benchmark history and overall performance colors. Full timing calculations and judge quotations appear in private and published result readers.

## Verification and limits

Deterministic tests cover investigator closure without an extra answer, reverse mode, unaccepted keyword cues, retry-stage reuse, duplicate request IDs, early-exit scoring/badges, round-15/16 boundaries, protected silence/medical exits, progressive caps, original closing-message references, automatic evaluation and UI rendering. They do not spend provider credits. A 5–15-round target is prompt guidance, not a guarantee of real-model behavior; observed outcomes remain subject to testing.

Actor calls keep their separate 300-second watchdogs. Additional semantic checks require outer API/FPM/proxy/browser budgets of 1200/1250/1300/1320 seconds, respectively, scoped to this domain. Successful stages are durable even if an outer request fails. No account histories, secret keys or runtime telemetry are part of code deployment or configuration backups.
