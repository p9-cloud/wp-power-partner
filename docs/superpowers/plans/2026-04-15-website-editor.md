# Website Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a website edit page to power-partner AdminApp so admins can edit website properties (package, user, status, PHP version, labels, memo).

**Architecture:** Use React Router `HashRouter` to add a `/websites/edit/:id` route alongside the existing Tabs dashboard. The edit page calls PowerCloud API directly (`PATCH /websites/{id}` and `PATCH /wordpress/{id}/php-version`). Selector components fetch data from PowerCloud API endpoints.

**Tech Stack:** React 18, Ant Design 5, TanStack Query v5, Jotai, react-router v7, Axios, Tailwind CSS

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `js/src/pages/AdminApp/Dashboard/SiteList/WebsiteEditor/index.tsx` | Page container: fetch website by ID, loading state, breadcrumb, render form |
| `js/src/pages/AdminApp/Dashboard/SiteList/WebsiteEditor/WebsiteEditorForm.tsx` | Read-only summary card + editable form fields + update button |
| `js/src/pages/AdminApp/Dashboard/SiteList/WebsiteEditor/useUpdateWebsite.ts` | Hook: PATCH /websites/{id} + PATCH /wordpress/{id}/php-version mutations |
| `js/src/pages/AdminApp/Dashboard/SiteList/components/UserSelector.tsx` | Debounced user search Select component |
| `js/src/pages/AdminApp/Dashboard/SiteList/components/WebsitePackageSelector.tsx` | Package Select component |
| `js/src/pages/AdminApp/Dashboard/SiteList/components/LabelSelector.tsx` | Multi-select label component |

### Modified Files

| File | Change |
|------|--------|
| `js/src/pages/AdminApp/Dashboard/SiteList/types.ts` | Add `ILabel` interface, extend `IWebsite` with `phpVersion`, `labels`, `packageId`, `userId` |
| `js/src/pages/AdminApp/Dashboard/index.tsx` | Wrap with `HashRouter`, add route for `/websites/edit/:id` |
| `js/src/pages/AdminApp/Dashboard/SiteList/WebsiteActionButtons.tsx` | Add edit button with `<Link>` to `/websites/edit/{id}` |

---

### Task 1: Extend IWebsite type and add ILabel

**Files:**
- Modify: `js/src/pages/AdminApp/Dashboard/SiteList/types.ts`

- [ ] **Step 1: Update types.ts with new fields**

```typescript
export interface ILabel {
	id: string
	key: string
	value: string
	isActive: boolean
	createdAt: string
	updatedAt: string
}

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
	memo?: string
	phpVersion?: string
	labels?: ILabel[]
	packageId?: string
	userId?: string
	createdAt: string
	updatedAt: string
}

export interface IWebsiteResponse {
	data: IWebsite[]
	total: number
}
```

- [ ] **Step 2: Verify no TypeScript errors**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && npx tsc --noEmit --pretty 2>&1 | head -30`
Expected: No new errors from type changes (existing errors may exist)

- [ ] **Step 3: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/types.ts
git commit -m "feat: extend IWebsite type with phpVersion, labels, packageId, userId fields"
```

---

### Task 2: Create selector components

**Files:**
- Create: `js/src/pages/AdminApp/Dashboard/SiteList/components/WebsitePackageSelector.tsx`
- Create: `js/src/pages/AdminApp/Dashboard/SiteList/components/UserSelector.tsx`
- Create: `js/src/pages/AdminApp/Dashboard/SiteList/components/LabelSelector.tsx`

- [ ] **Step 1: Create components directory**

```bash
mkdir -p js/src/pages/AdminApp/Dashboard/SiteList/components
```

- [ ] **Step 2: Create WebsitePackageSelector.tsx**

This component fetches active packages from `GET /website-packages?isActive=true&limit=250` and renders a searchable Select.

