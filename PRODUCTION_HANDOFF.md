# TechGenyz Social Publisher — PRODUCTION HANDOFF

## Purpose

This file is the mandatory production-release contract for TechGenyz Social Publisher.

**Before creating ANY production ZIP, Codex MUST read this file in full and follow it.**

The objective is to make production packaging and release auditing repeatable so that Buffer/OpenAI integration rules, manual editorial workflow, duplicate protection, REST security, stored configuration, historical publishing data, webhook compatibility, uninstall safety, and packaging requirements do not need to be manually re-specified for every release.

This document is a release gate, not a replacement for inspecting the actual current source.

---

# 1. Mandatory Release Rule

For every production release:

1. Read this `PRODUCTION_HANDOFF.md` in full.
2. Read `AGENTS.md` and `CODEX_HANDOFF.md`.
3. Inspect the complete current plugin source.
4. Compare the implementation against this handoff.
5. Resolve authoritative development and production version state.
6. Run all applicable static/security/regression checks.
7. Build a clean production staging copy.
8. Build a clean production ZIP from staging.
9. Inspect the actual generated ZIP.
10. Produce a complete production-readiness report.
11. Do not modify runtime source merely to make an audit-only release check pass without reporting the defect first.
12. Do not declare production-ready when a genuine code-level, security, data-loss, packaging, duplicate-post, credential, or functional blocker exists.

---

# 2. Permanent WordPress Plugin Identity

The internal WordPress plugin directory is permanently:

```text
techgenyz-social-publisher/
```

The main plugin file is permanently:

```text
techgenyz-social-publisher/techgenyz-social-publisher.php
```

The plugin basename must remain:

```text
techgenyz-social-publisher/techgenyz-social-publisher.php
```

The internal directory must not contain a release version.

Correct:

```text
TechGenyz Social Publisher vX.Y.Z Production.zip
└── techgenyz-social-publisher/
    └── techgenyz-social-publisher.php
```

Incorrect:

```text
TechGenyz Social Publisher vX.Y.Z Production.zip
└── techgenyz-social-publisher-vX.Y.Z/
```

Also incorrect:

```text
TechGenyz Social Publisher vX.Y.Z Production.zip
└── techgenyz-social-publisher/
    └── techgenyz-social-publisher/
        └── techgenyz-social-publisher.php
```

Verify the plugin header, text domain, main filename, and basename remain internally consistent.

---

# 3. Expected Production ZIP Structure

Expected high-level runtime structure for the current architecture:

```text
techgenyz-social-publisher/
├── techgenyz-social-publisher.php
├── uninstall.php
├── readme.txt
├── assets/
│   ├── admin.css
│   └── admin.js
├── includes/
│   ├── class-social-publisher-logger.php
│   ├── class-social-publisher-metabox.php
│   ├── class-social-publisher-security.php
│   ├── class-social-publisher-settings.php
│   ├── class-social-publisher-webhook.php
│   └── social/
│       ├── class-buffer-client.php
│       ├── class-caption-generator.php
│       ├── class-normalized-response.php
│       ├── class-openai-client.php
│       ├── class-social-manager.php
│       ├── class-social-payload.php
│       └── class-social-webhook.php
└── docs/
    └── webhook-response-schema.json
```

Future releases may legitimately change runtime files, but the permanent plugin root/main-file identity must remain stable.

The final ZIP must contain exactly one top-level plugin root.

---

# 4. Production Package Filtering

The production ZIP must not blindly contain the development repository.

Exclude unless genuinely required at runtime/distribution:

- `.git/` and repository metadata;
- Git history;
- test suites and fixtures;
- local development configuration;
- IDE metadata;
- temporary files;
- cache files;
- log files;
- screenshots;
- audit PDFs;
- old ZIPs;
- generated reports;
- local credential files;
- `.env` files;
- debug dumps;
- `AGENTS.md`;
- `CODEX_HANDOFF.md`;
- `PRODUCTION_HANDOFF.md`.

`readme.txt` is normal distributable plugin documentation and may remain.

`docs/webhook-response-schema.json` currently documents a compatibility contract. Keep it when the release continues to advertise/support that webhook schema; do not remove it solely because it is documentation.

