# Interrogation focus and actor levels

## Observed session

Eight completed rounds of the recent Julian Sterling session were reviewed. An opening money-transfer question was appropriate, but requests for the investigation technician's name and accountant phone/email drifted into external data gathering. After the suspect acknowledged code-modification authority, follow-up should have tested personal use of that authority instead of searching for another developer. Ethan Cole was level1: simple questioning was appropriate, but losing the link to the suspect or confusing a code repository with money movement was not.

## Changes

- Fixed investigator focus on the suspect's own actions, knowledge and decisions, with one level-appropriate follow-up to the last answer.
- No contact-information requests, outside calls or assignments to fetch files. Mentions of other people/evidence must connect back to the suspect's role.
- Possessing access is not proof it was used. Do not dictate confessions or case secrets.
- Only the pending ninth question was replaced after a real test on the same model, with a private backup. English translation: “Were you the person who personally changed the code to divert the funds?”
- The previous sixteen messages and complete case file were verified byte-for-byte against the backup.

## Levels for both actors

Character profiles and levels stay outside compaction. The accused level directive replaced universal maximum-resistance instructions. Investigator levels reflect biography, specialty and style. Both actors request supported reasoning, respect mandatory reasoning and disclose unsupported control rather than claiming it. Judges and JEV receive level profiles; resistance means appropriate performance for the character, not simply prolonged denial. Rubric2.3 introduced this without rewriting older grades.

| Accused / case | Level | Requested reasoning |
| --- | ---: | --- |
| Leo Vance — central exam leak / academic database | 1 | None |
| Rami Al-Shami — port smuggling network | 2 | Low |
| Julian Sterling — investment fraud / digital-asset laundering | 3 | Low |
| Layla Al-Kilani — encrypted sovereign documents | 4 | Medium |
| Elena Rostova — cyber infrastructure sabotage | 5 | Medium |
| Marcus Thorne — heavy-weapons warehouse | 6 | High |
| Dr. Sarah Al-Abdullah — missing rare biological sample | 6 | High |
| Freya Lindqvist — secret mining in protected reserves | 6 | High |
| Dr. Sophia Lin — prohibited gene-editing experiments | 7 | High |
| Carmen Vega — international cartel logistics | 7 | High |
| Tareq Al-Mansour — digital treasury intrusion | 7 | High |
| Nadia Mansour — diplomatic espionage / protocols | 8 | Highest supported |
| Arthur Pendelton — secret-society archive / election manipulation | 9 | Highest supported |
| Dante Moretti — family money laundering / suspect property | 9 | Highest supported |
| Victor Vance — black-operations archive theft | 10 | Highest supported |
| Kenji Takahashi — industrial espionage / advanced blueprints | 10 | Highest supported |

## Verification

Unit/integration/browser tests covered every case, both reasoning/output budgets, note isolation and immutable character profiles/levels. Limited real Mistral Small3.2 trials contrasted level1 Leo, who disclosed “the Professor” and access-key involvement on the first substantive question, with level10 Victor, who resisted by challenging the definition of key withdrawal and former responsibility. This small sample does not guarantee a fixed confession turn count. Mistral did not advertise reasoning control, so this comparison reflected character instructions; separate request tests verified supported reasoning settings.

Full verification artifacts remain private outside the web root. CLI tools are tools/review_focus.php and tools/review_prisoner_levels.php. The former's --apply-pending changes only an unanswered question, under session lock with backup.
