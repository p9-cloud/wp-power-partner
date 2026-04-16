import React from 'react'
import { InputNumber, Form } from 'antd'
import { disable_site_after_n_days } from '@/utils'
import ContentCard from '@/components/ContentCard'

const NAME = 'power_partner_disable_site_after_n_days'
const DEFAULT = disable_site_after_n_days
const { Item } = Form

const index = () => {
	return (
		<ContentCard>
			<p>
				當訂閱轉為<span className="bg-gray-200 px-1 mx-1">非啟用</span>
				狀態後，幾天後會<span className="bg-gray-200 px-1 mx-1">禁用</span>
				關聯的網站
			</p>
			<Item
				name={NAME}
				className="m-0"
				initialValue={DEFAULT}
				rules={[
					{
						required: true,
						message: '請輸入天數',
					},
				]}
			>
				<InputNumber addonAfter="天" min={0} max={100} />
			</Item>
		</ContentCard>
	)
}

export default index