---

# 5. Mandatory Production ZIP Build Procedure

```text
CURRENT PROJECT SOURCE
        ↓
Read AGENTS.md / PRODUCTION_HANDOFF.md / CODEX_HANDOFF.md
        ↓
Inspect complete source
        ↓
Resolve authoritative version values
        ↓
Audit functionality / security / data safety
        ↓
Create clean temporary staging directory
        ↓
Copy only intended production/distribution files
        ↓
Create exactly one plugin root:
techgenyz-social-publisher/
        ↓
Apply candidate release metadata only in staging
        ↓
Build outer versioned ZIP
        ↓
Inspect ZIP contents and integrity
        ↓
Run final audit against the artifact
        ↓
Perform/record runtime integration verification
        ↓
PASS or FAIL
        ↓
Update production ledger only after PASS
```

Never modify the working development tree merely to set release metadata for packaging.

---

# 6. Version Tracking and Authority

TechGenyz Social Publisher uses two logically independent version tracks under this workflow:

1. Development Version
2. Production Version

They must not be conflated.

## Development Version

The intended authoritative source is:

```text
TGSP_DEVELOPMENT_VERSION
```

inside the main plugin file.

### Current authority state

The current source establishes the Development Version through:

```text
TGSP_DEVELOPMENT_VERSION = 1.6.1
```

This source value is independent of the production release version.

## Last Production Version

Authoritative only from the ledger in this file.

Current authoritative values are recorded in the production ledger in section 41.

## Candidate Production Version

Calculate only after a valid Last Production Version exists.

Use normal patch progression unless the user explicitly approves another release type.

Example progression:

```text
2.0.1 → 2.0.2 → ... → 2.0.9 → 2.1.0 → ...
```

Do not derive candidate production numbering from the Development Version.

Major-version changes require explicit approval.

---

# 7. Staged Production Version Rules

Once both authorities are established:

- plugin header version in staging = Candidate Production Version;
- `TGSP_VERSION` in staging = Candidate Production Version;
- `readme.txt` Stable tag in staging = Candidate Production Version when shipped;
- `TGSP_DEVELOPMENT_VERSION` remains the current Development Version;
- working development source remains unchanged;
- final ZIP filename may include the Candidate Production Version.

Verify version strings in the actual ZIP, not only staging.

---

# 8. Core Product Regression Gate

The release must preserve the approved product flow unless an explicit approved change says otherwise:

```text
Editor initiates share
→ selects platform(s)
→ generates or prepares captions
→ reviews/edits exact text
→ clicks Share Now
→ Buffer immediate publishing or explicit legacy webhook
→ per-platform result tracking
```

The release must not silently introduce:

- automatic social posting on article publication;
- background auto-share from post-status hooks;
- Buffer queueing in place of immediate share;
- WordPress social scheduling in place of immediate share;
- unreviewed OpenAI auto-publishing.

Search for relevant hooks/logic during every production audit.

---

# 9. Admin UI Regression Gate

Verify current intended editorial entry points remain usable:

- Social Publisher meta box in the post editor;
- Social column on Posts → All Posts;
- row action where applicable;
- shared modal;
- platform selection;
- OpenAI generation action;
- per-platform editable captions;
- per-platform regeneration control;
- Share Now action;
- publishing status feedback;
- retry/force labels consistent with actual state.

Assets must load only on intended admin screens and must not unnecessarily load site-wide.

---

# 10. Supported Platform Gate

Current supported platform keys are:

```text
facebook
linkedin
x
```

Verify all layers agree:

- settings;
- modal;
- REST sanitization;
- OpenAI schema;
- Social Manager;
- Buffer mapping;
- payload generation;
- logging;
- UI status rendering;
- uninstall cleanup.

A new platform requires an intentional end-to-end implementation and corresponding handoff updates.

---

# 11. Published-Post / Permission Gate

Current sharing is intended for published standard posts.

Verify:

- the server validates the requested post;
- unauthorized users cannot share arbitrary posts;
- post-specific actions require `edit_post` capability;
- general sharing authorization retains `edit_posts` plus the post-specific check;
- administrative connection/configuration endpoints require `manage_options`;
- REST nonce validation remains in place where the browser flow expects it.

