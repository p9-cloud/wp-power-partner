# PowerCloud WooCommerce Subscription Binding

**Date:** 2026-04-15
**Status:** Draft
**Scope:** Add manual subscription binding for PowerCloud (new architecture) sites, with automatic site enable/disable on subscription status changes.

---

## Problem

The old architecture (WPCD) stores site-subscription relationships in `pp_linked_site_ids` subscription meta and displays "Order Number" and "Linked Subscription" in the admin site list. The new architecture (PowerCloud) lacks this:

1. PowerCloud `websiteId` is only stored in `_pp_create_site_responses_item` order item meta during auto-provisioning — not in `pp_linked_site_ids`.
2. The new architecture site list has no subscription/order columns.
3. There is no UI for manually binding a PowerCloud site to a subscription.
4. The automatic disable/enable code reads PowerCloud websiteId from order item meta, so manually-bound sites (without an order) would not be affected.

## Solution: Unified Storage (Approach A)

Store PowerCloud `websiteId` in `pp_linked_site_ids` (same as WPCD), add subscription columns to the new architecture site list, and modify disable/enable logic to read from `pp_linked_site_ids`.

---

## Backend Changes

### 1. Auto-Provisioning: Store websiteId in `pp_linked_site_ids`

**File:** `inc/classes/Product/SiteSync.php` — `site_sync_powercloud()`

After successful PowerCloud site creation (`$response_obj->status === 201`), extract `websiteId` from the response and store it in subscription meta:

```php
$website_id = $response_obj->data['websiteId'] ?? '';
if (!empty($website_id)) {
    \J7\PowerPartner\ShopSubscription::update_linked_site_ids(
        (int) $subscription->get_id(),
        [(string) $website_id]
    );
}
```

This ensures that all future PowerCloud sites are tracked in the same meta key as WPCD sites.

### 2. Disable Logic: Prefer `pp_linked_site_ids`

**File:** `inc/classes/Domains/Site/Services/DisableSiteScheduler.php` — `action_callback()`

Modify the PowerCloud branch to:
1. First check `pp_linked_site_ids` for stored websiteIds.
2. If found, iterate and call `FetchPowerCloud::disable_site()` for each.
3. If not found in `pp_linked_site_ids`, fall back to extracting from order item meta (backward compatibility for sites provisioned before this change).

Key change: The PowerCloud section should iterate `$linked_site_ids` first (like WPCD does), and only fall back to order item meta when no linked IDs exist for PowerCloud products.

```php
// PowerCloud branch — revised logic
if ($host_type === LinkedSites::DEFAULT_HOST_TYPE) {
    // Try pp_linked_site_ids first (covers both auto and manual binding)
    if (!empty($linked_site_ids)) {
        foreach ($linked_site_ids as $site_id) {
            $reason = "停用網站，訂閱ID: {$subscription_id}，websiteId: {$site_id}";
            FetchPowerCloud::disable_site((string) $current_user_id, (string) $site_id);
            $subscription->add_order_note($reason);
            $subscription->save();
        }
        continue;
    }

    // Fallback: extract from order item meta (pre-migration data)
    // ... existing order item meta extraction logic ...
}
```

**Important:** Since `pp_linked_site_ids` is shared between WPCD and PowerCloud, and a single subscription should only have one host_type, we need to ensure the WPCD branch doesn't also process the same IDs. Current code already handles this — WPCD uses `Fetch::disable_site()` and PowerCloud uses `FetchPowerCloud::disable_site()`, with the branch determined by the product's `host_type`.

### 3. Enable Logic: Prefer `pp_linked_site_ids`

**File:** `inc/classes/Domains/Site/Core/DisableHooks.php` — `restart_all_stopped_sites_scheduler()`

Apply the same pattern: for PowerCloud items, check `pp_linked_site_ids` first, fall back to order item meta.

```php
// PowerCloud branch — revised logic
if ($host_type === LinkedSites::DEFAULT_HOST_TYPE) {
    if (!empty($linked_site_ids)) {
        foreach ($linked_site_ids as $site_id) {
            FetchPowerCloud::enable_site((string) $current_user_id, (string) $site_id);
        }
        continue;
    }

    // Fallback: order item meta extraction
    // ... existing logic ...
}
```

### 4. REST API Changes

**Existing endpoints — no changes needed:**

| Endpoint | Purpose | Status |
|---|---|---|
| `GET /apps?app_ids[]=...` | Query subscription_ids by site/website IDs | Works as-is (queries `pp_linked_site_ids`) |
| `GET /subscriptions?user_id=X` | List user's subscriptions with `linked_site_ids` | Works as-is |
| `POST /change-subscription` | Bind site IDs to subscription | Works as-is (calls `ShopSubscription::change_linked_site_ids()`) |
| `GET /customers-by-search` | Search customers | Works as-is |

**New endpoint — unbind a site from its subscription:**