```tsx
import { useQuery } from '@tanstack/react-query'
import { Select } from 'antd'

import { powerCloudAxios, usePowerCloudAxiosWithApiKey } from '@/api'

interface WebsitePackage {
	id: string
	name: string
	price: number
	isActive: boolean
}

interface WebsitePackageSelectorProps {
	value?: string
	onChange?: (value: string) => void
	placeholder?: string
}

const WebsitePackageSelector = ({
	value,
	onChange,
	placeholder = '請選擇方案',
}: WebsitePackageSelectorProps) => {
	const powerCloudInstance = usePowerCloudAxiosWithApiKey(powerCloudAxios)

	const { data, isLoading } = useQuery({
		queryKey: ['website-packages'],
		queryFn: () =>
			powerCloudInstance.get<{ data: WebsitePackage[] }>(
				'/website-packages?isActive=true&page=1&limit=250'
			),
	})

	const packages = data?.data?.data || []

	return (
		<Select
			value={value}
			onChange={onChange}
			placeholder={placeholder}
			loading={isLoading}
			showSearch
			filterOption={(input, option) =>
				(option?.label ?? '').toLowerCase().includes(input.toLowerCase())
			}
			options={packages.map((pkg) => ({
				label: `${pkg.name} - $${Number(pkg.price).toFixed(2)}`,
				value: pkg.id,
			}))}
		/>
	)
}

export default WebsitePackageSelector
```

- [ ] **Step 3: Create UserSelector.tsx**

This component fetches users from `GET /users?limit=250` with debounced keyword search.

```tsx
import { useQuery } from '@tanstack/react-query'
import { Select } from 'antd'
import { useEffect, useState } from 'react'

import { powerCloudAxios, usePowerCloudAxiosWithApiKey } from '@/api'

interface User {
	id: string
	firstName: string
	lastName: string
	email: string
}

interface UserSelectorProps {
	value?: string
	onChange?: (value: string) => void
	placeholder?: string
}

const SEARCH_DEBOUNCE_MS = 300

const UserSelector = ({
	value,
	onChange,
	placeholder = '請選擇用戶',
}: UserSelectorProps) => {
	const powerCloudInstance = usePowerCloudAxiosWithApiKey(powerCloudAxios)
	const [searchKeyword, setSearchKeyword] = useState('')
	const [debouncedKeyword, setDebouncedKeyword] = useState('')

	useEffect(() => {
		const timer = window.setTimeout(() => {
			setDebouncedKeyword(searchKeyword)
		}, SEARCH_DEBOUNCE_MS)
		return () => window.clearTimeout(timer)
	}, [searchKeyword])

	const { data, isLoading } = useQuery({
		queryKey: ['powercloud-users', debouncedKeyword],
		queryFn: () => {
			const params = new URLSearchParams({
				page: '1',
				limit: '250',
			})
			if (debouncedKeyword) {
				params.set('keyword', debouncedKeyword)
			}
			return powerCloudInstance.get<{ data: User[] }>(
				`/users?${params.toString()}`
			)
		},
	})

	const users = data?.data?.data || []

	return (
		<Select
			value={value}
			onChange={onChange}
			placeholder={placeholder}
			loading={isLoading}
			showSearch
			searchValue={searchKeyword}
			onSearch={setSearchKeyword}
			onDropdownVisibleChange={(open) => {
				if (!open) setSearchKeyword('')
			}}
			filterOption={false}
			options={users.map((user) => ({
				label: `${user.firstName ?? ''} ${user.lastName ?? ''} (${user.email})`.trim(),
				value: user.id,
			}))}
		/>
	)
}

export default UserSelector
```

- [ ] **Step 4: Create LabelSelector.tsx**

This component fetches active labels from `GET /labels?isActive=true&limit=1000` and renders a multi-select.

```tsx
import { useQuery } from '@tanstack/react-query'
import { Select } from 'antd'

import { powerCloudAxios, usePowerCloudAxiosWithApiKey } from '@/api'
import type { ILabel } from '../types'

interface LabelSelectorProps {
	value?: string[]
	onChange?: (value: string[]) => void
	placeholder?: string
}

const LabelSelector = ({
	value,
	onChange,
	placeholder = '選擇標籤',
}: LabelSelectorProps) => {
	const powerCloudInstance = usePowerCloudAxiosWithApiKey(powerCloudAxios)

	const { data, isLoading } = useQuery({
		queryKey: ['powercloud-labels'],
		queryFn: () =>
			powerCloudInstance.get<{ data: ILabel[] }>(
				'/labels?isActive=true&page=1&limit=1000'
			),
	})

	const labels = data?.data?.data || []

	return (
		<Select
			mode="multiple"
			value={value}
			onChange={onChange}
			placeholder={placeholder}
			loading={isLoading}
			className="w-full"
			showSearch
			filterOption={(input, option) =>
				(option?.label ?? '').toLowerCase().includes(input.toLowerCase())
			}
			maxTagCount="responsive"
			options={labels.map((label) => ({
				label: `${label.key}: ${label.value}`,
				value: label.id,
			}))}
		/>
	)
}

export default LabelSelector
```

