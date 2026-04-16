import { Table, TableProps, Tag, Typography } from 'antd'
import { DataType, TLogParams } from './types'
import { identityAtom } from '@/pages/AdminApp/Atom/atom'
import { useAtomValue } from 'jotai'
import { useTable } from '@/hooks'
import ContentCard from '@/components/ContentCard'

const { Paragraph } = Typography

const LogTypeTag: React.FC<{ record: DataType }> = ({ record }) => {
	const type = record?.type || ''
	switch (type) {
		case 'cron':
			return <Tag color="purple">每日扣點</Tag>
		case 'modify':
			return <Tag color="magenta">管理員直接修改</Tag>
		case 'purchase':
			return <Tag color="cyan">儲值</Tag>
		default:
			return <></>
	}
}

const index = () => {
	const identity = useAtomValue(identityAtom)
	const user_id = identity.data?.user_id || ''
	const { tableProps } = useTable<TLogParams, DataType>({
		resource: 'logs',
		defaultParams: {
			user_id,
			offset: 0,
			numberposts: 10,
		},
		queryOptions: {
			enabled: !!user_id,
			staleTime: 1000 * 60 * 60 * 24,
			gcTime: 1000 * 60 * 60 * 24,
		},
	})

	const columns: TableProps<DataType>['columns'] = [
		{
			title: '日期',
			dataIndex: 'date',
			width: 160,
		},
		{
			title: '分類',
			dataIndex: 'type',
			width: 144,
			render: (_, record) => <LogTypeTag record={record} />,
		},
		{
			title: 'Power Money 變化',
			dataIndex: 'point_changed',
			width: 144,
			align: 'right',
		},
		{
			title: '餘額',
			dataIndex: 'new_balance',
			width: 208,
			align: 'right',
		},
		{
			title: '說明',
			dataIndex: 'title',
			render: (value: string) => (
				<Paragraph
					copyable
					ellipsis={{
						rows: 2,
						expandable: true,
						symbol: '更多',
					}}
					className="whitespace-break-spaces !m-0"
				>
					{value}
				</Paragraph>
			),
		},
	]
	return (
		<ContentCard>
			<Table rowKey="id" {...tableProps} columns={columns} />
		</ContentCard>
	)
}

export default index
