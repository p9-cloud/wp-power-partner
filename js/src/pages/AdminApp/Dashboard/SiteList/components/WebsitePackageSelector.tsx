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
