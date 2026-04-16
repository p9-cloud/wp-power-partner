# Design: Add Memo (備註) Field Display to Power-Partner Frontend

**Date:** 2026-04-15
**Status:** Approved

## Context

The `nestjs-helm-admin` frontend displays a "備註" (memo/notes) column in the website management table. The PowerCloud API (`api.wpsite.pro/websites`) already returns the `memo` field. The `power-partner` frontend currently does not display this field.

## Goal

Display the `memo` field as a read-only column in both the Admin and User PowerCloud website list tables in power-partner, matching the display style of nestjs-helm-admin.

## Design

### 1. Data Layer — `IWebsite` Interface

**File:** `js/src/pages/AdminApp/Dashboard/SiteList/types.ts`

Add `memo?: string` to the `IWebsite` interface. No API changes needed — the field is already returned by the PowerCloud API.

### 2. Admin List — PowerCloud Table Column

**File:** `js/src/pages/AdminApp/Dashboard/SiteList/index.tsx`

Add a "備註" column to the Admin PowerCloud website table:
- **Position:** Before the "創建時間" (createdAt) column
- **Data index:** `memo`
- **Render:** `<Typography.Text ellipsis={{ tooltip: memo }}>{memo || '-'}</Typography.Text>`
- **No sorting**

### 3. User List — PowerCloud Table Column

**File:** `js/src/pages/UserApp/SiteList/Powercloud.tsx`

Add the same "備註" column to the User PowerCloud website table:
- **Position:** Before the "創建時間" (createdAt) column
- **Render:** Same as Admin

## Files Changed

| File | Change |
|------|--------|
| `js/src/pages/AdminApp/Dashboard/SiteList/types.ts` | Add `memo?: string` to `IWebsite` |
| `js/src/pages/AdminApp/Dashboard/SiteList/index.tsx` | Add memo column definition |
| `js/src/pages/UserApp/SiteList/Powercloud.tsx` | Add memo column definition |

## Out of Scope

- Memo editing (handled by nestjs-helm-admin)
- WPCD (legacy) site list changes
- API modifications
