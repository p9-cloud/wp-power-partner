# Power-Partner UI Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align power-partner WordPress plugin admin panel content areas with nestjs-helm-admin frontend design — filter card, table columns, card containers, action buttons, and header info bar.

**Architecture:** Keep existing tab navigation and tech stack (Ant Design v5, Tailwind v3.4 with `.tailwind` scope). Modify content areas inside tabs to visually match nestjs-helm-admin's card-based layout, filter grid, column structure, and action button patterns. Create reusable `ContentCard` wrapper for all tab pages.

**Tech Stack:** React 18, Ant Design 5, Tailwind CSS 3.4 (scoped `.tailwind`), Jotai, TanStack Query v5

---

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `js/src/components/ContentCard.tsx` | Create | Reusable card wrapper (`rounded-xl border border-gray-300/50 p-4`) |
| `js/src/pages/AdminApp/Dashboard/SiteList/WebsiteListFilter.tsx` | Create | Grid-based advanced filter for PowerCloud site list |
| `js/src/pages/AdminApp/Dashboard/SiteList/WebsiteActionButtons.tsx` | Create | Icon-only action buttons with dropdown menu |
| `js/src/pages/AdminApp/Dashboard/SiteList/index.tsx` | Modify | Integrate filter, card containers, new columns, action buttons |
| `js/src/pages/AdminApp/Dashboard/AccountIcon/index.tsx` | Modify | Restyle to match nestjs-helm-admin UserProfile layout |
| `js/src/pages/AdminApp/Dashboard/index.tsx` | Modify | Wrap tab content in ContentCard |
| `js/src/pages/AdminApp/Dashboard/LogList/index.tsx` | Modify | Wrap in ContentCard |
| `js/src/pages/AdminApp/Dashboard/EmailSetting/index.tsx` | Modify | Wrap in ContentCard |
| `js/src/pages/AdminApp/Dashboard/ManualSiteSync/index.tsx` | Modify | Wrap in ContentCard |
| `js/src/pages/AdminApp/Dashboard/Settings/index.tsx` | Modify | Wrap in ContentCard |
| `js/src/pages/AdminApp/Dashboard/LicenseCodes/index.tsx` | Modify | Wrap in ContentCard |
| `js/src/pages/AdminApp/Dashboard/Description/index.tsx` | Modify | Wrap in ContentCard |
| `js/src/pages/AdminApp/Dashboard/PowercloudAuth/index.tsx` | Modify | Wrap in ContentCard |

---

### Task 1: Create ContentCard Component

**Files:**
- Create: `js/src/components/ContentCard.tsx`

- [ ] **Step 1: Create the ContentCard component**

```tsx
// js/src/components/ContentCard.tsx
import type { ReactNode } from 'react'

interface ContentCardProps {
	children: ReactNode
	className?: string
}

const ContentCard = ({ children, className = '' }: ContentCardProps) => {
	return (
		<div
			className={`bg-white rounded-xl border border-gray-300/50 p-4 ${className}`}
		>
			{children}
		</div>
	)
}

export default ContentCard
```

- [ ] **Step 2: Verify it builds**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && pnpm build`
Expected: Build succeeds

- [ ] **Step 3: Commit**

```bash
git add js/src/components/ContentCard.tsx
git commit -m "feat(ui): add ContentCard reusable wrapper component"
```

---

### Task 2: Restyle AccountIcon to Match nestjs-helm-admin Header

**Files:**
- Modify: `js/src/pages/AdminApp/Dashboard/AccountIcon/index.tsx`

The current AccountIcon already has the right data (balance, role, avatar, dropdown). We need to restyle it to match nestjs-helm-admin's `UserProfile` pattern — avatar with initials, badge status dot, user info, and cleaner layout.

- [ ] **Step 1: Read the current file**

```bash
cat js/src/pages/AdminApp/Dashboard/AccountIcon/index.tsx
```

- [ ] **Step 2: Update the AccountIcon component**

Replace the entire component render section. Key changes:
- Avatar: Use blue (#1677ff) background with white text initials (matching nestjs-helm-admin)
- Add Badge with success status dot around avatar
- Show role badge with crown inline (not in dropdown)
- Show balance inline (not in dropdown)
- Dropdown menu: simplify to Profile/Wallet/Settings/Disconnect with dividers
- Use `flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-gray-100` for avatar container

```tsx
import { Avatar, Badge, Dropdown, MenuProps, Tooltip } from 'antd'
import {
	identityAtom,
	globalLoadingAtom,
	defaultIdentity,
} from '@/pages/AdminApp/Atom/atom'
import { useAtom } from 'jotai'
import {
	UserOutlined,
	PoweroffOutlined,
	MailOutlined,
	SyncOutlined,
	CrownFilled,
	WalletOutlined,
	SettingOutlined,
} from '@ant-design/icons'
import { LOCALSTORAGE_ACCOUNT_KEY } from '@/utils'
import { LoadingText } from '@/components'
import { axios } from '@/api'
import { useQueryClient } from '@tanstack/react-query'
import { useRef } from 'react'

const DEPOSIT_LINK = 'https://cloud.luke.cafe/product/partner-top-up/'