Do not rely on client-side button visibility as security.

---

# 12. REST API Production Gate

REST namespace:

```text
tgsp/v1
```

Audit all registered routes, methods, callbacks, and permission callbacks.

Expected current routes include:

```text
POST /share/{id}
POST /generate/{id}
POST /buffer-status/{id}
GET  /publishing-status/{id}
POST /test-buffer
GET  /buffer-channels
POST /test-webhook
```

Verify:

- no sensitive route becomes public;
- IDs and request parameters are sanitized;
- platform lists are allowlisted;
- captions are sanitized server-side;
- permission errors return appropriate failures;
- status endpoints do not expose another user's protected post workflow.

---

# 13. OpenAI Production Gate

Current endpoint:

```text
https://api.openai.com/v1/responses
```

Verify:

- API key lookup remains server-side;
- `TGSP_OPENAI_API_KEY` constant override remains supported if configured;
- saved key is not accidentally overwritten by a blank masked field submission;
- current configured model remains a setting;
- supported platforms are allowlisted;
- article data sent to OpenAI is the intended bounded/sanitized article context;
- structured response format remains validated;
- malformed/empty/missing-platform output fails safely;
- generated copy is returned for editor review rather than immediately published;
- canonical article URL rules are enforced before publication.

Production package must never contain a real OpenAI secret.

---

# 14. Buffer Production Gate

Current Buffer endpoint:

```text
https://api.buffer.com
```

Verify the active publishing mutation retains immediate semantics:

```text
schedulingType = automatic
mode = shareNow
needsApproval = false
saveToDraft = false
```

Mandatory regression search:

- ensure active code does not use `addToQueue`;
- ensure active code does not silently schedule for later;
- ensure successful submission requires a Buffer post ID;
- ensure each platform uses its mapped channel;
- ensure article URL is validated;
- ensure platform metadata is correctly formed;
- ensure API key remains server-side;
- ensure `TGSP_BUFFER_API_KEY` constant override remains supported if configured.

Any unintended switch from immediate `shareNow` is a production blocker unless explicitly approved.

---

# 15. Buffer Channel Discovery and Mapping Gate

Verify:

- organization discovery still works against the configured account;
- channel discovery scopes to publish-capable channels;
- existing `tgsp_buffer_organization_id` is preserved;
- existing Facebook/LinkedIn/X channel mapping options are preserved;
- a missing mapping produces a safe error rather than publishing to an arbitrary channel;
- channel testing/discovery endpoints remain admin-only.

Do not auto-select and save a new channel over an existing valid mapping during upgrade.

---

# 16. Buffer Processing / Reconciliation Gate

A Buffer post may initially be processing.

Verify:

- processing is stored as processing, not falsely declared published;
- the remote Buffer post ID is stored with the attempt;
- reconciliation queries the existing stored remote ID;
- reconciliation does not call createPost again;
- the stored remote ID must match the active processing attempt;
- malformed/mismatched remote IDs fail closed;
- batched status queries remain bounded to supported platform aliases;
- 429/rate-limit behavior stops confirmation safely and does not produce a duplicate;
- a missing remote post is reported without blindly re-publishing.

This is a high-priority duplicate-safety gate.

---

# 17. Duplicate Protection / Retry Gate

Verify normal retry behavior against per-platform stored statuses.

Normal retry must not re-send platforms already in states treated as completed/safely accepted/in progress by the current implementation.

Failed platforms should remain retryable.

If a platform is processing, reconciliation must occur before deciding to send again.

`Share Again` remains the explicit force path.

The UI should warn/confirm appropriately because a forced share can create duplicate public posts.

Legacy `_social_publisher_status = webhook_sent` records must remain protected.

A regression that can create unintentional duplicate social posts is a production blocker.

---

# 18. Publishing Lock Gate

Verify the per-post lock:

```text
_social_publisher_lock
```

still prevents overlapping share attempts.

Verify:

- acquisition is atomic through the current meta approach or an equally safe replacement;
- stale locks can recover;
- lock release occurs after success/failure;
- an exception/error path cannot permanently strand ordinary publishing without stale recovery.

---

