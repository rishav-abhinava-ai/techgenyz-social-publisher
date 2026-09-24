=== TechGenyz Social Publisher ===
Contributors: techgenyz
Tags: social media, webhook, n8n, publishing
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Generate editable captions with OpenAI and immediately publish selected channels through Buffer shareNow.

== Description ==

TechGenyz Social Publisher adds a visible Social column to Posts > All Posts and keeps the Share Now control in the post editor. Both controls reuse the same popup, where an editor can select Facebook, LinkedIn, and X, generate separate captions with OpenAI, review or edit the exact outgoing text, and immediately publish through mapped Buffer channels.

Buffer publishing uses the current GraphQL createPost mutation with schedulingType automatic and mode shareNow. It does not use addToQueue, WordPress scheduling, or a second Buffer action.

Version 1.6.1 preserves the version 1.4.0/1.5.0 webhook URL, secret, default message, post metadata, duplicate protection, and historical log table.

== Installation ==

1. Back up WordPress and the database.
2. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
3. Replace the existing version when WordPress asks, then activate it.
4. Open Social Publisher in WordPress Admin and select Buffer API as the delivery method.
5. Save the server-side OpenAI and Buffer API keys.
6. Click Test Buffer and load channels, map Facebook, LinkedIn, and X, then save settings.
7. Open Posts > All Posts, use Share Now in the Social column, generate and review captions, then click Share Now.

The database upgrade runs through dbDelta on activation or the next admin request.

== Webhook configuration ==

The receiver can be self-hosted n8n or any HTTPS endpoint that accepts JSON. When a secret is configured it is sent in the X-Techgenyz-Webhook-Key request header.

Connection tests use event connection_test. Publishing requests use event social_publish and include attempt_id, post fields, a captions object, and facebook/linkedin/x flags in a platforms object.

For confirmed per-platform status, return HTTP 2xx and JSON matching docs/webhook-response-schema.json.

A legacy HTTP 2xx response without a platforms object remains compatible. It is displayed as Accepted because the plugin cannot verify publication without platform results.

== Caption placeholders ==

{title}, {excerpt}, {url}, {author}, {category}, {featured_image}, {site_name}

X captions preserve the canonical article URL and use a practical 280-character weighted envelope in which each detected URL counts as 23 characters.

== Duplicate behavior ==

Successful or accepted platforms are skipped on a normal retry. Failed platforms are requested again. Share Again explicitly forces the selected platforms and can create duplicate social posts; WordPress asks for confirmation first.

Legacy posts marked webhook_sent remain protected and require Share Again.

== Privacy and security ==

Only users who can edit a post can share it. Settings and connection tests require manage_options. WordPress REST nonces protect browser requests. The webhook URL and secret stay server-side and are never localized to JavaScript. Saved secrets are masked on the settings page, and common credential fields are redacted from logs.

== Known limitations ==

* Buffer and OpenAI accounts, API access, connected channels, permissions, quotas, and billing are external requirements.
* Buffer may initially report a shareNow post as processing; publication is only labelled successful when Buffer reports a sent/published status.
* The legacy webhook workflow remains available for backward compatibility.
* Social previews depend on public article/image URLs and the site's Open Graph metadata.
* Platform permissions, limits, pricing, and token expiration are controlled by each network.
* Live publishing requires end-to-end tests with the configured workflow and accounts.

== Changelog ==

= 1.6.1 =

* Guaranteed the server-derived canonical article URL in X outbound text.
* Added X-aware 23-character URL weighting while preserving complete URLs.
* Added final per-platform submitted text to the existing publishing log table.
* Made Buffer the default only when no delivery-method option exists; explicit legacy webhook selections remain unchanged.

= 1.6.0 =

* Added a Share action to Posts > All Posts and a review/edit popup.
* Added server-side OpenAI Responses API caption generation.
* Added Buffer GraphQL channel discovery and mapping.
* Added immediate createPost publishing with mode shareNow; addToQueue is never used.
* Preserved the v1.5 webhook workflow as a selectable legacy delivery method.

= 1.5.0 =

* Added modular social manager, payload builder, caption generator, webhook client, and response normalizer.
* Added separate platform caption templates and enable controls.
* Added webhook connection tests and delivery status tracking.
* Added per-platform editor results and retry behavior.
* Upgraded the existing log table without removing historical rows.
* Preserved version 1.4.0 settings, metadata, route, and logs.

= 1.4.0 =

* Original secured webhook publishing workflow.
