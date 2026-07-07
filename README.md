# Interactive Quiz & Poll

First-party quiz and poll [Gutenberg](https://developer.wordpress.org/block-editor/) blocks built on the native [WordPress Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/) — a free, self-hosted replacement for third-party quiz/poll embeds (Typeform, Opinion Stage, PollDaddy).

Everything runs on your own site: content is authored as blocks, votes are recorded in your database, and every response is validated and tallied on the server.

## Features

- **Poll block** — one question, several answers, a live aggregate breakdown after voting, and an optional explanation.
- **Quiz block** — one or more questions with correct/incorrect answers, per-question explanations, a "your answer" marker, a score result card, and an optional (editor-gated) retake.
- **Answer explanations** — shown after responding, and scopeable to *any* / *only-correct* / *only-incorrect* answers, so a quiz can give different feedback for a right vs. wrong choice. Served from the server after answering, so correct answers never leak into the page source.
- **Author once, embed anywhere** — quizzes and polls live in their own admin area (custom post types) and drop into any post/page via an embed block. The questions are a single source of truth; **responses are tracked per embedding post**, so the same quiz on two posts keeps two independent tallies.
- **Results for editors** — a Results panel on the edit screen, plus a dedicated Results admin page with a per-post breakdown ranked by volume and a CSV export.
- **Server-authoritative** — the browser only sends *which answer was picked*; correctness, tallying and results HTML are decided on the server against the quiz's own block tree.
- **Race-safe counts** — atomic SQL increments, so concurrent responses never lose updates.
- **Privacy-friendly abuse guards** — per-browser de-duplication (a random first-party cookie) and per-IP rate limiting using salted, expiring hashes (the raw IP is never stored); optional logged-in-only voting.
- **No external requests, no tracking, no upsell walls.**

## Architecture

```
Editor (React)                Storage                        Frontend (Interactivity API)
quiz  ─ question ─ answer  →   CPT: d9qp_quiz / d9qp_poll  →  click answer → POST {refId, contextId, ...}
poll  ─ question ─ answer      post meta: atomic counts,      server validates IDs against the quiz's
quiz-embed / poll-embed ──────→ reference a CPT by ID         keyed per embedding post → returns results
```

`refId` is the quiz/poll being answered (used to validate IDs against its authored blocks); `contextId` is the post it's displayed on (used to scope the tally). Counters live on the CPT keyed by the display post, so one quiz embedded on many posts keeps a separate tally per post, plus an overall total for the admin.

The trust boundary: **the client is untrusted and near-stateless.** It never sends totals, correctness or markup — only which answer was chosen. Validation, tallying, correct-answer lookup and results HTML all happen on the server.

### Blocks

| Block | Role |
|-------|------|
| `interactive-quiz-poll/quiz` | Multi-question container (rendered in the quiz CPT) |
| `interactive-quiz-poll/poll` | Single-question container (rendered in the poll CPT) |
| `interactive-quiz-poll/question` | A question wrapping its answers + optional details |
| `interactive-quiz-poll/answer` | One answer option (with `isCorrect` for quizzes) |
| `interactive-quiz-poll/question-details` | Feedback revealed after responding (server-rendered on demand) |
| `interactive-quiz-poll/quiz-embed` / `poll-embed` | Drop a saved quiz/poll into any post by ID |

### REST endpoints (`interactive-quiz-poll/v1`)

Each request carries a `contextId` (the display post) in the body; `{id}` in the path is the quiz/poll.

| Route | Purpose |
|-------|---------|
| `POST /poll/respond/{id}` | Record a vote, return the per-post breakdown |
| `POST /quiz/respond/{id}` | Grade one answer, return correctness + explanation + attempt token |
| `POST /quiz/complete/{id}` | Record a finished attempt (gated by the attempt token) |

## Development

```bash
npm install
npm run build      # or: npm run start
```

Blocks are built with [`@wordpress/scripts`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/). The frontend stores are script **modules** (`viewScriptModule`), so the build sets `WP_EXPERIMENTAL_MODULES=true` (handled by the npm scripts). Compiled output lands in `build/` (git-ignored) and is what WordPress registers.

Symlink the plugin folder into a WordPress install's `wp-content/plugins/` to test.

### Filters

| Filter | Default | Purpose |
|--------|---------|---------|
| `d9qp_abuse_guards_enabled` | `true` | Master switch for dedup + rate limiting |
| `d9qp_rate_limit_per_minute` | `30` | Per-IP request cap per minute |
| `d9qp_dedup_window` | `DAY_IN_SECONDS` | Cooldown for "one response per question, per post, per browser" |
| `d9qp_require_login` | `false` | Require a logged-in user to respond |

Define `D9QP_DELETE_DATA_ON_UNINSTALL` as `true` (e.g. in `wp-config.php`) to remove all quizzes, polls and responses on uninstall. Data is kept by default.

## License

GPL-2.0-or-later

---

Built by [Dhanendran Rajagopal](https://dhanendranrajagopal.me) · [D9 Labs](https://d9labs.io)
