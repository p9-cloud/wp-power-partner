# PowerCloud Subscription Binding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable manual subscription binding for PowerCloud sites and automatic site enable/disable on subscription status changes, mirroring the WPCD (old architecture) functionality.

**Architecture:** Store PowerCloud `websiteId` in `pp_linked_site_ids` subscription meta (same as WPCD). Modify disable/enable hooks to read from this meta first. Add subscription columns and inline binding UI to the PowerCloud admin site list.

**Tech Stack:** PHP 8.1+ (WordPress REST API, WooCommerce Subscriptions), React 18 + TypeScript (Ant Design 5, TanStack Query v5, Jotai), Axios

**Design Spec:** `docs/superpowers/specs/2026-04-15-powercloud-subscription-binding-design.md`

---

## File Structure

### PHP (Backend)

| Action | File | Responsibility |
|---|---|---|
| Modify | `inc/classes/Product/SiteSync.php` | Store websiteId in `pp_linked_site_ids` after PowerCloud provisioning |
| Modify | `inc/classes/Domains/Site/Services/DisableSiteScheduler.php` | Prefer `pp_linked_site_ids` for PowerCloud disable |
| Modify | `inc/classes/Domains/Site/Core/DisableHooks.php` | Prefer `pp_linked_site_ids` for PowerCloud enable |
| Modify | `inc/classes/Api/Main.php` | Add `POST /unbind-site` endpoint |

### TypeScript (Frontend)

| Action | File | Responsibility |
|---|---|---|
| Create | `js/src/pages/AdminApp/Dashboard/SiteList/hooks/useSubscriptionApps.ts` | Hook: batch query subscription mapping for PowerCloud sites |
| Create | `js/src/pages/AdminApp/Dashboard/SiteList/SubscriptionBinding.tsx` | Inline component: bind/unbind subscription to PowerCloud site |
| Modify | `js/src/pages/AdminApp/Dashboard/SiteList/types.ts` | Add `IWebsiteWithSubscription` type |
| Modify | `js/src/pages/AdminApp/Dashboard/SiteList/index.tsx` | Add subscription columns to PowerCloud table |

---

## Task 1: Store websiteId in `pp_linked_site_ids` during auto-provisioning

**Files:**
- Modify: `inc/classes/Product/SiteSync.php:203-249` (method `site_sync_powercloud`)

- [ ] **Step 1: Add `ShopSubscription` import**

The file already imports `ShopSubscription` indirectly via other classes, but the method uses it. Check if `ShopSubscription` is already imported — it is not. Add the import:

In `inc/classes/Product/SiteSync.php`, add after line 5 (`use J7\PowerPartner\Api\Fetch;`):

No — `ShopSubscription` is not imported here. Add it. Open the file and add after the existing `use` statements at the top (after line 12):

```php
use J7\PowerPartner\ShopSubscription;
```

- [ ] **Step 2: Store websiteId after successful PowerCloud site creation**

In `inc/classes/Product/SiteSync.php`, method `site_sync_powercloud()`, add the following code after the `$response_obj->status === 201` check (line 215) and before the `$subscription->update_meta_data('email_payloads_tmp', ...)` line (line 235). Insert at line 216:

```php
			// Store websiteId in pp_linked_site_ids for subscription binding
			$website_id = $response_obj->data['websiteId'] ?? '';
			if (!empty($website_id)) {
				$existing_site_ids = ShopSubscription::get_linked_site_ids((int) $subscription->get_id());
				$existing_site_ids_values = array_values($existing_site_ids);
				if (!in_array((string) $website_id, $existing_site_ids_values, true)) {
					$existing_site_ids_values[] = (string) $website_id;
				}
				ShopSubscription::update_linked_site_ids(
					(int) $subscription->get_id(),
					$existing_site_ids_values
				);
			}
```

- [ ] **Step 3: Commit**

```bash
git add inc/classes/Product/SiteSync.php
git commit -m "feat: store PowerCloud websiteId in pp_linked_site_ids during auto-provisioning"
```

---

## Task 2: Modify disable logic to prefer `pp_linked_site_ids`

**Files:**
- Modify: `inc/classes/Domains/Site/Services/DisableSiteScheduler.php:53-146` (method `action_callback`)

- [ ] **Step 1: Replace the PowerCloud branch in `action_callback`**

