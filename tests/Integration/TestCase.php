<?php
/**
 * 整合測試基礎類別
 * 所有 Power Partner 整合測試必須繼承此類別
 */

declare( strict_types=1 );

namespace Tests\Integration;

/**
 * Class TestCase
 * 整合測試基礎類別，提供共用 helper methods
 */
abstract class TestCase extends \WP_UnitTestCase {

	/**
	 * 最後發生的錯誤（用於驗證操作是否失敗）
	 *
	 * @var \Throwable|null
	 */
	protected ?\Throwable $lastError = null;

	/**
	 * 查詢結果（用於驗證 Query 操作的回傳值）
	 *
	 * @var mixed
	 */
	protected mixed $queryResult = null;

	/**
	 * ID 映射表（名稱 → ID 等）
	 *
	 * @var array<string, int>
	 */
	protected array $ids = [];

	/**
	 * Repository 容器
	 *
	 * @var \stdClass
	 */
	protected \stdClass $repos;

	/**
	 * Service 容器
	 *
	 * @var \stdClass
	 */
	protected \stdClass $services;

	/**
	 * WooCommerce Subscriptions 是否可用
	 *
	 * @var bool
	 */
	protected static bool $wcs_available;

	/**
	 * 設定（每個測試前執行）
	 */
	public function set_up(): void {
		parent::set_up();

		$this->lastError   = null;
		$this->queryResult = null;
		$this->ids         = [];
		$this->repos       = new \stdClass();
		$this->services    = new \stdClass();

		self::$wcs_available = class_exists( 'WC_Subscription' ) && function_exists( 'wcs_get_subscription' );

		$this->configure_dependencies();
	}

	/**
	 * 初始化依賴（子類別可選擇覆寫）
	 * 在此方法中初始化 $this->repos 和 $this->services
	 */
	protected function configure_dependencies(): void {
		// 預設空實作，子類別自行覆寫
	}

	/**
	 * 若 WooCommerce Subscriptions 不可用，自動跳過測試
	 */
	protected function skip_if_no_subscriptions(): void {
		if ( ! self::$wcs_available ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions 未安裝，跳過此測試' );
		}
	}

	// ========== WordPress Options Helper ==========

	/**
	 * 設定 power_partner_settings 選項
	 *
	 * @param array<string, mixed> $settings 設定值
	 */
	protected function set_power_partner_settings( array $settings ): void {
		update_option( 'power_partner_settings', $settings );
	}

	/**
	 * 取得 power_partner_settings 選項
	 *
	 * @return array<string, mixed>
	 */
	protected function get_power_partner_settings(): array {
		$settings = get_option( 'power_partner_settings', [] );
		return is_array( $settings ) ? $settings : [];
	}

	/**
	 * 清除 power_partner_settings 選項
	 */
	protected function clear_power_partner_settings(): void {
		delete_option( 'power_partner_settings' );
	}

	/**
	 * 建立標準 Email 設定陣列
	 *
	 * @param array<string, mixed> $overrides 覆蓋預設值
	 * @return array<string, mixed>
	 */
	protected function make_email_config( array $overrides = [] ): array {
		return array_merge(
			[
				'enabled'     => '1',
				'key'         => 'test_email_' . uniqid(),
				'action_name' => 'site_sync',
				'subject'     => '測試信件主旨 ##FIRST_NAME##',
				'body'        => '<p>測試信件內容</p>',
				'days'        => '0',
				'operator'    => 'after',
			],
			$overrides
		);
	}

	/**
	 * 建立含 Email 模板的 power_partner_settings
	 *
	 * @param array<array<string, mixed>> $emails Email 設定陣列
	 * @param int                         $disable_after_n_days 停用天數
	 */
	protected function setup_settings_with_emails( array $emails, int $disable_after_n_days = 7 ): void {
		$this->set_power_partner_settings(
			[
				'power_partner_disable_site_after_n_days' => $disable_after_n_days,
				'emails'                                  => $emails,
			]
		);
	}

