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

export const defaultFilters: WebsiteFilters = {
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
		const resetValue =
			key === 'startDailyCostPrice' || key === 'endDailyCostPrice' ? null : ''
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