# 19. X Caption / Canonical URL Gate

This is a locked regression area from the 1.6.1 baseline.

Verify:

- server-derived canonical URL is used;
- one complete canonical URL is present in final X outbound text;
- duplicate occurrences of that canonical URL are removed before final append;
- URL weighting treats detected URLs as 23 characters;
- final weighted length is at most 280;
- the canonical URL is never truncated;
- X server-side normalization is still applied even if browser/editor text was manually edited.

Do not rely only on client-side character counters.

---

# 20. Facebook / LinkedIn Caption URL Gate

For generated non-X captions, verify the OpenAI response includes the exact canonical article URL expected by the server.

For manually edited final captions, audit the product's intended behavior before changing enforcement. Do not invent stricter behavior during a production audit if the current source does not enforce it.

The production contract must describe current approved behavior, not silently impose a new feature.

---

# 21. SEO / Rank Math / Schema / Frontend Non-Interference Gate

TechGenyz Social Publisher is an editorial/social-publishing plugin. It is **not** the site's SEO authority and must not change public SEO output merely by being activated, upgraded, or used to publish social posts.

This gate is mandatory for every production candidate.

## A. SEO ownership / non-interference

Verify the candidate does not unexpectedly add, remove, replace, suppress, or duplicate:

- document titles;
- meta descriptions;
- canonical tags;
- robots meta directives;
- `noindex` / `nofollow` behavior;
- Open Graph metadata;
- Twitter Card metadata;
- schema / JSON-LD;
- breadcrumbs;
- sitemap participation or exclusions;
- public permalink/rewrite behavior.

The candidate must not introduce site-wide `wp_head`, `wp_footer`, template, rewrite, query, or frontend-output behavior unrelated to the Social Publisher's approved use case.

## B. Rank Math compatibility

When Rank Math is installed/configured on TechGenyz.com, Rank Math remains authoritative for its configured SEO responsibilities.

Verify the Social Publisher candidate does not:

- override Rank Math SEO titles or descriptions;
- override/suppress Rank Math canonicals;
- change Rank Math robots/indexability output;
- remove or duplicate Rank Math schema/JSON-LD;
- alter Rank Math sitemap inclusion/exclusion unexpectedly;
- alter Rank Math breadcrumb behavior unexpectedly;
- duplicate or conflict with Rank Math Open Graph/Twitter metadata;
- use social-caption/publishing logic to inject competing frontend SEO output.

Reading or submitting an article URL for social publishing must not mutate the public page canonical or other SEO metadata.

## C. Static production audit

Search current source, staging copy, and final production artifact for SEO/frontend-output touchpoints, including at minimum:

```text
rank_math
wp_head
wp_footer
canonical
rel="canonical"
robots
noindex
nofollow
application/ld+json
schema.org
breadcrumb
sitemap
og:
twitter:
```

Review any hit in context. The presence of a term is not itself a production failure; the goal is to prove whether the plugin changes public SEO output.

Also verify admin assets remain scoped to intended admin screens and are not unnecessarily enqueued on the public frontend.

## D. Rendered staging/live verification

When an appropriate configured staging or live environment is available, verify representative public content after activating/upgrading the candidate:

1. HTTP status and public permalink remain correct;
2. title remains correct;
3. meta description remains correct;
4. exactly the intended canonical is present;
5. robots/indexability remains correct;
6. Rank Math schema/JSON-LD remains present, valid, and non-duplicated where expected;
7. Open Graph and Twitter metadata remain correct and non-duplicated;
8. breadcrumbs remain correct where used;
9. sitemap/indexability behavior remains unchanged;
10. no unintended Social Publisher CSS/JS or visible UI appears on the public page;
11. no new PHP warning, fatal, redirect behavior, broken markup, or frontend regression is introduced;
12. representative pages/posts behave the same as the known-good baseline or prior approved production version except for explicitly approved release changes.

Where feasible, inspect rendered HTML, not only WordPress settings screens.

## E. Runtime evidence rule

Static inspection can establish that no obvious SEO integration exists, but it cannot prove final rendered Rank Math/schema/canonical/sitemap behavior on the configured site.

If rendered staging/live verification is unavailable:

