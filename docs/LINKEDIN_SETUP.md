# LinkedIn integration setup

Application display name: **PrisonLab**. The owner selected this capitalization.

## Current readiness

LinkedIn account login, per-account quotas, conversation deletion, public-only
profiles/leaderboards, funding binding, cost display and composer sharing are
implemented. Deployment requires configured credentials and registered callbacks.
The owner provisioned OpenID Connect and Share on LinkedIn in the developer app.
Sign-in requests only openid/profile. Do not advertise real-world identity
verification or automatic posting. Account deletion requests use the operator
contact; conversation deletion is available directly in the application.

Developer application form:

- Name: `PrisonLab`
- Upload-ready logo: `assets/branding/prisonlab-512.png` (512x512 PNG)
- Approved source logo: `assets/branding/prisonlab-logo.png` (1254x1254 PNG)
- Privacy policy: `https://prisonlab.odehit.com/privacy.php`
- Website: `https://prisonlab.odehit.com/`
- Operator contact: `https://odehit.com/#contact` (owner-authorized destination)
- LinkedIn company Page: must be selected by the owner; never invent a Page or
  bind to an unrelated company. LinkedIn says the association cannot be undone
  once saved.
- API legal agreement: the owner must review and accept it in their browser.

Enable **Sign in with LinkedIn using OpenID Connect** under Products. Request only
`openid profile`; do not request email, posting, contacts or organization permissions.
Authorize these exact redirects:

- `http://127.0.0.1:8488/auth.php?action=callback` (local development)
- `https://prisonlab.odehit.com/auth.php?action=callback` (production)

Set APP_BASE_URL, LINKEDIN_CLIENT_ID, LINKEDIN_CLIENT_SECRET and LINKEDIN_REDIRECT_URI
in each environment's ignored `.env`. The redirect must exactly equal its base
URL plus `/auth.php?action=callback`. Keep the secret out of Git, chat, screenshots
and browser JavaScript. Transfer production settings separately over SCP.

Sharing opens `https://www.linkedin.com/sharing/share-offsite/?url=...` only for an
already-public session. Publication itself requires a separate user confirmation.
No automatic UGC API call is made and no posting token/scope is required by this
composer flow, even though the app's Share on LinkedIn product is enabled.

Use the server's authorization-code exchange and authenticated userinfo endpoint,
short-lived single-use state, fixed redirect URI, secure cookies and session ID
rotation. Never trust client-supplied names or unsigned JWT claims. A LinkedIn
login authenticates an account; it does not verify a person's real-world identity.

Official reference: [LinkedIn OpenID Connect sign-in](https://learn.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin-v2).

## Logo provenance

Generated on 2026-10-23 with the built-in image generation tool. Square navy,
amber and cyan flask/circuit/bar emblem, no text. The final PNG is a reviewed
runtime brand asset. It does not replace existing third-party company icons.
The owner explicitly approved this mark for the app, site header and favicon.
Rebuild PNG sizes 16/32/64/128/180/192/256/512, multi-size ICO and the SVG
container using `python tools/prepare_brand.py` (Pillow). Source art is preserved
in the ignored `reference/originals/` directory as well. No PWA or installable
offline behavior is implied by the touch icon assets.

Generation prompt:

> Use case: logo-brand. Create one polished square app logo for PrisonLab, a professional community laboratory benchmarking AI models through fictional detective interrogation. Compact geometric emblem combining a laboratory flask silhouette with two minimal circuit nodes and three subtle vertical prison-bar lines, intellectually intriguing rather than punitive. Dark midnight navy solid background, warm amber emblem with tiny icy cyan accent, flat crisp graphic shapes, generous safe margin, highly readable at 64px, centered symmetrical composition. No text, no letters, no watermark, no mockup, no gradients, no photorealism. Output a finished square app icon.