- [ ] **Step 5: Verify TypeScript compiles**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && npx tsc --noEmit --pretty 2>&1 | head -30`
Expected: No new errors from these files

- [ ] **Step 6: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/components/
git commit -m "feat: add WebsitePackageSelector, UserSelector, LabelSelector components"
```

---

### Task 3: Create useUpdateWebsite hook

**Files:**
- Create: `js/src/pages/AdminApp/Dashboard/SiteList/WebsiteEditor/useUpdateWebsite.ts`

- [ ] **Step 1: Create WebsiteEditor directory**

```bash
mkdir -p js/src/pages/AdminApp/Dashboard/SiteList/WebsiteEditor
```

- [ ] **Step 2: Create useUpdateWebsite.ts**

This hook encapsulates the two API calls: `PATCH /websites/{id}` for general fields, and `PATCH /wordpress/{id}/php-version` when PHP version changes. When PHP version changes, status is NOT sent to avoid overwriting the backend's automatic "updating" status.

```typescript
import { useMutation } from '@tanstack/react-query'
import { message } from 'antd'

import { powerCloudAxios, usePowerCloudAxiosWithApiKey } from '@/api'

interface UpdateWebsiteValues {
	packageId: string
	userId: string
	status?: string
	labelIds?: string[]
	memo?: string | null
	namespace?: string
	domain?: string | null
}

interface UpdatePhpVersionValues {
	phpVersion: string
}

const useUpdateWebsite = () => {
	const powerCloudInstance = usePowerCloudAxiosWithApiKey(powerCloudAxios)

	const updateWebsite = useMutation({
		mutationFn: ({
			id,
			values,
		}: {
			id: string
			values: UpdateWebsiteValues
		}) => {
			return powerCloudInstance.patch(`/websites/${id}`, values)
		},
		onSuccess: () => {
			message.success('網站更新成功')
		},
	})

	const updatePhpVersion = useMutation({
		mutationFn: ({
			id,
			values,
		}: {
			id: string
			values: UpdatePhpVersionValues
		}) => {
			return powerCloudInstance.patch(
				`/wordpress/${id}/php-version`,
				values
			)
		},
		onSuccess: () => {
			message.success('PHP 版本更新已開始處理')
		},
	})

	return { updateWebsite, updatePhpVersion }
}

export default useUpdateWebsite
```

- [ ] **Step 3: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/WebsiteEditor/useUpdateWebsite.ts
git commit -m "feat: add useUpdateWebsite hook for PATCH /websites and /php-version"
```

---

### Task 4: Create WebsiteEditorForm component

**Files:**
- Create: `js/src/pages/AdminApp/Dashboard/SiteList/WebsiteEditor/WebsiteEditorForm.tsx`

- [ ] **Step 1: Create WebsiteEditorForm.tsx**

This component renders the read-only summary card and the editable form. It uses Ant Design `<Form>` (not react-hook-form) to stay consistent with the power-partner codebase patterns.

```tsx
import { Button, Col, Form, Input, Row, Select, Tag, Typography } from 'antd'
import { useEffect, useState } from 'react'

import LabelSelector from '../components/LabelSelector'
import UserSelector from '../components/UserSelector'
import WebsitePackageSelector from '../components/WebsitePackageSelector'
import type { IWebsite } from '../types'
import useUpdateWebsite from './useUpdateWebsite'

const { Text } = Typography

const statusColorMap: Record<string, string> = {
	creating: 'processing',
	running: 'success',
	stopped: 'warning',
	updating: 'processing',
	deleting: 'error',
}

const statusTextMap: Record<string, string> = {
	creating: '建置中',
	running: '運行中',
	stopped: '已停止',
	updating: '處理中',
	deleting: '刪除中',
}

