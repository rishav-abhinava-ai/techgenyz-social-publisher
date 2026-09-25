# AGENTS.md — TechGenyz Social Publisher Production Release Procedure

## Purpose

This file defines the mandatory operating procedure for Codex when working on the TechGenyz Social Publisher repository.

The most important rule is:

> Whenever the user says **"Give me production ready zip file"**, Codex MUST execute the complete production-release workflow defined in this file and in `PRODUCTION_HANDOFF.md`.

The user should not need to repeat the publishing-flow requirements, Buffer/OpenAI safeguards, security requirements, data-preservation rules, packaging rules, regression requirements, or versioning rules for every release.

`PRODUCTION_HANDOFF.md` is the detailed production-release contract.
This `AGENTS.md` defines how Codex must use that contract.

`CODEX_HANDOFF.md` provides implementation context and historical decisions. It is not production-version authority.

---

# 1. Mandatory Production Trigger

Treat the following request as a production-release command:

```text
Give me production ready zip file
```

Normal variations such as the following have the same meaning:

```text
Give me the production ready zip
Prepare the production ZIP
Create the production-ready plugin
Make the Social Publisher plugin production ready
```

When the intent is clearly to prepare a production release, execute the full workflow in this file.

Do NOT merely compress the current directory.
Do NOT skip the audit because an earlier build passed.
Do NOT assume external integrations work because static inspection is clean.

Every production release must be audited against the current source and the current production contract.

---

# 2. Document Authority and Reading Order

For significant development work, read:

1. `AGENTS.md`
2. `CODEX_HANDOFF.md`
3. the actual current source
4. `PRODUCTION_HANDOFF.md` when the change affects a production invariant

For a production-release request, read in this order:

1. `AGENTS.md`
2. `PRODUCTION_HANDOFF.md` in full
3. `CODEX_HANDOFF.md`
4. the actual current source

Authority is separated intentionally:

- `AGENTS.md` = operating procedure
- `CODEX_HANDOFF.md` = implementation/context only
- `PRODUCTION_HANDOFF.md` = production acceptance contract and production ledger
- actual source = authority for what the plugin currently implements

If documentation and source disagree about current behavior, inspect the source and report the mismatch. Do not silently rewrite source or documentation to hide the conflict.

---

# 3. Inspect the Complete Current Project Before Changes

Before significant implementation or production packaging:

1. Inspect the complete project structure.
2. Inspect `techgenyz-social-publisher.php`.
3. Inspect `uninstall.php`.
4. Inspect the settings class.
5. Inspect the admin/meta-box and REST class.
6. Inspect the social manager.
7. Inspect Buffer, OpenAI, caption, payload, response-normalization, and webhook classes.
8. Inspect JavaScript and CSS used by the publishing UI.
9. Inspect the logging table definition and upgrade behavior.
10. Inspect all REST routes and permission callbacks.
11. Inspect current version declarations.
12. Search for development/test artifacts.
13. Search for destructive/deletion paths.
14. Search for unexpected auto-publish hooks or scheduling behavior.
15. Search for credential exposure.
16. Compare the current implementation against `CODEX_HANDOFF.md` and `PRODUCTION_HANDOFF.md`.

Do not assume the current source is identical to the last inspected test build or last production release.

---

# 4. Scope Control

Codex must preserve approved functionality that is unrelated to the requested change.

For every development task:

1. identify the exact requested scope;
2. inspect the affected implementation before modifying it;
3. preserve unrelated behavior;
4. preserve stored option names, post-meta keys, database table identity, REST namespace, plugin identity, and external integration contracts unless the user explicitly requests a migration;
5. validate the change;
6. review the diff for unintended changes.

Do not perform opportunistic refactors that alter publishing behavior, data structures, external API behavior, or compatibility merely because the code could be organized differently.

---

# 5. Locked Product Behavior Unless Explicitly Changed

The following are current project invariants and must not be changed casually:

