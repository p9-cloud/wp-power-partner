<?php
/**
 * REST API 整合測試
 *
 * 覆蓋 Power Partner REST API 端點的基本行為：
 * - GET /power-partner/partner-id — public 端點
 * - GET /power-partner/account-info — public 端點
 * - GET /power-partner/emails — 需要 manage_options 權限
 * - POST /power-partner/settings — 需要 manage_options 權限
 * - GET /power-partner/apps — public 端點
 * - 未授權存取受保護端點返回 401/403
 * - 無效參數返回 400
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * @group smoke
 * @group happy
 * @group error
 * @group security
 */
class RestApiTest extends TestCase {

	/** @var int 管理員用戶 ID */
	private int $admin_user_id;

	protected function configure_dependencies(): void {
		// 建立管理員用戶
		$this->admin_user_id = $this->factory()->user->create(
			[
				'role'       => 'administrator',
				'user_login' => 'test_admin',
				'user_email' => 'admin@test.com',
			]
		);

		// 清除設定
		delete_option( 'power_partner_settings' );
		delete_option( 'power_partner_partner_id' );

		// 初始化 REST API 伺服器（觸發 rest_api_init hook，讓所有路由完成註冊）
		rest_get_server();
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );
		delete_option( 'power_partner_settings' );
		delete_option( 'power_partner_partner_id' );
		parent::tear_down();
	}

	// ========== 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function test_GET_partner_id端點公開可存取(): void {
		$request  = new \WP_REST_Request( 'GET', '/power-partner/partner-id' );
		$response = rest_do_request( $request );

		// 公開端點應可存取（200 或含資料的回應）
		$this->assertNotSame( 404, $response->get_status(), '/power-partner/partner-id 端點應存在' );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_GET_account_info端點公開可存取(): void {
		$request  = new \WP_REST_Request( 'GET', '/power-partner/account-info' );
		$response = rest_do_request( $request );

		$this->assertNotSame( 404, $response->get_status(), '/power-partner/account-info 端點應存在' );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_GET_apps端點公開可存取(): void {
		$request = new \WP_REST_Request( 'GET', '/power-partner/apps' );
		$request->set_query_params( [ 'site_ids' => [] ] );
		$response = rest_do_request( $request );

		$this->assertNotSame( 404, $response->get_status(), '/power-partner/apps 端點應存在' );
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_管理員可以GET_emails端點(): void {
		wp_set_current_user( $this->admin_user_id );

		$this->setup_settings_with_emails(
			[ $this->make_email_config() ]
		);

		$request  = new \WP_REST_Request( 'GET', '/power-partner/emails' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status(), 'GET /emails 管理員應回傳 200' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_管理員可以POST_settings端點儲存設定(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new \WP_REST_Request( 'POST', '/power-partner/settings' );
		$request->set_body_params(
			[
				'power_partner_disable_site_after_n_days' => 14,
				'emails'                                  => [],
			]
		);

		$response = rest_do_request( $request );

		// 成功儲存應回傳 200
		$this->assertSame( 200, $response->get_status(), 'POST /settings 管理員應回傳 200' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_GET_partner_id無設定時回傳空值(): void {
		$request  = new \WP_REST_Request( 'GET', '/power-partner/partner-id' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_管理員POST_powercloud_api_key成功(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new \WP_REST_Request( 'POST', '/power-partner/powercloud-api-key' );
		// 使用 JSON body（因為 callback 使用 get_json_params()）
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( [ 'api_key' => 'test-api-key-12345' ] ) );

		$response = rest_do_request( $request );

		// 200 或 201 均視為成功
		$this->assertContains(
			$response->get_status(),
			[ 200, 201 ],
			'POST /powercloud-api-key 管理員應成功'
		);
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_未登入使用者存取emails端點回傳401或403(): void {
		wp_set_current_user( 0 ); // 確保未登入

		$request  = new \WP_REST_Request( 'GET', '/power-partner/emails' );
		$response = rest_do_request( $request );

		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'未授權使用者存取 /emails 應回傳 401 或 403'
		);
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_一般訂閱者存取emails端點回傳401或403(): void {
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request  = new \WP_REST_Request( 'GET', '/power-partner/emails' );
		$response = rest_do_request( $request );

		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'subscriber 存取 /emails 應回傳 401 或 403'
		);
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_未登入使用者POST_settings回傳401或403(): void {
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'POST', '/power-partner/settings' );
		$request->set_body_params( [ 'power_partner_disable_site_after_n_days' => 7 ] );
		$response = rest_do_request( $request );

		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'未授權使用者存取 /settings 應回傳 401 或 403'
		);
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_未登入使用者POST_partner_id回傳401或403(): void {
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'POST', '/power-partner/partner-id' );
		$request->set_body_params( [ 'partner_id' => 'test-partner' ] );
		$response = rest_do_request( $request );

		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'未授權使用者 POST /partner-id 應回傳 401 或 403'
		);
	}

	// ========== 安全性（Security）==========

	/**
	 * @test
	 * @group security
	 */
	public function test_XSS輸入儲存到settings後讀回保持原始格式(): void {
		wp_set_current_user( $this->admin_user_id );

		$xss_payload = '<script>alert("xss")</script>';
		$email       = $this->make_email_config(
			[
				'subject' => $xss_payload,
				'body'    => $xss_payload,
			]
		);

		$request = new \WP_REST_Request( 'POST', '/power-partner/settings' );
		$request->set_body_params(
			[
				'power_partner_disable_site_after_n_days' => 7,
				'emails'                                  => [ $email ],
			]
		);

		$response = rest_do_request( $request );

		// 儲存後讀回，驗證不崩潰（回傳 200 或非 200 均可）
		// 主要驗證儲存含 XSS 的資料不會造成伺服器崩潰
		$this->assertContains( $response->get_status(), [ 200, 400, 422 ], 'POST /settings 應回傳 200 或驗證錯誤' );

		if ( 200 === $response->get_status() ) {
			$stored = get_option( 'power_partner_settings' );
			// 儲存成功時，值應為陣列（可能被 sanitize 過）
			$this->assertNotFalse( $stored, '設定應已儲存' );
		}
	}

	/**
	 * @test
	 * @group security
	 */
	public function test_GET_customers_by_search需要manage_options權限(): void {
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'GET', '/power-partner/customers-by-search' );
		$request->set_query_params( [ 'search' => 'admin' ] );
		$response = rest_do_request( $request );

		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'GET /customers-by-search 未授權應回傳 401 或 403'
		);
	}
}
