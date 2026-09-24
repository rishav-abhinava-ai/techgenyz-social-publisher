# TechGenyz Social Publisher — CODEX HANDOFF

Last updated: 2026-09-14

Version authority notice: this file does not define the current Development Version or Last Production Version. Historical/baseline version numbers are context only.

## Purpose

This document records implementation context, architecture, compatibility decisions, protected identifiers, and historical behavior for TechGenyz Social Publisher.

It exists so future Codex sessions can understand why the current implementation works the way it does and avoid accidentally removing compatibility or safety behavior.

This document must never override the actual current source or the production ledger in `PRODUCTION_HANDOFF.md`.

---

# 1. Inspected Baseline

The handoff was created from the uploaded test build:

```text
techgenyz-social-publisher-1.6.1-test-build.zip
```

Inspected source metadata:

```text
Plugin Name: TechGenyz Social Publisher
Plugin header version: 1.6.1
TGSP_VERSION: 1.6.1
readme.txt Stable tag: 1.6.1
Requires WordPress: 6.2+
Requires PHP: 7.4+
Text Domain: techgenyz-social-publisher
```

These values describe the inspected baseline only. They are not a production ledger.

---

# 2. Permanent Plugin Identity

The intended WordPress plugin identity is:

```text
Internal plugin directory:
techgenyz-social-publisher/

Main plugin file:
techgenyz-social-publisher/techgenyz-social-publisher.php

Plugin basename:
techgenyz-social-publisher/techgenyz-social-publisher.php
```

Production upgrades must preserve this identity so WordPress treats later packages as upgrades to the same plugin.

---

# 3. Current High-Level Architecture

```text
techgenyz-social-publisher.php
        │
        ├── Logger / database table
        ├── Security helpers
        ├── Legacy webhook compatibility class
        ├── Caption generator
        ├── Social payload builder
        ├── Response normalizer
        ├── Active webhook transport
        ├── OpenAI client
        ├── Buffer client
        ├── Social manager
        ├── Settings UI
        └── Meta box / Posts screen / REST UI
```

Current project layout:

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

---

# 4. Core Product Model

TechGenyz Social Publisher is currently an **editor-driven immediate social publishing tool**.

The intended flow is:

```text
Published WordPress article
        ↓
Editor opens Share action
        ↓
Select Facebook / LinkedIn / X
        ↓
Generate separate captions with OpenAI
        ↓
Review / edit exact outgoing text
        ↓
Share Now
        ↓
Buffer shareNow (default)
        OR
legacy webhook (compatibility mode)
        ↓
Per-platform result/status stored
        ↓
Processing attempts reconciled without duplicate creation
```

The plugin does not currently auto-share simply because a post becomes published.

No normal product change should introduce automatic sharing through post-status hooks unless explicitly approved.

---

# 5. Admin Entry Points

The plugin exposes the sharing workflow in two primary editorial locations.

## Post editor

A side meta box titled:

```text
Social Publisher
```

It displays publishing status and an action such as:

```text
Share Now
Share Again
View Status
Try Again
Open / Retry
```

## Posts → All Posts

The plugin adds:

- a `Social` column;
- per-post social state;
- a row action;
- the same modal workflow used by the editor meta box.

Only published standard WordPress `post` entries with appropriate edit permissions are eligible for these actions.

---

# 6. Shared Publishing Modal

The modal currently provides:

- platform selection for enabled Facebook, LinkedIn, and X channels;
- `Generate Social Content with OpenAI`;
- a separate editable caption field per platform;
- per-platform regeneration controls;
- character/status feedback;
- a final `Share Now` action.

Human review is part of the product design. OpenAI generation does not directly publish.

---

# 7. REST API Architecture

REST namespace:

```text
tgsp/v1
```

Current routes include:

```text
POST /tgsp/v1/share/{id}
POST /tgsp/v1/generate/{id}
POST /tgsp/v1/buffer-status/{id}
GET  /tgsp/v1/publishing-status/{id}
POST /tgsp/v1/test-buffer
GET  /tgsp/v1/buffer-channels
POST /tgsp/v1/test-webhook
```

Authorization model:

- post-specific sharing/generation requires a logged-in user who can `edit_posts` and `edit_post` for that post;
- Buffer status/polling also verifies the WordPress REST nonce in addition to post authorization;
- administrative Buffer/webhook connection routes require `manage_options`.