const index = () => {
	const containerRef = useRef<HTMLDivElement>(null)
	const [identity, setIdentity] = useAtom(identityAtom)
	const powerMoney = identity.data?.power_money_amount || '0.00'
	const email = identity.data?.email
	const user_id = identity.data?.user_id || ''
	const partnerLvTitle = identity.data?.partner_lv?.title || ''
	const partnerLvKey = identity.data?.partner_lv?.key || '0'
	const [globalLoading, setGlobalLoading] = useAtom(globalLoadingAtom)
	const queryClient = useQueryClient()

	const handleDisconnect = async () => {
		setGlobalLoading({
			isLoading: true,
			label: '正在解除帳號綁定...',
		})
		try {
			await axios.delete('/power-partner/partner-id')
		} catch (error) {
			console.log('error', error)
		}
		localStorage.removeItem(LOCALSTORAGE_ACCOUNT_KEY)
		setIdentity(defaultIdentity)
		setGlobalLoading({
			isLoading: false,
			label: '',
		})
	}

	const handleRefetch = () => {
		;[
			'apps',
			'logs',
			'license-codes',
			'identity',
			'subscriptions/next-payment',
		].forEach((key) => {
			queryClient.invalidateQueries({
				queryKey: [key],
			})
		})
	}

	const items: MenuProps['items'] = [
		{
			key: 'user_id',
			label: `#${user_id}`,
			icon: <UserOutlined />,
		},
		{
			key: 'email',
			label: <span className="text-xs">{email || ''}</span>,
			icon: <MailOutlined />,
		},
		{
			key: 'wallet',
			label: (
				<a target="_blank" rel="noopener noreferrer" href={DEPOSIT_LINK}>
					前往儲值
				</a>
			),
			icon: <WalletOutlined />,
		},
		{
			type: 'divider',
		},
		{
			key: 'disconnect',
			label: <span onClick={handleDisconnect}>解除帳號綁定</span>,
			icon: <PoweroffOutlined className="text-red-500" />,
			danger: true,
		},
	]

	return (
		<div
			className="ml-4 xl:mr-4 flex items-center gap-4"
			ref={containerRef}
		>
			<Tooltip
				title="刷新資料"
				getPopupContainer={() => containerRef.current as HTMLElement}
			>
				<SyncOutlined
					spin={globalLoading?.isLoading}
					onClick={handleRefetch}
					className="cursor-pointer text-gray-500 hover:text-primary"
				/>
			</Tooltip>

			{partnerLvTitle && (
				<Tooltip
					title={
						partnerLvKey === '2'
							? '您已是最高階經銷商'
							: '升級為高階經銷商，享受更高主機折扣'
					}
					getPopupContainer={() => containerRef.current as HTMLElement}
				>
					<a
						target="_blank"
						rel="noopener noreferrer"
						href={DEPOSIT_LINK}
						className="flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-sm no-underline"
					>
						<CrownFilled
							className={`${
								partnerLvKey === '2' ? 'text-yellow-500' : 'text-gray-300'
							}`}
						/>
						<LoadingText
							isLoading={globalLoading?.isLoading}
							content={
								<span className="text-gray-700 text-sm">{partnerLvTitle}</span>
							}
						/>
					</a>
				</Tooltip>
			)}

			<Tooltip
				title="前往儲值"
				getPopupContainer={() => containerRef.current as HTMLElement}
			>
				<a
					target="_blank"
					rel="noopener noreferrer"
					href={DEPOSIT_LINK}
					className="flex items-center gap-1 text-sm no-underline"
				>
					<span className="text-yellow-500 font-bold">¥</span>
					<LoadingText
						isLoading={globalLoading?.isLoading}
						content={
							<span className="text-gray-700 font-medium">{powerMoney}</span>
						}
					/>
				</a>
			</Tooltip>

			<Dropdown
				menu={{ items }}
				placement="bottomRight"
				trigger={['click']}
				getPopupContainer={() => containerRef.current as HTMLElement}
			>
				<Badge dot status="success" offset={[-4, 4]}>
					<Avatar
						size={36}
						className="cursor-pointer"
						style={{ backgroundColor: '#1677ff', color: '#fff', fontWeight: 600 }}
					>
						{(email || 'U').charAt(0).toUpperCase()}
					</Avatar>
				</Badge>
			</Dropdown>
		</div>
	)
}

export default index
```

- [ ] **Step 3: Verify it builds**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && pnpm build`
Expected: Build succeeds

- [ ] **Step 4: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/AccountIcon/index.tsx
git commit -m "feat(ui): restyle AccountIcon to match nestjs-helm-admin header"
```

---

### Task 3: Create WebsiteListFilter Component

**Files:**
- Create: `js/src/pages/AdminApp/Dashboard/SiteList/WebsiteListFilter.tsx`

Port the filter from nestjs-helm-admin but adapt for power-partner's data fetching pattern (no URL search params — use local state + callback).

- [ ] **Step 1: Create the filter component**

```tsx
// js/src/pages/AdminApp/Dashboard/SiteList/WebsiteListFilter.tsx
import { Button, DatePicker, Input, InputNumber, Select } from 'antd'
import { useState } from 'react'
import { CloseCircleOutlined } from '@ant-design/icons'

const statusOptions = [
	{ label: '全部狀態', value: '' },
	{ label: '建置中', value: 'creating' },
	{ label: '運行中', value: 'running' },
	{ label: '已停止', value: 'stopped' },
	{ label: '刪除中', value: 'deleting' },
]