In `inc/classes/Domains/Site/Services/DisableSiteScheduler.php`, replace the PowerCloud branch (lines 109-144) with the new logic that prefers `pp_linked_site_ids`:

Find this block (line 109-144):

```php
		// 如果 host_type 為 PowerCloud 新架構
		if ($host_type === LinkedSites::DEFAULT_HOST_TYPE) {
			$website_id = null;
			$order_item = $item->get_meta( SiteSync::CREATE_SITE_RESPONSES_ITEM_META_KEY );

			// get websiteId from order_item
			if ( is_string( $order_item ) && ! empty( $order_item ) ) {
				$responses = json_decode( $order_item, true );
				if ( is_array( $responses ) && ! empty( $responses ) ) {
					// 取第一個 response 的 data.websiteId
					$first_response = $responses[0];
					if ( is_array( $first_response ) && isset( $first_response['data'] ) && is_array( $first_response['data'] ) && isset( $first_response['data']['websiteId'] ) ) {
						$website_id = (string) $first_response['data']['websiteId'];
					}
				}
			}

			if ( empty( $website_id ) ) {
				Plugin::logger(
					"訂閱 #{$subscription_id} 的訂單項目 #{$item->get_id()} 找不到 websiteId",
					'error',
					[
						'order_item' => $order_item,
						'item_id'    => $item->get_id(),
					]
				);
				continue;
			}

			$reason = "停用網站，訂閱ID: {$subscription_id}，上層訂單號碼: {$order_id}，websiteId: {$website_id}";
			FetchPowerCloud::disable_site( (string) $current_user_id, $website_id );
			$subscription->add_order_note( $reason );
			$subscription->save();
			Plugin::logger( $reason, 'info' );
			continue;
		}
```

Replace with:

```php
		// 如果 host_type 為 PowerCloud 新架構
		if ($host_type === LinkedSites::DEFAULT_HOST_TYPE) {
			// 優先從 pp_linked_site_ids 取 websiteId（涵蓋自動開站 + 手動綁定）
			if (!empty($linked_site_ids)) {
				foreach ($linked_site_ids as $site_id) {
					$reason = "停用網站，訂閱ID: {$subscription_id}，上層訂單號碼: {$order_id}，websiteId: {$site_id}";
					FetchPowerCloud::disable_site((string) $current_user_id, (string) $site_id);
					$subscription->add_order_note($reason);
					$subscription->save();
					Plugin::logger($reason, 'info');
				}
				continue;
			}

			// Fallback: 從 order item meta 提取 websiteId（相容舊資料）
			$website_id = null;
			$order_item = $item->get_meta(SiteSync::CREATE_SITE_RESPONSES_ITEM_META_KEY);

			if (is_string($order_item) && !empty($order_item)) {
				$responses = json_decode($order_item, true);
				if (is_array($responses) && !empty($responses)) {
					$first_response = $responses[0];
					if (is_array($first_response) && isset($first_response['data']) && is_array($first_response['data']) && isset($first_response['data']['websiteId'])) {
						$website_id = (string) $first_response['data']['websiteId'];
					}
				}
			}

			if (empty($website_id)) {
				Plugin::logger(
					"訂閱 #{$subscription_id} 的訂單項目 #{$item->get_id()} 找不到 websiteId",
					'error',
					[
						'order_item' => $order_item,
						'item_id'    => $item->get_id(),
					]
				);
				continue;
			}

			$reason = "停用網站，訂閱ID: {$subscription_id}，上層訂單號碼: {$order_id}，websiteId: {$website_id}";
			FetchPowerCloud::disable_site((string) $current_user_id, $website_id);
			$subscription->add_order_note($reason);
			$subscription->save();
			Plugin::logger($reason, 'info');
			continue;
		}
```

- [ ] **Step 2: Commit**

```bash
git add inc/classes/Domains/Site/Services/DisableSiteScheduler.php
git commit -m "feat: prefer pp_linked_site_ids for PowerCloud site disable"
```

---

## Task 3: Modify enable logic to prefer `pp_linked_site_ids`

**Files:**
- Modify: `inc/classes/Domains/Site/Core/DisableHooks.php:77-142` (method `restart_all_stopped_sites_scheduler`)

- [ ] **Step 1: Replace the PowerCloud branch in `restart_all_stopped_sites_scheduler`**