```text
SEO / Rank Math / Schema / Frontend Runtime Verification: NOT VERIFIED
```

List the missing checks explicitly in the final production report. Do not convert an unavailable runtime check into PASS.

A verified SEO/indexability/frontend regression is a **CODE-LEVEL / RUNTIME PRODUCTION BLOCKER** until resolved or explicitly accepted through an approved change.

---

# 22. Legacy Webhook Compatibility Gate

When legacy webhook remains supported, verify:

- `tgsp_delivery_method = webhook` remains respected when saved;
- upgrades do not force an existing webhook user onto Buffer;
- webhook URL remains server-side;
- optional secret remains server-side;
- outbound requests use safe HTTP behavior and zero redirects as implemented;
- connection-test status remains separate from actual publishing history;
- social publish payload still contains the expected article/platform/caption/attempt fields;
- platform-specific webhook responses normalize correctly;
- a plain 2xx without platform confirmation is represented as accepted/unconfirmed rather than guaranteed published;
- `docs/webhook-response-schema.json` matches the supported response contract when shipped;
- intentionally retained compatibility class(es) are not removed as accidental duplicates.

---

# 23. Settings Preservation Gate

The following stored options are protected identifiers unless an explicit migration is approved:

```text
tgsp_delivery_method
tgsp_openai_api_key
tgsp_openai_model
tgsp_buffer_api_key
tgsp_buffer_organization_id
tgsp_buffer_channel_facebook
tgsp_buffer_channel_linkedin
tgsp_buffer_channel_x
tgsp_webhook_url
tgsp_webhook_secret
tgsp_webhook_connection_status
tgsp_webhook_last_success
tgsp_enable_facebook
tgsp_enable_linkedin
tgsp_enable_x
tgsp_facebook_caption_template
tgsp_linkedin_caption_template
tgsp_x_caption_template
tgsp_message_format
tgsp_debug_logging
tgsp_db_version
```

Verify:

- upgrade/activation does not reset valid saved values;
- default `buffer` applies only when no saved delivery method exists;
- a saved `webhook` selection remains `webhook`;
- blank masked secret input does not unintentionally clear existing saved secrets;
- caption template fallbacks preserve legacy `tgsp_message_format` behavior.

---

# 24. Post Metadata Preservation Gate

Protected historical/state keys:

```text
_social_publisher_status
_social_publisher_sent_time
_social_publisher_lock
_social_publisher_platform_statuses
```

Verify upgrades do not rename/reset these values unintentionally.

If a migration is introduced, prove that historical posts retain correct share/retry/duplicate behavior after migration.

---

# 25. Log Table / Database Gate

Protected table identity:

```text
{$wpdb->prefix}social_publish_logs
```

Expected current data model includes:

```text
id
post_id
platform
status
response
submitted_text
remote_id
response_code
attempt_id
created_at
updated_at
```

Verify:

- activation/upgrade uses `dbDelta()` or a safe migration approach;
- historical rows are preserved;
- table is not dropped/recreated during normal upgrade;
- new columns/indexes are backward-safe;
- `tgsp_db_version` updates only as intended;
- logs remain bounded;
- stored response text is sanitized/redacted;
- credentials are not written to logs;
- reconciliation updates the correct attempt row.

---

# 26. Secret / Credential Security Gate

Production release must contain no real secrets.

Audit for:

- OpenAI API keys;
- Buffer API keys;
- webhook secrets;
- bearer tokens;
- `.env` files;
- copied HTTP transcripts containing authorization headers;
- test credentials in documentation/comments.

Verify browser JavaScript/localized data does not receive service credentials.

Verify logs redact common credential fields.

Saved secrets on the settings screen must remain masked.

---

# 27. Outbound HTTP Security Gate

Audit all external requests.

Expected current behavior uses safe WordPress HTTP APIs and disables redirects for external service/webhook requests.

Verify:

- user-configured webhook URL is sanitized and constrained to HTTP/HTTPS;
- outbound article/image URLs are structurally valid HTTP/HTTPS values;
- API responses are size-bounded where stored/logged;
- timeout behavior is finite;
- errors do not echo credentials;
- external failures fail safely rather than causing repeated duplicate posts.

---

