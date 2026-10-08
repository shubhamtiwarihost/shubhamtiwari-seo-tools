<?php
/**
 * Minimal WooCommerce declarations for static analysis of src/WooCommerce.
 *
 * Only what DumpSEO calls; signatures follow WooCommerce's own docblocks.
 * Read by PHPStan (scanFiles), never executed. Extend when the integration
 * uses more of WooCommerce, or switch to php-stubs/woocommerce-stubs.
 *
 * @package DumpSEO
 */

// phpcs:disable -- Declarations mirroring a third-party API; not DumpSEO code.

/**
 * @param string $page cart, checkout, myaccount, shop, terms.
 */
function wc_get_page_id( $page ): int {}

/**
 * @param mixed $the_product Post object, ID or false.
 * @return WC_Product|null|false
 */
function wc_get_product( $the_product = false ) {}

/**
 * @param string|float $number     Number.
 * @param int|false    $dp         Decimal places.
 * @param bool         $trim_zeros Trim zeros.
 */
function wc_format_decimal( $number, $dp = false, $trim_zeros = false ): string {}

function wc_get_price_decimals(): int {}

function get_woocommerce_currency(): string {}

class WooCommerce {}

class WC_Product {
	/**
	 * @param string $context view or edit.
	 * @return string
	 */
	public function get_price( $context = 'view' ) {}

	/**
	 * @param string $context view or edit.
	 * @return string instock, outofstock or onbackorder.
	 */
	public function get_stock_status( $context = 'view' ) {}
}