	// ========== WooCommerce 商品 Helper ==========

	/**
	 * 建立測試用訂閱商品
	 *
	 * @param array<string, mixed> $args 覆蓋預設值
	 * @return int 商品 ID
	 */
	protected function create_subscription_product( array $args = [] ): int {
		$defaults = [
			'post_title'  => '測試訂閱商品',
			'post_status' => 'publish',
			'post_type'   => 'product',
		];

		$post_args  = wp_parse_args( $args, $defaults );
		$product_id = $this->factory()->post->create( $post_args );

		// 設定商品類型 meta
		update_post_meta( $product_id, '_price', $args['price'] ?? '100' );
		update_post_meta( $product_id, '_regular_price', $args['price'] ?? '100' );

		return $product_id;
	}

	/**
	 * 設定商品的 Power Partner 欄位 meta
	 *
	 * @param int    $product_id      商品 ID
	 * @param string $host_type       主機類型 ('powercloud' | 'wpcd')
	 * @param string $linked_site_id  模板站 ID
	 * @param string $host_position   區域
	 * @param string $open_site_plan  方案 ID
	 */
	protected function set_product_pp_meta(
		int $product_id,
		string $host_type = 'powercloud',
		string $linked_site_id = '',
		string $host_position = 'tw',
		string $open_site_plan = ''
	): void {
		update_post_meta( $product_id, 'power_partner_host_type', $host_type );
		update_post_meta( $product_id, 'power_partner_linked_site', $linked_site_id );
		update_post_meta( $product_id, 'power_partner_host_position', $host_position );
		update_post_meta( $product_id, 'power_partner_open_site_plan', $open_site_plan );
	}

	// ========== 斷言 Helper ==========

	/**
	 * 斷言操作成功（$this->lastError 應為 null）
	 */
	protected function assert_operation_succeeded(): void {
		$this->assertNull(
			$this->lastError,
			sprintf( '預期操作成功，但發生錯誤：%s', $this->lastError?->getMessage() )
		);
	}

	/**
	 * 斷言操作失敗（$this->lastError 不應為 null）
	 */
	protected function assert_operation_failed(): void {
		$this->assertNotNull( $this->lastError, '預期操作失敗，但沒有發生錯誤' );
	}

	/**
	 * 斷言操作失敗且錯誤訊息包含指定文字
	 *
	 * @param string $msg 期望錯誤訊息包含的文字
	 */
	protected function assert_operation_failed_with_message( string $msg ): void {
		$this->assertNotNull( $this->lastError, '預期操作失敗' );
		$this->assertStringContainsString(
			$msg,
			$this->lastError->getMessage(),
			"錯誤訊息不包含 \"{$msg}\"，實際訊息：{$this->lastError->getMessage()}"
		);
	}

	/**
	 * 斷言 action hook 被觸發
	 *
	 * @param string $action_name action 名稱
	 */
	protected function assert_action_fired( string $action_name ): void {
		$this->assertGreaterThan(
			0,
			did_action( $action_name ),
			"Action '{$action_name}' 未被觸發"
		);
	}

	/**
	 * 斷言 WordPress Option 值符合預期
	 *
	 * @param string $option_name 選項名稱
	 * @param mixed  $expected    期望值
	 */
	protected function assert_option_equals( string $option_name, mixed $expected ): void {
		$actual = get_option( $option_name );
		$this->assertEquals(
			$expected,
			$actual,
			"選項 '{$option_name}' 值不符，期望：" . print_r( $expected, true ) . "，實際：" . print_r( $actual, true ) // phpcs:ignore
		);
	}

	/**
	 * 斷言 transient 存在
	 *
	 * @param string $transient_name transient 名稱
	 */
	protected function assert_transient_exists( string $transient_name ): void {
		$value = get_transient( $transient_name );
		$this->assertNotFalse( $value, "Transient '{$transient_name}' 不存在或已過期" );
	}
}