export interface WebsiteFilters {
	websiteKeyword: string
	userKeyword: string
	status: string
	startDailyCostPrice: number | null
	endDailyCostPrice: number | null
	startDate: string
	endDate: string
}

const defaultFilters: WebsiteFilters = {
	websiteKeyword: '',
	userKeyword: '',
	status: '',
	startDailyCostPrice: null,
	endDailyCostPrice: null,
	startDate: '',
	endDate: '',
}

interface SearchLabel {
	key: keyof WebsiteFilters
	label: string
	value: string
}

interface WebsiteListFilterProps {
	onSearch: (filters: WebsiteFilters) => void
}

const WebsiteListFilter = ({ onSearch }: WebsiteListFilterProps) => {
	const [filters, setFilters] = useState<WebsiteFilters>(defaultFilters)

	const handleFilterChange = <K extends keyof WebsiteFilters>(
		key: K,
		value: WebsiteFilters[K],
	) => {
		setFilters((prev) => ({ ...prev, [key]: value }))
	}

	const handleSearch = () => onSearch(filters)

	const handleClear = () => {
		setFilters(defaultFilters)
		onSearch(defaultFilters)
	}

	const searchLabels: SearchLabel[] = []
	if (filters.websiteKeyword)
		searchLabels.push({
			key: 'websiteKeyword',
			label: '網站關鍵字',
			value: filters.websiteKeyword,
		})
	if (filters.userKeyword)
		searchLabels.push({
			key: 'userKeyword',
			label: '用戶關鍵字',
			value: filters.userKeyword,
		})
	if (filters.status)
		searchLabels.push({
			key: 'status',
			label: '狀態',
			value:
				statusOptions.find((o) => o.value === filters.status)?.label ||
				filters.status,
		})
	if (filters.startDailyCostPrice != null)
		searchLabels.push({
			key: 'startDailyCostPrice',
			label: '最低每日成本',
			value: String(filters.startDailyCostPrice),
		})
	if (filters.endDailyCostPrice != null)
		searchLabels.push({
			key: 'endDailyCostPrice',
			label: '最高每日成本',
			value: String(filters.endDailyCostPrice),
		})
	if (filters.startDate)
		searchLabels.push({
			key: 'startDate',
			label: '開始日期',
			value: filters.startDate,
		})
	if (filters.endDate)
		searchLabels.push({
			key: 'endDate',
			label: '結束日期',
			value: filters.endDate,
		})

	const removeLabel = (key: keyof WebsiteFilters) => {
		const resetValue = key === 'startDailyCostPrice' || key === 'endDailyCostPrice' ? null : ''
		const newFilters = { ...filters, [key]: resetValue }
		setFilters(newFilters)
		onSearch(newFilters)
	}

	return (
		<div className="space-y-4">
			<div className="grid grid-cols-2 xl:grid-cols-4 items-center gap-4">
				<Input
					placeholder="網站名稱、域名..."
					value={filters.websiteKeyword}
					onChange={(e) =>
						handleFilterChange('websiteKeyword', e.target.value)
					}
					onPressEnter={handleSearch}
				/>
				<Input
					placeholder="用戶關鍵字（管理員 Email、擁有者）"
					value={filters.userKeyword}
					onChange={(e) =>
						handleFilterChange('userKeyword', e.target.value)
					}
					onPressEnter={handleSearch}
				/>
				<Select
					value={filters.status}
					onChange={(value) => handleFilterChange('status', value)}
					options={statusOptions}
					className="w-full"
				/>
				<InputNumber
					placeholder="最低每日成本"
					min={0}
					value={filters.startDailyCostPrice}
					onChange={(v) => handleFilterChange('startDailyCostPrice', v)}
					onPressEnter={handleSearch}
					className="w-full"
				/>
				<InputNumber
					placeholder="最高每日成本"
					min={0}
					value={filters.endDailyCostPrice}
					onChange={(v) => handleFilterChange('endDailyCostPrice', v)}
					onPressEnter={handleSearch}
					className="w-full"
				/>
				<DatePicker
					placeholder="開始日期"
					onChange={(_, dateString) =>
						handleFilterChange('startDate', dateString as string)
					}
					className="w-full"
				/>
				<DatePicker
					placeholder="結束日期"
					onChange={(_, dateString) =>
						handleFilterChange('endDate', dateString as string)
					}
					className="w-full"
				/>
				<div className="flex gap-2">
					<Button type="primary" onClick={handleSearch} className="flex-1">
						搜尋
					</Button>
					<Button onClick={handleClear} className="flex-1">
						清除
					</Button>
				</div>
			</div>

			{searchLabels.length > 0 && (
				<div className="flex flex-wrap gap-2">
					{searchLabels.map((label) => (
						<div
							key={label.key}
							className="flex items-center gap-2 rounded-full bg-blue-200/50 px-3 py-1 text-sm text-blue-500"
						>
							<span>
								{label.label}: {label.value}
							</span>
							<CloseCircleOutlined
								className="cursor-pointer hover:text-blue-700"
								onClick={() => removeLabel(label.key)}
							/>
						</div>
					))}
				</div>
			)}
		</div>
	)
}

export default WebsiteListFilter
```

- [ ] **Step 2: Verify it builds**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && pnpm build`
Expected: Build succeeds

