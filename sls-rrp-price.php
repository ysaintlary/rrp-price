<?php
/**
 * Plugin Name:       SLS | RRP Price
 * Plugin URI:        https://github.com/ysaintlary/sls-rrp-price
 * Description:       Ajoute un champ « RRP » (prix de vente conseillé) aux fiches produits WooCommerce, avec import/export CSV.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Yves Saint-Lary
 * Author URI:        https://ysaintlary.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       sls-rrp-price
 * Domain Path:       /languages
 *
 * WC requires at least: 8.0
 * WC tested up to:      9.8
 * Requires Plugins:     woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SLS_RRP_VERSION', '1.0.0' );

require_once __DIR__ . '/lib/wp-plugin-base/wp-plugin-base-runtime-updater.php';

/* ─── Compatibilité HPOS ─── */

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
	}
} );

/* ─── Back-office : champ RRP dans l'onglet Général ─── */

add_action( 'woocommerce_product_options_pricing', function () {
	woocommerce_wp_text_input( [
		'id'                => '_rrp_price',
		'label'             => __( 'RRP', 'sls-rrp-price' ) . ' (' . get_woocommerce_currency_symbol() . ')',
		'description'       => __( 'Prix de vente conseillé (Recommended Retail Price)', 'sls-rrp-price' ),
		'desc_tip'          => true,
		'type'              => 'text',
		'data_type'         => 'price',
		'custom_attributes' => [ 'step' => 'any', 'min' => '0' ],
	] );
} );

add_action( 'woocommerce_process_product_meta', function ( $post_id ) {
	if ( isset( $_POST['_rrp_price'] ) ) {
		update_post_meta(
			$post_id,
			'_rrp_price',
			wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_rrp_price'] ) ) )
		);
	}
} );

/* ─── Front-end : affiche le RRP sous le prix ─── */

add_filter( 'woocommerce_get_price_html', function ( $price_html, $product ) {
	$rrp = $product->get_meta( '_rrp_price' );
	if ( '' !== $rrp && false !== $rrp ) {
		$rrp_formatted = wc_price( $rrp );
		$price_html   .= '<p class="sls-rrp-price"><small>'
			. sprintf(
				/* translators: %s: formatted RRP price */
				__( 'RRP : %s', 'sls-rrp-price' ),
				$rrp_formatted
			)
			. '</small></p>';
	}
	return $price_html;
}, 10, 2 );

/* ─── CSV Export : colonne RRP ─── */

add_filter( 'woocommerce_product_export_column_names', 'sls_rrp_export_column' );
add_filter( 'woocommerce_product_export_product_default_columns', 'sls_rrp_export_column' );

/**
 * Register the RRP column for CSV export.
 *
 * @param array<string,string> $columns Export columns.
 * @return array<string,string>
 */
function sls_rrp_export_column( array $columns ): array {
	$columns['rrp_price'] = __( 'RRP', 'sls-rrp-price' );
	return $columns;
}

add_filter( 'woocommerce_product_export_product_column_rrp_price', function ( $value, $product ) {
	return $product->get_meta( '_rrp_price' );
}, 10, 2 );

/* ─── CSV Import : mapper la colonne RRP ─── */

add_filter( 'woocommerce_csv_product_import_mapping_options', function ( array $options ): array {
	$options['rrp_price'] = __( 'RRP', 'sls-rrp-price' );
	return $options;
} );

add_filter( 'woocommerce_csv_product_import_mapping_default_columns', function ( array $columns ): array {
	$columns[ __( 'RRP', 'sls-rrp-price' ) ] = 'rrp_price';
	$columns['RRP'] = 'rrp_price';
	return $columns;
} );

add_filter( 'woocommerce_product_importer_parsed_data', function ( array $parsed_data ): array {
	if ( isset( $parsed_data['rrp_price'] ) ) {
		$parsed_data['meta_data'][] = [
			'key'   => '_rrp_price',
			'value' => wc_format_decimal( $parsed_data['rrp_price'] ),
		];
		unset( $parsed_data['rrp_price'] );
	}
	return $parsed_data;
} );
