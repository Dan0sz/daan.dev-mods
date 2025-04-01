<?php
/**
 * @package Daan\Mods
 * @author  Daan van den Bergh
 * @url     https://daan.dev
 * @license MIT
 */

namespace Daan\Mods;

class Plugin {
	public function __construct() {
		$this->init();
	}

	private function init() {
		// Core
		add_filter( 'login_url', [ $this, 'change_login_url' ] );

		// EDD Software Licensing
		add_filter( 'edd_sl_url_subdomains', [ $this, 'add_local_urls' ] );
		add_filter( 'edd_file_download_has_access', [ $this, 'maybe_allow_download' ], 10, 3 );

		// EDD EU VAT
		add_filter( 'edd_eu_vat_uk_hide_checkout_input', '__return_true' );
		add_filter( 'edd_vat_current_eu_vat_rates', [ $this, 'change_gb_to_zero_vat' ] );

		// Syntax Highlighter
		add_filter( 'plugins_url', [ $this, 'modify_css_url' ], 1000, 3 );

		// Easy Digital Downloads
		new FormerPrice(); // Product Details Widget
	}

	/**
	 * @return string
	 */
	public function change_login_url() {
		return home_url( 'account' );
	}

	/**
	 * Modify the list of subdomains to mark as local/staging.
	 *
	 * @param mixed $subdomains
	 *
	 * @return string[]
	 */
	public function add_local_urls( $subdomains ) {
		return array_merge(
			[
				'test.*',
				'dev.*',
				'*.servebolt.cloud',
				'*.kinsta.cloud',
				'*.cloudwaysapps.com',
				'*.wpengine.com',
				'*.e.wpstage.net',
			],
			$subdomains
		);
	}

	/**
	 * Custom function to allow download, because for some reason ours keep failing since EDD 3.0.
	 * Checks the payment status and if the token is valid. Nothing else, which is probably enough in our case.
	 * @return bool
	 */
	public function maybe_allow_download( $has_access, $payment_id, $args ) {
		$payment = edd_get_payment( $payment_id );

		if ( ! $payment ) {
			return $has_access;
		}

		$status               = $payment->status;
		$deliverable_statuses = edd_get_deliverable_order_item_statuses();

		if ( ! in_array( $status, $deliverable_statuses ) ) {
			return $has_access;
		}

		$parts = parse_url( add_query_arg( [] ) );
		wp_parse_str( $parts[ 'query' ], $query_args );
		$url = add_query_arg( $query_args, site_url() );

		$valid_token = edd_validate_url_token( $url );

		return $valid_token;
	}

	/**
	 * @param array $countries
	 *
	 * @return array
	 */
	public function change_gb_to_zero_vat( $countries ) {
		$countries[ 'GB' ] = 0;

		return $countries;
	}

	/**
	 * Make Syntax Highlighing Code Block use Github Dark Dimmed theme.
	 *
	 * Requires WP Help Scout Docs to be installed. @url https://daan.dev/wordpress/wp-help-scout-docs
	 *
	 * @param mixed $url
	 * @param mixed $filename
	 * @param mixed $plugin_file_path
	 *
	 * @return mixed
	 */
	public function modify_css_url( $url, $filename, $plugin_file_path ) {
		if ( ! defined( 'WP_HELP_SCOUT_DOCS_PLUGIN_FILE' ) ) {
			return $url;
		}

		if ( ! str_contains( $plugin_file_path, 'syntax-highlighting-code-block' ) ) {
			return $url;
		}

		if ( ! str_contains( $filename, 'scrivo' ) ) {
			return $url;
		}

		return plugins_url( 'assets/css/github-dark-dimmed.min.css', WP_HELP_SCOUT_DOCS_PLUGIN_FILE );
	}
}