# 28. Deactivation Safety Gate

Deactivation must remain non-destructive.

A production release must not delete settings, metadata, history, or the log table merely because the plugin is deactivated.

If a future deactivation hook exists, inspect it explicitly.

---

# 29. Uninstall Safety Gate — HIGH RISK

Current destructive authority:

```text
TGSP_REMOVE_DATA === true
```

## Flag absent or false

Uninstall must preserve plugin-owned data.

## Flag explicitly true

The current uninstall routine may delete the documented plugin-owned data:

- saved TGSP options;
- Facebook/LinkedIn/X enable/template/channel options;
- custom log table;
- `_social_publisher_status`;
- `_social_publisher_sent_time`;
- `_social_publisher_lock`;
- `_social_publisher_platform_statuses`.

Verify the routine is protected by:

```text
WP_UNINSTALL_PLUGIN
```

and the explicit data-removal constant.

Do not introduce destructive cleanup on normal filesystem deletion, deactivation, activation, settings save, or routine upgrade.

---

# 30. No Duplicate Delete-Data Mechanism

The project currently uses the explicit `TGSP_REMOVE_DATA` constant as the authority for destructive uninstall cleanup.

Do not add another unrelated checkbox/prompt/automatic purge path unless the user explicitly redesigns the data-removal UX and the handoff is updated accordingly.

Multiple conflicting deletion authorities increase data-loss risk.

---

# 31. Activation / Upgrade Gate

Verify activation continues to create/upgrade the log table without overwriting unrelated settings or publishing history.

Verify the admin upgrade check based on `tgsp_db_version` does not become a destructive reset.

A version mismatch may trigger `dbDelta()`; it must not erase historical rows.

---

# 32. JavaScript / Browser Workflow Gate

Audit `assets/admin.js` for:

- use of WordPress REST nonce;
- intended REST paths only;
- no service secret exposure;
- no bypass of server-side platform validation;
- force-share confirmation behavior;
- polling/reconciliation behavior;
- bounded polling behavior;
- correct distinction between processing/published/failed states;
- no duplicate create request generated merely because polling is slow;
- generated captions remaining editable before final share.

Browser behavior is not a substitute for server-side security.

---

# 33. Performance / External Request Gate

Review external request count and timeout behavior.

Current architecture may call:

- OpenAI for caption generation;
- Buffer for organization/channel discovery;
- Buffer once per target platform to create posts;
- Buffer reconciliation for stored processing attempts;
- optional webhook delivery.

Verify no accidental loops create excessive API traffic.

Verify polling is bounded and rate-limit behavior does not hammer Buffer.

Verify admin assets remain scoped to relevant screens.

---

# 34. Debug / Development Audit

Search source and package for:

- `var_dump`;
- `print_r` debug output;
- temporary `error_log` statements containing sensitive data;
- console debugging that exposes internals;
- test endpoints not protected by capabilities;
- hard-coded test URLs/tokens;
- temporary bypass flags;
- development-only files.

Debug logging as an intentional plugin setting is not itself a defect; audit what it stores and whether secrets are redacted.

---

# 35. Required Static Validation

## PHP

Run syntax checks across every PHP file shipped in the candidate.

## JavaScript

Run JavaScript syntax validation for every shipped JS file.

## JSON

Validate shipped JSON, including webhook schema documentation.

## Archive

Test ZIP integrity.

## Searches

Search for at least:

```text
addToQueue
save_post
publish_post
transition_post_status
future_to_publish
TGSP_OPENAI_API_KEY
TGSP_BUFFER_API_KEY
Authorization
DROP TABLE
_social_publisher_
tgsp_
social_publish_logs
rank_math
wp_head
wp_footer
canonical
robots
noindex
nofollow
application/ld+json
schema.org
breadcrumb
sitemap
og:
twitter:
.git
.env
```

Interpret results in context; a search hit is not automatically a defect.

---

# 36. Runtime / Staging Checks

When an appropriate configured staging/live environment is available, verify:

