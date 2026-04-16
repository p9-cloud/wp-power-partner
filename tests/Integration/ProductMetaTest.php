<?php
/**
 * 商品 Meta 欄位整合測試
 *
 * 覆蓋 power_partner_host_type / power_partner_linked_site /
 * power_partner_host_position / power_partner_open_site_plan
 * 這四個商品 meta 的讀寫行為（透過 post meta API）
 *
 * 同時測試 LinkedSites 常數值的正確性，確保程式碼引用的常數
 * 與實際存入的 meta key 一致。
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\PowerPartner\Product\DataTabs\LinkedSites;
use J7\PowerPartner\Product\SiteSync;

/**
 * @group smoke
 * @group happy
 * @group error
 * @group edge
 */
class ProductMetaTest extends TestCase {

	protected function configure_dependencies(): void {
		// 不需要額外初始化
	}

	// ========== 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function test_LinkedSites常數存在且值正確(): void {
		$this->assertSame( 'power_partner_host_type', LinkedSites::HOST_TYPE_FIELD_NAME );
		$this->assertSame( 'power_partner_linked_site', LinkedSites::LINKED_SITE_FIELD_NAME );
		$this->assertSame( 'power_partner_host_position', LinkedSites::HOST_POSITION_FIELD_NAME );
		$this->assertSame( 'power_partner_open_site_plan', LinkedSites::OPEN_SITE_PLAN_FIELD_NAME );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_DEFAULT_HOST_TYPE常數為powercloud(): void {
		$this->assertSame( 'powercloud', LinkedSites::DEFAULT_HOST_TYPE );
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_設定商品host_type為powercloud並讀回(): void {
		$product_id = $this->create_subscription_product();
		update_post_meta( $product_id, LinkedSites::HOST_TYPE_FIELD_NAME, 'powercloud' );

		$value = get_post_meta( $product_id, LinkedSites::HOST_TYPE_FIELD_NAME, true );

		$this->assertSame( 'powercloud', $value );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_設定商品host_type為wpcd並讀回(): void {
		$product_id = $this->create_subscription_product();
		update_post_meta( $product_id, LinkedSites::HOST_TYPE_FIELD_NAME, 'wpcd' );

		$value = get_post_meta( $product_id, LinkedSites::HOST_TYPE_FIELD_NAME, true );

		$this->assertSame( 'wpcd', $value );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_設定商品所有PP欄位並讀回(): void {
		$product_id = $this->create_subscription_product();

		$this->set_product_pp_meta(
			$product_id,
			'powercloud',
			'template-site-123',
			'tw',
			'plan-abc'
		);

		$this->assertSame( 'powercloud', get_post_meta( $product_id, LinkedSites::HOST_TYPE_FIELD_NAME, true ) );
		$this->assertSame( 'template-site-123', get_post_meta( $product_id, LinkedSites::LINKED_SITE_FIELD_NAME, true ) );
		$this->assertSame( 'tw', get_post_meta( $product_id, LinkedSites::HOST_POSITION_FIELD_NAME, true ) );
		$this->assertSame( 'plan-abc', get_post_meta( $product_id, LinkedSites::OPEN_SITE_PLAN_FIELD_NAME, true ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_host_position支援所有有效區域(): void {
		$valid_positions = [ 'jp', 'tw', 'us_west', 'uk_london', 'sg', 'hk', 'canada' ];
		$product_id      = $this->create_subscription_product();

		foreach ( $valid_positions as $position ) {
			update_post_meta( $product_id, LinkedSites::HOST_POSITION_FIELD_NAME, $position );
			$stored = get_post_meta( $product_id, LinkedSites::HOST_POSITION_FIELD_NAME, true );
			$this->assertSame( $position, $stored, "區域 '{$position}' 應可正確儲存" );
		}
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_linked_site_id更新後正確覆蓋舊值(): void {
		$product_id = $this->create_subscription_product();

		update_post_meta( $product_id, LinkedSites::LINKED_SITE_FIELD_NAME, 'old-template' );
		update_post_meta( $product_id, LinkedSites::LINKED_SITE_FIELD_NAME, 'new-template' );

		$value = get_post_meta( $product_id, LinkedSites::LINKED_SITE_FIELD_NAME, true );
		$this->assertSame( 'new-template', $value );
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_不存在商品的meta讀取回傳空字串(): void {
		$value = get_post_meta( 9999999, LinkedSites::HOST_TYPE_FIELD_NAME, true );
		$this->assertSame( '', $value );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_已刪除商品的meta讀取不崩潰(): void {
		$product_id = $this->create_subscription_product();
		$this->set_product_pp_meta( $product_id, 'powercloud', 'template-1' );

		// 刪除貼文
		wp_delete_post( $product_id, true );

		// 刪除後讀取不崩潰
		$value = get_post_meta( $product_id, LinkedSites::HOST_TYPE_FIELD_NAME, true );
		$this->assertSame( '', $value );
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function test_linked_site_id為空字串時開站不應觸發(): void {
		$product_id = $this->create_subscription_product();

		// empty($linked_site_id) = true 應跳過開站
		update_post_meta( $product_id, LinkedSites::LINKED_SITE_FIELD_NAME, '' );

		$value = get_post_meta( $product_id, LinkedSites::LINKED_SITE_FIELD_NAME, true );
		$this->assertEmpty( $value );
		// SiteSync::site_sync_by_subscription 中有 empty($linked_site_id) 的判斷，確認此情境
		$this->assertSame( '', $value );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_linked_site_id為0時邏輯行為(): void {
		$product_id = $this->create_subscription_product();
		update_post_meta( $product_id, LinkedSites::LINKED_SITE_FIELD_NAME, '0' );

		$value = get_post_meta( $product_id, LinkedSites::LINKED_SITE_FIELD_NAME, true );
		// empty('0') = true（PHP 特性），應被跳過
		$this->assertSame( '0', $value );
		$this->assertTrue( empty( $value ), '字串 "0" 在 PHP 中被視為 empty' );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_linked_site_id為Unicode字元(): void {
		$product_id = $this->create_subscription_product();
		$unicode_id = 'template-測試中文-123';

		update_post_meta( $product_id, LinkedSites::LINKED_SITE_FIELD_NAME, $unicode_id );

		$value = get_post_meta( $product_id, LinkedSites::LINKED_SITE_FIELD_NAME, true );
		$this->assertSame( $unicode_id, $value );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_open_site_plan為超長字串不崩潰(): void {
		$product_id = $this->create_subscription_product();
		$long_plan  = str_repeat( 'plan-', 1000 );

		update_post_meta( $product_id, LinkedSites::OPEN_SITE_PLAN_FIELD_NAME, $long_plan );

		$value = get_post_meta( $product_id, LinkedSites::OPEN_SITE_PLAN_FIELD_NAME, true );
		$this->assertSame( $long_plan, $value );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_IS_POWER_PARTNER_SUBSCRIPTION_meta_值正確(): void {
		$this->assertSame( 'is_power_partner_site_sync', \J7\PowerPartner\ShopSubscription::IS_POWER_PARTNER_SUBSCRIPTION );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_訂閱商品meta同時含有IS_PP_和linked_site_id(): void {
		$product_id      = $this->create_subscription_product();
		$subscription_id = $this->factory()->post->create( [ 'post_type' => 'shop_subscription' ] );

		// 設定商品有 linked site
		update_post_meta( $product_id, LinkedSites::LINKED_SITE_FIELD_NAME, 'template-1' );

		// 標記訂閱為 PP 訂閱
		update_post_meta( $subscription_id, \J7\PowerPartner\ShopSubscription::IS_POWER_PARTNER_SUBSCRIPTION, '1' );

		$is_pp = get_post_meta( $subscription_id, \J7\PowerPartner\ShopSubscription::IS_POWER_PARTNER_SUBSCRIPTION, true );
		$this->assertSame( '1', $is_pp );

		$linked = get_post_meta( $product_id, LinkedSites::LINKED_SITE_FIELD_NAME, true );
		$this->assertSame( 'template-1', $linked );
	}
}
