<?php
/**
 * PHPUnit 整合測試引導文件
 * 載入順序（重要，不可更改）：
 * 1. Composer autoloader
 * 2. 解析 WP_TESTS_DIR 路徑
 * 3. 確認 WP 測試套件檔案存在
 * 4. 定義 WP_TESTS_PHPUNIT_POLYFILLS_PATH
 * 5. 載入 WP 測試函式 (functions.php)
 * 6. 透過 muplugins_loaded hook 載入插件
 * 7. 載入 WP 測試 bootstrap (bootstrap.php)
 */

declare( strict_types=1 );

// 載入 Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// 取得 wp-phpunit 提供的測試目錄路徑
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	// 優先使用 wp-phpunit vendor 套件提供的路徑
	$_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

// 確認 WP 測試套件存在
if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "找不到 WordPress 測試套件：{$_tests_dir}/includes/functions.php\n";
	exit( 1 );
}

// 設定 PHPUnit Polyfills 路徑（yoast/phpunit-polyfills 需要）
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

// 載入 WP 測試函式
require_once "{$_tests_dir}/includes/functions.php";

/**
 * 在 WordPress muplugins_loaded 時載入插件
 * 順序：WooCommerce → WooCommerce Subscriptions → Powerhouse → Power Partner
 */
function _power_partner_integration_manually_load_plugin(): void {
	// 1. 載入 WooCommerce
	$woo_path = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
	if ( file_exists( $woo_path ) ) {
		require_once $woo_path;
	} else {
		echo "警告：WooCommerce 不存在於 {$woo_path}\n";
	}

	// 2. 載入 WooCommerce Subscriptions（可選，測試環境可能沒有）
	$woo_subs_path = WP_PLUGIN_DIR . '/woocommerce-subscriptions/woocommerce-subscriptions.php';
	if ( file_exists( $woo_subs_path ) ) {
		require_once $woo_subs_path;
	} else {
		echo "警告：WooCommerce Subscriptions 不存在於 {$woo_subs_path}，相關測試將跳過\n";
	}

	// 3. 載入 Powerhouse（提供 J7\WpUtils 工具庫）
	$powerhouse_path = WP_PLUGIN_DIR . '/powerhouse/plugin.php';
	if ( file_exists( $powerhouse_path ) ) {
		require_once $powerhouse_path;
	} else {
		echo "警告：Powerhouse 不存在於 {$powerhouse_path}\n";
	}

	// 4. 載入 Power Partner plugin
	require dirname( __DIR__ ) . '/plugin.php';
}

tests_add_filter( 'muplugins_loaded', '_power_partner_integration_manually_load_plugin' );

/**
 * 在 plugins_loaded 後強制初始化 Bootstrap
 * 原因：PluginTrait::check_required_plugins() 會呼叫 is_j7rp_complete()
 * 但測試環境中 WooCommerce 和 Powerhouse 是透過 muplugins_loaded 手動載入
 * 並未在 active_plugins DB 選項中登記，導致 is_j7rp_complete() 回傳 false
 * 進而阻止 Bootstrap::instance() 被呼叫，LifeCycle hooks 未被註冊
 * 解決：在 plugins_loaded 之後直接呼叫 Bootstrap::instance()
 */
function _power_partner_integration_force_bootstrap(): void {
	if ( class_exists( 'J7\PowerPartner\Bootstrap' ) ) {
		\J7\PowerPartner\Bootstrap::instance();
	}
}

tests_add_filter( 'plugins_loaded', '_power_partner_integration_force_bootstrap', 20 );

// 啟動 WP 測試套件
require "{$_tests_dir}/includes/bootstrap.php";

/**
 * 在 WP 測試 bootstrap 完成後才載入測試基礎類別
 * 原因：Tests\Integration\TestCase 繼承自 WP_UnitTestCase
 * 而 WP_UnitTestCase 只有在 WP 測試 bootstrap 執行完畢（載入 testcase.php）後才可用
 * 若透過 PSR-4 autoloader 過早載入（在 bootstrap 之前），WP_UnitTestCase 尚未定義
 *
 * 解決方式：在 `require bootstrap.php` 之後立即 require TestCase.php
 * 此時 WP_UnitTestCase 已確定存在於記憶體中
 */
require_once dirname( __DIR__ ) . '/tests/Integration/TestCase.php';
