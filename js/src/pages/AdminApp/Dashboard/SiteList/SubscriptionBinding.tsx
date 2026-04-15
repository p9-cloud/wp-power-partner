import { useState } from 'react'
import {
	Button,
	Popconfirm,
	Select,
	Space,
	Tag,
	Typography,
	message,
} from 'antd'
import { LinkOutlined, DisconnectOutlined } from '@ant-design/icons'
import { useMutation, useQuery } from '@tanstack/react-query'
import { axios } from '@/api'
import { kebab, siteUrl } from '@/utils'
import {
	useSubscriptionSelect,
} from '@/components/SubscriptionSelect'
import { debounce } from 'lodash-es'
import type { AxiosResponse } from 'axios'

const { Text } = Typography

type TCustomer = {
	id: string
	display_name: string
}

type TGetCustomersResponse = AxiosResponse<{
	status: number
	data: TCustomer[]
}>

type SubscriptionBindingProps = {
	websiteId: string
	subscriptionIds: string[]
	onBindingChange: () => void
}

export const SubscriptionBinding = ({
	websiteId,
	subscriptionIds,
	onBindingChange,
}: SubscriptionBindingProps) => {
	const [isBinding, setIsBinding] = useState(false)
	const [search, setSearch] = useState('')
	const [selectedCustomerId, setSelectedCustomerId] = useState<string | null>(
		null,
	)
	const [selectedSubscriptionId, setSelectedSubscriptionId] = useState<
		string | null
	>(null)

	// Customer search
	const handleSearch = debounce((searchValue: string) => {
		setSearch(searchValue)
	}, 1000)

	const { data: searchedData, isFetching: isSearching } =
		useQuery<TGetCustomersResponse>({
			queryKey: ['get_customers_by_search', search],
			queryFn: () =>
				axios.get(`/${kebab}/customers-by-search`, {
					params: { search },
				}),
			enabled: search.length > 1,
		})

	const searchedCustomers = searchedData?.data?.data || []

	// Subscription select for chosen customer
	const { selectProps: subscriptionSelectProps } = useSubscriptionSelect({
		user_id: selectedCustomerId || '',
	})

	// Bind mutation
	const { mutate: bindSubscription, isPending: isBindPending } = useMutation({
		mutationFn: (params: {
			subscription_id: string
			site_id: string
			linked_site_ids: string[]
		}) => axios.post(`/${kebab}/change-subscription`, params),
		onSuccess: () => {
			message.success('綁定成功')
			setIsBinding(false)
			resetForm()
			onBindingChange()
		},
		onError: (error: any) => {
			message.error(
				`綁定失敗: ${error?.response?.data?.message || error.message}`,
			)
		},
	})

	// Unbind mutation
	const { mutate: unbindSite, isPending: isUnbindPending } = useMutation({
		mutationFn: (siteId: string) =>
			axios.post(`/${kebab}/unbind-site`, { site_id: siteId }),
		onSuccess: () => {
			message.success('解除綁定成功')
			onBindingChange()
		},
		onError: (error: any) => {
			message.error(
				`解除綁定失敗: ${error?.response?.data?.message || error.message}`,
			)
		},
	})

	const resetForm = () => {
		setSearch('')
		setSelectedCustomerId(null)
		setSelectedSubscriptionId(null)
	}

	const handleConfirmBind = () => {
		if (!selectedSubscriptionId) {
			message.warning('請選擇訂閱')
			return
		}
		bindSubscription({
			subscription_id: selectedSubscriptionId,
			site_id: websiteId,
			linked_site_ids: [websiteId],
		})
	}

	// Already bound — show subscription links + unbind button
	if (subscriptionIds.length > 0 && !isBinding) {
		return (
			<Space direction="vertical" size={2}>
				<div className="flex flex-wrap gap-1">
					{subscriptionIds.map((id) => (
						<a
							key={id}
							href={`${siteUrl}/wp-admin/post.php?post=${id}&action=edit`}
							target="_blank"
							rel="noreferrer"
						>
							<Tag color="blue">#{id}</Tag>
						</a>
					))}
				</div>
				<Popconfirm
					title="確認解除綁定？"
					description="解除後此網站將不再與訂閱關聯，訂閱狀態變更也不會影響此網站"
					onConfirm={() => unbindSite(websiteId)}
					okText="確認解除"
					cancelText="取消"
				>
					<Button
						type="link"
						danger
						size="small"
						icon={<DisconnectOutlined />}
						loading={isUnbindPending}
					>
						解除綁定
					</Button>
				</Popconfirm>
			</Space>
		)
	}

	// Not bound — show bind button or inline form
	if (!isBinding) {
		return (
			<Button
				type="link"
				size="small"
				icon={<LinkOutlined />}
				onClick={() => setIsBinding(true)}
			>
				綁定訂閱
			</Button>
		)
	}

	// Inline binding form
	return (
		<div className="flex flex-col gap-2 min-w-[200px]">
			<Select
				showSearch
				allowClear
				loading={isSearching}
				placeholder="搜尋客戶（至少 2 字元）"
				filterOption={false}
				onSearch={handleSearch}
				onChange={(value) => {
					setSelectedCustomerId(value || null)
					setSelectedSubscriptionId(null)
				}}
				notFoundContent={null}
				options={searchedCustomers.map((c) => ({
					value: c.id,
					label: `${c.display_name} - #${c.id}`,
				}))}
				size="small"
			/>

			{selectedCustomerId && (
				<Select
					{...subscriptionSelectProps}
					placeholder="選擇訂閱"
					onChange={(value) => setSelectedSubscriptionId(value || null)}
					size="small"
				/>
			)}

			<Space size={4}>
				<Button
					type="primary"
					size="small"
					onClick={handleConfirmBind}
					loading={isBindPending}
					disabled={!selectedSubscriptionId}
				>
					確認綁定
				</Button>
				<Button
					size="small"
					onClick={() => {
						setIsBinding(false)
						resetForm()
					}}
				>
					取消
				</Button>
			</Space>
		</div>
	)
}
