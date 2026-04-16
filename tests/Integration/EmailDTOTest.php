<?php
/**
 * Email DTO 整合測試
 *
 * 覆蓋 Domains\Email\DTOs\Email 的邊緣案例：
 * - 正常建立 DTO
 * - unique 自動設定（特定 action_name）
 * - 無效 action_name 拋出例外
 * - 無效 days（非數字）拋出例外
 * - 無效 operator 拋出例外
 * - enabled 欄位的各種格式（'1', '0', 'yes', 'no'）
 * - 邊界：days 為 '0', '-1', '999999', 小數點
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\PowerPartner\Domains\Email\DTOs\Email;

/**
 * @group smoke
 * @group happy
 * @group error
 * @group edge
 */
class EmailDTOTest extends TestCase {

	protected function configure_dependencies(): void {
		// Email DTO 不需要額外初始化
	}

	// ========== 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function test_建立基本site_sync_Email_DTO成功(): void {
		$data = $this->make_email_config();

		try {
			$email = Email::create( $data );
			$this->assertInstanceOf( Email::class, $email );
			$this->assertSame( 'site_sync', $email->action_name );
			$this->assertSame( '1', $email->enabled );
		} catch ( \Throwable $th ) {
			$this->fail( '建立基本 Email DTO 不應拋出例外：' . $th->getMessage() );
		}
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_建立subscription_failed_Email_DTO成功(): void {
		$data  = $this->make_email_config( [ 'action_name' => 'subscription_failed' ] );
		$email = Email::create( $data );

		$this->assertSame( 'subscription_failed', $email->action_name );
		$this->assertFalse( $email->unique );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_建立subscription_success_Email_DTO成功(): void {
		$data  = $this->make_email_config( [ 'action_name' => 'subscription_success' ] );
		$email = Email::create( $data );

		$this->assertSame( 'subscription_success', $email->action_name );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_trial_end類型的Email_unique自動設為true(): void {
		$unique_actions = [ 'trial_end', 'watch_trial_end', 'end', 'watch_end', 'next_payment', 'watch_next_payment' ];

		foreach ( $unique_actions as $action ) {
			$data  = $this->make_email_config( [ 'action_name' => $action ] );
			$email = Email::create( $data );

			$this->assertTrue( $email->unique, "action_name={$action} 的 unique 應為 true，但實際為 false" );
		}
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_site_sync類型的Email_unique為false(): void {
		$non_unique_actions = [ 'site_sync', 'subscription_failed', 'subscription_success' ];

		foreach ( $non_unique_actions as $action ) {
			$data  = $this->make_email_config( [ 'action_name' => $action ] );
			$email = Email::create( $data );

			$this->assertFalse( $email->unique, "action_name={$action} 的 unique 應為 false，但實際為 true" );
		}
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_enabled_使用字串1表示啟用(): void {
		$data  = $this->make_email_config( [ 'enabled' => '1' ] );
		$email = Email::create( $data );

		$this->assertSame( '1', $email->enabled );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_operator為before時建立成功(): void {
		$data  = $this->make_email_config( [ 'operator' => 'before' ] );
		$email = Email::create( $data );

		$this->assertSame( 'before', $email->operator );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_operator為after時建立成功(): void {
		$data  = $this->make_email_config( [ 'operator' => 'after' ] );
		$email = Email::create( $data );

		$this->assertSame( 'after', $email->operator );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_days為正整數字串時建立成功(): void {
		$data  = $this->make_email_config( [ 'days' => '7' ] );
		$email = Email::create( $data );

		$this->assertSame( '7', $email->days );
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_無效action_name拋出例外(): void {
		$data = $this->make_email_config( [ 'action_name' => '不存在的動作' ] );

		try {
			Email::create( $data );
			$this->fail( '預期應拋出例外，但沒有' );
		} catch ( \Throwable $th ) {
			$this->assertStringContainsString( 'action_name', $th->getMessage() );
		}
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_無效operator拋出例外(): void {
		$data = $this->make_email_config( [ 'operator' => 'invalid_operator' ] );

		try {
			Email::create( $data );
			$this->fail( '預期應拋出例外，但沒有' );
		} catch ( \Throwable $th ) {
			$this->assertNotNull( $th );
		}
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_非數字的days拋出例外(): void {
		$data = $this->make_email_config( [ 'days' => 'abc' ] );

		try {
			Email::create( $data );
			$this->fail( '預期應拋出例外，但沒有' );
		} catch ( \Throwable $th ) {
			$this->assertStringContainsString( 'days', $th->getMessage() );
		}
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_空字串days拋出例外(): void {
		$data = $this->make_email_config( [ 'days' => '' ] );

		try {
			Email::create( $data );
			$this->fail( '預期應拋出例外，但沒有' );
		} catch ( \Throwable $th ) {
			$this->assertStringContainsString( 'days', $th->getMessage() );
		}
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function test_days為0時建立成功(): void {
		$data  = $this->make_email_config( [ 'days' => '0' ] );
		$email = Email::create( $data );

		$this->assertSame( '0', $email->days );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_days為負數字串時建立成功_is_numeric接受負數(): void {
		// is_numeric('-1') 返回 true，因此 DTO 允許負數
		$data  = $this->make_email_config( [ 'days' => '-1' ] );
		$email = Email::create( $data );

		$this->assertSame( '-1', $email->days );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_days為極大數字時建立成功(): void {
		$data  = $this->make_email_config( [ 'days' => '999999' ] );
		$email = Email::create( $data );

		$this->assertSame( '999999', $email->days );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_days為小數點字串時建立成功_is_numeric接受小數(): void {
		// is_numeric('1.5') 返回 true，DTO 允許小數
		$data  = $this->make_email_config( [ 'days' => '1.5' ] );
		$email = Email::create( $data );

		$this->assertSame( '1.5', $email->days );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_enabled為yes字串時正規化為1(): void {
		$data  = $this->make_email_config( [ 'enabled' => 'yes' ] );
		$email = Email::create( $data );

		// wc_string_to_bool('yes') = true，正規化後應為 '1'
		$this->assertSame( '1', $email->enabled );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_enabled為no字串時正規化為0(): void {
		$data  = $this->make_email_config( [ 'enabled' => 'no' ] );
		$email = Email::create( $data );

		// wc_string_to_bool('no') = false，正規化後應為 '0'
		$this->assertSame( '0', $email->enabled );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_超長subject和body可以正常建立(): void {
		$long_text = str_repeat( '測試內容', 5000 );
		$data      = $this->make_email_config(
			[
				'subject' => $long_text,
				'body'    => $long_text,
			]
		);

		$email = Email::create( $data );

		$this->assertSame( $long_text, $email->subject );
	}
}
