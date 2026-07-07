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

Interactive Quiz & Poll replaces third-party quiz/poll embeds (Typeform, Opinion Stage, PollDaddy and friends) with two native Gutenberg blocks. Everything runs on your own site: content is authored as blocks, responses are recorded in your database, and every submission is validated and tallied on the server.

* **Poll** — one question with several answers, an aggregate vote breakdown shown after voting, and an optional explanation revealed afterwards.
* **Quiz** — one or more questions, each answer flagged correct or incorrect, with per-question explanations, a "your answer" marker, and a final score.

Quizzes and polls are authored once in their own admin area and dropped into any post or page with an embed block. The content is a single source of truth, while **responses are tracked separately per post** — so the same quiz embedded on two posts keeps two independent tallies.

= Built the right way =

* **Server-authoritative.** The browser only sends *which answer was picked*. Correctness, tallying and results are decided on the server against the quiz's own blocks — forged or garbage IDs are rejected.
* **Race-safe counts.** Responses are counted with atomic SQL increments, so simultaneous submissions never lose updates.
* **Interactivity API.** The front end uses WordPress's native Interactivity API (no jQuery, no heavy framework) for instant, accessible interactions.
* **Privacy-friendly abuse guards.** Per-visitor de-duplication (a random first-party cookie) and per-IP rate limiting (salted, expiring hashes — the raw IP is never stored). You can require login for voting with a single filter.
* **Nothing phones home.** No external requests, no tracking, no upsell walls.

= Answers and explanations =

* Mark the correct answer(s) on a quiz; the front end highlights the correct option in green and a wrong pick in red, with a "Your answer" marker.
* Add an **Answer Explanation** shown after responding — and scope it to *any answer*, *only when correct*, or *only when incorrect*, so a quiz can give different feedback for a right vs. wrong answer.
* Quiz explanations and the correct answer are returned from the server only *after* the visitor responds, so they never appear in the initial page source.
* Optionally let visitors **retake** a quiz (a per-quiz setting, off by default).

= Results for editors =

* Every quiz/poll edit screen has a **Results** panel with the per-answer breakdown (and the correct answer marked, for quizzes).
* A dedicated **Results** page (under Quizzes and Polls) lists totals and, per item, a **"responses by post"** table ranked by volume — so you can see which posts drive engagement for a shared quiz — plus a one-click **CSV export** of the full per-post/per-answer data.
* Admin list columns show total responses and last activity, with a capability-gated **Reset counts** action (which also clears visitors' locally-saved answers on their next visit).

= Developer friendly =

* Filters to tune behaviour: `d9qp_abuse_guards_enabled`, `d9qp_rate_limit_per_minute`, `d9qp_dedup_window`, `d9qp_require_login`.
* Data removal on uninstall is opt-in — define `D9QP_DELETE_DATA_ON_UNINSTALL` as `true` to purge quizzes, polls and their responses.

== Installation ==

1. Upload the `interactive-quiz-poll` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin.
3. Create a quiz under **Quizzes → Add New** or a poll under **Polls → Add New**, add your questions and answers, and publish.
4. In any post or page, add the **Quiz** or **Poll** block and choose the item you created.
5. View responses under **Quizzes → Results** (or **Polls → Results**), or in the **Results** panel on the item's edit screen.

== Frequently Asked Questions ==

= Can the same quiz or poll be used on more than one post? =

Yes. Author it once and embed it anywhere. The questions are a single source of truth, but responses are tracked **per post**, so each post gets its own independent tally and each visitor can respond once per post.

= Where do I see the results? =

On the quiz/poll edit screen there's a **Results** panel, and there's a dedicated **Results** page under both Quizzes and Polls. The detail view shows a per-post breakdown ranked by responses and lets you export everything to CSV.

= Can people cheat the vote counts? =

Anonymous polls can never be made perfectly fraud-proof, but the plugin ships real guards: server-side de-duplication per visitor and per-IP rate limiting, plus an optional logged-in-only mode via the `d9qp_require_login` filter. The "one response" rule is enforced on the server, not just in the browser.

= Can visitors retake a quiz? =

Only if you allow it. Each quiz has an "Allow visitors to retake" setting (off by default); when enabled, a Retake button appears on the results and clears that visitor's attempt.

= Does it work without JavaScript frameworks? =

Yes. It uses WordPress's built-in Interactivity API, which ships with WordPress 6.5+. There is no jQuery, React runtime or third-party library on the front end.

= Does the quiz reveal the correct answers in the page source? =

No. Per-question explanations and the correct answer are returned from the server only after the visitor responds, so they are not present in the initial HTML.

= Does it change my content or send data anywhere? =

No. It stores quizzes and polls as normal posts, records counts in post meta, and makes no external requests.

== Changelog ==

= 1.0.0 =
* Initial release.
* Quiz and Poll blocks (container + embed) with nested question / answer authoring.
* Anonymous responding via REST with full server-side validation against the authored blocks.
* Atomic, race-safe response counts, tracked independently per embedding post.
* Poll results breakdown (bars and percentages); quiz correct/incorrect highlighting with a "Your answer" marker and a score result card.
* Answer explanations, scoped to any / correct / incorrect answers, revealed after responding.
* Optional visitor retake (per-quiz setting).
* Results meta box on the edit screen, a dedicated Results admin page with a per-post breakdown and CSV export, admin list columns, and a Reset counts action.
* Privacy-friendly per-visitor de-duplication and per-IP rate limiting; optional logged-in-only mode.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
