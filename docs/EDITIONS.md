# PrisonLab editions

- main: standalone, browser-owned private history, optional personal OpenRouter key; no LinkedIn credentials, account requirements, leaderboards, LinkedIn share composer or daily test quotas.
- linkedin: deployed community edition at https://prisonlab.odehit.com/, requiring LinkedIn accounts and enforcing three site-funded starts per account per Jerusalem calendar day. Personal keys bypass the daily quota, not provider limits or a session's original funding binding.

Shared fixes belong in both branches. Implement common changes on main and cherry-pick their reviewed commits onto linkedin; keep account/OAuth/quota/profile/leaderboard/social-specific commits confined to linkedin. Never merge the LinkedIn edition wholesale into main. If a shared commit touches edition-specific code, resolve the patch explicitly and test both editions. A branch name is not an authentication toggle: production stays checked out on linkedin with its private environment and data unchanged.

lib/model-policy.json is the reviewed source for shortlist overrides and the three judge pools. Approved aliases and the explicit Mistral batch variant are intentional. Candidate discovery may prepare reports outside Git but must not change approved lists without the owner's choice. Missing judges do not trigger replacement with unapproved models. The three selected judges remain fixed across retries; a changed price exceeding the saved cap requires review.

The jury's output-price sum is not the experiment's accumulated cost. Human roles contribute zero model price. Thresholds are strict: <2 economic, >=2 and <10 medium, >=10 professional. The panel deadline excludes catalog lookup and JEV final verification. A zero countdown starts finalizing valid reports; it never fabricates a final score. Completed historical evaluations are not regraded automatically.

Branch histories preserve earlier public commits for traceability; the main working tree is the standalone distribution. No secrets or production data belong in either branch.