Do not make these routes public or weaken capability checks without explicit approval.

---

# 8. Published-Post Constraint

The active share/generation workflow resolves the requested post and requires it to be a published WordPress post.

The UI similarly limits normal sharing actions to published `post` entries.

Do not broaden the post types or statuses implicitly. If pages, custom post types, drafts, scheduled posts, or private posts are later supported, update authorization, payload, UI, and production regression rules together.

---

# 9. OpenAI Caption Generation

Current OpenAI implementation:

```text
Endpoint: https://api.openai.com/v1/responses
Default configured model: gpt-6-luna
```

API key lookup order:

1. `TGSP_OPENAI_API_KEY` constant when defined/non-empty;
2. saved option `tgsp_openai_api_key`.

Generation input includes sanitized article data such as:

- title;
- excerpt;
- trimmed article content;
- canonical permalink;
- author;
- category;
- requested platforms.

The request uses structured JSON-schema output for exactly the requested platforms.

Current caption expectations include:

- promotional social copy rather than a full article summary;
- article-supported claims only;
- separate platform tone guidance;
- exact canonical article URL once in each requested caption;
- tighter X output suitable for the platform's limit.

The returned data is validated before being returned to the editor.

---

# 10. X Caption Invariant

X has dedicated outbound normalization in `TGSP_Caption_Generator`.

Important behavior:

- the server-derived canonical article URL is authoritative;
- existing occurrences of the same canonical URL are removed before final composition;
- the complete canonical URL is appended once;
- detected URLs count as 23 weighted characters;
- non-URL text is trimmed when required;
- final practical weighted length is capped at 280;
- the canonical URL must not be truncated.

This logic exists both to protect link integrity and to prevent an edited/generated caption from producing an invalid X payload.

Do not replace it with a raw `strlen()` limit or client-only validation.

---

# 11. Buffer Is the Primary Delivery Path

Current default behavior:

```text
tgsp_delivery_method = buffer
```

The default applies when no saved delivery-method option exists. An explicit saved legacy webhook selection must remain preserved.

Buffer endpoint:

```text
https://api.buffer.com
```

The active Buffer client uses GraphQL.

Immediate publish semantics:

```text
schedulingType = automatic
mode = shareNow
needsApproval = false
saveToDraft = false
```

The plugin intentionally does **not** use `addToQueue`.

The plugin also marks its request source as:

```text
techgenyz-wordpress
```

and currently sends `aiAssisted = true`.

---

# 12. Buffer Organization and Channel Mapping

The plugin can discover Buffer organizations and Publish channels.

Saved mapping identifiers:

```text
tgsp_buffer_organization_id
tgsp_buffer_channel_facebook
tgsp_buffer_channel_linkedin
tgsp_buffer_channel_x
```

Each platform must have an explicit mapped Buffer channel before publishing.

Do not collapse the three mappings into one generic channel setting.

---

# 13. Platform-Specific Buffer Payloads

The Buffer publishing path uses article metadata and platform-specific metadata.

Current behavior includes:

- Facebook link attachment metadata;
- LinkedIn link attachment metadata;
- X/Twitter AI-generated marker metadata;
- article URL/title/excerpt;
- featured-image thumbnail when available.

The article URL must be valid HTTP/HTTPS before social publishing.

---

# 14. Buffer Processing and Reconciliation

Buffer may accept a `shareNow` request before final publication is confirmed.

Current plugin behavior distinguishes:

```text
processing
```

from confirmed:

```text
published / sent
```

The Social Manager stores the Buffer remote post ID and later queries that exact remote post.

Reconciliation is specifically designed to avoid creating another social post while status is uncertain.

Remote IDs are validated, matched to the currently stored processing attempt, and queried in a bounded GraphQL request.

Rate-limit and lookup failures do not automatically create a replacement post.

This is a major duplicate-protection invariant.

---

# 15. Duplicate Protection and Retry Semantics

Per-platform state is stored in:

```text
_social_publisher_platform_statuses
```

A normal retry skips platform states that are already effectively complete or safely in progress, including current successful/accepted/processing states.

Failed platforms remain eligible for retry.

When a stored Buffer platform is still `processing`, the plugin attempts reconciliation before deciding whether another publish is safe.

If all enabled platforms are already complete, normal publishing returns an already-sent condition.

`Share Again` sends an explicit force flag and is the deliberate path that can create duplicates.