- [ ] **Step 3: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/WebsiteListFilter.tsx
git commit -m "feat(ui): add WebsiteListFilter component matching nestjs-helm-admin"
```

---

### Task 4: Create WebsiteActionButtons Component

**Files:**
- Create: `js/src/pages/AdminApp/Dashboard/SiteList/WebsiteActionButtons.tsx`

Port the action buttons pattern from nestjs-helm-admin — quick action buttons (WP admin, start/stop) + dropdown menu for more actions (delete, domain change).

- [ ] **Step 1: Create the action buttons component**

```tsx
// js/src/pages/AdminApp/Dashboard/SiteList/WebsiteActionButtons.tsx
import {
	DeleteOutlined,
	EditOutlined,
	EllipsisOutlined,
	GlobalOutlined,
	SettingOutlined,
	StopOutlined,
	SyncOutlined,
} from '@ant-design/icons'
import { Button, Dropdown, Modal, Popconfirm, Tooltip } from 'antd'
import type { MenuProps } from 'antd'
import type { IWebsite } from './types'

interface WebsiteActionButtonsProps {
	record: IWebsite
	onStart: (id: string) => void
	onStop: (id: string) => void
	onDelete: (id: string) => void
	onChangeDomain: (website: IWebsite) => void
}

const getDomain = (website: IWebsite): string => {
	return (
		website.primaryDomain ||
		website.domain ||
		website.subDomain ||
		website.wildcardDomain ||
		''
	)
}

const WebsiteActionButtons = ({
	record,
	onStart,
	onStop,
	onDelete,
	onChangeDomain,
}: WebsiteActionButtonsProps) => {
	const domain = getDomain(record)
	const isRunning = record.status === 'running'
	const isStopped = record.status === 'stopped'
	const isUpdating = record.status === 'creating' || record.status === 'updating'

	const buildDropdownItems = (): MenuProps['items'] => {
		const serviceItems: MenuProps['items'] = [
			{
				key: 'change-domain',
				icon: <GlobalOutlined />,
				label: '變更域名',
				disabled: isStopped,
				onClick: () => onChangeDomain(record),
			},
		]

		const dangerItems: MenuProps['items'] = [
			{
				key: 'delete',
				icon: <DeleteOutlined />,
				label: '刪除網站',
				danger: true,
				onClick: () => {
					Modal.confirm({
						title: '刪除網站',
						content: `確定要刪除站台 ${domain} 嗎？此操作無法復原。`,
						okText: '確認刪除',
						cancelText: '取消',
						okButtonProps: { danger: true },
						onOk: () => onDelete(record.id),
					})
				},
			},
		]

		return [
			{
				key: 'service-group',
				type: 'group' as const,
				label: '服務管理',
				children: serviceItems,
			},
			{ type: 'divider' as const },
			{
				key: 'danger-group',
				type: 'group' as const,
				label: '危險操作',
				children: dangerItems,
			},
		]
	}

	return (
		<div
			className={`flex items-center justify-center gap-1 ${
				isUpdating ? 'pointer-events-none opacity-50' : ''
			}`}
		>
			<a href={`https://${domain}/wp-admin`} target="_blank" rel="noreferrer">
				<Tooltip title="前往 WordPress 後台">
					<Button icon={<SettingOutlined />} size="small" type="text" />
				</Tooltip>
			</a>

			{isRunning && (
				<Popconfirm
					title="確認停止站台"
					description={`確定要停止站台 ${domain} 嗎？`}
					onConfirm={() => onStop(record.id)}
					okText="確認停止"
					cancelText="取消"
					okButtonProps={{ danger: true }}
				>
					<Tooltip title="停止站台">
						<Button icon={<StopOutlined />} size="small" type="text" danger />
					</Tooltip>
				</Popconfirm>
			)}

			{isStopped && (
				<Popconfirm
					title="確認啟動站台"
					description={`確定要啟動站台 ${domain} 嗎？`}
					onConfirm={() => onStart(record.id)}
					okText="確認啟動"
					cancelText="取消"
				>
					<Tooltip title="啟動站台">
						<Button icon={<SyncOutlined />} size="small" type="text" />
					</Tooltip>
				</Popconfirm>
			)}

			<Dropdown
				menu={{ items: buildDropdownItems() }}
				trigger={['click']}
				placement="bottomRight"
			>
				<Button icon={<EllipsisOutlined />} size="small" type="text" />
			</Dropdown>
		</div>
	)
}

export default WebsiteActionButtons
```

- [ ] **Step 2: Create the shared IWebsite types file**

```tsx
// js/src/pages/AdminApp/Dashboard/SiteList/types.ts
export interface IWebsite {
	id: string
	name: string
	domain?: string
	primaryDomain?: string
	subDomain?: string
	wildcardDomain: string
	namespace: string
	status: string
	adminUsername: string
	adminEmail: string
	adminPassword: string
	databaseName: string
	databaseUsername: string
	databasePassword: string | null
	databaseRootPassword: string | null
	package: {
		id: string
		name: string
		description: string
		price: string
		wordpressSize: string
		mysqlSize: string
	} | null
	user: {
		id: string
		firstName: string
		lastName: string
		email: string
	} | null
	phpPodSize: number
	ipAddress: string
	dailyCost?: number
	createdAt: string
	updatedAt: string
}

export interface IWebsiteResponse {
	data: IWebsite[]
	total: number
}
```

- [ ] **Step 3: Verify it builds**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && pnpm build`
Expected: Build succeeds