| Method | Route | Auth | Description |
|---|---|---|---|
| POST | `/unbind-site` | `manage_options` | Remove a single site_id from all subscriptions |

**File:** `inc/classes/Api/Main.php`

```php
// POST /power-partner/unbind-site
// Body: { "site_id": "websiteId" }
// Calls ShopSubscription::remove_linked_site_ids([$site_id])
```

This avoids requiring the frontend to know the full `linked_site_ids` list before unbinding.

---

## Frontend Changes

### 5. Add Subscription Columns to PowerCloud Site List

**File:** `js/src/pages/AdminApp/Dashboard/SiteList/index.tsx` — `PowercloudContent`

Add two columns to the `columns` array, inserted before "建立時間":

#### Column: 訂單編號 (Order Number)
- Displays the parent order ID of the linked subscription
- Rendered as `#OrderID` link to WooCommerce order edit page
- Shows `-` if no subscription is linked

#### Column: 對應訂閱 (Linked Subscription)
- Displays linked subscription IDs from `GET /apps` response
- Rendered as `#SubscriptionID` link(s) to subscription edit page
- When no subscription is linked, shows a "綁定訂閱" (Bind Subscription) button
- When bound, shows subscription link(s) + "解綁" (Unbind) button

### 6. Batch Query Subscriptions

After the PowerCloud website list loads, collect all `website.id` values and call:

```
GET /wp-json/power-partner/apps?app_ids[]=id1&app_ids[]=id2&...
```

Store the mapping `{ [websiteId]: subscription_ids[] }` in component state and merge with table data.

**Type extension:**

```typescript
type IWebsiteWithSubscription = IWebsite & {
    subscriptionIds?: string[]
}
```

### 7. Manual Binding Flow

UI flow within the "對應訂閱" column:

1. **No binding:** Show "綁定訂閱" button
2. **Click button:** Expand inline form with:
   - Customer search input (using `GET /customers-by-search?search=...`)
   - After selecting customer, show subscription dropdown (using `GET /subscriptions?user_id=X`)
   - "確認綁定" (Confirm) and "取消" (Cancel) buttons
3. **On confirm:** Call `POST /change-subscription` with `{ subscription_id, site_id: websiteId, linked_site_ids: [websiteId] }`
4. **On success:** Refetch the apps query to update the display

**Unbinding:** Call `POST /unbind-site` with `{ site_id: websiteId }`. This removes the websiteId from all subscriptions containing it.

### 8. Component Structure

```
js/src/pages/AdminApp/Dashboard/SiteList/
├── index.tsx                    # Existing — add columns + apps query
├── SubscriptionBinding.tsx      # NEW — inline binding/unbinding component
├── types.ts                     # Existing — add IWebsiteWithSubscription
└── hooks/
    └── useSubscriptionApps.ts   # NEW — hook for batch querying apps endpoint
```

---

## Data Flow Diagram

```
PowerCloud Site List loads
    │
    ├── GET /websites (PowerCloud API) → site data
    │
    └── GET /apps?app_ids[]=... (WP REST API) → subscription mapping
         │
         └── Merged in component state → display in table

Manual Bind:
    User clicks "綁定訂閱"
    │
    ├── GET /customers-by-search?search=... → customer list
    │
    ├── GET /subscriptions?user_id=X → subscription list
    │
    └── POST /change-subscription → updates pp_linked_site_ids
         │
         └── Refetch GET /apps → updated display

Subscription Status Change:
    Subscription fails → DisableHooks schedules disable
    │
    └── DisableSiteScheduler::action_callback()
         │
         ├── Read pp_linked_site_ids (covers auto + manual bindings)
         │
         └── FetchPowerCloud::disable_site(userId, websiteId)

    Subscription recovers → DisableHooks restarts sites
    │
    └── restart_all_stopped_sites_scheduler()
         │
         ├── Read pp_linked_site_ids
         │
         └── FetchPowerCloud::enable_site(userId, websiteId)
```

---

## Edge Cases

1. **Pre-existing PowerCloud sites:** Sites created before this change won't have `pp_linked_site_ids`. The fallback to order item meta in disable/enable ensures backward compatibility. Admin can manually bind them via the new UI.

2. **Multiple sites per subscription:** `pp_linked_site_ids` is multi-value meta, supporting multiple sites per subscription. The UI should display all linked subscription IDs.

3. **Subscription with mixed host types:** A subscription's parent order could theoretically have items with different host_types. The disable/enable logic already iterates by order item and checks host_type per product.

4. **PowerCloud API key missing:** The site list tab already handles this — shows "登入新架構" button when not authenticated. The subscription columns simply won't load.

---

## Out of Scope

- Data migration for existing PowerCloud sites (manual binding via UI is sufficient)
- Frontend subscription list view changes
- User-facing (App2) subscription display
- License code binding changes