1. plugin activates cleanly;
2. existing settings survive replacement/upgrade;
3. historical log rows survive upgrade;
4. existing shared posts retain statuses;
5. settings page loads;
6. OpenAI generation succeeds;
7. captions appear separately per requested platform;
8. manual edits are the exact text submitted by the active delivery path, subject to intentional server-side X normalization;
9. Buffer connection/channel discovery succeeds;
10. mapped channels are correct;
11. controlled Facebook immediate share succeeds;
12. controlled LinkedIn immediate share succeeds;
13. controlled X immediate share succeeds;
14. X output contains one full canonical URL and respects weighted length;
15. a processing Buffer post reconciles without duplicate creation;
16. normal retry skips a completed platform;
17. failed-only retry does not re-send successful platforms;
18. force `Share Again` works only when deliberately invoked;
19. legacy webhook path still works if supported for the release;
20. unauthorized editor/admin cases are rejected correctly;
21. no API secret appears in browser source/network responses beyond intended server-bound requests;
22. representative public posts/pages retain correct title and meta description;
23. representative public posts/pages retain a correct, singular canonical;
24. robots/indexability remains unchanged and no accidental `noindex`/`nofollow` appears;
25. Rank Math schema/JSON-LD, Open Graph/Twitter metadata, breadcrumbs, and sitemap behavior remain correct and non-duplicated where applicable;
26. no Social Publisher admin-only asset or visible publishing UI leaks onto the public frontend;
27. representative frontend rendering, status codes, permalinks, and redirects remain unchanged apart from explicitly approved changes;
28. uninstall data behavior is tested only under controlled backup/sandbox conditions.

Never claim these passed when they were not executed.

---

# 37. Destructive Testing Rule

Do not test destructive uninstall behavior against production data merely to satisfy an audit.

Use a disposable/staging database or a verified backup/restore procedure.

Static inspection may verify the guard structure, but actual destructive cleanup should be tested only in an appropriate controlled environment.

---

# 38. Final Production Gates

## A. CODE-LEVEL BLOCKERS

Examples:

- syntax error;
- public/unprotected sensitive REST route;
- hard-coded live credential;
- unintended automatic publishing;
- Buffer queueing replacing approved immediate share;
- broken duplicate protection;
- unintended SEO/title/meta/canonical/robots/schema/sitemap/breadcrumb interference;
- duplicate Rank Math/schema/canonical or frontend SEO output introduced by the plugin;
- destructive upgrade/deactivation behavior;
- unsafe uninstall path;
- missing runtime file;
- wrong plugin root identity;
- corrupted ZIP.

Any verified blocker = **NOT READY FOR PRODUCTION**.

## B. VERSION-AUTHORITY BLOCKERS

Examples:

- missing `TGSP_DEVELOPMENT_VERSION` when the workflow requires it;
- unseeded/ambiguous Last Production Version;
- contradictory ledger state.

Verdict = **PRODUCTION PACKAGING STOPPED — VERSION AUTHORITY REQUIRED**.

## C. STAGING/LIVE VERIFICATION REQUIRED

External-service behavior that cannot be proven statically must be explicitly listed as outstanding.

Do not turn "not tested" into "passed."

## D. NON-BLOCKING OBSERVATIONS

Document maintainability, future improvements, or environment-dependent notes that do not make the candidate unsafe.

## E. PASS

All mandatory code/package/version gates pass and required runtime verification is complete according to the release policy.

Verdict = **READY FOR PRODUCTION**.

---

# 39. Final Verdict Rules

Use one clear verdict:

```text
READY FOR PRODUCTION
```

or:

```text
NOT READY FOR PRODUCTION
```

or, when authoritative release state itself is missing:

```text
PRODUCTION PACKAGING STOPPED — VERSION AUTHORITY REQUIRED
```

If code/package gates pass but external runtime checks remain legitimately unavailable, explicitly state the narrower status and outstanding verification. Do not overstate certainty.

---

# 40. Mandatory Final Report

Every production build/audit must include:

```text
# Executive Summary
# Version Authority Audit
# Development Version
# Last Production Version
# Candidate Production Version
# WordPress Plugin Identity
# Production Package Structure
# Core Workflow Regression Audit
# Admin UI / Posts Screen Audit
# Supported Platform Audit
# REST / Permission Audit
# OpenAI Integration Audit
# Buffer Immediate Publishing Audit
# Buffer Channel Mapping Audit
# Processing / Reconciliation Audit
# Duplicate Protection / Retry Audit
# Publishing Lock Audit
# X Caption / Canonical URL Audit
# SEO / Rank Math / Schema / Frontend Non-Interference Audit
# Legacy Webhook Compatibility Audit
# Settings Preservation Audit
# Post Metadata Preservation Audit
# Log Table / Database Audit
# Secret Handling Audit
# Outbound HTTP Security Audit
# Deactivation Audit
# Uninstall / Data Safety Audit
# Performance Audit
# Debug / Development Audit
# Production Package Audit
# Static Validation
# Runtime / Staging Verification
# Issues Found
# Final Verdict
# Production ZIP Path
# SHA-256
```

For every identified issue:

```text
Severity:
Impact:
Recommendation:
Production blocker: YES/NO
```

---

# 41. Production Release Ledger

## Current authority state

```text
Development Version Authority: TGSP_DEVELOPMENT_VERSION in techgenyz-social-publisher.php
Development Version: 1.6.1

Last Production Version: 1.0.0
Last Production ZIP: TechGenyz Social Publisher v1.0.0 Production.zip
Release Date: 2026-09-16
SHA-256: 34474939EA05D423F87F40CA2CC3F84415D251E30BBEBF9D1F089D4EAC1F632C
```

## Release history

## Production Release 1.0.0 — 2026-09-16

Last Production Version: 1.0.0  
Last Production ZIP: TechGenyz Social Publisher v1.0.0 Production.zip  
Development Version: 1.6.1  
SHA-256: 34474939EA05D423F87F40CA2CC3F84415D251E30BBEBF9D1F089D4EAC1F632C  
Verdict: READY FOR PRODUCTION  
Notes: Initial governed production baseline explicitly approved by the user. Static source and package gates passed; configured external-service and rendered staging/live checks remain environment-dependent and were not executed during packaging.

After a successful release, append a record such as:

```text
## Production Release X.Y.Z — YYYY-MM-DD

Last Production Version: X.Y.Z
Last Production ZIP: TechGenyz Social Publisher vX.Y.Z Production.zip
Development Version: <source value at release time>
SHA-256: <hash>
Verdict: READY FOR PRODUCTION
Notes: <brief release context>
```

Never erase prior release records.

---

# 42. Ledger Update Rule

Only a successful governed production release may advance:

```text
Last Production Version
```

A failed candidate, abandoned build, test build, or audit-only artifact does not advance the ledger.

The ledger should record the exact delivered production ZIP identity/hash when available.

---

# 43. Final Release Confirmation

Before delivering a production ZIP, confirm all of the following:

- authoritative versions resolved;
- stable internal plugin directory;
- correct main plugin file path;
- no double nesting;
- no repository/development artifacts;
- no credentials;
- approved manual workflow preserved;
- OpenAI generation remains review-before-publish;
- Buffer path remains immediate `shareNow`;
- no accidental `addToQueue`/scheduling;
- duplicate protection preserved;
- processing reconciliation preserved;
- X canonical URL safety preserved;
- saved options/meta/history preserved;
- legacy webhook compatibility preserved when still supported;
- REST/capability protections pass;
- uninstall remains preserve-by-default;
- final ZIP itself was inspected and validated;
- outstanding runtime checks are explicitly disclosed.

---

## SEO / frontend release confirmation

Before final READY FOR PRODUCTION status, explicitly report one of:

```text
SEO / Rank Math / Schema / Frontend Non-Interference: PASS
```

or, when rendered environment checks were unavailable:

```text
SEO / Rank Math / Schema / Frontend Static Audit: PASS
SEO / Rank Math / Schema / Frontend Runtime Verification: NOT VERIFIED
```

If a verified regression exists, final verdict must be `NOT READY FOR PRODUCTION`.

---

# 44. Release Philosophy

```text
Develop → Verify → Audit → Stage → Package → Inspect → Report → Release
```

A production ZIP is not ready merely because it can be installed.

It is ready only when the artifact preserves approved behavior, protects existing data, protects credentials, avoids unintended duplicate public posts, keeps WordPress plugin identity stable, and passes the defined release gates.