- [ ] **Step 4: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/WebsiteActionButtons.tsx js/src/pages/AdminApp/Dashboard/SiteList/types.ts
git commit -m "feat(ui): add WebsiteActionButtons and shared types"
```

---

### Task 5: Refactor SiteList PowercloudContent with Card Layout, Filter, and Aligned Columns

**Files:**
- Modify: `js/src/pages/AdminApp/Dashboard/SiteList/index.tsx`

This is the main task — integrate the filter, card containers, reordered columns, and action buttons into the PowercloudContent component.

- [ ] **Step 1: Read the current file**

```bash
cat js/src/pages/AdminApp/Dashboard/SiteList/index.tsx
```

- [ ] **Step 2: Rewrite the PowercloudContent component**

Key changes:
1. Import ContentCard, WebsiteListFilter, WebsiteActionButtons
2. Import types from `./types` (remove inline IWebsite/IWebsiteResponse)
3. Wrap filter in ContentCard
4. Wrap table in ContentCard
5. Reorder columns to match nestjs-helm-admin: 網站資訊 → 狀態 → 管理員電子郵件 → 管理員密碼 → IP 位址 → 網站方案 → 網站擁有者 → 每日扣款 → 容器數量 → 建立時間 → 操作
6. Status Tag: add `bordered` prop for outlined style
7. 網站名稱 → 網站資訊: show domain + namespace with copy
8. Action column: use WebsiteActionButtons component
9. Add `space-y-4` layout between filter card and table card

Replace the entire `PowercloudContent` component:

```tsx
import {
	CloudOutlined,
	GlobalOutlined,
	LinkOutlined,
	ReloadOutlined,
	SyncOutlined,
} from '@ant-design/icons'
import { useMutation, useQuery } from '@tanstack/react-query'
import {
	Alert,
	Button,
	Empty,
	Form,
	Input,
	InputNumber,
	message,
	Modal,
	Popconfirm,
	Space,
	Spin,
	Table,
	Tabs,
	TabsProps,
	Tag,
	Tooltip,
	Typography,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useAtomValue, useSetAtom } from 'jotai'
import { useEffect, useState } from 'react'

import {
	EPowercloudIdentityStatusEnum,
	powercloudIdentityAtom,
} from '../../Atom/powercloud.atom'
import { setTabAtom, TabKeyEnum } from '../../Atom/tab.atom'

import { powerCloudAxios, usePowerCloudAxiosWithApiKey } from '@/api'
import {
	SiteListTable,
	useCustomers,
	useTable,
} from '@/components/SiteListTable'
import { globalLoadingAtom, identityAtom } from '@/pages/AdminApp/Atom/atom'
import ContentCard from '@/components/ContentCard'
import WebsiteListFilter from './WebsiteListFilter'
import type { WebsiteFilters } from './WebsiteListFilter'
import WebsiteActionButtons from './WebsiteActionButtons'
import type { IWebsite, IWebsiteResponse } from './types'

const { Text, Link } = Typography

const statusColorMap: Record<string, string> = {
	creating: 'processing',
	running: 'success',
	stopped: 'warning',
	deleting: 'error',
}

const statusTextMap: Record<string, string> = {
	creating: '建置中',
	running: '運行中',
	stopped: '已停止',
	deleting: '刪除中',
}

// PodSizeEditor stays the same (keep existing code)
const PodSizeEditor = ({
	initialValue,
	domain,
	packagePrice,
	onUpdate,
}: {
	initialValue: number
	domain: string
	packagePrice?: string
	onUpdate: (value: number) => void
}) => {
	const [value, setValue] = useState(initialValue)
	const dailyCostPerPod = +(+(packagePrice ?? 0) / 365).toFixed(2)
	const dailyCost = +(dailyCostPerPod * (1 + 0.6 * (value - 1))).toFixed(2)
	useEffect(() => { setValue(initialValue) }, [initialValue])

	return (
		<div className="flex gap-2 items-center">
			<InputNumber min={1} max={10} value={value} onChange={(v) => setValue(v ?? 1)} size="small" />
			<Tooltip title="更新容器數量">
				<Popconfirm
					title="確認更新容器數量"
					description={
						<div className="flex flex-col gap-1">
							<div>確定要將站台 <strong>{domain}</strong> 的容器數量更新為 <strong>{value}</strong> 個嗎？</div>
							<div className="mt-2 text-xs text-gray-500">
								<div>計算公式：每日扣款價格 X 1 + 每日扣款價格 X 額外容器數量 X 0.6</div>
								<div>= {dailyCostPerPod} X 1 + {dailyCostPerPod} X ({value} - 1) X 0.6</div>
								<div>= NT$ {dailyCost}/日</div>
							</div>
							<div className="mt-2 font-medium">每日預計扣款：<span className="text-blue-600">NT$ {dailyCost}/日</span></div>
						</div>
					}
					onConfirm={() => onUpdate(value)}
					okText="確認更新"
					cancelText="取消"
				>
					<Button type="link" size="small" icon={<SyncOutlined />} />
				</Popconfirm>
			</Tooltip>
		</div>
	)
}

const getDomain = (website: IWebsite): string => {
	return website.primaryDomain || website.domain || website.subDomain || website.wildcardDomain || ''
}

