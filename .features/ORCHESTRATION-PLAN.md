# Solidtime Feature Orchestration Plan

## Overview

This document defines the management structure for implementing 10 major feature epics
into the solidtime codebase. Each feature gets its own git branch, PRD, sprint plan,
and dedicated agent pipeline.

## Current State (Baseline)

Solidtime today is a lean, self-hostable time tracker with:
- Core time entry + project/client/task management
- Weekly timesheet grid (feature/weekly-timesheet-grid - in progress)
- Role-based access (Owner/Admin/Manager/Employee)
- Multi-level billable rates
- Import/export + audit trail (OwenIt/Auditing)
- Shareable reports with optional expiry
- REST API + Passport tokens
- Calendar view (FullCalendar, basic)
- Dashboard charts

## Feature Epics (10 branches)

### Tier 1 — Governance & Billing Foundation (Highest Priority)

| # | Epic | Branch | Dependencies | Scope |
|---|------|--------|-------------|-------|
| 1 | Timesheet Approvals | `feature/timesheet-approvals` | Weekly timesheet grid | Submission workflow, approval/reject, locking, reminders, compliance nudges |
| 2 | Expense Management | `feature/expense-management` | None | Expense entries, categories, receipt uploads, markups, billable flag, approval |
| 3 | Budgets & Alerts | `feature/budgets-alerts` | None | Project budgets (hours/cost/fixed), burn tracking, threshold alerts, forecasting |

### Tier 2 — Revenue & Invoicing

| # | Epic | Branch | Dependencies | Scope |
|---|------|--------|-------------|-------|
| 4 | Invoicing System | `feature/invoicing` | Expenses, Budgets (soft) | Invoice from time/expenses, templates, branding, recurring, online payments, accounting sync |

### Tier 3 — Time Capture Enhancements

| # | Epic | Branch | Dependencies | Scope |
|---|------|--------|-------------|-------|
| 5 | Calendar View Enhanced | `feature/calendar-enhanced` | None | Drag-resize, Outlook/Google sync, event-to-entry conversion, planning vs actual |
| 6 | Kiosk & Clock Mode | `feature/kiosk-clock-mode` | None | Shared-device kiosk, PIN/QR auth, break tracking, punch-only mode, attendance |

### Tier 4 — Workforce Management

| # | Epic | Branch | Dependencies | Scope |
|---|------|--------|-------------|-------|
| 7 | PTO & Time Off | `feature/pto-time-off` | None | Policies, holiday calendars, accruals, requests, approvals, balances |
| 8 | Resource Scheduling | `feature/resource-scheduling` | PTO (soft) | Assignments, milestones, capacity planning, scheduled vs tracked comparison |

### Tier 5 — Analytics & Organization

| # | Epic | Branch | Dependencies | Scope |
|---|------|--------|-------------|-------|
| 9 | Advanced Reporting | `feature/advanced-reporting` | Budgets, Expenses (soft) | Profitability, capacity/utilization, scheduled delivery, enhanced export |
| 10 | Teams & Groups | `feature/teams-groups` | None | Sub-org teams, client/project scoping, manager visibility boundaries |

## Agent Pipeline (per feature)

Each feature branch runs through this agent pipeline:

```
┌─────────────────────────────────────────────────────────┐
│                    ORCHESTRATOR                          │
│  (creates branch, coordinates pipeline, tracks status)  │
└────────────┬────────────────────────────────────────────┘
             │
    ┌────────▼────────┐
    │   1. PRD Agent   │  Product Requirements Document
    │   (planning-prd) │  - User stories & acceptance criteria
    │                  │  - Technical architecture decisions
    │                  │  - Task breakdown with estimates
    └────────┬────────┘
             │
    ┌────────▼────────┐
    │  2. Architect    │  Technical Blueprint
    │  (code-architect)│  - Data model / migrations
    │                  │  - API endpoints & contracts
    │                  │  - Component tree & state design
    │                  │  - Integration points with existing code
    └────────┬────────┘
             │
    ┌────────▼────────┐
    │  3. Explorer     │  Codebase Analysis
    │  (code-explorer) │  - Existing patterns to follow
    │                  │  - Files to modify vs create
    │                  │  - Risk assessment
    └────────┬────────┘
             │
    ┌────────▼────────┐
    │  4. Sprint Plan  │  Execution Roadmap
    │  (planning-prd)  │  - Sprint-sized task files
    │                  │  - Dependency ordering
    │                  │  - Definition of done per task
    └─────────────────┘
```

## Directory Structure

```
.features/
├── ORCHESTRATION-PLAN.md          ← This file
├── STATUS.md                      ← Live status tracker
├── 01-timesheet-approvals/
│   ├── PRD.md
│   ├── ARCHITECTURE.md
│   ├── CODEBASE-ANALYSIS.md
│   └── SPRINT-PLAN.md
├── 02-expense-management/
│   ├── PRD.md
│   ├── ARCHITECTURE.md
│   ├── CODEBASE-ANALYSIS.md
│   └── SPRINT-PLAN.md
├── ...
└── 10-teams-groups/
    ├── PRD.md
    ├── ARCHITECTURE.md
    ├── CODEBASE-ANALYSIS.md
    └── SPRINT-PLAN.md
```

## Branch Strategy

```
main
 └── feature/weekly-timesheet-grid  (current, in-progress)
 └── feature/timesheet-approvals    (new - depends on weekly grid merge)
 └── feature/expense-management     (new - independent)
 └── feature/budgets-alerts         (new - independent)
 └── feature/invoicing              (new - soft-depends on expenses + budgets)
 └── feature/calendar-enhanced      (new - independent)
 └── feature/kiosk-clock-mode       (new - independent)
 └── feature/pto-time-off           (new - independent)
 └── feature/resource-scheduling    (new - soft-depends on PTO)
 └── feature/advanced-reporting     (new - soft-depends on budgets + expenses)
 └── feature/teams-groups           (new - independent)
```

All branches fork from `main`. Features with hard dependencies will rebase
onto their dependency branch when ready. Soft dependencies mean the feature
CAN work standalone but is enhanced when the dependency is merged.

## Execution Order

Phase 1 (parallel): Branches 1, 2, 3, 5, 6, 7, 10 — all independent
Phase 2 (after Phase 1 PRDs): Branches 4, 8, 9 — depend on Phase 1 outputs
Phase 3: Code implementation per sprint plans
Phase 4: Integration testing across features
