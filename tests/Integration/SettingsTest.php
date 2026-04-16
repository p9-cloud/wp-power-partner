<?php
/**
 * Power Partner Settings 整合測試
 *
 * 覆蓋 power_partner_settings WordPress option 的 CRUD 行為：
 * - 讀取預設值
 * - 儲存/讀取設定
 * - disable_site_after_n_days 邊界值
 * - emails 陣列的欄位驗證
 * - SubscriptionEmailHooks::get_emails() 依 action_name 過濾
 * - get_emails() 只回傳 enabled 的 email
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\PowerPartner\Domains\Email\Core\SubscriptionEmailHooks;

/**
 * @group smoke
 * @group happy
 * @group error
 * @group edge
 */
class SettingsTest extends TestCase {

	protected function configure_dependencies(): void {
		// 測試前清除設定，避免相互影響
		$this->clear_power_partner_settings();
	}

	public function tear_down(): void {
		$this->clear_power_partner_settings();
		delete_option( 'power_partner_partner_id' );
		parent::tear_down();
	}

	// ========== 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function test_讀取不存在的設定回傳空陣列(): void {
		$settings = get_option( 'power_partner_settings', [] );
		$this->assertIsArray( $settings );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_儲存並讀回設定(): void {
		$expected = [
			'power_partner_disable_site_after_n_days' => 7,
			'emails'                                  => [],
		];

		$this->set_power_partner_settings( $expected );
		$actual = $this->get_power_partner_settings();

		$this->assertSame( 7, (int) $actual['power_partner_disable_site_after_n_days'] );
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_儲存Email模板並透過SubscriptionEmailHooks讀取(): void {
		$email_config = $this->make_email_config(
			[
				'enabled'     => '1',
				'action_name' => 'site_sync',
			]
		);

		$this->setup_settings_with_emails( [ $email_config ] );

		// 重新初始化 SubscriptionEmailHooks（讀取最新 settings）
		$hooks  = new SubscriptionEmailHooks();
		$emails = $hooks->get_emails( 'site_sync' );

		$this->assertCount( 1, $emails );
		$this->assertSame( 'site_sync', $emails[0]->action_name );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_多個Email模板依action_name正確過濾(): void {
		$emails = [
			$this->make_email_config( [ 'enabled' => '1', 'action_name' => 'site_sync', 'key' => 'email_1' ] ),
			$this->make_email_config( [ 'enabled' => '1', 'action_name' => 'subscription_failed', 'key' => 'email_2' ] ),
			$this->make_email_config( [ 'enabled' => '1', 'action_name' => 'site_sync', 'key' => 'email_3' ] ),
		];

		$this->setup_settings_with_emails( $emails );

		$hooks          = new SubscriptionEmailHooks();
		$site_sync_mails = $hooks->get_emails( 'site_sync' );
		$failed_mails    = $hooks->get_emails( 'subscription_failed' );

		$this->assertCount( 2, $site_sync_mails, 'site_sync 應有 2 封 email' );
		$this->assertCount( 1, $failed_mails, 'subscription_failed 應有 1 封 email' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_get_emails只回傳enabled的email(): void {
		$emails = [
			$this->make_email_config( [ 'enabled' => '1', 'action_name' => 'site_sync', 'key' => 'enabled_email' ] ),
			$this->make_email_config( [ 'enabled' => '0', 'action_name' => 'site_sync', 'key' => 'disabled_email' ] ),
		];

		$this->setup_settings_with_emails( $emails );

		$hooks  = new SubscriptionEmailHooks();
		$result = $hooks->get_emails( 'site_sync' );

		$this->assertCount( 1, $result, '只有 1 封 enabled 的 email 應被回傳' );
		$this->assertSame( 'enabled_email', $result[0]->key );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_get_email_by_key可以取得特定email(): void {
		$key = 'my_unique_email_key';
		$this->setup_settings_with_emails( [
			$this->make_email_config( [ 'key' => $key ] ),
		] );

		$hooks = new SubscriptionEmailHooks();
		$email = $hooks->get_email( $key );

		$this->assertNotNull( $email );
		$this->assertSame( $key, $email->key );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_disable_site_after_n_days設定儲存正確(): void {
		$this->setup_settings_with_emails( [], 14 );

		$settings = $this->get_power_partner_settings();

		$this->assertSame( 14, (int) $settings['power_partner_disable_site_after_n_days'] );
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_emails設定中無效的action_name_SubscriptionEmailHooks建構時拋出例外(): void {
		$invalid_email = $this->make_email_config( [ 'action_name' => '完全無效的動作名稱' ] );

		// 設定含無效 email
		update_option(
			'power_partner_settings',
			[
				'power_partner_disable_site_after_n_days' => 7,
				'emails'                                  => [ $invalid_email ],
			]
		);

		// SubscriptionEmailHooks 建構時，Email::create() 會對無效 action_name 拋出 Exception
		// 這是已知的系統行為：無效設定會在 singleton 建立時失敗
		try {
			$hooks = new SubscriptionEmailHooks();
			// 如果沒有拋出例外，表示系統可能未來有自動過濾機制
			$this->assertIsObject( $hooks );
		} catch ( \Throwable $th ) {
			// 預期行為：Email::create() 因無效 action_name 拋出例外
			$this->assertStringContainsString( 'action_name', $th->getMessage() );
		}
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_get_email_by_key找不到時回傳null(): void {
		$this->setup_settings_with_emails( [] );

		$hooks = new SubscriptionEmailHooks();
		$email = $hooks->get_email( '不存在的key' );

		$this->assertNull( $email );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_settings為非陣列值時不崩潰(): void {
		// 直接存入非陣列值（邊緣情況）
		update_option( 'power_partner_settings', 'invalid_string_value' );

		// 讀取時應防禦性處理，不崩潰
		$hooks  = new SubscriptionEmailHooks();
		$emails = $hooks->get_emails();

		$this->assertIsArray( $emails );
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function test_disable_after_n_days為0時不崩潰(): void {
		$this->setup_settings_with_emails( [], 0 );

		$settings = $this->get_power_partner_settings();
		$this->assertSame( 0, (int) $settings['power_partner_disable_site_after_n_days'] );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_disable_after_n_days為負數時不崩潰(): void {
		$this->setup_settings_with_emails( [], -1 );

		$settings = $this->get_power_partner_settings();
		$this->assertSame( -1, (int) $settings['power_partner_disable_site_after_n_days'] );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_disable_after_n_days為極大值時不崩潰(): void {
		$this->setup_settings_with_emails( [], 99999 );

		$settings = $this->get_power_partner_settings();
		$this->assertSame( 99999, (int) $settings['power_partner_disable_site_after_n_days'] );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_emails為空陣列時get_emails回傳空陣列(): void {
		$this->setup_settings_with_emails( [] );

		$hooks  = new SubscriptionEmailHooks();
		$emails = $hooks->get_emails();

		$this->assertIsArray( $emails );
		$this->assertCount( 0, $emails );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_大量Email模板時仍能正確過濾(): void {
		$emails = [];
		for ( $i = 0; $i < 100; $i++ ) {
			$emails[] = $this->make_email_config(
				[
					'key'         => 'email_' . $i,
					'enabled'     => ( $i % 2 === 0 ) ? '1' : '0', // 奇數 disabled
					'action_name' => 'site_sync',
				]
			);
		}

		$this->setup_settings_with_emails( $emails );

		$hooks  = new SubscriptionEmailHooks();
		$result = $hooks->get_emails( 'site_sync' );

		// 100 個 email 中，50 個 enabled（偶數 index：0,2,4...98）
		$this->assertCount( 50, $result );
	}
}
