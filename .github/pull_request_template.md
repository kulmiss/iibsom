## What and why
<!-- Easy English. One topic per PR. -->

## Self-review checklist (all must be ticked before "ready")
- [ ] 1. **Plan:** what could go wrong is written below (edge cases, empty/huge/wrong input, two users at once, slow internet, switch OFF, each role, each language, shared vs VPS)
- [ ] 2. **Tests:** happy path + edge cases + failures + permissions; every bug fix has a test that failed before the fix
- [ ] 3. **Double-checked:** every changed line re-read, then the whole diff a second time
- [ ] 4. **All checks green:** tests (VPS + shared), Larastan level 8, Pint, ESLint + TypeScript strict, admin build, Lighthouse, gitleaks, audits — zero errors, zero new warnings, no test skipped or weakened
- [ ] 5. **Real browser:** phone 360/390/414 + desktop, light/dark, every role, Somali + English; no console errors, no PHP warnings, no failed requests, no broken links/images, no cut-off text
- [ ] 6. **No loose ends:** no TODO/FIXME, dead code, debug output, hard-coded test values or secrets
- [ ] 7. **Regression:** browse, search, sign in, post with photos, edit, report, approve, switches still work
- [ ] 8. **Safe failures:** outside services fail with a clear message and a log entry
- [ ] 9. **Honest:** anything not fully checked is listed below

### What could go wrong (and how it is handled)

### Not fully checked