Legacy posts using:

```text
_social_publisher_status = webhook_sent
```

also remain protected and require the force path before re-sharing.

---

# 16. Publishing Lock

The plugin uses:

```text
_social_publisher_lock
```

as a short-lived per-post lock to reduce overlapping/double-send requests.

A stale lock older than approximately 120 seconds is removed before attempting a new atomic lock.

The lock is released after the publishing attempt, including through the `finally` path in the active REST share workflow.

Do not replace this with a non-atomic read/write pattern without understanding the concurrency consequences.

---

# 17. Aggregate Publishing State

Additional historical/summary post metadata:

```text
_social_publisher_status
_social_publisher_sent_time
```

The overall status supports UI/history compatibility while per-platform details live in `_social_publisher_platform_statuses`.

Current UI states include concepts such as:

```text
Never shared
Processing…
Published ✓
Failed
Partial
```

These identifiers and semantics interact with retry behavior. Migrations must preserve old records.

---

# 18. Legacy Webhook Compatibility

The older webhook workflow is intentionally retained as a selectable delivery method.

Relevant settings include:

```text
tgsp_webhook_url
tgsp_webhook_secret
tgsp_webhook_connection_status
tgsp_webhook_last_success
tgsp_message_format
```

The active modular transport is:

```text
TGSP_Social_Webhook
```

The older class:

```text
TGSP_Webhook
```

is still loaded and explicitly marked as retained for backward compatibility.

Do not delete it solely because another webhook class exists.

Current active webhook behavior uses:

- `wp_safe_remote_post()`;
- redirection disabled;
- JSON body;
- optional `X-Techgenyz-Webhook-Key` header;
- bounded response body;
- connection-status tracking.

The documented response contract is:

```text
docs/webhook-response-schema.json
```

When a webhook returns platform-specific data, it is normalized per platform.

A legacy HTTP 2xx response without a platform object remains compatible and is treated as accepted/unconfirmed rather than falsely confirmed as published.

---

# 19. Social Payload Builder

The active payload/article-value layer derives reusable server-side article values including:

```text
title
excerpt
content
url
author
category
featured_image
site_name
```

Outbound article/featured-image URLs are restricted to structurally valid HTTP/HTTPS URLs.

The webhook payload includes an attempt ID, article data, selected platforms, and captions.

Keep canonical article data server-derived rather than trusting arbitrary browser-submitted URLs.

---

# 20. Settings Architecture

Settings page is implemented by:

```text
TGSP_Settings
```

Important settings currently include:

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

Blank secret fields preserve the previously stored key/secret rather than unintentionally clearing it.

The settings UI masks saved secrets.

---

# 21. Caption Templates and Legacy Message Format

Each platform can have its own caption template.

If a platform template is empty, the caption generator checks the legacy:

```text
tgsp_message_format
```

before falling back to platform defaults.

This preserves earlier workflow configuration.

Supported placeholders include:

```text
{title}
{excerpt}
{url}
{author}
{category}
{featured_image}
{site_name}
```

Do not remove the legacy format fallback without a migration plan.

---

# 22. Secret Handling

OpenAI and Buffer credentials may be stored as WordPress options, but the preferred server-defined alternatives are supported:

```text
TGSP_OPENAI_API_KEY
TGSP_BUFFER_API_KEY
```

Secrets must remain server-side.

The admin JavaScript receives REST paths and a WordPress REST nonce, not service API keys.

Do not add API credentials to:

- localized JavaScript;
- HTML data attributes;
- REST responses;
- logs;
- handoff files;
- production reports.

---

# 23. Logging and Database Schema

Custom table:

```text
{$wpdb->prefix}social_publish_logs
```

Current schema stores fields including:

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

Indexes include post/status/platform/attempt-oriented access.

Table creation/upgrades use WordPress `dbDelta()`.

`tgsp_db_version` is updated to the current plugin release metadata version after table creation/upgrade.

Existing rows are expected to survive upgrades.

Common credential-like keys are redacted from stored response text, and response data is bounded before storage.

Version 1.6.1 added submitted outbound text to the existing history model rather than replacing history.

---

# 24. Activation / Upgrade Behavior

Activation creates or upgrades the log table.

On admin boot, if:

```text
TGSP_VERSION !== get_option('tgsp_db_version')
```

the table upgrade routine runs again through `dbDelta()`.