- The plugin is editor-driven/manual social publishing, not automatic post-status publishing.
- Editors generate, review, and may edit outgoing captions before sharing.
- Supported platforms are Facebook, LinkedIn, and X.
- Buffer is the primary/default delivery path when no saved delivery-method option exists.
- Buffer publishing uses immediate `shareNow` behavior.
- Buffer publishing must not silently become `addToQueue`, scheduled publishing, approval-required publishing, or draft creation.
- The legacy webhook delivery path remains available for backward compatibility unless explicitly removed through a migration plan.
- OpenAI generation is server-side.
- OpenAI and Buffer secrets must not be exposed to browser JavaScript.
- Normal retries must skip platforms already successfully/accepted/published or still safely processing.
- `Share Again` is the explicit force path and may create duplicate social posts only after deliberate editor action.
- Buffer reconciliation must inspect an existing remote post and must not create a duplicate while checking status.
- X outbound text must preserve one complete server-derived canonical article URL and remain within the practical weighted envelope used by the plugin.
- Historical settings, post metadata, and log rows must be preserved through upgrades.
- Data is preserved by default on uninstall unless the explicit destructive opt-in is enabled.

If a requested change conflicts with one of these invariants, call out the conflict before implementation and document the approved change in `CODEX_HANDOFF.md` and, when production acceptance changes, `PRODUCTION_HANDOFF.md`.

---

# 6. Development Version Authority

TechGenyz Social Publisher must use an explicit Development Version source of truth separate from production release numbering.

The intended authoritative source is:

```text
TGSP_DEVELOPMENT_VERSION
```

inside:

```text
techgenyz-social-publisher/techgenyz-social-publisher.php
```

## Current authority state

The current source defines `TGSP_DEVELOPMENT_VERSION`, and it is the sole authority for the Development Version.

The following must never override it:

- `TGSP_VERSION`
- plugin header version
- `readme.txt` stable tag
- ZIP filename
- `CODEX_HANDOFF.md`
- historical changelog entries
- prior audit reports

---

# 7. Production Version Authority

The Last Production Version is authoritative only from the production ledger in `PRODUCTION_HANDOFF.md`.

The Production Version is independent of the Development Version.

The Candidate Production Version must be calculated only from the Last Production Version recorded in the production ledger.

Never infer a Last Production Version from:

- the current test-build filename;
- `TGSP_VERSION`;
- the plugin header;
- the stable tag;
- changelog entries;
- Git tags;
- historical chat messages.

If the production ledger is unseeded, missing, malformed, or ambiguous, report the issue and STOP production packaging rather than guessing.

Major-version changes require explicit approval.

---

# 8. Production Release Copy and Version Safety

Production packaging must never mutate the working development source merely to create a release archive.

When both version authorities are established:

1. Read the current Development Version from `TGSP_DEVELOPMENT_VERSION`.
2. Read the Last Production Version from the ledger in `PRODUCTION_HANDOFF.md`.
3. Calculate the Candidate Production Version only from that ledger.
4. Create a clean temporary staging copy.
5. Copy only production/runtime-required files into the staging copy.
6. In the staging copy only, set:
   - plugin header version = Candidate Production Version;
   - `TGSP_VERSION` = Candidate Production Version;
   - `readme.txt` stable tag = Candidate Production Version when `readme.txt` is shipped;
7. Preserve `TGSP_DEVELOPMENT_VERSION` unchanged in the staged package.
8. Leave the working development source unchanged.
9. Build the ZIP from the staged copy.
10. Inspect the actual ZIP.
11. Only after all mandatory gates pass, update the production ledger.

A failed candidate must never advance the Last Production Version.

---

# 9. Existing Data Is Sacred

Production upgrades must preserve existing WordPress data unless an explicit migration is approved.

Do not rename, reset, or remove existing stored identifiers casually.

Important current options include:

