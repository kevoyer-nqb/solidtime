# Task Assignments: Kiosk & Clock Mode

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/06-kiosk-clock-mode/PRD.md`

## Task Assignment Table

| Task ID | Description | Type | Assigned Sub-Agent | Dependencies | Effort | Status |
|---------|-------------|------|-------------------|-------------|--------|--------|
| TASK-001 | Database Migrations for Kiosk Feature (kiosks, kiosk_sessions, kiosk_pin_attempts tables + members alterations) | Backend / Database | Backend Dev | None | 4 hours (2 SP) | To Do |
| TASK-002 | Eloquent Models for Kiosk, KioskSession, KioskPinAttempt + Enums (KioskMode, KioskSessionStatus) + Member/Organization relationship updates | Backend | Backend Dev | TASK-001 | 6 hours (3 SP) | To Do |
| TASK-003 | Kiosk Management API (CRUD: create, list, show, update, delete, regenerate-token) + KioskService + Resources | Backend | Backend Dev | TASK-002 | 10 hours (5 SP) | To Do |
| TASK-004 | Kiosk Permissions Registration in JetstreamServiceProvider (kiosks:view, kiosks:create, kiosks:update, kiosks:delete) | Backend | Backend Dev | TASK-002 | 2 hours (1 SP) | To Do |
| TASK-005 | Member PIN Management API (set, update, remove PIN + QR token generation) + MemberPinService + KioskQrService | Backend | Backend Dev | TASK-001, TASK-004 | 6 hours (3 SP) | To Do |
| TASK-006 | Custom Kiosk Authentication Guard (KioskGuard, KioskTokenProvider, AuthenticateKiosk middleware, auth config) | Backend | Backend Dev | TASK-002 | 8 hours (5 SP) | To Do |
| TASK-007 | Kiosk Device API Endpoints (PIN auth, QR auth, clock-in, clock-out, break start/end, status, attendance) + KioskSessionService | Backend | Backend Dev | TASK-005, TASK-006 | 12 hours (8 SP) | To Do |
| TASK-008 | Kiosk Full-Screen Vue Page (KioskApp, IdleScreen, PinPad, MemberStatus, Confirmation, BreakScreen, ErrorScreen + useKiosk store) | Frontend | Frontend Dev | TASK-007 | 16 hours (8 SP) | To Do |
| TASK-009 | QR Code Scanner Component (camera-based scanner for kiosk + QR generation modal for member profile) | Frontend | Frontend Dev | TASK-008 | 8 hours (5 SP) | To Do |
| TASK-010 | Break Tracking Logic & Time Entry Integration (detailed break logic in KioskSessionService, time entry splitting) | Backend | Backend Dev | TASK-007 | 6 hours (3 SP) | To Do |
| TASK-011 | Attendance Dashboard Backend API (AttendanceController, AttendanceService, summary + per-member status endpoint) | Backend | Backend Dev | TASK-007 | 6 hours (3 SP) | To Do |
| TASK-012 | Attendance Dashboard Vue Page (Inertia page with summary cards, member table, filters, auto-refresh) | Frontend | Frontend Dev | TASK-011 | 12 hours (5 SP) | To Do |
| TASK-013 | Kiosk Management Frontend (KioskTable, CreateModal, EditModal, TokenModal, MoreOptionsDropdown + useKiosks store) | Frontend | Frontend Dev | TASK-003, TASK-008 | 10 hours (5 SP) | To Do |
| TASK-014 | Member PIN Management Frontend (MemberPinModal, profile PIN section, admin PIN management in MemberEditModal) | Frontend | Frontend Dev | TASK-005, TASK-013 | 6 hours (3 SP) | To Do |
| TASK-015 | Backend API Tests -- Kiosk Management (KioskEndpointTest, MemberPinEndpointTest + factories) | Testing | QA / Backend Dev | TASK-003, TASK-004, TASK-005 | 8 hours (5 SP) | To Do |
| TASK-016 | Backend API Tests -- Kiosk Device Endpoints (KioskDeviceEndpointTest, KioskSessionServiceTest) | Testing | QA / Backend Dev | TASK-007, TASK-010 | 12 hours (8 SP) | To Do |
| TASK-017 | Frontend Component Tests (Vitest tests for PinPad, MemberStatus, IdleScreen, AttendanceTable, KioskTable) | Testing | QA / Frontend Dev | TASK-008, TASK-012, TASK-013 | 8 hours (5 SP) | To Do |
| TASK-018 | E2E Playwright Tests (kiosk-clock-in-out, kiosk-management, attendance-dashboard specs) | Testing | QA | TASK-008, TASK-012, TASK-013, TASK-014 | 8 hours (5 SP) | To Do |
| TASK-019 | OpenAPI Specification Update (add operationId annotations, regenerate TypeScript client) | Backend / Docs | Backend Dev | TASK-003, TASK-005, TASK-007, TASK-011 | 4 hours (2 SP) | To Do |
| TASK-020 | Stale Session Cleanup Command (KioskCleanupStaleSessions artisan command + scheduler registration) | Backend | Backend Dev | TASK-007 | 4 hours (2 SP) | To Do |

## Summary

| Metric | Value |
|--------|-------|
| **Total Tasks** | 20 |
| **Total Effort** | 152 hours (~76 story points) |
| **Backend Tasks** | 13 (TASK-001 through TASK-007, TASK-010, TASK-011, TASK-015, TASK-016, TASK-019, TASK-020) |
| **Frontend Tasks** | 5 (TASK-008, TASK-009, TASK-012, TASK-013, TASK-014) |
| **Testing Tasks** | 4 (TASK-015, TASK-016, TASK-017, TASK-018) -- some overlap with backend/frontend devs |
| **Estimated Duration** | 4 sprints (8 weeks at 2-week sprints) |
| **Recommended Team** | 2 developers (1 backend, 1 frontend) + 1 QA part-time |

## Dependency-Respecting Execution Order

### Sprint 1 (Weeks 1-2): Foundation

**Parallel Track A (Backend)**: TASK-001 -> TASK-002 -> TASK-003, TASK-004 (parallel) -> TASK-005

**Key Milestone**: All database tables created, models defined, admin CRUD API functional, permissions registered, PIN API functional.

### Sprint 2 (Weeks 3-4): Core Kiosk

**Parallel Track A (Backend)**: TASK-006 -> TASK-007

**Parallel Track B (Frontend)**: TASK-008 (starts mid-sprint once TASK-007 endpoints are available for mocking), TASK-009, TASK-013

**Key Milestone**: Kiosk auth guard working, device API endpoints functional, kiosk Vue page renders and connects to API, admin can manage kiosks from UI.

### Sprint 3 (Weeks 5-6): Features & Polish

**Parallel Track A (Backend)**: TASK-010, TASK-011, TASK-020

**Parallel Track B (Frontend)**: TASK-012, TASK-014

**Key Milestone**: Break tracking fully functional, attendance dashboard operational, member PIN management in UI, stale sessions cleaned up automatically.

### Sprint 4 (Weeks 7-8): Testing & Integration

**All Tracks**: TASK-015, TASK-016, TASK-017, TASK-018, TASK-019

**Key Milestone**: Full test coverage, OpenAPI spec updated, TypeScript client regenerated, all tests green.

## Status Logic

- **To Do**: All tasks are currently in "To Do" status as implementation has not begun.
- **Blocked**: No tasks are blocked by external constraints. All dependencies are internal and follow the defined execution order.
- **Note**: TASK-008 (Kiosk Vue Page) can begin development with mocked API responses before TASK-007 is fully complete, as the API contract is fully specified in the PRD.

## Critical Path

```
TASK-001 (4h) -> TASK-002 (6h) -> TASK-006 (8h) -> TASK-007 (12h) -> TASK-008 (16h) -> TASK-018 (8h)
Total Critical Path: 54 hours
```

Any delay in tasks on this path directly impacts the overall delivery timeline.
