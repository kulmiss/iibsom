# Testing guide

Most important tests first. "Expected" is what you should see.

## Automated (run before every merge)
| # | Command | Expected |
|---|---------|----------|
| 1 | `docker compose exec app php artisan test` | All tests green |
| 2 | `docker compose exec app vendor/bin/pint --test` | "PASS" (code style OK) |

## Manual (PR 1: foundation)
| # | Do this | Expected |
|---|---------|----------|
| 1 | Open http://localhost:8095 | "Iibsom": "Buy and sell anything in Somalia. Coming soon." |
| 2 | Open http://localhost:8095/up | Green "Application up" page |
| 3 | Open http://localhost:8096 | Mailpit inbox opens (empty) |
| 4 | Open a pull request on GitHub (not draft) | CI runs 3 jobs: tests, secret-scan, docker-build. All green |
| 5 | Mark the PR as draft and push again | CI does **not** run |
