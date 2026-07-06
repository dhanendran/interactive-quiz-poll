# Interactive Quiz & Poll

First-party quiz and poll [Gutenberg](https://developer.wordpress.org/block-editor/) blocks built on the native [WordPress Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/) — a free, self-hosted replacement for third-party quiz/poll embeds (Typeform, Opinion Stage, PollDaddy).

Everything runs on your own site: content is authored as blocks, votes are recorded in your database, and every response is validated and tallied on the server.

## Features

- **Poll block** — one question, several answers, a live aggregate breakdown after voting, and an optional explanation.
- **Quiz block** — one or more questions with correct/incorrect answers, per-question feedback, and a final score.
- **Author once, embed anywhere** — quizzes and polls live in their own admin area (custom post types) and drop into any post/page via an embed block, keeping a single source of truth and one tally.
- **Server-authoritative** — the browser only sends *which answer was picked*; correctness, tallying and results HTML are decided on the server against the quiz's own block tree.
- **Race-safe counts** — atomic SQL increments, so concurrent votes never lose updates.
- **Privacy-friendly abuse guards** — per-visitor de-duplication and per-IP rate limiting using salted, expiring hashes (the raw IP is never stored); optional logged-in-only voting.
- **No external requests, no tracking, no upsell walls.**

## Architecture

```
Editor (React)                Storage                     Frontend (Interactivity API)
quiz  ─ question ─ answer  →   CPT: d9qp_quiz / d9qp_poll  →  click answer → POST {questionId, answerId}
poll  ─ question ─ answer      post meta: atomic counts       server validates IDs against the post's
quiz-embed / poll-embed ──────→ reference a CPT by ID         own blocks, increments, returns results
```

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

| Route | Purpose |
|-------|---------|
| `POST /poll/respond/{id}` | Record a vote, return the breakdown |
| `POST /quiz/respond/{id}` | Grade one answer, return correctness + feedback + attempt token |
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
| `d9qp_dedup_window` | `DAY_IN_SECONDS` | How long a "one response per question" cooldown lasts |
| `d9qp_require_login` | `false` | Require a logged-in user to respond |

Define `D9QP_DELETE_DATA_ON_UNINSTALL` as `true` (e.g. in `wp-config.php`) to remove all quizzes, polls and responses on uninstall. Data is kept by default.

## License

GPL-2.0-or-later

---

Built by [Dhanendran Rajagopal](https://dhanendranrajagopal.me) · [D9 Labs](https://d9labs.io)