const statusOptions = [
	{ label: '運行中', value: 'running' },
	{ label: '已停止', value: 'stopped' },
	{ label: '建置中', value: 'creating' },
	{ label: '處理中', value: 'updating' },
]

const phpVersionOptions = [
	{ label: 'PHP 7.4', value: 'php7.4' },
	{ label: 'PHP 8.0', value: 'php8.0' },
	{ label: 'PHP 8.1', value: 'php8.1' },
	{ label: 'PHP 8.2', value: 'php8.2' },
	{ label: 'PHP 8.3', value: 'php8.3' },
	{ label: 'PHP 8.4', value: 'php8.4' },
	{ label: 'PHP 8.5', value: 'php8.5' },
]

const getDomain = (website: IWebsite): string => {
	return (
		website.primaryDomain ||
		website.domain ||
		website.subDomain ||
		website.wildcardDomain ||
		''
	)
}

interface WebsiteEditorFormProps {
	websiteData: IWebsite
}

const WebsiteEditorForm = ({ websiteData }: WebsiteEditorFormProps) => {
	const [form] = Form.useForm()
	const { updateWebsite, updatePhpVersion } = useUpdateWebsite()
	const [submitting, setSubmitting] = useState(false)

	const domain = getDomain(websiteData)

	useEffect(() => {
		form.setFieldsValue({
			packageId: websiteData.packageId ?? websiteData.package?.id ?? '',
			userId: websiteData.userId ?? websiteData.user?.id ?? '',
			status: websiteData.status,
			phpVersion: websiteData.phpVersion ?? undefined,
			labelIds: websiteData.labels?.map((l) => l.id) ?? [],
			memo: websiteData.memo ?? '',
		})
	}, [websiteData, form])

	const handleFinish = async (values: {
		packageId: string
		userId: string
		status: string
		phpVersion?: string
		labelIds?: string[]
		memo?: string
	}) => {
		setSubmitting(true)

		try {
			const isPhpVersionChanged =
				!!values.phpVersion &&
				values.phpVersion !== websiteData.phpVersion

			if (isPhpVersionChanged) {
				await updatePhpVersion.mutateAsync({
					id: websiteData.id,
					values: { phpVersion: values.phpVersion! },
				})
			}

			const requestValues = {
				packageId: values.packageId,
				userId: values.userId,
				labelIds: values.labelIds ?? [],
				memo: values.memo || null,
				...(!isPhpVersionChanged && { status: values.status }),
			}

			await updateWebsite.mutateAsync({
				id: websiteData.id,
				values: requestValues,
			})
		} finally {
			setSubmitting(false)
		}
	}

	return (
		<div className="rounded-xl border border-gray-300 border-solid p-6">
			{/* 唯讀摘要卡片 */}
			<div className="grid grid-cols-1 gap-4 rounded-lg bg-gray-50 p-4 xl:grid-cols-4 mb-6">
				<div className="space-y-1">
					<span className="block text-sm text-gray-500">網站域名</span>
					<Text copyable ellipsis>
						{domain}
					</Text>
				</div>
				<div className="space-y-1">
					<span className="block text-sm text-gray-500">
						WordPress 管理員 Email
					</span>
					<Text copyable>{websiteData.adminEmail ?? ''}</Text>
				</div>
				<div className="space-y-1">
					<span className="block text-sm text-gray-500">
						WordPress 密碼
					</span>
					<Text copyable={{ text: websiteData.adminPassword ?? '' }}>
						•••••••••
					</Text>
				</div>
				<div className="space-y-1">
					<span className="block text-sm text-gray-500">網站狀態</span>
					<Tag
						bordered={false}
						color={statusColorMap[websiteData.status] || 'default'}
					>
						{statusTextMap[websiteData.status] || websiteData.status}
					</Tag>
				</div>
				<div className="space-y-1">
					<span className="block text-sm text-gray-500">每日扣款</span>
					<span className="block font-semibold text-green-600">
						${Number(websiteData.dailyCost ?? 0).toFixed(2)}
					</span>
				</div>
				<div className="space-y-1">
					<span className="block text-sm text-gray-500">IP 地址</span>
					<Text copyable ellipsis>
						{websiteData.ipAddress || '尚未分配'}
					</Text>
				</div>
				<div className="space-y-1">
					<span className="block text-sm text-gray-500">容器數量</span>
					<span>{websiteData.phpPodSize}</span>
				</div>
				<div className="space-y-1">
					<span className="block text-sm text-gray-500">PHP 版本</span>
					<span>
						{websiteData.phpVersion ?? 'php8.1（預設）'}
					</span>
				</div>
			</div>

			{/* 可編輯表單 */}
			<Form
				form={form}
				layout="vertical"
				onFinish={handleFinish}
			>
				<Row gutter={[16, 0]}>
					<Col xs={24} xl={12}>
						<Form.Item
							label="網站方案"
							name="packageId"
							rules={[{ required: true, message: '請選擇網站方案' }]}
						>
							<WebsitePackageSelector placeholder="請選擇網站方案" />
						</Form.Item>
					</Col>
					<Col xs={24} xl={12}>
						<Form.Item
							label="所屬用戶"
							name="userId"
							rules={[{ required: true, message: '請選擇用戶' }]}
						>
							<UserSelector placeholder="請選擇用戶" />
						</Form.Item>
					</Col>
					<Col xs={24} xl={12}>
						<Form.Item
							label="狀態"
							name="status"
							rules={[{ required: true, message: '請選擇狀態' }]}
						>
							<Select options={statusOptions} />
						</Form.Item>
					</Col>
					<Col xs={24} xl={12}>
						<Form.Item label="PHP 版本" name="phpVersion">
							<Select
								options={phpVersionOptions}
								placeholder="選擇 PHP 版本（預設 php8.1）"
								allowClear
							/>
						</Form.Item>
					</Col>
					<Col xs={24}>
						<Form.Item label="標籤" name="labelIds">
							<LabelSelector placeholder="選擇標籤" />
						</Form.Item>
					</Col>
					<Col xs={24}>
						<Form.Item
							label="備註"
							name="memo"
							rules={[
								{ max: 500, message: '備註最多 500 個字元' },
							]}
						>
							<Input.TextArea
								rows={3}
								placeholder="請輸入備註"
								maxLength={500}
								showCount
							/>
						</Form.Item>
					</Col>
				</Row>

				<div className="border-t border-gray-200 border-solid pt-4 flex justify-end">
					<Button
						type="primary"
						htmlType="submit"
						loading={submitting}
					>
						{submitting ? '更新中...' : '更新'}
					</Button>
				</div>
			</Form>
		</div>
	)
}

