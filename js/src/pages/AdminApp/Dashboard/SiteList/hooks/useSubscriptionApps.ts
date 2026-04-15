import { useQuery } from '@tanstack/react-query'
import { axios } from '@/api'
import { kebab } from '@/utils'
import type { TGetAppsResponse } from '@/components/SiteListTable/types'

/**
 * Batch query subscription_ids for a list of PowerCloud website IDs.
 * Returns a map: { [websiteId]: string[] }
 */
export const useSubscriptionApps = ({
	websiteIds,
}: {
	websiteIds: string[]
}) => {
	const result = useQuery<TGetAppsResponse>({
		queryKey: ['get_powercloud_apps', websiteIds.join(',')],
		queryFn: () =>
			axios.get(`/${kebab}/apps`, {
				params: {
					app_ids: websiteIds,
				},
			}),
		enabled: websiteIds.length > 0,
		staleTime: 1000 * 60 * 5, // 5 minutes
	})

	const apps = result.data?.data || []

	const subscriptionMap: Record<string, string[]> = {}
	for (const app of apps) {
		if (app.subscription_ids?.length) {
			subscriptionMap[app.app_id] = app.subscription_ids.map(String)
		}
	}

	return {
		subscriptionMap,
		isLoading: result.isLoading,
		isFetching: result.isFetching,
		refetch: result.refetch,
	}
}
