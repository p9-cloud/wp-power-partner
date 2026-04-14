import {
	Tag,
	Tooltip,
	message,
	Table,
	TableColumnsType,
	Input,
	Form,
	Button,
	Affix,
} from 'antd'
import SendingCondition from '@/pages/AdminApp/Dashboard/EmailSetting/SendingCondition'
import { DataType } from '@/pages/AdminApp/Dashboard/EmailSetting/types'
import {
	siteSyncTokens,
	orderTokens,
	handleCopy,
	getEmailTemplate,
} from '@/pages/AdminApp/Dashboard/EmailSetting/utils'
import EmailBody from '@/pages/AdminApp/Dashboard/EmailSetting/EmailBody'
import DeleteButton from '@/pages/AdminApp/Dashboard/EmailSetting/DeleteButton'
import EmailSwitch from '@/pages/AdminApp/Dashboard/EmailSetting/EmailSwitch'
import {
	emailsAtom,
	focusEmailIndexAtom,
} from '@/pages/AdminApp/Dashboard/EmailSetting/atom'
import { useAtom, useAtomValue } from 'jotai'
import useGetEmails from '@/pages/AdminApp/Dashboard/EmailSetting/hooks/useGetEmails'
import { PlusCircleOutlined } from '@ant-design/icons'
import { useRef, useMemo } from 'react'
import ContentCard from '@/components/ContentCard'

const { Item } = Form

const EmailSetting = () => {
	const containerRef = useRef<HTMLDivElement>(null)
	const form = Form.useFormInstance()
	const focusEmailIndex = useAtomValue(focusEmailIndexAtom)

	const [messageApi, contextHolder] = message.useMessage({
		top: 50,
	})
	const [dataSource, setDataSource] = useAtom(emailsAtom)

	const handleAdd = () => {
		setDataSource([
			...dataSource,
			getEmailTemplate(),
		])
	}

	const { isPending } = useGetEmails()

	const columns: TableColumnsType<DataType> = useMemo(
		() => [
			{
				title: '啟用',
				width: 77,
				dataIndex: 'enabled',
				render: (_value: boolean, record, index) => (
					<EmailSwitch record={record} index={index} />
				),
			},
			{
				title: '主旨',
				dataIndex: 'subject',
				render: (value: string, _record, index) => (
					<Item
						name={['emails', index, 'subject']}
						initialValue={value}
						className="mb-0"
						shouldUpdate
					>
						<Input />
					</Item>
				),
			},
			{
				title: '時機',
				width: 422,
				dataIndex: 'condition',
				render: (_value: string, record, index) => (
					<SendingCondition
						record={record}
						index={index}
						containerRef={containerRef}
					/>
				),
			},
			{
				title: '',
				width: 46,
				dataIndex: 'action',
				render: (_value: string, record) => (
					<DeleteButton record={record} containerRef={containerRef} />
				),
			},
		],
		[dataSource, containerRef],
	)

	return (
		<ContentCard>
			<div ref={containerRef}>
				{contextHolder}
			<div className="flex flex-col lg:flex-row gap-8 relative">
				<div className="flex-1">
					<Button
						type="primary"
						className="mt-2 mb-4"
						icon={<PlusCircleOutlined />}
						onClick={handleAdd}
					>
						新增 Email
					</Button>
					<Table
						rowKey="key"
						tableLayout="auto"
						columns={columns}
						expandable={{
							expandedRowRender: (record, index) => (
								<EmailBody
									record={record}
									index={index}
									containerRef={containerRef}
								/>
							),
						}}
						dataSource={dataSource}
						pagination={false}
						loading={isPending}
						scroll={{ x: 768 }}
					/>
				</div>
				<div className="w-[320px] sticky top-0">
					<Affix offsetTop={48}>
						<p className="mb-2 text-[14px]">可用變數</p>
						{(focusEmailIndex?.actionName === 'site_sync'
							? siteSyncTokens
							: orderTokens
						).map((token) => (
							<Tooltip
								key={token?.value}
								title={token?.label}
								getPopupContainer={() => containerRef.current as HTMLElement}
							>
								<Tag
									color="#eee"
									className="rounded-xl !text-gray-600 px-3 cursor-pointer mb-2"
									onClick={handleCopy(token, messageApi)}
								>
									{token?.value}
								</Tag>
							</Tooltip>
						))}
					</Affix>
				</div>
			</div>
		</div>
		</ContentCard>
	)
}

export default EmailSetting
