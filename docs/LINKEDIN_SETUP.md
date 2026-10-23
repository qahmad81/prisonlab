# LinkedIn integration setup

Application display name: **PrisonLab**. The owner selected this capitalization.

## Current readiness

The existing application still uses private browser-session ownership. LinkedIn
login, account quotas, profile deletion and public-only leaderboards are not yet
implemented or enabled. The initial privacy page explicitly states this status;
update it alongside the account implementation before enabling sign-in. Never
advertise account access or deletion controls that are not available.

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

After creation, request **Sign in with LinkedIn using OpenID Connect** under
Products. Request only `openid profile`; do not request email, posting, contacts
or organization permissions. The intended production callback is
`https://prisonlab.odehit.com/auth.php?action=callback`; it must be implemented
and tested before being used. Do not add a callback placeholder to the public
publisher. Keep the client secret in the ignored server `.env`, never in Git,
chat, screenshots or browser JavaScript.

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