```text
tgsp_delivery_method
tgsp_openai_api_key
tgsp_openai_model
tgsp_openai_caption_prompt
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

Important current post-meta keys include:

```text
_social_publisher_status
_social_publisher_sent_time
_social_publisher_lock
_social_publisher_platform_statuses
```

Important database identity:

```text
{$wpdb->prefix}social_publish_logs
```

Activation, upgrade, reactivation, or replacement must not overwrite a valid saved delivery method, API configuration, channel mapping, caption template, publishing state, or historical log row unless an explicit migration requires it.

Defaults apply only where no saved value exists.

---

# 10. Deactivation Safety

Deactivation must be non-destructive.

Do not introduce deactivation behavior that deletes:

- options;
- API/channel settings;
- post metadata;
- publishing history;
- log rows;
- the custom log table.

If a deactivation hook is added in the future, audit it specifically for data destruction.

---

# 11. Uninstall Safety

The current destructive uninstall authority is deliberately explicit:

```text
TGSP_REMOVE_DATA === true
```

Without that explicit constant, uninstall must preserve settings, post metadata, history, and the log table.

When the constant is true, uninstall may remove only the plugin-owned data documented in `uninstall.php` and `PRODUCTION_HANDOFF.md`.

Never move destructive cleanup into:

- deactivation;
- activation;
- routine upgrades;
- plugin bootstrap;
- settings save;
- manual filesystem removal.

Do not introduce a second unrelated delete-data mechanism unless the user explicitly redesigns the uninstall contract.

---

# 12. Security Rules

Every relevant change must preserve or improve the following:

- direct PHP access guards;
- authenticated REST behavior;
- post-specific capability checks for sharing/generation/status actions;
- `manage_options` protection for administrative connection/settings actions;
- WordPress REST nonce protection for browser requests where required;
- server-side API secrets;
- sanitized request parameters;
- sanitized/escaped admin output;
- safe outbound HTTP requests;
- no credential leakage in localized JavaScript;
- no credential leakage in logs;
- bounded response logging;
- remote ID validation before Buffer reconciliation;
- no arbitrary post sharing by users without `edit_post` permission.

A security-related regression is a production blocker.

---

# 13. OpenAI Integration Rules

The current OpenAI integration is server-side and uses the Responses API.

Production checks must verify:

- the API key remains server-side;
- `TGSP_OPENAI_API_KEY` may override the stored option when defined;
- the saved key is not cleared merely because the settings field is submitted blank;
- the configured model remains a setting and the current default remains documented;
- the administrator-configurable caption prompt remains stored in `tgsp_openai_caption_prompt`;
- a missing or meaninglessly empty caption prompt falls back to the built-in default;
- editor HTML is sanitized and normalized to readable plain text before it is sent as Responses API instructions;
- PHP-enforced platform, schema, canonical URL, X-length, and featured-image validation remains authoritative regardless of the editable prompt;
- only supported platform keys are requested;
- structured output is validated;
- empty or malformed responses fail safely;
- non-X captions contain the canonical URL;
- X captions are normalized through the existing X-specific logic;
- generation does not itself publish;
- editors can review/edit generated output before sharing.

Do not hard-code secrets into source, handoff files, logs, or release reports.

---

# 14. Buffer Integration Rules

The current Buffer path is immediate publishing.

Production checks must verify the create-post input continues to use the intended semantics:

```text
schedulingType = automatic
mode = shareNow
needsApproval = false
saveToDraft = false
```

The plugin must not silently switch to queueing or scheduling.

Also verify:

- API key remains server-side;
- `TGSP_BUFFER_API_KEY` may override the stored option when defined;
- organization/channel discovery remains admin-only;
- Facebook, LinkedIn, and X channel mappings remain separate;
- missing mappings fail cleanly;
- article URL validation remains in place;
- platform metadata remains correctly scoped;
- a created remote ID is required before treating Buffer submission as valid;
- `processing` is not misreported as confirmed publication;
- reconciliation queries the stored remote post instead of creating a new one;
- rate limiting stops/defers confirmation without generating duplicates;
- mismatched remote IDs fail closed.

---

# 15. Duplicate Protection and Publishing State

Normal publishing must remain idempotent at the plugin layer for already successful/accepted/processing platform attempts.

Preserve:

- per-platform status storage;
- normal retry skipping of completed platforms;
- explicit force behavior for `Share Again`;
- the short per-post publishing lock;
- stale lock recovery;
- legacy `_social_publisher_status = webhook_sent` protection;
- Buffer reconciliation before retrying a stored `processing` state;
- partial/failure states that allow only appropriate retry behavior.

Do not change success-state semantics without auditing the UI, retry logic, logs, legacy compatibility, and production contract together.

---

# 16. Manual Publishing Model

The plugin currently does not automatically share on WordPress post publication.

Do not add or activate automatic publishing through hooks such as:

```text
save_post
publish_post
transition_post_status
wp_insert_post
future_to_publish
```

unless the user explicitly requests a product change.

Do not add WordPress cron/social scheduling as an implicit replacement for the current manual Share Now workflow.

---

# 17. Legacy Webhook Compatibility

The legacy webhook delivery method is a supported compatibility path.

Preserve unless explicitly deprecated:

- `tgsp_delivery_method = webhook`;
- webhook URL and secret options;
- server-side secret header behavior;
- connection-test behavior;
- `social_publish` payload contract;
- per-platform normalized response support;
- HTTP 2xx legacy responses without a `platforms` object being treated as accepted/unconfirmed rather than falsely published;
- `docs/webhook-response-schema.json` when the schema remains part of the shipped compatibility contract;
- the older `TGSP_Webhook` class when current source intentionally retains it for backward compatibility.

Do not delete apparently duplicate webhook code until actual call paths and compatibility requirements are verified.

---

# 18. Logging Rules

The existing custom table and historical rows must be preserved during normal upgrades.

Verify:

- table name remains stable;
- `dbDelta()` upgrades rather than drops/recreates the table;
- failed/important publishing events can be recorded even when optional debug logging is off where the source intentionally forces them;
- platform, status, remote ID, response code, attempt ID, and submitted text remain bounded/sanitized;
- common credential fields remain redacted from stored response content;
- logs do not become a secret-storage mechanism;
- reconciliation updates the matching attempt rather than rewriting unrelated rows.

A schema migration must preserve historical rows unless explicit data removal is approved.

---

# 19. Production Artifact Filtering

Production ZIPs must be built from a clean staging copy.

Exclude repository/development artifacts such as:

- `.git/`;
- Git metadata;
- tests and fixtures not required at runtime;
- IDE files;
- local environment files;
- logs;
- caches;
- temporary files;
- screenshots;
- old ZIPs;
- audit documents;
- development-only notes;
- `AGENTS.md`, `CODEX_HANDOFF.md`, and `PRODUCTION_HANDOFF.md` unless the user explicitly requires them in the distributable plugin.

Do not remove `readme.txt` or `docs/webhook-response-schema.json` merely because they are documentation. Determine whether the intended distribution still uses them before excluding them.

---

# 20. Permanent WordPress Plugin Identity

The internal plugin directory is permanently:

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

The internal directory must not contain a version number.

Do not introduce double nesting or a version wrapper inside the ZIP.

---

# 21. Required Static Validation

Before declaring a candidate code-clean:

## PHP

Run PHP syntax validation across every shipped PHP file.

## JavaScript

Run syntax validation against every shipped JavaScript file using an available appropriate parser/runtime.

## Archive integrity

Test the final ZIP for archive integrity.

## Static searches

Search the staged source and final ZIP for:

- hard-coded real API keys/tokens/secrets;
- accidental `.env` or credential files;
- `addToQueue` or unexpected scheduling behavior;
- automatic post-publish hooks;
- destructive database operations outside the explicit uninstall path;
- renamed protected options/meta keys/table identifiers;
- debug artifacts;
- repository metadata;
- duplicate plugin-root nesting;
- stale development package names/version strings;
- unexpected SEO/frontend-output hooks or strings such as `rank_math`, `wp_head`, `wp_footer`, canonical, robots, schema/JSON-LD, breadcrumbs, sitemap, Open Graph, or Twitter Card output.

A static search is evidence, not proof of runtime behavior.

---

# 22. SEO / Rank Math / Schema / Frontend Non-Interference Checks

TechGenyz Social Publisher is **not** the SEO authority for TechGenyz.com. Its production release must therefore prove that it does not interfere with the site's existing SEO stack or rendered frontend behavior.

For every production candidate, perform a source-level non-interference audit covering at minimum:

- document title handling;
- meta description output;
- canonical URLs;
- robots directives, including accidental `noindex` / `nofollow`;
- Rank Math hooks, filters, metadata, sitemap behavior, breadcrumbs, Open Graph/Twitter metadata, and schema/JSON-LD when Rank Math is installed/configured on the target site;
- any other `wp_head`, `wp_footer`, template, rewrite, query, or frontend-output hook that could change public pages;
- sitemap inclusion/exclusion behavior;
- breadcrumb output and destinations;
- schema/JSON-LD duplication or replacement;
- duplicate canonical, robots, Open Graph, Twitter Card, or structured-data output;
- unintended frontend CSS/JS enqueueing by the Social Publisher plugin;
- any change to public post/page status codes, rendering, permalinks, or indexability.

Mandatory static searches should include relevant patterns such as:

```text
rank_math
wp_head
wp_footer
rel=\"canonical\"
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
```

Interpret hits in context; a search result alone is not a defect.

## Rank Math authority

When Rank Math is the site's configured SEO system:

- Rank Math must remain authoritative for SEO title, meta description, canonical, robots, sitemap, breadcrumbs, Open Graph/Twitter metadata, and schema output;
- Social Publisher must not overwrite, suppress, duplicate, or replace those outputs unless an explicitly approved project requirement says otherwise;
- Social Publisher may read/use an article URL for social publishing, but must not mutate the page's SEO canonical as a side effect;
- social-caption generation or publishing must not inject competing frontend schema or SEO metadata.

## Staging/live non-hamper verification

When an appropriate staging or live environment is available, inspect representative rendered public pages with the candidate active and verify, as applicable:

1. title and meta description remain correct;
2. canonical remains correct and singular;
3. robots directives remain correct;
4. Rank Math schema/JSON-LD remains present, valid, and non-duplicated;
5. Open Graph/Twitter metadata remains authoritative and non-duplicated;
6. breadcrumbs continue to render and link correctly where used;
7. sitemap/indexability behavior remains unchanged;
8. no Social Publisher admin asset leaks onto public pages unnecessarily;
9. no new frontend warnings, PHP errors, broken markup, redirect changes, or visible output are introduced;
10. representative post/page rendering is otherwise unchanged by activating/upgrading the plugin.

Where practical, compare the rendered candidate page against a known-good baseline or the prior approved production version.

If staging/live access is unavailable, mark these rendered checks **NOT VERIFIED IN RUNTIME**. Do not claim Rank Math, schema, sitemap, canonical, or public-page non-interference passed solely because static inspection found no obvious conflict.

---

# 23. Runtime and External Integration Checks

Static inspection cannot prove live service behavior.

When credentials and an appropriate staging/live environment are available, verify as applicable:

1. Settings page loads without PHP/JS errors.
2. Saved secrets remain masked and are not exposed to browser source/localized data.
3. Buffer connection/channel discovery succeeds.
4. Existing saved channel mappings remain intact after upgrade.
5. OpenAI generates separate valid captions for selected platforms.
6. Generated captions remain editable before publishing.
7. A controlled test article can publish to each mapped platform through Buffer `shareNow`.
8. Buffer processing status reconciles to a final result without duplicate creation.
9. A normal retry skips a completed platform.
10. A failed platform can be retried without re-sending completed platforms.
11. `Share Again` clearly behaves as the deliberate force path.
12. X output contains one complete canonical URL and fits the plugin's weighted limit.
13. Legacy webhook connection test works when the legacy path is still supported.
14. Legacy webhook responses normalize correctly.
15. Historical statuses/log rows remain visible/preserved after upgrade.
16. Representative public posts/pages retain their expected title, meta description, canonical, robots directives, Rank Math schema/JSON-LD, Open Graph/Twitter metadata, breadcrumbs, and indexability.
17. No duplicate SEO meta/schema is introduced by the Social Publisher candidate.
18. No Social Publisher admin-only asset or unintended visible output appears on the public frontend.
19. Sitemap behavior and representative public URLs remain unchanged unless an explicitly approved release requirement says otherwise.

If any of these checks were not actually run, mark them **NOT VERIFIED IN RUNTIME**. Never report them as passed based only on source inspection.

---

# 24. Production ZIP Build Procedure

For every production release:

```text
CURRENT PROJECT SOURCE
        ↓
