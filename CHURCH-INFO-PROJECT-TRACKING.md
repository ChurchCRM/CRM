# Church Information Configuration - Project Tracking

**GitHub Issue**: [#8190](https://github.com/ChurchCRM/CRM/issues/8190)  
**Status**: 🟢 Planning Complete - Ready for Development  
**Created**: March 5, 2026

---

## Project Overview

**Feature**: Dedicated Church Information Configuration Page (First-Run UX)

This project encompasses creating a new admin page at `/admin/church-info` that:
- Extracts church metadata from system settings into a dedicated page
- Provides a tabbed interface for organized data entry
- Enforces first-run completion before proceeding with other tasks
- Improves UX for new installations

---

## Deliverables ✅

### Documentation (Complete)
- [x] GitHub Issue #8190 - Feature specification
- [x] QUICKSTART-CHURCH-INFO.md - Developer guide (START HERE)
- [x] IMPLEMENTATION-CHURCH-INFO.md - Technical specification
- [x] CHURCH-INFO-WIREFRAMES.md - UX/wireframes
- [x] PROJECT-SUMMARY-CHURCH-INFO.md - Executive overview

### Implementation Tasks (Pending)
- [ ] Phase 1: Create routes + form template
- [ ] Phase 2: Create middleware for first-run enforcement
- [ ] Phase 3: Write Cypress tests
- [ ] Phase 4: Update documentation
- [ ] Phase 5: Code review + merge

---

## Quick Links

| Resource | Purpose | Location |
|----------|---------|----------|
| **GitHub Issue** | Official requirements | [#8190](https://github.com/ChurchCRM/CRM/issues/8190) |
| **Quick Start** | Developer implementation guide | [QUICKSTART-CHURCH-INFO.md](https://github.com/ChurchCRM/CRM/blob/master/QUICKSTART-CHURCH-INFO.md) |
| **Technical Spec** | Architecture & detailed planning | [IMPLEMENTATION-CHURCH-INFO.md](https://github.com/ChurchCRM/CRM/blob/master/IMPLEMENTATION-CHURCH-INFO.md) |
| **Wireframes** | UI/UX designs & accessibility | [CHURCH-INFO-WIREFRAMES.md](https://github.com/ChurchCRM/CRM/blob/master/CHURCH-INFO-WIREFRAMES.md) |
| **Project Summary** | Executive overview | [PROJECT-SUMMARY-CHURCH-INFO.md](https://github.com/ChurchCRM/CRM/blob/master/PROJECT-SUMMARY-CHURCH-INFO.md) |

---

## Implementation Checklist

### Phase 1: Routes & Template (1-2 days)
- [ ] Add GET/POST routes to `src/admin/routes/system.php`
- [ ] Create `src/admin/views/church-info.php` with tabbed form
- [ ] Test form displays correctly
- [ ] Test form saves via POST

See [QUICKSTART-CHURCH-INFO.md - Step 1-2](QUICKSTART-CHURCH-INFO.md#step-1-create-the-route-handlers-30-min)

### Phase 2: Middleware & Enforcement (1 day)
- [ ] Create `src/ChurchCRM/Slim/Middleware/ChurchInfoRequiredMiddleware.php`
- [ ] Register middleware in `src/admin/index.php`
- [ ] Test redirect on empty church name
- [ ] Test exempt routes don't redirect

See [QUICKSTART-CHURCH-INFO.md - Step 3](QUICKSTART-CHURCH-INFO.md#step-3-create-middleware-for-first-run-enforcement-20-min)

### Phase 3: Testing (1 day)
- [ ] Create `cypress/e2e/admin/church-info.cy.js`
- [ ] Run full Cypress test suite
- [ ] Update Finance Dashboard link: `src/finance/views/dashboard.php`
- [ ] Manual QA on fresh install

See [QUICKSTART-CHURCH-INFO.md - Step 4](QUICKSTART-CHURCH-INFO.md#step-4-create-cypress-tests-60-min)

### Phase 4: Documentation (1 day)
- [ ] Update `docs.churchcrm.io/docs/getting-started/first-run.md`
- [ ] Add screenshots showing the new page
- [ ] Add entry to `CHANGELOG.md`
- [ ] Update wiki if applicable

See [QUICKSTART-CHURCH-INFO.md - Step 6](QUICKSTART-CHURCH-INFO.md#step-6-update-documentation-30-min)

### Phase 5: Code Review & Polish (1-2 days)
- [ ] Address code review comments
- [ ] Final testing pass
- [ ] Verify all tests pass
- [ ] Merge to main branch

---

## Key Metrics

| Metric | Value |
|--------|-------|
| Documentation Size | ~80 KB (4 docs) |
| Estimated Dev Time | 5-7 days |
| Files to Create | 3 new files |
| Files to Modify | 3 existing files |
| Test Coverage | Cypress full suite |
| Breaking Changes | 0 (fully backward compatible) |
| Database Migrations | 0 (uses existing fields) |

---

## Field Mapping

**SystemConfig Fields Used** (all existing - no schema changes):

| Field | Type | ID | Tab | Required |
|-------|------|----|----|----------|
| sChurchName | text | 1003 | Basic | ✅ YES |
| sChurchAddress | text | 1004 | Location | no |
| sChurchCity | text | 1005 | Location | no |
| sChurchState | text | 1006 | Location | no |
| sChurchZip | text | 1007 | Location | no |
| sChurchCountry | choice | 1047 | Location | no |
| sChurchPhone | text | 1008 | Contact | no |
| sChurchEmail | text | 1009 | Contact | no |
| iChurchLatitude | number | 1010 | Map | no |
| iChurchLongitude | number | 1011 | Map | no |
| sTimeZone | choice | 65 | Map | no |
| sChurchWebSite | text | - | Basic | no |

---

## First-Run Workflow

```
┌─ Fresh Install ─────────────────────────────────┐
│ User: Admin logs in                             │
│ System: Middleware checks sChurchName           │
└────────────────┬────────────────────────────────┘
                 │
      ┌──────────┴──────────┐
      │                     │
      ▼                     ▼
EMPTY                    SET
│                        │
├─ Redirect to           ├─ Proceed normally
│  /admin/church-info    │
│                        │
├─ Display form with     ├─ Dashboard shows ✓
│  all 5 tabs            │  badge
│                        │
├─ Validate church name  ├─ All features
│  (required)            │  available
│                        │
└─ Save → Success        └─────────────────
```

---

## File Structure

```
CRM/
├── QUICKSTART-CHURCH-INFO.md          ← START HERE
├── IMPLEMENTATION-CHURCH-INFO.md      ← Technical spec
├── CHURCH-INFO-WIREFRAMES.md          ← UX reference
├── PROJECT-SUMMARY-CHURCH-INFO.md     ← Overview
├── CHURCH-INFO-PROJECT-TRACKING.md    ← This file
│
├── src/admin/
│   ├── routes/system.php              [MODIFY: add routes]
│   ├── views/church-info.php          [CREATE: form template]
│   └── index.php                       [MODIFY: register middleware]
│
├── src/ChurchCRM/Slim/Middleware/
│   └── ChurchInfoRequiredMiddleware.php [CREATE: enforcement]
│
├── cypress/e2e/admin/
│   └── church-info.cy.js              [CREATE: tests]
│
└── docs.churchcrm.io/docs/getting-started/
    └── first-run.md                   [UPDATE: add section]
```

---

## Success Criteria

### Functional ✅
- [x] Issue created with detailed spec
- [x] All documentation complete
- [ ] New page displays at `/admin/church-info`
- [ ] Form shows 5 organized tabs
- [ ] Form saves all fields to SystemConfig
- [ ] Middleware enforces church name on first run
- [ ] Dashboard reflects status (✓/✗ badges)

### Non-Functional ✅
- [x] Architecture documented
- [ ] No breaking changes
- [ ] Tests pass (Cypress)
- [ ] Docs updated with screenshots
- [ ] Backward compatible
- [ ] Mobile responsive
- [ ] Accessible (WCAG 2.1)

### Quality ✅
- [x] Clear implementation guide
- [ ] Code review approval
- [ ] All tests passing
- [ ] No lint errors
- [ ] Translated strings (gettext)

---

## Getting Started

### For Developers
1. Read: [QUICKSTART-CHURCH-INFO.md](QUICKSTART-CHURCH-INFO.md) (15 min)
2. Review: [GitHub Issue #8190](https://github.com/ChurchCRM/CRM/issues/8190)
3. Implement: Follow the 5 steps in QUICKSTART
4. Test: Run Cypress suite
5. Submit: PR with all changes

### For Reviewers
- Check: Code matches patterns in existing admin routes
- Verify: Middleware properly enforces rules
- Test: UI displays correctly on desktop + mobile
- Validate: All tests pass
- Review: Docs updated with screenshots

### For Project Managers
- Timeline: 5-7 days for full implementation
- Risk: Low (no schema changes, backward compatible)
- Complexity: Medium (middleware + form + tests)
- Value: High (improves new-user experience)

---

## Documentation References

### For Implementers
- **Route Pattern**: See `src/admin/routes/system.php`
- **View Patterns**: See `src/admin/views/backup.php`, `users.php`
- **Middleware Pattern**: See `src/ChurchCRM/Slim/Middleware/AuthMiddleware.php`
- **Config API**: See `src/ChurchCRM/dto/SystemConfig.php`
- **Tests**: See `cypress/e2e/` for existing test patterns

### External Resources
- Slim 4: https://www.slimframework.com/docs/v4/
- Bootstrap 4: https://getbootstrap.com/docs/4.6/
- Cypress: https://docs.cypress.io/
- ARIA/A11y: https://www.w3.org/WAI/ARIA/

### Internal Standards
- See `CLAUDE.md` for code standards
- See `CONTRIBUTING.md` for PR guidelines
- See `.agents/skills/churchcrm/SKILL.md` for architecture

---

## Communication

### Questions?
- **Architecture**: See [IMPLEMENTATION-CHURCH-INFO.md](IMPLEMENTATION-CHURCH-INFO.md)
- **Code Examples**: See [QUICKSTART-CHURCH-INFO.md](QUICKSTART-CHURCH-INFO.md)
- **UI/Design**: See [CHURCH-INFO-WIREFRAMES.md](CHURCH-INFO-WIREFRAMES.md)
- **Issues**: Comment on [GitHub Issue #8190](https://github.com/ChurchCRM/CRM/issues/8190)

### Updates
- Track implementation progress in GitHub Issue #8190
- Post questions/blockers as comments
- Link PRs to this issue for context

---

## Timeline

```
Week 1
├─ Day 1-2: Phase 1 (Routes + Template)
├─ Day 2-3: Phase 2 (Middleware)
├─ Day 3-4: Phase 3 (Testing)
├─ Day 4-5: Phase 4 (Documentation)
└─ Day 5-7: Phase 5 (Review + Polish)

Expected Completion: ~7 days from start
Target Merge Date: TBD (based on start date)
```

---

## Related Issues

- **Prerequisite**: None
- **Blocks**: None identified
- **Related**: [Finance Dashboard Church Info Check](https://github.com/ChurchCRM/CRM/blob/master/src/finance/views/dashboard.php#L241)
- **Blocked By**: None

---

## Notes

### Implementation Philosophy
- Use existing patterns (don't reinvent)
- Fail safe (redirect instead of error)
- User-friendly (clear messages and guidance)
- Accessible (WCAG 2.1 standards)
- Tested (Cypress coverage)
- Documented (comments + wiki)

### Future Enhancements (Not in Scope)
- Logo upload functionality
- Auto-geocoding from address
- Mobile wizard flow
- Audit logging
- Multi-church support

---

**Project Created**: March 5, 2026  
**Status**: 🟢 Ready for Development  
**Contact**: See GitHub Issue #8190
