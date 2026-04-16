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
