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
