# Memo (備註) Field Display Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Display the `memo` field as a read-only column in both Admin and User PowerCloud website list tables.

**Architecture:** Add `memo?: string` to the `IWebsite` interface (in both the shared type file and User's local copy), then add a "備註" column to both Admin and User table column definitions using Ant Design `Typography.Text` with ellipsis + tooltip.

**Tech Stack:** React 18, Ant Design 5, TypeScript

---

## File Structure

| File | Action | Purpose |
|------|--------|---------|
| `js/src/pages/AdminApp/Dashboard/SiteList/types.ts` | Modify | Add `memo` to shared `IWebsite` interface |
| `js/src/pages/AdminApp/Dashboard/SiteList/index.tsx` | Modify | Add memo column to Admin table |
| `js/src/pages/UserApp/SiteList/Powercloud.tsx` | Modify | Add `memo` to local `IWebsite` + add memo column to User table |

---

### Task 1: Add `memo` to shared `IWebsite` type

**Files:**
- Modify: `js/src/pages/AdminApp/Dashboard/SiteList/types.ts:34`

- [ ] **Step 1: Add `memo` field to `IWebsite` interface**

In `js/src/pages/AdminApp/Dashboard/SiteList/types.ts`, add `memo?: string` before `createdAt` (line 34):

```typescript
	dailyCost?: number
	memo?: string
	createdAt: string
```

- [ ] **Step 2: Verify no type errors**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && npx tsc --noEmit --project tsconfig.app.json 2>&1 | head -20`
Expected: No new errors related to `memo`

- [ ] **Step 3: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/types.ts
git commit -m "feat: add memo field to IWebsite interface"
```

---

### Task 2: Add memo column to Admin PowerCloud table

**Files:**
- Modify: `js/src/pages/AdminApp/Dashboard/SiteList/index.tsx:432-450`

- [ ] **Step 1: Add memo column before '建立時間' column**

In `js/src/pages/AdminApp/Dashboard/SiteList/index.tsx`, insert a new column object before the `'建立時間'` column (before line 433). The new column goes between the `phpPodSize` column (ending at line 432) and the `createdAt` column (starting at line 433):

```typescript
		{
			title: '備註',
			dataIndex: 'memo',
			key: 'memo',
			width: 150,
			ellipsis: true,
			render: (memo?: string) => (
				<Text ellipsis={{ tooltip: memo }}>{memo || '-'}</Text>
			),
		},
		{
			title: '建立時間',
```

- [ ] **Step 2: Verify no type errors**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && npx tsc --noEmit --project tsconfig.app.json 2>&1 | head -20`
Expected: No new errors

- [ ] **Step 3: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/index.tsx
git commit -m "feat: add memo column to admin PowerCloud site list"
```

---

### Task 3: Add memo field and column to User PowerCloud table

**Files:**
- Modify: `js/src/pages/UserApp/SiteList/Powercloud.tsx:44-78` (interface) and `js/src/pages/UserApp/SiteList/Powercloud.tsx:207-224` (columns)

- [ ] **Step 1: Add `memo` to local `IWebsite` interface**

In `js/src/pages/UserApp/SiteList/Powercloud.tsx`, the file has its own local `IWebsite` interface (lines 44-78). Add `memo?: string` before `createdAt` (before line 76):

```typescript
	ipAddress: string
	memo?: string
	createdAt: string
```

- [ ] **Step 2: Add memo column before '建立時間' column**

In the same file, insert a new column object before the `'建立時間'` column (before line 208). The new column goes between the `adminPassword` column (ending at line 207) and the `createdAt` column (starting at line 208):

```typescript
		{
			title: '備註',
			dataIndex: 'memo',
			key: 'memo',
			width: 150,
			ellipsis: true,
			render: (memo?: string) => (
				<Text ellipsis={{ tooltip: memo }}>{memo || '-'}</Text>
			),
		},
		{
			title: '建立時間',
```

- [ ] **Step 3: Verify no type errors**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && npx tsc --noEmit --project tsconfig.app.json 2>&1 | head -20`
Expected: No new errors

- [ ] **Step 4: Commit**

```bash
git add js/src/pages/UserApp/SiteList/Powercloud.tsx
git commit -m "feat: add memo column to user PowerCloud site list"
```

---

### Task 4: Visual verification

- [ ] **Step 1: Build the project**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && pnpm build`
Expected: Build succeeds without errors

- [ ] **Step 2: Verify visually (manual)**

Open the WordPress admin dashboard and verify:
1. Admin PowerCloud site list shows "備註" column between "容器數量" and "建立時間"
2. User PowerCloud site list shows "備註" column between "WordPress 管理員密碼" and "建立時間"
3. Empty memo displays "-"
4. Long memo text truncates with ellipsis and shows full text on hover tooltip