In `inc/classes/Domains/Site/Core/DisableHooks.php`, replace the PowerCloud branch (lines 96-141) with the new logic:

Find this block (lines 96-141):

```php
		// PowerCloud 的數據放在 order item 的 meta data
		foreach ($items as $item) {
			/** @var \WC_Order_Item_Product $item */
			$product_id = $item->get_variation_id() ?: $item->get_product_id();
			$host_type  = \get_post_meta($product_id, LinkedSites::HOST_TYPE_FIELD_NAME, true);

			// powercloud 為新架構（新架構是默認Host Type)
			if ($host_type === LinkedSites::DEFAULT_HOST_TYPE) {
				$website_id = null;
				$order_item = $item->get_meta(SiteSync::CREATE_SITE_RESPONSES_ITEM_META_KEY);
				// get websiteId from order_item
				if (is_string($order_item) && ! empty($order_item)) {
					$responses = json_decode($order_item, true);
					if (\is_array($responses) && ! empty($responses)) {
						// 取第一個 response 的 data.websiteId
						$first_response = \reset($responses);
						if (is_array($first_response) && isset($first_response['data']) && is_array($first_response['data']) && isset($first_response['data']['websiteId'])) {
							$website_id = (string) $first_response['data']['websiteId'];
						}
					}
				}

				if (empty($website_id)) {
					Plugin::logger(
						"訂閱 #{$subscription_id} 的訂單項目 #{$item->get_id()} 找不到 websiteId",
						'error',
						[
							'order_item' => $order_item,
							'item_id'    => $item->get_id(),
						]
					);
					continue;
				}

				FetchPowerCloud::enable_site( (string) $current_user_id, $website_id);
				Plugin::logger(
					'restart WordPress site success',
					'info',
					[
						'websiteId'       => $website_id,
						'subscription_id' => $subscription_id,
					]
				);
				continue;
			}
		}
```

Replace with:

```php
		// PowerCloud: 優先從 pp_linked_site_ids 啟用，fallback 到 order item meta
		$powercloud_handled = false;
		if (!empty($linked_site_ids)) {
			// 檢查是否有 PowerCloud 產品
			foreach ($items as $item) {
				/** @var \WC_Order_Item_Product $item */
				$product_id = $item->get_variation_id() ?: $item->get_product_id();
				$host_type  = \get_post_meta($product_id, LinkedSites::HOST_TYPE_FIELD_NAME, true);

				if ($host_type === LinkedSites::DEFAULT_HOST_TYPE) {
					foreach ($linked_site_ids as $site_id) {
						FetchPowerCloud::enable_site((string) $current_user_id, (string) $site_id);
						Plugin::logger(
							'restart WordPress site success',
							'info',
							[
								'websiteId'       => (string) $site_id,
								'subscription_id' => $subscription_id,
							]
						);
					}
					$powercloud_handled = true;
					break;
				}
			}
		}

		// Fallback: 從 order item meta 提取 websiteId（相容舊資料）
		if (!$powercloud_handled) {
			foreach ($items as $item) {
				/** @var \WC_Order_Item_Product $item */
				$product_id = $item->get_variation_id() ?: $item->get_product_id();
				$host_type  = \get_post_meta($product_id, LinkedSites::HOST_TYPE_FIELD_NAME, true);

				if ($host_type === LinkedSites::DEFAULT_HOST_TYPE) {
					$website_id = null;
					$order_item = $item->get_meta(SiteSync::CREATE_SITE_RESPONSES_ITEM_META_KEY);

					if (is_string($order_item) && !empty($order_item)) {
						$responses = json_decode($order_item, true);
						if (\is_array($responses) && !empty($responses)) {
							$first_response = \reset($responses);
							if (is_array($first_response) && isset($first_response['data']) && is_array($first_response['data']) && isset($first_response['data']['websiteId'])) {
								$website_id = (string) $first_response['data']['websiteId'];
							}
						}
					}

					if (empty($website_id)) {
						Plugin::logger(
							"訂閱 #{$subscription_id} 的訂單項目 #{$item->get_id()} 找不到 websiteId",
							'error',
							[
								'order_item' => $order_item,
								'item_id'    => $item->get_id(),
							]
						);
						continue;
					}

					FetchPowerCloud::enable_site((string) $current_user_id, $website_id);
					Plugin::logger(
						'restart WordPress site success',
						'info',
						[
							'websiteId'       => $website_id,
							'subscription_id' => $subscription_id,
						]
					);
					continue;
				}
			}
		}
```

