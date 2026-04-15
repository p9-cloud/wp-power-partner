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
