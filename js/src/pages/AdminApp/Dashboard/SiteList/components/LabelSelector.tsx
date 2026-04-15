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