Read AGENTS.md
        ↓
Read PRODUCTION_HANDOFF.md in full
        ↓
Read CODEX_HANDOFF.md
        ↓
Inspect current source
        ↓
Resolve Development Version authority
        ↓
Resolve Last Production Version from ledger
        ↓
Calculate Candidate Production Version
        ↓
Run source/security/regression audit
        ↓
Create clean temporary staging copy
        ↓
Copy runtime-required production files only
        ↓
Apply Candidate Production Version only in staging
        ↓
Create exactly one root:
techgenyz-social-publisher/
        ↓
Build versioned outer ZIP
        ↓
Inspect actual ZIP contents
        ↓
Run final static/archive validation
        ↓
Record outstanding live integration checks
        ↓
PASS / FAIL
        ↓
Update production ledger only after PASS
```

Never build production by blindly zipping the development repository.

---

# 25. Inspect the Generated ZIP

After packaging, inspect the actual archive rather than assuming staging was copied correctly.

Confirm:

- exactly one top-level plugin root;
- root name = `techgenyz-social-publisher/`;
- main plugin file exists at the expected path;
- `uninstall.php` is present when the current release uses it;
- all required `includes/`, `includes/social/`, and `assets/` files are present;
- shipped documentation is intentional;
- no development/repository artifacts are present;
- no double nesting exists;
- version metadata is internally consistent;
- no real credentials are packaged.

Generate a cryptographic hash of the final ZIP when tooling is available and report it.

---

# 26. Final Production Verdict

Use these categories:

## A. CODE-LEVEL BLOCKER

A verified defect in source/package/security/data safety/integration logic that makes release unsafe.

Verdict: **NOT READY FOR PRODUCTION**.

## B. AUTHORITATIVE RELEASE STATE MISSING

Development-version authority or production-ledger authority is missing/ambiguous.

Verdict: **PRODUCTION PACKAGING STOPPED — VERSION AUTHORITY REQUIRED**.

## C. STAGING/LIVE VERIFICATION REQUIRED

Static/code/package gates pass, but one or more external-service behaviors cannot be proven without a configured environment.

State exactly what remains unverified. Do not pretend it passed.

## D. PASS

All mandatory code/package gates pass, authoritative version state exists, and any required runtime gates for the intended release have been completed or explicitly classified according to the release policy.

Only then may the package be called **READY FOR PRODUCTION**.

---

# 27. Production Ledger Update Rule

Update the Last Production Version only after the candidate passes the required release gates.

Record at minimum:

```text
Last Production Version:
Last Production ZIP:
Release Date:
SHA-256:
```

Keep historical release entries.

A failed candidate must not advance the ledger.

Do not rewrite permanent production requirements merely to make a candidate pass.

---

# 28. Git Safety

Codex MUST NOT automatically:

- commit;
- push;
- create a Git commit;
- rewrite Git history;
- force-push;

unless the user explicitly requests it.

A production ZIP may be generated without committing.

If a commit is appropriate after approved work, provide a recommended commit message and let the user perform the Git operation unless explicitly instructed otherwise.

---

# 29. Mandatory Final Production Report

Every production ZIP request must produce a report containing at minimum:

```text
Executive Summary
Version Authority Audit
Development Version
Last Production Version
Candidate Production Version
WordPress Plugin Identity
Production ZIP Structure
Core Workflow Regression Audit
Admin UI / Posts Screen Audit
REST API / Permission Audit
OpenAI Integration Audit
Buffer Integration Audit
Duplicate Protection / Retry Audit
Processing / Reconciliation Audit
X Caption / Canonical URL Audit
SEO / Rank Math / Schema / Frontend Non-Interference Audit
Legacy Webhook Compatibility Audit
Settings / Stored Configuration Audit
Post Metadata / Historical State Audit
Log Table / Database Audit
Uninstall / Data Safety Audit
Security / Secret Handling Audit
Performance / External Request Audit
Debug / Development Artifact Audit
Production Package Audit
Static Validation
Runtime / Staging Verification
Issues Found
Final Verdict
Production ZIP Path
SHA-256 (when available)
```

For every issue report:

```text
Severity:
Impact:
Recommendation:
Production blocker: YES/NO
```

---

# 30. Final Response Requirements

When the production command is executed, the final response must include:

1. Development Version or a clear authority blocker.
2. Last Production Version or a clear ledger blocker.
3. Candidate Production Version when it can be calculated.
4. Complete production-readiness report.
5. Final verdict.
6. Production ZIP when release gates allow it.
7. Confirmation of internal ZIP structure.
8. Confirmation of data/uninstall behavior.
9. Confirmation that approved Social Publisher functionality was preserved.
10. Remaining staging/live verification items.
11. SEO/Rank Math/schema/frontend non-interference result, including any rendered checks that remain unverified.

Do not merely say "ZIP created."

---

# 31. Core Principle

```text
Inspect → Preserve → Implement → Validate → Audit → Stage → Package → Inspect Artifact → Report → Release
```

The project must remain predictable across sessions. The handoff system exists so approved functionality and release safety are not dependent on chat memory.