const PowercloudContent = () => {
	const powerCloudInstance = usePowerCloudAxiosWithApiKey(powerCloudAxios)
	const [pagination, setPagination] = useState({ page: 1, limit: 10 })
	const [isChangeDomainModalOpen, setIsChangeDomainModalOpen] = useState(false)
	const [selectedWebsite, setSelectedWebsite] = useState<IWebsite | null>(null)
	const [form] = Form.useForm()
	const [searchFilters, setSearchFilters] = useState<WebsiteFilters | null>(null)

	const { data, isLoading, refetch, isFetching } = useQuery({
		queryKey: ['powercloud-websites', pagination.page, pagination.limit, searchFilters],
		queryFn: () => {
			const params = new URLSearchParams()
			params.set('page', String(pagination.page))
			params.set('limit', String(pagination.limit))
			if (searchFilters) {
				if (searchFilters.websiteKeyword) params.set('keyword', searchFilters.websiteKeyword)
				if (searchFilters.userKeyword) params.set('userKeyword', searchFilters.userKeyword)
				if (searchFilters.status) params.set('status', searchFilters.status)
				if (searchFilters.startDailyCostPrice != null) params.set('startDailyCostPrice', String(searchFilters.startDailyCostPrice))
				if (searchFilters.endDailyCostPrice != null) params.set('endDailyCostPrice', String(searchFilters.endDailyCostPrice))
				if (searchFilters.startDate) params.set('startDate', searchFilters.startDate)
				if (searchFilters.endDate) params.set('endDate', searchFilters.endDate)
			}
			return powerCloudInstance.get<IWebsiteResponse>(`/websites?${params.toString()}`)
		},
	})

	// Keep existing mutations (deleteWebsite, startWebsite, stopWebsite, updatePodSize, changeDomain)
	// ... (same as current code)

	const { mutate: deleteWebsite } = useMutation({
		mutationFn: (id: string) => powerCloudInstance.delete(`/wordpress/${id}`),
	})
	const { mutate: startWebsite } = useMutation({
		mutationFn: (id: string) => powerCloudInstance.patch(`/wordpress/${id}/start`),
	})
	const { mutate: stopWebsite } = useMutation({
		mutationFn: (id: string) => powerCloudInstance.patch(`/wordpress/${id}/stop`),
	})
	const { mutate: updatePodSize } = useMutation({
		mutationFn: ({ id, phpPodSize }: { id: string; phpPodSize: number }) =>
			powerCloudInstance.patch(`/wordpress/${id}/pod-size`, { phpPodSize }),
	})
	const { mutate: changeDomain, isPending: isChangingDomain } = useMutation({
		mutationFn: ({ id, newDomain }: { id: string; newDomain: string }) =>
			powerCloudInstance.patch(`/wordpress/${id}/domain`, { domain: newDomain }),
		onSuccess: () => {
			message.success('域名變更成功')
			setIsChangeDomainModalOpen(false)
			form.resetFields()
			refetch()
		},
		onError: (error: any) => {
			message.error(`域名變更失敗: ${error?.response?.data?.message || error.message}`)
		},
	})

	const websites = data?.data?.data || []
	const total = data?.data?.total || 0

	const handleDelete = (id: string) => deleteWebsite(id)
	const handleStop = (id: string) => stopWebsite(id)
	const handleStart = (id: string) => startWebsite(id)
	const handlePodSizeChange = (id: string, value: number) => updatePodSize({ id, phpPodSize: value })

	const handleShowChangeDomainModal = (website: IWebsite) => {
		setSelectedWebsite(website)
		setIsChangeDomainModalOpen(true)
		form.setFieldsValue({ newDomain: '' })
	}

	const handleChangeDomain = () => {
		form.validateFields().then((values) => {
			if (selectedWebsite) changeDomain({ id: selectedWebsite.id, newDomain: values.newDomain })
		})
	}

	const handleSearch = (filters: WebsiteFilters) => {
		setSearchFilters(filters)
		setPagination((prev) => ({ ...prev, page: 1 }))
	}

	// NEW column order matching nestjs-helm-admin
	const columns: ColumnsType<IWebsite> = [
		{
			title: '網站資訊',
			key: 'website-info',
			width: 300,
			render: (_, record) => (
				<Space direction="vertical" size={0}>
					<div className="flex gap-2">
						<Link href={`https://${getDomain(record)}`} target="_blank" style={{ fontSize: 14 }}>
							<LinkOutlined /> {getDomain(record)}
						</Link>
						<Text className="text-xs text-gray-500" copyable={{ text: `https://${getDomain(record)}` }} />
					</div>
					<Text className="text-xs text-gray-500" copyable>{record.namespace || record.name}</Text>
				</Space>
			),
		},
		{
			title: '狀態',
			dataIndex: 'status',
			key: 'status',
			width: 100,
			render: (status: string) => (
				<Tag color={statusColorMap[status] || 'default'} bordered={false}>
					{statusTextMap[status] || status}
				</Tag>
			),
		},
		{
			title: '管理員電子郵件',
			dataIndex: 'adminEmail',
			key: 'adminEmail',
			ellipsis: true,
			render: (email: string) => <Text copyable ellipsis>{email}</Text>,
		},
		{
			title: '管理員密碼',
			key: 'adminPassword',
			render: (_, record) => (
				<Text copyable={{ text: record.adminPassword }}>•••••••••••</Text>
			),
		},
		{
			title: 'IP 地址',
			dataIndex: 'ipAddress',
			key: 'ipAddress',
			render: (ipAddress: string) => <Text copyable ellipsis>{ipAddress || '-'}</Text>,
		},
		{
			title: '網站方案',
			dataIndex: 'package',
			key: 'package',
			render: (pkg: IWebsite['package']) =>
				pkg ? (
					<div className="flex flex-col gap-1">
						<span className="text-gray-600">{pkg.name}</span>
						<span className="text-xs text-gray-400">$NT {Number(pkg.price).toFixed(2)}/年</span>
					</div>
				) : (
					<span className="text-gray-400">-</span>
				),
		},
		{
			title: '網站擁有者',
			dataIndex: 'user',
			key: 'user',
			render: (user: IWebsite['user']) => {
				if (!user) return <span className="text-gray-400">-</span>
				return (
					<span className="text-blue-500">
						{user.firstName ?? ''} {user.lastName ?? ''}
					</span>
				)
			},
		},
		{
			title: '每日扣款',
			dataIndex: 'dailyCost',
			key: 'dailyCost',
			sorter: (a: IWebsite, b: IWebsite) => (a.dailyCost ?? 0) - (b.dailyCost ?? 0),
			render: (dailyCost: number) => (
				<span className="font-medium">${Number(dailyCost ?? 0).toFixed(2)}</span>
			),
		},
		{
			title: '容器數量',
			dataIndex: 'phpPodSize',
			key: 'phpPodSize',
			sorter: (a: IWebsite, b: IWebsite) => a.phpPodSize - b.phpPodSize,
			render: (phpPodSize: number, record) => {
				const isDisabled = record.status === 'creating' || record.status === 'stopped'
				return (
					<div className={`flex items-center justify-center gap-2 ${isDisabled ? 'pointer-events-none opacity-50' : ''}`}>
						<PodSizeEditor
							initialValue={phpPodSize ?? 1}
							domain={getDomain(record)}
							packagePrice={record.package?.price}
							onUpdate={(value) => handlePodSizeChange(record.id, value)}
						/>
					</div>
				)
			},
		},
		{
			title: '建立時間',
			dataIndex: 'createdAt',
			key: 'createdAt',
			sorter: (a: IWebsite, b: IWebsite) => new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime(),
			render: (date: string) => (
				<span>{new Date(date).toLocaleString('zh-TW')}</span>
			),
		},
		{
			title: '操作',
			key: 'actions',
			fixed: 'right',
			width: 150,
			render: (_, record) => (
				<WebsiteActionButtons
					record={record}
					onStart={handleStart}
					onStop={handleStop}
					onDelete={handleDelete}
					onChangeDomain={handleShowChangeDomainModal}
				/>
			),
		},
	]

	if (isLoading) {
		return (
			<div style={{ textAlign: 'center', padding: '60px 0' }}>
				<Spin size="large" />
				<div style={{ marginTop: 16 }}>
					<Text type="secondary">載入網站列表中...</Text>
				</div>
			</div>
		)
	}

	if (!websites.length && !searchFilters) {
		return (
			<Empty description="尚無網站資料" style={{ padding: '60px 0' }}>
				<Text type="secondary">請前往「手動開站」建立您的第一個網站</Text>
			</Empty>
		)
	}

	return (
		<div className="space-y-4">
			<ContentCard>
				<WebsiteListFilter onSearch={handleSearch} />
			</ContentCard>

			<ContentCard>
				<div className="flex justify-between items-center mb-4">
					<Text type="secondary">共 {total || 0} 個網站</Text>
					<Button
						icon={<ReloadOutlined spin={isFetching} />}
						onClick={() => refetch()}
						loading={isFetching}
					>
						重新整理
					</Button>
				</div>
				<Table
					columns={columns}
					dataSource={websites}
					rowKey="id"
					loading={isFetching}
					scroll={{ x: 'max-content' }}
					pagination={{
						current: pagination.page,
						pageSize: pagination.limit,
						total,
						showSizeChanger: true,
						showQuickJumper: true,
						pageSizeOptions: ['10', '20', '50'],
						showTotal: (total, range) =>
							`顯示 ${range[0]}-${range[1]} 共 ${total} 筆記錄`,
						onChange: (page, pageSize) => {
							setPagination({ page, limit: pageSize })
						},
					}}
				/>
			</ContentCard>

			{/* Domain change modal stays the same */}
			<Modal
				title="變更域名 (Domain Name)"
				open={isChangeDomainModalOpen}
				onCancel={() => { setIsChangeDomainModalOpen(false); form.resetFields() }}
				onOk={handleChangeDomain}
				confirmLoading={isChangingDomain}
				okText="確認變更域名"
				cancelText="取消"
				okButtonProps={{ danger: true }}
			>
				<Form form={form} layout="vertical" className="mt-8">
					<Alert message="提醒：" description="請先將網域 DNS 設定中的 A 紀錄 (A Record) 指向正確的 IP，再變更網域" type="info" showIcon className="mb-4" />
					<div className="mb-6">
						<p className="mt-0 mb-2 text-sm font-medium">當前域名</p>
						<div className="px-3 py-2 bg-gray-100 rounded-md border border-gray-300">
							<Text copyable>{selectedWebsite?.domain}</Text>
						</div>
					</div>
					<Form.Item label="新域名" name="newDomain" rules={[
						{ required: true, message: '請輸入新的 domain name' },
						{ pattern: /^(?!http(s)?:\/\/)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/g, message: <>請輸入不含 <Tag>{'http(s)://'}</Tag> 的合格的網址</> },
					]}>
						<Input placeholder="example.com" />
					</Form.Item>
				</Form>
			</Modal>
		</div>
	)
}
```

The rest of the file (Powercloud auth check, WPCD, siteTypeItems, SiteList export) stays the same.

- [ ] **Step 3: Verify it builds**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && pnpm build`
Expected: Build succeeds