export default WebsiteEditorForm
```

- [ ] **Step 2: Verify TypeScript compiles**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && npx tsc --noEmit --pretty 2>&1 | head -30`

- [ ] **Step 3: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/WebsiteEditor/WebsiteEditorForm.tsx
git commit -m "feat: add WebsiteEditorForm with summary card and editable fields"
```

---

### Task 5: Create WebsiteEditor page component

**Files:**
- Create: `js/src/pages/AdminApp/Dashboard/SiteList/WebsiteEditor/index.tsx`

- [ ] **Step 1: Create WebsiteEditor/index.tsx**

This component fetches a single website by ID from the URL params, shows loading skeleton, breadcrumb navigation, and renders the form.

```tsx
import { ArrowLeftOutlined, EditOutlined, GlobalOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Breadcrumb, Button, Skeleton } from 'antd'
import { Link, useNavigate, useParams } from 'react-router-dom'

import { powerCloudAxios, usePowerCloudAxiosWithApiKey } from '@/api'
import type { IWebsite } from '../types'
import WebsiteEditorForm from './WebsiteEditorForm'

const WebsiteEditor = () => {
	const { id } = useParams<{ id: string }>()
	const navigate = useNavigate()
	const powerCloudInstance = usePowerCloudAxiosWithApiKey(powerCloudAxios)

	const { data, isLoading } = useQuery({
		queryKey: ['powercloud-website', id],
		queryFn: () =>
			powerCloudInstance.get<{ data: IWebsite }>(`/websites/${id}`),
		enabled: !!id,
	})

	const websiteData = data?.data?.data

	return (
		<div className="space-y-4">
			<div className="flex items-center gap-4">
				<Button
					type="text"
					icon={<ArrowLeftOutlined />}
					onClick={() => navigate('/')}
				>
					返回列表
				</Button>
				<Breadcrumb
					items={[
						{
							title: (
								<Link to="/">
									<GlobalOutlined className="mr-1" />
									網站列表
								</Link>
							),
						},
						{
							title: (
								<span>
									<EditOutlined className="mr-1" />
									編輯網站
								</span>
							),
						},
					]}
				/>
			</div>

			{isLoading && (
				<div className="rounded-xl border border-gray-300 border-solid p-6">
					<Skeleton active paragraph={{ rows: 12 }} />
				</div>
			)}

			{!isLoading && websiteData && (
				<WebsiteEditorForm websiteData={websiteData} />
			)}

			{!isLoading && !websiteData && (
				<div className="text-center py-16 text-gray-500">
					找不到此網站資料
				</div>
			)}
		</div>
	)
}

