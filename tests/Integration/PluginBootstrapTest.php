<?php
/**
 * Plugin Bootstrap 整合測試
 *
 * 驗證外掛的基本載入與初始化行為：
 * - Plugin 類別存在
 * - Bootstrap 類別存在
 * - 核心 WordPress hooks 已正確註冊
 * - 常數與選項的初始狀態
 * - Plugin activate() 設定預設值
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\PowerPartner\Plugin;

/**
 * @group smoke
 * @group happy
 */
class PluginBootstrapTest extends TestCase {

	protected function configure_dependencies(): void {
		// 此測試直接驗證外掛初始化結果，不需要額外設定
	}

	public function tear_down(): void {
		delete_option( 'power_partner_settings' );
		delete_option( 'power_partner_partner_id' );
		parent::tear_down();
	}

	// ========== 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function test_Plugin類別已載入(): void {
		$this->assertTrue( class_exists( Plugin::class ), 'J7\PowerPartner\Plugin 類別應已載入' );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_Bootstrap類別已載入(): void {
		$this->assertTrue(
			class_exists( 'J7\PowerPartner\Bootstrap' ),
			'J7\PowerPartner\Bootstrap 類別應已載入'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_WooCommerce已載入(): void {
		$this->assertTrue( class_exists( 'WooCommerce' ) || function_exists( 'WC' ), 'WooCommerce 應已載入' );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_WpUtils工具庫已載入(): void {
		$this->assertTrue(
			trait_exists( 'J7\WpUtils\Traits\SingletonTrait' ),
			'J7\WpUtils\Traits\SingletonTrait 應已載入'
		);
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_DEFAULT_EMAIL_BODY常數不為空(): void {
		$this->assertNotEmpty( Plugin::DEFAULT_EMAIL_BODY );
		$this->assertIsString( Plugin::DEFAULT_EMAIL_BODY );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_DEFAULT_EMAIL_BODY包含基本Token佔位符(): void {
		$body = Plugin::DEFAULT_EMAIL_BODY;

		$this->assertStringContainsString( '##FIRST_NAME##', $body );
		$this->assertStringContainsString( '##FRONTURL##', $body );
		$this->assertStringContainsString( '##ADMINURL##', $body );
		$this->assertStringContainsString( '##SITEUSERNAME##', $body );
		$this->assertStringContainsString( '##SITEPASSWORD##', $body );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_Plugin_activate設定預設power_partner_settings(): void {
		// 確保設定不存在
		delete_option( 'power_partner_settings' );

		// 執行 activate
		$plugin = Plugin::instance();
		$plugin->activate();

		$settings = get_option( 'power_partner_settings' );

		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'power_partner_disable_site_after_n_days', $settings );
		$this->assertArrayHasKey( 'emails', $settings );
		$this->assertSame( 7, (int) $settings['power_partner_disable_site_after_n_days'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_Plugin_activate預設Email包含site_sync動作(): void {
		delete_option( 'power_partner_settings' );

		$plugin = Plugin::instance();
		$plugin->activate();

		$settings = get_option( 'power_partner_settings' );
		$emails   = $settings['emails'] ?? [];

		$this->assertNotEmpty( $emails );
		$this->assertSame( 'site_sync', $emails[0]['action_name'] );
		$this->assertSame( '1', $emails[0]['enabled'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_Plugin_activate不覆蓋已存在的設定(): void {
		// 先設定自訂值
		update_option( 'power_partner_settings', [ 'custom' => 'value' ] );

		$plugin = Plugin::instance();
		$plugin->activate();

		// add_option 不覆蓋已存在的選項
		$settings = get_option( 'power_partner_settings' );
		$this->assertArrayHasKey( 'custom', $settings );
	}

	// ========== 核心 Domain 類別 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_Email_DTO類別已載入(): void {
		$this->assertTrue( class_exists( 'J7\PowerPartner\Domains\Email\DTOs\Email' ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_SubscriptionEmailHooks類別已載入(): void {
		$this->assertTrue( class_exists( 'J7\PowerPartner\Domains\Email\Core\SubscriptionEmailHooks' ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_DisableHooks類別已載入(): void {
		$this->assertTrue( class_exists( 'J7\PowerPartner\Domains\Site\Core\DisableHooks' ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_Token工具類別已載入(): void {
		$this->assertTrue( class_exists( 'J7\PowerPartner\Utils\Token' ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_ShopSubscription類別已載入(): void {
		$this->assertTrue( class_exists( 'J7\PowerPartner\ShopSubscription' ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_SiteSync類別已載入(): void {
		$this->assertTrue( class_exists( 'J7\PowerPartner\Product\SiteSync' ) );
	}

	// ========== WordPress REST API 路由 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_REST_API路由已在全域路由中(): void {
		// 確認 power-partner REST namespace 存在
		$server = rest_get_server();
		$routes = $server->get_routes();

		$has_power_partner_route = false;
		foreach ( array_keys( $routes ) as $route ) {
			if ( strpos( $route, '/power-partner/' ) !== false ) {
				$has_power_partner_route = true;
				break;
			}
		}

		$this->assertTrue( $has_power_partner_route, '/wp-json/power-partner/ 路由應已註冊' );
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_Plugin_logger不拋出例外(): void {
		try {
			Plugin::logger( '測試 log 訊息', 'info', [], 0 );
			$this->assertTrue( true ); // 沒有例外即通過
		} catch ( \Throwable $th ) {
			$this->fail( 'Plugin::logger 不應拋出例外：' . $th->getMessage() );
		}
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function test_Plugin_logger空訊息不崩潰(): void {
		try {
			Plugin::logger( '', 'info' );
			$this->assertTrue( true );
		} catch ( \Throwable $th ) {
			$this->fail( 'Plugin::logger 空訊息不應崩潰：' . $th->getMessage() );
		}
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_Plugin_logger無效log等級觸發WC_doing_it_wrong(): void {
		// WC_Logger::log 對無效等級會呼叫 wc_doing_it_wrong
		// WP_UnitTestCase 會將 wc_doing_it_wrong 轉為測試失敗
		// 所以這裡使用 $this->expectException 或直接允許 doing_it_wrong
		// 正確測試方式：使用 @expectedDeprecation 或允許 doing_it_wrong
		$this->setExpectedIncorrectUsage( 'WC_Logger::log' );

		Plugin::logger( '測試', 'invalid_level' );

		// 如果走到這裡，表示 doing_it_wrong 被正確觸發且被我們預期
		$this->assertTrue( true );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_Plugin_logger超長訊息不崩潰(): void {
		$long_message = str_repeat( '很長的日誌訊息', 10000 );

		try {
			Plugin::logger( $long_message, 'info' );
			$this->assertTrue( true );
		} catch ( \Throwable $th ) {
			$this->fail( 'Plugin::logger 超長訊息不應崩潰：' . $th->getMessage() );
		}
	}
}