- [ ] **Step 4: Manually verify in browser**

Open the WordPress admin panel, go to the power-partner page, and verify:
- Filter card appears above the table with grid layout
- Table columns match the new order
- Status tags use outlined style
- Action buttons use the new dropdown pattern
- Card containers have rounded borders

- [ ] **Step 5: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/index.tsx
git commit -m "feat(ui): refactor PowercloudContent with filter, cards, aligned columns"
```

---

### Task 6: Wrap All Other Tab Content in ContentCard

**Files:**
- Modify: `js/src/pages/AdminApp/Dashboard/LogList/index.tsx`
- Modify: `js/src/pages/AdminApp/Dashboard/EmailSetting/index.tsx`
- Modify: `js/src/pages/AdminApp/Dashboard/ManualSiteSync/index.tsx`
- Modify: `js/src/pages/AdminApp/Dashboard/Settings/index.tsx`
- Modify: `js/src/pages/AdminApp/Dashboard/LicenseCodes/index.tsx`
- Modify: `js/src/pages/AdminApp/Dashboard/Description/index.tsx`
- Modify: `js/src/pages/AdminApp/Dashboard/PowercloudAuth/index.tsx`

For each file, wrap the existing content in a ContentCard component.

- [ ] **Step 1: Read each file to understand structure**

```bash
head -30 js/src/pages/AdminApp/Dashboard/LogList/index.tsx
head -30 js/src/pages/AdminApp/Dashboard/EmailSetting/index.tsx
head -30 js/src/pages/AdminApp/Dashboard/ManualSiteSync/index.tsx
head -30 js/src/pages/AdminApp/Dashboard/Settings/index.tsx
head -30 js/src/pages/AdminApp/Dashboard/LicenseCodes/index.tsx
head -30 js/src/pages/AdminApp/Dashboard/Description/index.tsx
head -30 js/src/pages/AdminApp/Dashboard/PowercloudAuth/index.tsx
```

- [ ] **Step 2: For each file, add ContentCard import and wrap**

Pattern for each file — add import at top:
```tsx
import ContentCard from '@/components/ContentCard'
```

Then wrap the outermost return `<div>` (or fragment) in `<ContentCard>`:
```tsx
// Before
return (
  <div>
    {/* existing content */}
  </div>
)

