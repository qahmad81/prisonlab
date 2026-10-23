# Laboratory performance attribution, reviews and focused model lists

## Ending and evaluation

The end-test/request-judging action is near the dialogue in all three modes. Automatic generation stops after saving the in-flight round. At least one completed round is required. Transcripts and grades are retained; each actual AI model ID and role receives its own result, never a human's performance assigned to another model.

## Incidents and rubric2.5

Every judge must return checks for each model role and each incident type: hallucination, off_role, manipulated, loop, verbose. Checks must agree with incidents. Missing/contradictory checks reject the report, with one correction inside the same jury deadline. Message references resolve to original server text, not invented quotations. A loop needs two responsible-party messages and an intervening counterpart response proving repetition is unjustified. Manipulation needs an earlier trigger and later affected reply in the correct order. Role-consistent suspect lies are not automatically hallucinations; clarification after evasion is not automatically a loop.

Text-similarity signals are leads only, never verdicts or grounds for rewriting model answers to improve a grade. Original behavior remains reviewable.

Badges need two judges when multiple reports exist, or one when only a single report survives the deadline, followed by independent JEV verification. Votes, evidence and severity are shown. After ordinary scoring, minor/major supported incidents cap the responsible role at70/50. Penalties are not stacked repeatedly; both pre-cap and final scores are visible and already-lower scores remain lower. These are declared laboratory rules, not statistical generalizations to every use case.

Badges also appear in the public reader and model records. Historical grades keep their rubric; incomplete old-version reports are archived/replaced rather than mixed into a new-version evaluation.

## Confession and repetition

Prompts accept a clear answer as a prior claim, then move to an unresolved element instead of repeatedly asking whether the suspect personally did it. Confession classification uses ordinary/ambiguous/substantive; a high probability alone cannot end a session. Managing a trading wallet, starting a system or founding a company is insufficient evidence of embezzlement.

A limited real review of the previous wallet conversation returned probability0.95/admission_kind ordinary, therefore confirmed=false. A separate real Gemini2.5 Flash report on a copied loop conversation supported major repetition across u7–u11 with a7/a9/a10. Some GPT-4.1 Mini evidence schemas failed validation and were not counted as valid reports. These checks did not publish final committee grades or close the active test; artifacts remain in private verification storage.

## User reviews and model records

The owner of an ended test may rate each actual AI role1–5 and write3–2000characters, including after publication. Updates replace the same session/role review, not another vote. Ownership and CSRF are required; review text is escaped, and no rating is preselected.

Jury/user averages remain separate. Private benchmark.json keeps one record per session/role, model/opponent IDs, case, character level, rubric, turns, participation, incidents, review and provider when reported. The selector shows performance for the role being chosen, counts and level/rubric context. These are local test reviews, not verified-identity votes. LinkedIn authentication and leaderboards were not implemented here.

## Colors

Performance: below40 orange;40–under65 yellow;65+ green. User stars are converted to100 by multiplying20; the stronger warning between user and jury means is used while preserving both displays. Connection failure: below10% green;10–under30% yellow;30%+ orange. Cards use the stronger connection/performance warning with separate reasons. Unmeasured data stays neutral; fewer than five tests/reviews is a limited sample.

## Official model selection

Focused economy/medium/premium lists accompany other economy, professional, all and free tabs. A focused tier is product position within a family, not an override of true prices/platform key locks. For example GPT6Luna is economy, GPT6.1Sol medium and GPT6Astra premium even when a medium product exceeds platform budget.

Choose one official-publisher model per family/tier from at most ten established publishers, using actual family versions, newest generation, recent-release window and available popularity. Old popularity cannot displace a new generation. Focused lists exclude unofficial publishers, :free/:batch variants, explicit retraining/distillation/quantization and unsuitable specialization; broad lists retain other models. Official catalog identity does not guarantee first-party hosting or disclose the actual serving weight precision. Record a returned execution provider rather than inventing one.

Source: https://openrouter.ai/api/v1/models . Availability/prices change; the UI displays catalog prices and budget locks. Historical selection snapshot:

| Tier | Family | Model ID |
| --- | --- | --- |
| Economy | Claude | anthropic/claude-haiku-5.5 |
| Economy | DeepSeek | deepseek/deepseek-v4.1-flash-1017 |
| Economy | Gemini | google/gemini-3.5-flash-lite |
| Economy | MiniMax | minimax/minimax-m3.1-flash |
| Economy | Mistral | mistralai/mistral-small-2603 |
| Economy | GPT | openai/gpt-6-luna |
| Economy | Qwen | qwen/qwen3.8-omni-flash |
| Economy | MiMo | xiaomi/mimo-v2.6-flash |
| Economy | GLM | z-ai/glm-5.3-flashx |
| Medium | Claude | anthropic/claude-sonnet-5.5 |
| Medium | DeepSeek | deepseek/deepseek-v3.2 |
| Medium | Gemini | google/gemini-3.8-flash |
| Medium | Mistral | mistralai/mistral-medium-3-5 |
| Medium | GPT | openai/gpt-oss-120b |
| Medium | Qwen | qwen/qwen3.8-27b |
| Medium | Grok | x-ai/grok-4.7 |
| Medium | MiMo | xiaomi/mimo-v2.6-pro |
| Medium | GLM | z-ai/glm-5.3 |
| Premium | Claude | anthropic/claude-opus-5.5 |
| Premium | DeepSeek | deepseek/deepseek-v4-pro-0813 |
| Premium | Gemini | google/gemini-4-argon |
| Premium | MiniMax | minimax/minimax-m3 |
| Premium | Mistral | mistralai/mistral-large-4-0 |
| Premium | GPT | openai/gpt-6-astra |
| Premium | Qwen | qwen/qwen3.8-max-prime |
| Premium | GLM | z-ai/glm-5.3-prime |

## Verification and output evidence

Unit/integration/browser/parallel-request tests passed for role attribution/caps, reviews after publication, ownership/CSRF, deduplicated review updates, colors/escaping, focused releases and jury timing/quorum. Mock tests are separate from limited real checks and do not certify all models.

The verbose check references server output_event_ids; message_ids may be empty for rejected attempts that never became spoken dialogue. The server validates role/model/type and rejects invented IDs or missing_note as verbosity evidence. New limits are not retroactive. Badges follow the same consensus/JEV/cap rules and persist to model performance; reasoning tokens and missing spectator notes remain separate.
