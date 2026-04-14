# Power-Partner UI Alignment with nestjs-helm-admin

**Date:** 2026-04-14
**Status:** Approved
**Goal:** Align power-partner WordPress plugin admin panel content areas with nestjs-helm-admin frontend design, while keeping top tab navigation and existing tech stack (Ant Design v5, Tailwind v3.4).

---

## Scope

- **In scope:** All content areas inside the tab panels — site list table, filters, top info bar, settings pages, all tab content
- **Out of scope:** Tab navigation structure (stays as-is), Ant Design version upgrade, Tailwind version upgrade, WordPress admin shell

## Constraints

| Constraint | Reason |
|---|---|
| Ant Design v5 (no upgrade to v6) | Breaking changes risk in production WP plugin |
| Tailwind v3.4 with `.tailwind` prefix scope | WordPress CSS isolation required |
| Top tab navigation preserved | Runs inside WP admin (already has sidebar) |
| All styles scoped | Must not conflict with WP admin CSS |

---

## Design Changes

### 1. Top Info Bar (Tab Bar Extra Content)

**Current:** Simple `<AccountIcon />` in `tabBarExtraContent`.

**Target:** Match nestjs-helm-admin's header — user role badge, wallet/balance display, avatar with dropdown.

**Implementation:**
- Replace `<AccountIcon />` with a new `<HeaderInfo />` component
- Layout: `flex items-center gap-3`
- Elements (right-to-left):
  - **Avatar** — Ant Design Avatar with user initials, blue background (#1677ff), wrapped in Dropdown with menu items: Profile, Wallet, Settings, Logout
  - **Balance** — Display wallet balance (e.g. `¥ -27,826.10`) in amber/yellow text
  - **Role badge** — Display partner tier (e.g. `高階經銷商`) in a small badge with crown icon
  - **Refresh button** — Circular icon button for global refresh
- Data source: existing `power_partner_data.env` globals + identity API

**Reference:** `nestjs-helm-admin/src/layouts/Default.tsx` → UserProfile component

### 2. Filter Card (Site List)

**Current:** No visible advanced filter UI on the PowerCloud tab.

**Target:** Match nestjs-helm-admin's `WebsiteListFilter` — grid-based filter inside a rounded card.

**Implementation:**
- New `<WebsiteListFilter />` component placed above the table
- Container: `bg-white rounded-xl border border-gray-300/50 p-4` (scoped via `.tailwind`)
- Grid layout: `grid grid-cols-2 xl:grid-cols-4 gap-4`
- Filter fields:
  - Website keyword (Ant Input with search icon)
  - User keyword (Ant Input)
  - Status dropdown (Ant Select — 建置中/運行中/已停止/刪除中)
  - Package selector (Ant Select — fetched from API)
  - Daily cost range start (Ant InputNumber)
  - Daily cost range end (Ant InputNumber)
  - Date range start (Ant DatePicker)
  - Date range end (Ant DatePicker)
- Action buttons: Search (primary outlined) + Clear (default outlined)
- Active filter pills: `rounded-full bg-blue-200/50 px-3 py-1 text-sm text-blue-500` with close button
- Input styling: `border border-gray-300/50` to match nestjs-helm-admin

**Reference:** `nestjs-helm-admin/src/pages/auth/WebsiteList/components/WebsiteListFilter.tsx`

### 3. Card Containers (All Content)

**Current:** Content renders directly inside tab panels with flat styling.

**Target:** All content sections wrapped in card containers matching nestjs-helm-admin.

**Implementation:**
- Content wrapper class: `bg-white rounded-xl border border-gray-300/50 p-4`
- Section spacing: `space-y-4` or `space-y-6` between cards
- Apply to ALL tab panels:
  - SiteList — filter card + table card
  - LogList — table card
  - EmailSetting — form card
  - ManualSiteSync — form card
  - Settings — form card
  - LicenseCodes — table card
  - Description — content card
  - PowercloudAuth — form card

### 4. Site List Table Alignment

**Current columns (11):** 網站名稱, 狀態, IP 位址, 方案, 網站擁有者, 每日扣款, 容器數量, WP 管理員信箱, WP 管理員密碼, 建立時間, 操作

**Target columns (matching nestjs-helm-admin's 13):**

| # | Column | Key Changes |
|---|---|---|
| 1 | 網站資訊 | Rename from 網站名稱. Show domain as link + namespace below. Add LinkOutlined icon |
| 2 | 狀態 | Change to `variant="outlined"` Tag style. Use `WEBSITE_STATUS_COLOR_MAP` pattern |
| 3 | 管理員電子郵件 | Move up. Copyable with ellipsis |
| 4 | 管理員密碼 | Masked (•••••••••) with copyable actual value |
| 5 | IP 位址 | Copyable, ellipsis, fallback to '-' |
| 6 | 網站方案 | Flex column: package name (gray-600) + price below (xs gray-400) |
| 7 | 網站擁有者 | Show full name as link (blue-500). Remove email from this column |
| 8 | 每日扣款 | Font-medium, fixed 2 decimals, sortable |
| 9 | 標籤 | NEW — Add labels/tags column with colored badges |
| 10 | 容器數量 | Keep PodSizeEditor. Add disabled state on UPDATING/CREATING/STOPPED |
| 11 | 備註 | NEW — Admin-only memo field with ellipsis tooltip |
| 12 | 創建時間 | Format to zh-TW locale string, sortable |
| 13 | 操作 | Fixed right. Use icon-only buttons with tooltips. Width: 150 |

**Table container:**
- Wrap in `rounded-xl border border-gray-300/50 p-4` card
- Add "重新整理" refresh button above table (right-aligned)
- Add record count display: `共 X 個網站`
- Pagination: show record range (e.g. `顯示 1-20 共 500 筆記錄`)

**Action buttons (column 13) — match nestjs-helm-admin:**
- WordPress Admin link (SettingOutlined) — tooltip
- AdminNeo/DB tool (DatabaseOutlined) — with service loading state
- SSH terminal (CodeOutlined) — with service loading state
- Wordfence scan (SecurityScanOutlined) — with service loading state
- Manual backup (CloudDownloadOutlined) — tooltip
- Start/Stop toggle — tooltip
- Delete (danger) — confirmation modal
- Domain change — modal

**Reference:** `nestjs-helm-admin/src/pages/auth/WebsiteList/hooks/useWebsiteColumns.tsx`

### 5. Action Buttons Redesign

**Current:** `<Space>` with mixed button types (link, danger).

**Target:** Match nestjs-helm-admin's `WebsiteActionButtons` pattern.

**Implementation:**
- Use icon-only Ant Button with `type="text"` and Tooltip wrappers
- Group related actions
- Service buttons (AdminNeo, SSH, Wordfence) show loading spinner (PiSpinnerGap style) with 10-second timer
- Destructive actions require confirmation Modal
- Disabled state: `pointer-events-none opacity-50` during status updates

### 6. User Profile Dropdown

**Current:** `<AccountIcon />` — basic icon.

**Target:** Match nestjs-helm-admin's `UserProfile` component.

**Implementation:**
- Avatar: 36px, blue (#1677ff), user initials, Badge with success dot
- Container: `flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-gray-100`
- Dropdown menu items:
  - My Wallet (IoWalletOutline icon)
  - Settings (LuSettings icon) — navigate to Settings tab
  - Logout (LuLogOut icon) — danger variant
- Placement: bottomRight

### 7. Status Tags

**Current:** Ant Tag with `processing`, `success`, `warning`, `error` color keywords.

**Target:** Outlined variant Tags matching nestjs-helm-admin.

**Implementation:**
- Add `variant="outlined"` to all status Tags (Ant Design v5 supports this via `bordered` prop)
- Status color map (consistent with nestjs-helm-admin):
  - `creating` → processing (blue)
  - `running` → success (green)
  - `stopped` → warning (orange)
  - `deleting` → error (red)
  - `updating` → processing (blue)

### 8. Other Tab Content Pages

Apply card container treatment to all other tabs:

- **LogList:** Wrap table in card container
- **EmailSetting:** Wrap form in card container, consistent button styling
- **ManualSiteSync:** Wrap form in card container
- **Settings:** Wrap form sections in card containers
- **LicenseCodes:** Wrap table in card container
- **Description:** Wrap content in card container
- **PowercloudAuth:** Wrap form in card container

---

## Component Architecture

```
Dashboard (existing tabs)
├── tabBarExtraContent: <HeaderInfo /> (NEW - replaces AccountIcon)
│   ├── RefreshButton
│   ├── RoleBadge
│   ├── WalletBalance
│   └── UserProfileDropdown
├── Tab: SiteList
│   ├── SubTabs (PowerCloud / WPCD)
│   ├── <WebsiteListFilter /> (NEW)
│   └── <ContentCard> (NEW wrapper)
│       ├── RefreshButton + RecordCount
│       └── Table (aligned columns)
│           └── ActionButtons (redesigned)
├── Tab: LogList
│   └── <ContentCard> → Table
├── Tab: EmailSetting
│   └── <ContentCard> → Form
├── Tab: ManualSiteSync
│   └── <ContentCard> → Form
├── Tab: Settings
│   └── <ContentCard> → Form sections
├── Tab: LicenseCodes
│   └── <ContentCard> → Table
├── Tab: Description
│   └── <ContentCard> → Content
└── Tab: PowercloudAuth
    └── <ContentCard> → Form
```

## New Shared Components

| Component | Purpose |
|---|---|
| `ContentCard` | Reusable card wrapper: `bg-white rounded-xl border border-gray-300/50 p-4` |
| `HeaderInfo` | Top-right info bar: role badge, balance, avatar dropdown |
| `UserProfileDropdown` | Avatar + dropdown menu (wallet, settings, logout) |
| `WebsiteListFilter` | Grid-based advanced filter for site list |
| `WebsiteActionButtons` | Icon-only action buttons with tooltips and service states |

## Styling Strategy

- All new Tailwind classes use the existing `.tailwind` scope prefix
- Ant Design components use ConfigProvider theme for consistency
- No new global CSS — all styling via Tailwind utilities + Ant Design props
- Card borders use `border-gray-300/50` (50% opacity) for subtle appearance
- Consistent spacing: `space-y-4` between sections, `gap-4` in grids

## Data Dependencies

- **Filter fields:** Existing API endpoints already support filter params (keyword, status, package, cost range, date range)
- **Labels column (NEW):** Requires labels API endpoint — check if power-partner backend supports this. If not, skip this column initially.
- **Memo column (NEW):** Requires memo field in WordPress model — check if available. If not, skip initially.
- **Wallet balance:** Available via existing identity/user API
- **User role:** Available via `power_partner_data.env.partner_id` or identity API

## Testing

- Visual regression: Manual comparison against nestjs-helm-admin screenshots
- Functional: All existing CRUD operations must continue to work
- Responsive: Test at breakpoints (576px, 810px, 1080px, 1280px, 1440px)
- WordPress isolation: Verify no CSS leaks into WP admin
