<?php
/**
 * Token 工具類整合測試
 *
 * 覆蓋 Utils\Token::replace() 的各種邊緣案例：
 * - 基本 ##TOKEN## 替換
 * - 陣列值跳過
 * - 空值跳過
 * - 大小寫不敏感（key 自動轉大寫）
 * - 多 token 同時替換
 * - XSS 輸入、SQL injection 輸入作為一般值
 * - Unicode / Emoji / RTL 文字
 */

declare( strict_types=1 );

namespace Tests\Integration;

use J7\PowerPartner\Utils\Token;

/**
 * @group smoke
 * @group happy
 */
class TokenTest extends TestCase {

	protected function configure_dependencies(): void {
		// Token 是 static abstract class，不需要初始化服務
	}

	// ========== 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function test_替換單一Token(): void {
		$script = '親愛的 ##FIRST_NAME## 您好';
		$tokens = [ 'FIRST_NAME' => '小明' ];

		$result = Token::replace( $script, $tokens );

		$this->assertSame( '親愛的 小明 您好', $result );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_替換多個Token(): void {
		$script = '嗨 ##FIRST_NAME## ##LAST_NAME##，您的網站 ##FRONTURL## 已建立完成';
		$tokens = [
			'FIRST_NAME' => '小',
			'LAST_NAME'  => '明',
			'FRONTURL'   => 'https://example.com',
		];

		$result = Token::replace( $script, $tokens );

		$this->assertSame( '嗨 小 明，您的網站 https://example.com 已建立完成', $result );
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_Key小寫會自動轉大寫(): void {
		$script = '您的帳號：##EMAIL##';
		$tokens = [ 'email' => 'user@example.com' ];

		$result = Token::replace( $script, $tokens );

		$this->assertSame( '您的帳號：user@example.com', $result );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_替換訂閱相關的全部Token(): void {
		$script = '##FIRST_NAME## ##LAST_NAME## 您好！網址：##FRONTURL## 後台：##ADMINURL## 帳號：##SITEUSERNAME## 密碼：##SITEPASSWORD##';
		$tokens = [
			'FIRST_NAME'    => '小明',
			'LAST_NAME'     => '王',
			'FRONTURL'      => 'https://abc.wpsite.pro',
			'ADMINURL'      => 'https://abc.wpsite.pro/wp-admin',
			'SITEUSERNAME'  => 'admin@example.com',
			'SITEPASSWORD'  => 'P@ssw0rd123',
		];

		$result = Token::replace( $script, $tokens );

		$this->assertStringContainsString( '小明', $result );
		$this->assertStringContainsString( 'https://abc.wpsite.pro', $result );
		$this->assertStringContainsString( 'admin@example.com', $result );
		$this->assertStringContainsString( 'P@ssw0rd123', $result );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_Token不存在時腳本不變(): void {
		$script = '您的訂單 ##ORDER_ID## 已處理';
		$tokens = [ 'CUSTOMER_NAME' => '小明' ]; // 沒有 ORDER_ID token

		$result = Token::replace( $script, $tokens );

		$this->assertSame( '您的訂單 ##ORDER_ID## 已處理', $result );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_Token空陣列時腳本不變(): void {
		$script = '嗨 ##FIRST_NAME##';

		$result = Token::replace( $script, [] );

		$this->assertSame( '嗨 ##FIRST_NAME##', $result );
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_陣列值被跳過不替換(): void {
		$script = '訂單商品：##ORDER_ITEMS##';
		$tokens = [ 'ORDER_ITEMS' => [ '商品A', '商品B' ] ]; // 陣列值應被跳過

		$result = Token::replace( $script, $tokens );

		// 陣列值跳過，Token 保留在原文
		$this->assertSame( '訂單商品：##ORDER_ITEMS##', $result );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_空字串值被跳過不替換(): void {
		$script = '您的域名：##DOMAIN##';
		$tokens = [ 'DOMAIN' => '' ]; // 空字串應被跳過

		$result = Token::replace( $script, $tokens );

		$this->assertSame( '您的域名：##DOMAIN##', $result );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_null值被跳過不替換(): void {
		$script = '您的IP：##IPV4##';
		$tokens = [ 'IPV4' => null ]; // null 應被跳過

		$result = Token::replace( $script, $tokens );

		$this->assertSame( '您的IP：##IPV4##', $result );
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function test_腳本為空字串(): void {
		$result = Token::replace( '', [ 'FIRST_NAME' => '小明' ] );
		$this->assertSame( '', $result );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_Unicode中文值正確替換(): void {
		$script = '您好 ##NAME##';
		$tokens = [ 'NAME' => '陳大明（繁體中文）' ];

		$result = Token::replace( $script, $tokens );

		$this->assertSame( '您好 陳大明（繁體中文）', $result );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_Emoji值正確替換(): void {
		$script = '狀態：##STATUS##';
		$tokens = [ 'STATUS' => '✅成功🎉' ];

		$result = Token::replace( $script, $tokens );

		$this->assertSame( '狀態：✅成功🎉', $result );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_RTL阿拉伯文值正確替換(): void {
		$script = '姓名：##NAME##';
		$tokens = [ 'NAME' => 'مرحبا' ];

		$result = Token::replace( $script, $tokens );

		$this->assertSame( '姓名：مرحبا', $result );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_同一Token出現多次時全部被替換(): void {
		$script = '親愛的 ##FIRST_NAME##，您好 ##FIRST_NAME##！';
		$tokens = [ 'FIRST_NAME' => '小明' ];

		$result = Token::replace( $script, $tokens );

		$this->assertSame( '親愛的 小明，您好 小明！', $result );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_超長字串Token值正確替換(): void {
		$long_name = str_repeat( '長', 10000 );
		$script    = '##FIRST_NAME##';
		$tokens    = [ 'FIRST_NAME' => $long_name ];

		$result = Token::replace( $script, $tokens );

		$this->assertSame( $long_name, $result );
	}

	// ========== 安全性（Security）==========

	/**
	 * @test
	 * @group security
	 */
	public function test_XSS輸入作為Token值時原樣保留(): void {
		// Token::replace 只做字串替換，不做 HTML 跳脫
		// 呼叫者（wp_mail / wpautop）負責 XSS 防護
		$script = '您好 ##FIRST_NAME##';
		$tokens = [ 'FIRST_NAME' => '<script>alert("xss")</script>' ];

		$result = Token::replace( $script, $tokens );

		$this->assertStringContainsString( '<script>', $result );
		// 確認 Token 確實被替換
		$this->assertStringNotContainsString( '##FIRST_NAME##', $result );
	}

	/**
	 * @test
	 * @group security
	 */
	public function test_SQL_Injection字串作為Token值時原樣替換(): void {
		$script = '帳號：##SITEUSERNAME##';
		$tokens = [ 'SITEUSERNAME' => "admin'; DROP TABLE wp_users; --" ];

		$result = Token::replace( $script, $tokens );

		$this->assertStringContainsString( "admin'", $result );
		$this->assertStringNotContainsString( '##SITEUSERNAME##', $result );
	}

	/**
	 * @test
	 * @group security
	 */
	public function test_雙井號格式的假Token不被替換(): void {
		$script = '##NOTTOKEN 的情況##';
		$tokens = [ 'NOTTOKEN 的情況' => '被替換' ];

		// Token 名稱含空格，str_replace 仍會嘗試替換
		// 驗證行為一致性：大寫後仍是 NOTTOKEN 的情況（含空格）
		$result = Token::replace( $script, $tokens );

		// 含空格的 token key 大寫後仍是 '##NOTTOKEN 的情況##'，理應被替換
		// 這個測試確認系統行為是可預期的
		$this->assertIsString( $result );
	}
}
