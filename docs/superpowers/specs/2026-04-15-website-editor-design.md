# Website Editor Page — Design Spec

**Date:** 2026-04-15
**Scope:** power-partner AdminApp — 新增網站編輯頁面
**Reference:** nestjs-helm-admin WebsiteEditor

---

## Goal

在 power-partner 的 Admin Dashboard 新增網站編輯頁面，讓管理員可以點擊列表中的編輯按鈕，進入獨立頁面編輯網站屬性（方案、用戶、狀態、PHP 版本、標籤、備註）。功能與 nestjs-helm-admin 的 WebsiteEditor 一致。

---

## Navigation Strategy: HashRouter

保留現有 Tabs + Jotai 架構，引入 React Router `HashRouter` 包裹 Dashboard 內部。

- 列表頁（預設）：`#/`  — 顯示現有 Tabs Dashboard
- 編輯頁：`#/websites/edit/:id` — 顯示 WebsiteEditor

HashRouter 的好處：
- 不影響 WordPress admin URL（`?page=power-partner` 不變）
- 不需要改動現有 Tabs 邏輯
- 瀏覽器上一頁可回到列表

### 修改範圍

**`Dashboard/index.tsx`**：
- 用 `HashRouter` 包裹現有的 `<Tabs>` 組件
- 新增 `<Route path="/websites/edit/:id" element={<WebsiteEditor />} />`
- 預設路由 `<Route path="/" element={<ExistingTabsDashboard />} />`

---

## Edit Page Structure

### 1. 麵包屑導航

```
網站列表 → 編輯網站
```

點擊「網站列表」返回 `#/`。

### 2. 唯讀摘要卡片

灰底卡片，顯示網站基本資訊（不可編輯）：

| 欄位 | 來源 |
|------|------|
| 網站域名 | `primaryDomain \|\| domain \|\| subDomain \|\| wildcardDomain` |
| WordPress 管理員 Email | `adminEmail` |
| WordPress 密碼 | `adminPassword`（遮罩 + 可複製） |
| 網站狀態 | `status`（色彩 Tag） |
| 每日扣款 | `dailyCost` |
| IP 地址 | `ipAddress` |
| 容器數量 | `phpPodSize` |
| PHP 版本 | `phpVersion`（預設顯示 php8.1） |

### 3. 可編輯表單

使用 Ant Design Form + 手動管理 state（power-partner 現有模式，不引入 react-hook-form）。

| 欄位 | 元件 | 驗證 | API 資料源 |
|------|------|------|-----------|
| 網站方案 | Select | Required | `GET /website-packages?isActive=true&limit=250` |
| 所屬用戶 | Select + 搜尋 | Required | `GET /users?limit=250` |
| 狀態 | Select | Required | Enum: running, stopped, creating, updating |
| PHP 版本 | Select | Optional | Enum: php7.4 ~ php8.5 |
| 標籤 | Multi-Select | Optional | `GET /labels?isActive=true&limit=1000` |
| 備註 | TextArea | Max 500 chars | — |

表單 Layout（Ant Design Row/Col）：
```
[網站方案]     [所屬用戶]
[狀態]         [PHP 版本]
[標籤 (全寬)]
[備註 (全寬)]
─────────────────────────
              [更新按鈕]
```

### 4. 更新按鈕

右對齊，disabled 條件：表單驗證未通過或提交中。提交中顯示 loading + "更新中..."。

---

## API Calls

power-partner 直接呼叫 PowerCloud API（`api.wpsite.pro`），與 nestjs-helm-admin 使用相同後端。

### 讀取網站資料

```
GET /websites/{id}
```

使用 `powerCloudAxios` 實例 + `usePowerCloudAxiosWithApiKey` hook 取得認證。

### 更新網站（一般欄位）

```
PATCH /websites/{id}
Body: {
  packageId: string,
  userId: string,
  status?: string,       // 僅在 PHP 版本未變更時帶入
  labelIds?: string[],
  memo?: string | null
}
```

### 更新 PHP 版本（獨立呼叫）

```
PATCH /wordpress/{id}/php-version
Body: { phpVersion: string }
```

PHP 版本變更時，不帶入 status，避免覆蓋後端自動設定的 "updating" 狀態。

### 成功/失敗通知

- 成功：antd notification "網站更新成功"
- 失敗：由 powerCloudAxios 的 response interceptor 自動顯示錯誤

---

## Data Model Changes

### 擴充 `IWebsite` interface（`SiteList/types.ts`）

新增欄位：
```typescript
dailyCost?: number
phpVersion?: string
labels?: ILabel[]
packageId?: string
userId?: string
```

新增 interface：
```typescript
interface ILabel {
  id: string
  key: string
  value: string
  isActive: boolean
  createdAt: string
  updatedAt: string
}
```

---

## New Files

```
js/src/pages/AdminApp/Dashboard/SiteList/
├── WebsiteEditor/
│   ├── index.tsx                 # 頁面容器：讀取資料、loading、麵包屑
│   ├── WebsiteEditorForm.tsx     # 表單：摘要卡片 + 可編輯欄位 + 更新按鈕
│   └── hooks/
│       └── useUpdateWebsite.ts   # 封裝 PATCH /websites + PATCH php-version
├── components/
│   ├── UserSelector.tsx          # 用戶搜尋 Select
│   ├── WebsitePackageSelector.tsx # 方案 Select
│   └── LabelSelector.tsx         # 標籤 Multi-Select
```

## Modified Files

| 檔案 | 變更 |
|------|------|
| `Dashboard/index.tsx` | 引入 HashRouter，設定路由 |
| `SiteList/WebsiteActionButtons.tsx` | 新增編輯按鈕（`SettingOutlined` + `<Link to={...}>`） |
| `SiteList/types.ts` | 擴充 IWebsite + 新增 ILabel |

---

## Action Button Icon

在 WebsiteActionButtons 的操作欄新增編輯按鈕：

```tsx
<Link to={`/websites/edit/${record.id}`}>
  <Tooltip title="編輯">
    <Button icon={<SettingOutlined />} size="small" type="text" />
  </Tooltip>
</Link>
```

按鈕順序（左到右）：
1. GlobalOutlined — 前往 WordPress 後台
2. **SettingOutlined — 編輯**（新增）
3. PlayCircleOutlined — 啟動（stopped 時顯示）
4. StopOutlined — 停止（running 時顯示）
5. EllipsisOutlined — 更多操作（變更域名、刪除）

---

## Out of Scope

- UserApp（前端客戶端）不需要編輯功能
- 不改動現有 Tabs 切換邏輯
- 不引入 react-hook-form 或 zod（使用 Ant Design Form 內建驗證）
- 不新增 PHP 後端 endpoints（直接呼叫 PowerCloud API）