The normal upgrade path must remain non-destructive to historical log data and saved settings.

---

# 25. Uninstall Contract

Current uninstall behavior is preserve-by-default.

Destructive cleanup executes only when:

```text
TGSP_REMOVE_DATA === true
```

is defined at uninstall time.

When the flag is absent or false, uninstall returns without deleting plugin data.

When explicitly enabled, the uninstall routine deletes the plugin-owned options, the four documented post-meta keys, and the custom log table.

Deactivation is not the destructive cleanup path.

---

# 26. Current Source Has No Dedicated Development Version Constant

The inspected baseline defines:

```text
TGSP_VERSION = 1.6.1
```

but does not yet define:

```text
TGSP_DEVELOPMENT_VERSION
```

The global TechGenyz production workflow requires development and production version authority to remain separate.

Therefore the repository should introduce a dedicated `TGSP_DEVELOPMENT_VERSION` before the first production release governed by the new three-file handoff system.

Until then, production packaging must not pretend a dedicated development-version authority exists.

---

# 27. Production Ledger Is Not Yet Seeded

At creation of this handoff, no authoritative Social Publisher `PRODUCTION_HANDOFF.md` ledger existed before these files.

The uploaded filename contains `1.6.1-test-build`, which is not proof of the Last Production Version.

Therefore the initial production ledger is deliberately marked **NOT ESTABLISHED**.

The user must explicitly establish the last approved production release (or explicitly establish that there has been no prior production release) before automatic production-version progression can begin.

---

# 28. Historical Changelog Context From Current readme.txt

These entries are implementation context only.

## 1.4.0

- Original secured webhook publishing workflow.

## 1.5.0

- Added modular social manager, payload builder, caption generator, webhook client, and response normalizer.
- Added separate platform caption templates and enable controls.
- Added webhook connection tests and delivery status tracking.
- Added per-platform results/retry behavior.
- Upgraded the existing log table without removing historical rows.
- Preserved 1.4.0 settings, metadata, route, and logs.

## 1.6.0

- Added sharing from Posts → All Posts plus shared review/edit modal.
- Added server-side OpenAI Responses API generation.
- Added Buffer GraphQL channel discovery/mapping.
- Added immediate `createPost` with `shareNow`.
- Explicitly avoided `addToQueue`.
- Preserved 1.5 webhook workflow as a selectable legacy method.

## 1.6.1

- Guaranteed server-derived canonical article URL in X outbound text.
- Added practical X URL weighting where detected URLs count as 23 characters.
- Added submitted outbound text to existing publishing logs.
- Made Buffer the default only when no delivery-method option exists; explicit legacy webhook selections remain preserved.

Do not use these historical version numbers as production-ledger authority.

---

# 29. Known External Dependencies and Limitations

Runtime behavior depends on external systems outside the plugin repository:

- OpenAI API access, model availability, quota, billing, and credentials;
- Buffer API access, organizations, mapped channels, permissions, quotas, rate limits, and credentials;
- Facebook/LinkedIn/X platform-side permissions and account state;
- public crawlability of article and image URLs;
- site Open Graph/social metadata for downstream previews;
- optional legacy webhook/n8n receiver behavior.

Static source inspection cannot prove these services are operational.

---

# 30. Development Rules for Future Changes

When adding or changing functionality:

1. preserve manual review unless product requirements explicitly change;
2. preserve immediate Buffer semantics unless explicitly redesigned;
3. preserve duplicate protection;
4. preserve per-platform state/history;
5. preserve legacy data identifiers or migrate deliberately;
6. preserve legacy webhook compatibility unless explicitly deprecated;
7. keep service secrets server-side;
8. keep REST permissions scoped;
9. keep data deletion restricted to explicit uninstall authority;
10. update this handoff when architecture or protected behavior changes;
11. update `PRODUCTION_HANDOFF.md` when release acceptance requirements change.

---

# 31. Core Context Summary

```text
Manual editorial share
        ↓
OpenAI generates editable platform-specific captions
        ↓
Editor reviews/edits
        ↓
Social Manager
        ├── Buffer immediate shareNow (default)
        └── Legacy webhook (compatibility)
        ↓
Per-platform status + remote IDs + attempt ID
        ↓
Buffer reconciliation where needed
        ↓
Duplicate-safe retry behavior
        ↓
Historical log table + post metadata preserved
```

This is the implementation model future work must understand before modifying the project.