// After
return (
  <ContentCard>
    {/* existing content */}
  </ContentCard>
)
```

For files with multiple sections, wrap each section in its own ContentCard with `space-y-4` between them:
```tsx
return (
  <div className="space-y-4">
    <ContentCard>
      {/* section 1 */}
    </ContentCard>
    <ContentCard>
      {/* section 2 */}
    </ContentCard>
  </div>
)
```

- [ ] **Step 3: Verify it builds**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && pnpm build`
Expected: Build succeeds

- [ ] **Step 4: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/LogList/index.tsx js/src/pages/AdminApp/Dashboard/EmailSetting/index.tsx js/src/pages/AdminApp/Dashboard/ManualSiteSync/index.tsx js/src/pages/AdminApp/Dashboard/Settings/index.tsx js/src/pages/AdminApp/Dashboard/LicenseCodes/index.tsx js/src/pages/AdminApp/Dashboard/Description/index.tsx js/src/pages/AdminApp/Dashboard/PowercloudAuth/index.tsx
git commit -m "feat(ui): wrap all tab content pages in ContentCard"
```

---

### Task 7: Final Integration and Visual Verification

**Files:**
- Review all modified files

- [ ] **Step 1: Build the project**

```bash
cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && pnpm build
```

Expected: Build succeeds with no TypeScript errors

- [ ] **Step 2: Visual verification checklist**

Open the WordPress admin panel with the power-partner plugin active. Verify each item:

1. **Tab bar**: Top tabs preserved, no changes to tab structure
2. **Header info bar (right side)**: Shows refresh icon, role badge (crown + title), balance (¥), blue avatar with initials
3. **Site list - Filter card**: Rounded card with grid layout, input fields for keyword/status/cost/date, search + clear buttons
4. **Site list - Table card**: Rounded card containing table
5. **Site list - Columns**: Ordered as 網站資訊, 狀態, 管理員電子郵件, 管理員密碼, IP 地址, 網站方案, 網站擁有者, 每日扣款, 容器數量, 建立時間, 操作
6. **Site list - Status tags**: Outlined style
7. **Site list - Action buttons**: Icon buttons + ellipsis dropdown menu
8. **Site list - Pagination**: Shows "顯示 1-10 共 X 筆記錄"
9. **Other tabs**: All wrapped in ContentCard with rounded borders
10. **No CSS leaks**: WordPress admin styles unaffected

- [ ] **Step 3: Commit final state**

```bash
git add -A
git commit -m "feat(ui): complete UI alignment with nestjs-helm-admin"
```
