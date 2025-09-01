=== WooCommerce Function Suite ===
Contributors: zito
Donate link: https://example.com/
Tags: woocommerce, plugin, order management, discord, shipping, checkout
Requires at least: 5.6
Tested up to: 6.5.3
Requires PHP: 7.4
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

一個集中管理多項 WooCommerce 自訂功能的整合型插件，支援後台開關模組，未來可彈性擴充功能。

== Description ==

**WooCommerce Function Suite** 是一個讓 WooCommerce 商店管理者可統一控制各項自訂功能的整合型插件，適合使用 WPCode 或自訂 PHP 片段的使用者進一步模組化管理功能。

目前支援以下功能模組（未來可擴充）：

- 重量控制（可設定最大重量與啟用開關）
- 最小訂購金額（待開發）
- 運費控制
- 結帳優化功能（待開發）
- Discord 通知（可設定 Webhook 與啟用開關）
- 後台訂單欄位新增（待開發）
- 拋單功能（將整合 PhpSpreadsheet）

== Installation ==

1. 將插件資料夾上傳至 `/wp-content/plugins/woocommerce-function-suite/`
2. 或在 WordPress 後台 → 插件 → 安裝 → 上傳 `.zip` 檔安裝
3. 啟用插件
4. 進入「Woo功能整合」後台選單設定各項功能開關

== Screenshots ==

1. 主設定頁可控制各模組開關
2. 子頁：重量控制設定
3. 子頁：Discord 通知設定

== Frequently Asked Questions ==

= 啟用後功能沒反應？ =
請確認是否在各子頁啟用了對應功能模組。

= 是否支援多語系？ =
目前暫無語言檔，後續將提供 `.pot` 檔以利翻譯。

== Changelog ==

= 1.8.0 =
* 優化：新增結帳欄位優化功能。

= 1.7.0 =
* 優化：新增傳送訂單資訊至官方 LINE。

= 1.6.0 =
* 優化：新增用戶最小訂購金額模組。

= 1.5.6 =
* 修正：調整進度調啟用勾選框位置
* 修正：調整提示圖示位置

= 1.5.5 =
* 修正：修復未啟用的運送方式依然會出現在進度條下方的錯誤。

= 1.5.4 =
* 修正：修正部分說明文字。

= 1.5.3 =
* 修正：修正部分說明文字。

= 1.5.2 =
* 修正：修正部分說明文字。

= 1.5.1 =
* 優化：在訂單重量進度條上方新增運送類別。
* 優化：修正進度條顏色。

= 1.5.0 =
* 優化：在商城目錄、商品頁面、購物車頁面顯示目前訂單重量進度條。

= 1.4.0 =
* 優化：重量控制模組新增「加入購物車」與「更新購物車」的前端驗證，提升使用者體驗。
* 優化：重量控制模組後台設定頁面排版與 UX。

= 1.3.1 =
* 修正：修正部分說明文字。

= 1.3.0 =
* 新增：可透過 Discord Webhook 來接收網站通知（新訂單、商品低庫存）。

= 1.2.0 =
* 新增：後台訂單欄位新增模組，可顯示顧客備註、商家備註、重覆 IP 訂單與 LINE 名稱。

= 1.1.0 =
* 新增：運費控制模組，可排除特價與精選商品計算免運門檻。
* 修正：修正後台提示框 (Tooltip) 因缺少 DOMPurify 依賴而無法顯示的問題。

= 1.0.1 =
* 修正後台設定內容
* 新增提示圖標

= 1.0.0 =
* 建立插件架構
* 加入主設定頁與兩個子功能模組設定頁（重量控制、Discord通知）

== License ==

GPLv2 or later - Free to use, modify and distribute.