- [ ] **Step 2: Commit**

```bash
git add inc/classes/Domains/Site/Core/DisableHooks.php
git commit -m "feat: prefer pp_linked_site_ids for PowerCloud site enable"
```

---

## Task 4: Add `POST /unbind-site` REST endpoint

**Files:**
- Modify: `inc/classes/Api/Main.php:37-176` (method `register_apis`) and add new callback method

- [ ] **Step 1: Register the new route**

In `inc/classes/Api/Main.php`, inside `register_apis()`, add after the `change-subscription` route registration (after line 141):

```php
		\register_rest_route(
			Plugin::$kebab,
			'unbind-site',
			[
				'methods'             => 'POST',
				'callback'            => [$this, 'post_unbind_site_callback'],
				'permission_callback' => function () {
					return \current_user_can('manage_options');
				},
			]
		);
```

- [ ] **Step 2: Add the callback method**

In `inc/classes/Api/Main.php`, add the following method after `post_change_subscription_callback()` (after line 422):

```php
	/**
	 * Post unbind site callback
	 * 從所有訂閱中解除綁定指定的 site ID
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function post_unbind_site_callback($request): \WP_REST_Response
	{
		try {
			$body_params = $request->get_json_params();
			$site_id     = $body_params['site_id'] ?? '';

			if (empty($site_id)) {
				return new \WP_REST_Response(
					[
						'status'  => 400,
						'message' => 'missing site_id',
					],
					400
				);
			}

			$is_success = ShopSubscription::remove_linked_site_ids([(string) $site_id]);

			if ($is_success) {
				return new \WP_REST_Response(
					[
						'status'  => 200,
						'message' => "unbind site {$site_id} success",
					],
					200
				);
			} else {
				return new \WP_REST_Response(
					[
						'status'  => 500,
						'message' => "unbind site {$site_id} fail",
					],
					500
				);
			}
		} catch (\Throwable $th) {
			return new \WP_REST_Response(
				[
					'status'  => 500,
					'message' => 'unbind site fail: ' . $th->getMessage(),
				],
				500
			);
		}
	}
```

- [ ] **Step 3: Commit**

```bash
git add inc/classes/Api/Main.php
git commit -m "feat: add POST /unbind-site REST endpoint"
```

---

## Task 5: Add `IWebsiteWithSubscription` type

**Files:**
- Modify: `js/src/pages/AdminApp/Dashboard/SiteList/types.ts`

Note: `TApp` and `TGetAppsResponse` already exist in `js/src/components/SiteListTable/types.tsx` and can be reused.

- [ ] **Step 1: Add the extended type**

In `js/src/pages/AdminApp/Dashboard/SiteList/types.ts`, add at the end of the file (after line 55):

```typescript

export type IWebsiteWithSubscription = IWebsite & {
	subscriptionIds?: string[]
}
```

- [ ] **Step 2: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/types.ts
git commit -m "feat: add IWebsiteWithSubscription type for PowerCloud subscription binding"
```

---

## Task 6: Create `useSubscriptionApps` hook

**Files:**
- Create: `js/src/pages/AdminApp/Dashboard/SiteList/hooks/useSubscriptionApps.ts`

- [ ] **Step 1: Create the hook file**

Create `js/src/pages/AdminApp/Dashboard/SiteList/hooks/useSubscriptionApps.ts`:

```typescript
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
```

- [ ] **Step 2: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/hooks/useSubscriptionApps.ts
git commit -m "feat: add useSubscriptionApps hook for PowerCloud subscription mapping"
```

---

## Task 7: Create `SubscriptionBinding` component

**Files:**
- Create: `js/src/pages/AdminApp/Dashboard/SiteList/SubscriptionBinding.tsx`

This component handles inline binding/unbinding of subscriptions for a PowerCloud website row.

- [ ] **Step 1: Create the component file**

Create `js/src/pages/AdminApp/Dashboard/SiteList/SubscriptionBinding.tsx`:

```typescript
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
	SubscriptionSelect,
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
```