export default WebsiteEditor
```

- [ ] **Step 2: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/WebsiteEditor/
git commit -m "feat: add WebsiteEditor page with breadcrumb and data fetching"
```

---

### Task 6: Integrate HashRouter into Dashboard

**Files:**
- Modify: `js/src/pages/AdminApp/Dashboard/index.tsx`

- [ ] **Step 1: Wrap Dashboard with HashRouter and add routes**

Replace the current Dashboard component to use `HashRouter`. The default route (`/`) shows the existing Tabs dashboard. The `/websites/edit/:id` route shows the WebsiteEditor.

The key change: wrap the entire return in a `<HashRouter>` and use `<Routes>` to switch between the tabs view and the editor view.

Replace the full content of `js/src/pages/AdminApp/Dashboard/index.tsx` with:

```tsx
import {
	MoneyCollectOutlined,
	ClusterOutlined,
	InfoCircleOutlined,
	MailOutlined,
	CodeOutlined,
	BarcodeOutlined,
	SettingOutlined,
	CloudOutlined,
} from '@ant-design/icons'
import { Tabs, TabsProps, Form, Button } from 'antd'
import { HashRouter, Routes, Route } from 'react-router-dom'
import AccountIcon from './AccountIcon'
import SiteList from './SiteList'
import LogList from './LogList'
import Description from './Description'
import EmailSetting from './EmailSetting'
import ManualSiteSync from './ManualSiteSync'
import Settings from './Settings'
import LicenseCodes from './LicenseCodes'
import PowercloudAuth from './PowercloudAuth'
import WebsiteEditor from './SiteList/WebsiteEditor'
import useSave, {
	TFormValues,
} from '@/pages/AdminApp/Dashboard/EmailSetting/hooks/useSave'

import { windowWidth } from '@/utils'
import { TabKeyEnum, setTabAtom } from '../Atom/tab.atom'
import { useAtomValue, useSetAtom } from 'jotai'
import { tabAtom } from '../Atom/tab.atom'

const items: TabsProps['items'] = [
	{
		key: TabKeyEnum.SITE_LIST,
		icon: <ClusterOutlined />,
		label: '所有站台',
		children: <SiteList />,
		forceRender: false,
	},
	{
		key: TabKeyEnum.LOG_LIST,
		icon: <MoneyCollectOutlined />,
		label: '點數 Log',
		children: <LogList />,
		forceRender: false,
	},
	{
		key: TabKeyEnum.EMAIL,
		icon: <MailOutlined />,
		label: 'Email 設定',
		children: <EmailSetting />,
		forceRender: true,
	},
	{
		key: TabKeyEnum.MANUAL_SITE_SYNC,
		icon: <CodeOutlined />,
		label: '手動開站',
		children: <ManualSiteSync />,
		forceRender: true,
	},
	{
		key: TabKeyEnum.SETTINGS,
		icon: <SettingOutlined />,
		label: '設定',
		children: <Settings />,
		forceRender: true,
	},
	{
		key: TabKeyEnum.LICENSE_CODES,
		icon: <BarcodeOutlined />,
		label: '授權碼管理',
		children: <LicenseCodes isAdmin />,
		forceRender: false,
	},
	{
		key: TabKeyEnum.DESCRIPTION,
		icon: <InfoCircleOutlined />,
		label: '其他資訊',
		children: <Description />,
		forceRender: false,
	},
	{
		key: TabKeyEnum.POWERCLOUD_AUTH,
		icon: <CloudOutlined />,
		label: '新架構權限',
		children: <PowercloudAuth />,
		forceRender: false,
	},
]

const TabsDashboard = () => {
	const [form] = Form.useForm()
	const { mutation, contextHolder } = useSave(form)
	const { mutate: saveSettings, isPending } = mutation
	const activeKey = useAtomValue(tabAtom)
	const setActiveKey = useSetAtom(setTabAtom)
	const handleSave = () => {
		form
			.validateFields()
			.then((settings: TFormValues) => {
				saveSettings(settings)
			})
			.catch((error) => {
				console.log(error)
			})
	}
	return (
		<Form form={form} layout="vertical">
			{contextHolder}
			<Tabs
				activeKey={activeKey}
				onChange={(key) => setActiveKey(key as TabKeyEnum)}
				className={`${windowWidth < 1200 ? 'mt-16' : ''}`}
				type="card"
				tabBarExtraContent={<AccountIcon />}
				items={items}
			/>
			{[TabKeyEnum.EMAIL, TabKeyEnum.SETTINGS].includes(activeKey) && (
				<Button
					type="primary"
					className="mt-4"
					onClick={handleSave}
					loading={isPending}
				>
					儲存
				</Button>
			)}
		</Form>
	)
}

const index = () => {
	return (
		<HashRouter>
			<Routes>
				<Route path="/" element={<TabsDashboard />} />
				<Route
					path="/websites/edit/:id"
					element={<WebsiteEditor />}
				/>
			</Routes>
		</HashRouter>
	)
}

export default index
```

