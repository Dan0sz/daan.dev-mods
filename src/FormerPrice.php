<?php
/**
 * @package Daan\Mods
 * @author  Daan van den Bergh
 * @url     https://daan.dev
 * @license MIT
 */

namespace Daan\Mods;

class FormerPrice {
	/**
	 * @var array $recurring_amounts Array containing amounts that belong to a recurring license.
	 */
	private $recurring_amounts;

	/**
	 * Stores the amount of signup discounts shown on current product page.
	 * @var int
	 */
	private $signup_discount = 0;

	/**
	 * Build class.
	 */
	public function __construct() {
		add_filter( 'edd_purchase_variable_prices', [ $this, 'save_recurring_license_amounts' ], 10, 2 );
		add_filter( 'edd_format_amount', [ $this, 'add_recurring_label_to_price' ], 10, 5 );
		add_filter( 'edd_download_price_after_html', [ $this, 'add_signup_discount_to_price' ], 10, 4 );
	}

	/**
	 * Save all amounts belonging to recurring licenses for later processing.
	 * @see self::add_recurring_label_to_price()
	 *
	 * @param array $prices
	 * @param int   $download_id
	 *
	 * @return array
	 */
	public function save_recurring_license_amounts( $prices, $download_id ) {
		foreach ( $prices as $key => $price ) {
			if ( ! isset( $price[ 'amount' ] ) || $price[ 'amount' ] <= 0 || ! isset( $price[ 'recurring' ] ) || $price[ 'recurring' ] == 'no' ) {
				continue;
			}

			$this->recurring_amounts[ $key ][ 'amount' ]          = $price[ 'amount' ];
			$this->recurring_amounts[ $key ][ 'period' ]          = $price[ 'period' ];
			$this->recurring_amounts[ $key ][ 'signup_discount' ] = $price[ 'signup_fee' ];
		}

		return $prices;
	}

	/**
	 * Modify price label to display
	 *
	 * @param mixed $formatted
	 * @param mixed $amount
	 * @param mixed $decimals
	 * @param mixed $decimal_sep
	 * @param mixed $thousands_sep
	 *
	 * @return mixed
	 */
	public function add_recurring_label_to_price( $formatted, $amount, $decimals, $decimal_sep, $thousands_sep ) {
		/**
		 * Do not run this in the admin area.
		 */
		if ( is_admin() ) {
			return $formatted;
		}

		/**
		 * Replace ',00' with ',-'.
		 */
		$formatted = str_replace( $decimal_sep . '00', $decimal_sep . '-', $formatted );

		if ( ! $this->recurring_amounts ) {
			return "$formatted";
		}

		$current_amount = [];

		foreach ( $this->recurring_amounts as $recurring_amount ) {
			if ( $amount == $recurring_amount[ 'amount' ] ) {
				$current_amount = $recurring_amount;

				break;
			}
		}

		if ( empty( $current_amount ) ) {
			return $formatted;
		}

		/**
		 * This isn't a discount, it's a fee. So, we're not going to show it up front.
		 * We're still going to add the renewal period though.
		 */
		if ( (float) $current_amount[ 'signup_discount' ] > 0 ) {
			return $formatted . '<small>/' . $current_amount[ 'period' ] . '*</small>';
		}

		$no_discount = (float) $current_amount[ 'signup_discount' ] == 0;

		if ( ! $no_discount ) {
			$this->signup_discount ++;

			$formatted = "<span class='edd-former-price'>$formatted</span> ";
			$amount    = (float) $amount + (float) $current_amount[ 'signup_discount' ];
			$formatted .= edd_currency_filter( number_format( $amount, $decimals, $decimal_sep, $thousands_sep ) );

			return str_replace( $decimal_sep . '00', $decimal_sep . '-', $formatted ) . '<small>/' . $current_amount[ 'period' ] . '*</small>';
		}

		return str_replace( $decimal_sep . '00', $decimal_sep . '-', $formatted ) . '<small>/' . $current_amount[ 'period' ] . '</small>';
	}

	public function add_signup_discount_to_price( $formatted_price, $download_id, $price, $price_id ) {
		return $formatted_price;
	}
}