- [ ] **Step 2: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/SubscriptionBinding.tsx
git commit -m "feat: add SubscriptionBinding inline component for PowerCloud sites"
```

---

## Task 8: Add subscription columns to PowerCloud site list

**Files:**
- Modify: `js/src/pages/AdminApp/Dashboard/SiteList/index.tsx`

- [ ] **Step 1: Add imports**

In `js/src/pages/AdminApp/Dashboard/SiteList/index.tsx`, add these imports after the existing imports (after line 52):

```typescript
import { useSubscriptionApps } from './hooks/useSubscriptionApps'
import { SubscriptionBinding } from './SubscriptionBinding'
```

- [ ] **Step 2: Add `useSubscriptionApps` hook call in `PowercloudContent`**

In the `PowercloudContent` component, after the existing `useQuery` for websites (after line 204), add:

```typescript
	// Batch query subscription mapping for all loaded websites
	const websiteIds = websites.map((w: IWebsite) => w.id)
	const {
		subscriptionMap,
		isFetching: isAppsFetching,
		refetch: refetchApps,
	} = useSubscriptionApps({ websiteIds })
```

- [ ] **Step 3: Import siteUrl**

At the top of the file, add `siteUrl` to the imports from `@/utils`. Find the line:

```typescript
import { powerCloudAxios, usePowerCloudAxiosWithApiKey } from '@/api'
```

And after it, add (if `siteUrl` is not already imported — check existing imports):

```typescript
import { siteUrl } from '@/utils'
```

Note: The existing file imports from `@/api` on line 39, and `@/components/SiteListTable` on line 40. The `siteUrl` import should come from `@/utils`. Check if any existing line already imports from `@/utils` — if not, add a new import line.

- [ ] **Step 4: Add the subscription column to the `columns` array**

> **Note:** The spec mentions a "訂單編號" (Order Number) column, but the `/apps` endpoint only returns `subscription_ids`, not parent order IDs. Getting order IDs would require modifying the backend `/apps` endpoint. This is deferred — the admin can access the order through the subscription link. Only the "對應訂閱" column is added now.

In `PowercloudContent`, in the `columns` array, insert the new column before the "建立時間" column (before the `createdAt` column definition, which starts around line 445). Insert after the "備註" column (after line 443):

```typescript
		{
			title: '對應訂閱',
			key: 'subscription',
			width: 200,
			render: (_: unknown, record: IWebsite) => {
				const subscriptionIds = subscriptionMap[record.id] || []
				return (
					<SubscriptionBinding
						websiteId={record.id}
						subscriptionIds={subscriptionIds}
						onBindingChange={() => refetchApps()}
					/>
				)
			},
		},
```

- [ ] **Step 5: Update refetch to also refetch apps**

In the "重新整理" button's `onClick` handler (around line 529), update it to also refetch apps:

Find:
```typescript
onClick={() => refetch()}
```

Replace with:
```typescript
onClick={() => {
	refetch()
	refetchApps()
}}
```

- [ ] **Step 6: Import IWebsite type for column render**

The `columns` array already uses `IWebsite` type. Verify the import exists on line 52:

```typescript
import type { IWebsite, IWebsiteResponse } from './types'
```

This should already be present. No change needed.

- [ ] **Step 7: Commit**

```bash
git add js/src/pages/AdminApp/Dashboard/SiteList/index.tsx
git commit -m "feat: add subscription binding column to PowerCloud site list"
```

---

## Task 9: Verify build

**Files:** None (verification only)

- [ ] **Step 1: Run TypeScript type check**

```bash
cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && npx tsc --noEmit
```

Expected: No type errors. If there are errors, fix them before proceeding.

- [ ] **Step 2: Run ESLint**

```bash
cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && pnpm lint
```

Expected: No new lint errors from our changes. Fix any that appear.

- [ ] **Step 3: Run PHP static analysis**

```bash
cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && vendor/bin/phpstan analyse
```

Expected: No new errors from our PHP changes. Fix any that appear.

- [ ] **Step 4: Build the frontend**

```bash
cd /Users/powerhouse/Documents/works/ai-projects/powerhouse/power-partner && pnpm build
```

Expected: Build succeeds with no errors.

- [ ] **Step 5: Commit any fixes**

If any fixes were needed, commit them:

```bash
git add -A
git commit -m "fix: address lint and type errors from subscription binding feature"
```
