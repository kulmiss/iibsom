# Iibsom: rules for AI assistants

Read DECISIONS.md, ROADMAP.md and handover.md before starting work.

- Owner wants: easy English, recommendation first, small PRs, tests for everything, never merge.
- Ask the owner about important choices; log every answer in DECISIONS.md with the date. Small choices: decide and log them under "Small choices".
- New feature ideas go into ROADMAP.md → "Ideas".
- Update handover.md at the end of each PR.
- Every report ends with "Reminders for you": open questions, things the owner must do, next 3 roadmap ideas.
- CI runs only on non-draft PRs. Keep lower stacked PRs as draft.
- Before pushing, run like CI: `php artisan test`, `vendor/bin/pint --test`, `composer audit`, `npm audit --omit=dev`.
- Tests run on MySQL (`iibsom_testing`), not SQLite.
- Phase 2/3 features: build only when the owner says so.
- Security: never commit secrets; no IP storage for visitors who aren't signed in; every new feature needs a feature switch.