- [ ] **Step 2: Verify TypeScript compiles**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && npx tsc --noEmit --pretty 2>&1 | head -30`

- [ ] **Step 3: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/index.tsx
git commit -m "feat: add HashRouter to Dashboard with /websites/edit/:id route"
```

---

### Task 7: Add edit button to WebsiteActionButtons

**Files:**
- Modify: `js/src/pages/AdminApp/Dashboard/SiteList/WebsiteActionButtons.tsx`

- [ ] **Step 1: Add SettingOutlined import and Link from react-router-dom**

Add `SettingOutlined` to the ant-design icons import and add `Link` from react-router-dom. Then add the edit button between the WordPress backend link and the stop/start buttons.

The full updated file:

```tsx
import {
	DeleteOutlined,
	EllipsisOutlined,
	GlobalOutlined,
	PlayCircleOutlined,
	SettingOutlined,
	StopOutlined,
} from '@ant-design/icons'
import { Button, Dropdown, Modal, Popconfirm, Tooltip } from 'antd'
import type { MenuProps } from 'antd'
import { Link } from 'react-router-dom'
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
	const isUpdating =
		record.status === 'creating' || record.status === 'updating'

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
			<a
				href={`https://${domain}/wp-admin`}
				target="_blank"
				rel="noreferrer"
			>
				<Tooltip title="前往 WordPress 後台">
					<Button icon={<GlobalOutlined />} size="small" type="text" />
				</Tooltip>
			</a>

			<Link to={`/websites/edit/${record.id}`}>
				<Tooltip title="編輯">
					<Button icon={<SettingOutlined />} size="small" type="text" />
				</Tooltip>
			</Link>

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
						<Button
							icon={<StopOutlined />}
							size="small"
							type="text"
							danger
						/>
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
						<Button icon={<PlayCircleOutlined />} size="small" type="text" />
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

- [ ] **Step 2: Verify TypeScript compiles**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && npx tsc --noEmit --pretty 2>&1 | head -30`

- [ ] **Step 3: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/WebsiteActionButtons.tsx
git commit -m "feat: add edit button to WebsiteActionButtons with Link to /websites/edit/:id"
```

---

### Task 8: Build and verify

**Files:** None (verification only)

- [ ] **Step 1: Run the Vite build to confirm everything compiles**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && pnpm build 2>&1 | tail -20`
Expected: Build succeeds without errors

- [ ] **Step 2: Run ESLint to check for lint issues**

Run: `cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && pnpm lint 2>&1 | tail -30`
Expected: No new lint errors from our changes

- [ ] **Step 3: Fix any issues found, then commit fixes**

If there are issues, fix them and commit:
```bash
git add -A
git commit -m "fix: resolve lint/build issues in website editor"
```
