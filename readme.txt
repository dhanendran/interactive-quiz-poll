=== Interactive Quiz & Poll ===
Contributors: dhanendran
Tags: quiz, poll, blocks, interactivity, survey
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

First-party quiz and poll blocks built on native Gutenberg and the WordPress Interactivity API — no third-party embeds, no data leaving your site.

== Description ==

Interactive Quiz & Poll replaces third-party quiz/poll embeds (Typeform, Opinion Stage, PollDaddy and friends) with two native Gutenberg blocks. Everything runs on your own site: content is authored as blocks, votes are recorded in your database, and results are validated and tallied on the server.

* **Poll** — one question with several answers, an aggregate vote breakdown shown after voting, and an optional explanation revealed afterwards.
* **Quiz** — one or more questions, each answer flagged correct or incorrect, with per-question feedback and a final score.

Quizzes and polls are authored once in their own admin area and dropped into any post or page with an embed block, so a single quiz can appear in many places while keeping one source of truth and one tally.

= Built the right way =

* **Server-authoritative.** The browser only sends *which answer was picked*. Correctness, tallying and results are decided on the server against the quiz's own blocks — forged or garbage IDs are rejected.
* **Race-safe counts.** Votes are counted with atomic SQL increments, so simultaneous votes never lose updates.
* **Interactivity API.** The frontend uses WordPress's native Interactivity API (no jQuery, no heavy framework) for instant, accessible interactions.
* **Privacy-friendly abuse guards.** Optional per-visitor de-duplication and per-IP rate limiting use salted, expiring hashes — the raw IP is never stored. You can require login for voting with a single filter.
* **Nothing phones home.** No external requests, no tracking, no upsell walls.

= Developer friendly =

* Filters to tune behaviour: `d9qp_abuse_guards_enabled`, `d9qp_rate_limit_per_minute`, `d9qp_dedup_window`, `d9qp_require_login`.
* Data removal on uninstall is opt-in — define `D9QP_DELETE_DATA_ON_UNINSTALL` to `true` to purge quizzes, polls and their responses.

== Installation ==

1. Upload the `interactive-quiz-poll` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin.
3. Create a quiz under **Quizzes → Add New** or a poll under **Polls → Add New**, add your questions and answers, and publish.
4. In any post or page, add the **Quiz** or **Poll** block and choose the item you created.

== Frequently Asked Questions ==

= Can people cheat the vote counts? =

Anonymous polls can never be made perfectly fraud-proof, but the plugin ships real guards: server-side de-duplication per visitor and per-IP rate limiting (both using expiring, salted hashes), plus an optional logged-in-only mode via the `d9qp_require_login` filter. The "one vote" rule is enforced on the server, not just in the browser.

= Does it work without JavaScript frameworks? =

Yes. It uses WordPress's built-in Interactivity API, which ships with WordPress 6.5+. There is no jQuery, React runtime or third-party library on the front end.

= Does the quiz reveal the correct answers in the page source? =

No. Per-question feedback and the correct answer are returned from the server only after the visitor responds, so they are not present in the initial HTML.

= Does it change my content or send data anywhere? =

No. It stores quizzes and polls as normal posts, records counts in post meta, and makes no external requests.

== Changelog ==

= 1.0.0 =
* Initial release: Quiz and Poll blocks (container + embed), nested question/answer authoring, server-side validation, atomic vote counts, per-question feedback, Interactivity API front end, admin response columns with a reset action, rate limiting and de-duplication.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
