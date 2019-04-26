<?php
/**
 * Booster for WooCommerce - Module - Product Price by Formula
 *
 * @version 4.2.0
 * @since   2.5.0
 * @author  Algoritmika Ltd.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WCJ_Product_Price_by_Formula' ) ) :

class WCJ_Product_Price_by_Formula extends WCJ_Module {

	/**
	 * Constructor.
	 *
	 * @version 4.1.0
	 * @since   2.5.0
	 * @todo    use WC math library instead of `PHPMathParser`
	 */
	function __construct() {

		$this->id         = 'product_price_by_formula';
		$this->short_desc = __( 'Product Price by Formula', 'woocommerce-jetpack' );
		$this->desc       = __( 'Set formula for automatic product price calculation.', 'woocommerce-jetpack' );
		$this->link_slug  = 'woocommerce-product-price-formula';
		parent::__construct();

		if ( $this->is_enabled() ) {
			require_once( wcj_plugin_path() . '/includes/lib/PHPMathParser/Math.php' );

			add_action( 'add_meta_boxes',    array( $this, 'add_meta_box' ) );
			add_action( 'save_post_product', array( $this, 'save_meta_box' ), PHP_INT_MAX, 2 );

			if (
				( wcj_is_frontend() && "yes" === get_option( 'wcj_product_price_by_formula_admin_scope', 'yes' ) ) ||
				( "no" === get_option( 'wcj_product_price_by_formula_admin_scope', 'yes' ) && ( wcj_is_frontend() || is_admin() ) ) ||
				isset( $_GET['wcj_create_products_xml'] )
			) {
				wcj_add_change_price_hooks( $this, wcj_get_module_price_hooks_priority( 'product_price_by_formula' ), false );
			}

			add_filter( 'wcj_save_meta_box_value', array( $this, 'save_meta_box_value' ), PHP_INT_MAX, 3 );
			add_action( 'admin_notices',           array( $this, 'admin_notices' ) );

			$this->rounding           = get_option( 'wcj_product_price_by_formula_rounding', 'no_rounding' );
			$this->rounding_precision = get_option( 'wcj_product_price_by_formula_rounding_precision', 0 );
		}
	}

	/**
	 * change_price_grouped.
	 *
	 * @version 2.7.0
	 * @since   2.5.0
	 */
	function change_price_grouped( $price, $qty, $_product ) {
		if ( $_product->is_type( 'grouped' ) ) {
			foreach ( $_product->get_children() as $child_id ) {
				$the_price = get_post_meta( $child_id, '_price', true );
				$the_product = wc_get_product( $child_id );
				$the_price = wcj_get_product_display_price( $the_product, $the_price, 1 );
				if ( $the_price == $price ) {
					return $this->change_price( $price, $the_product );
				}
			}
		}
		return $price;
	}

	/**
	 * Adds product id param on shortcodes.
	 *
	 * @version 4.1.0
	 * @since   4.1.0
	 *
	 * @param $the_param
	 * @param $_product
	 *
	 * @return string
	 */
	function add_product_id_param( $the_param, $_product ) {
		if (
			preg_match( '/^\[.*\]$/', $the_param ) &&
			! preg_match( '/product_id=[\'"]?\d+[\'"]?/', $the_param )
		) {
			$product_id = $_product->get_id();
			$the_param  = preg_replace( '/\[[^\]]*/', "$0 product_id='{$product_id}'", $the_param );
		}
		return $the_param;
	}

	/**
	 * change_price.
	 *
	 * @version 4.1.0
	 * @since   2.5.0
	 */
	function change_price( $price, $_product, $output_errors = false ) {
		if ( $this->is_price_by_formula_product( $_product ) && '' != $price ) {
			$_product_id = wcj_get_product_id_or_variation_parent_id( $_product );
			$is_per_product = ( 'per_product' === get_post_meta( $_product_id, '_' . 'wcj_product_price_by_formula_calculation', true ) );
			$the_formula = ( $is_per_product )
				? get_post_meta( $_product_id, '_' . 'wcj_product_price_by_formula_eval', true )
				: get_option( 'wcj_product_price_by_formula_eval', '' );
			$the_formula = do_shortcode( $the_formula );
			if ( '' != $the_formula ) {
				$total_params = ( $is_per_product )
					? get_post_meta( $_product_id, '_' . 'wcj_product_price_by_formula_total_params', true )
					: get_option( 'wcj_product_price_by_formula_total_params', 1 );
				if ( $total_params > 0 ) {
					$the_current_filter = current_filter();
					if ( 'woocommerce_get_price_including_tax' == $the_current_filter || 'woocommerce_get_price_excluding_tax' == $the_current_filter ) {
						return wcj_get_product_display_price( $_product );
					}
					$math = new WCJ_Math();
					$math->registerVariable( 'x', $price );
					for ( $i = 1; $i <= $total_params; $i++ ) {
						$the_param = ( $is_per_product )
							? get_post_meta( $_product_id, '_' . 'wcj_product_price_by_formula_param_' . $i, true )
							: get_option( 'wcj_product_price_by_formula_param_' . $i, '' );
						$the_param = $this->add_product_id_param( $the_param, $_product );
						$the_param = do_shortcode( $the_param );
						if ( '' != $the_param ) {
							$math->registerVariable( 'p' . $i, $the_param );
						}
					}
					$the_formula = str_replace( 'x', '$x', $the_formula );
					$the_formula = str_replace( 'p', '$p', $the_formula );
					try {
						$price = $math->evaluate( $the_formula );
					} catch ( Exception $e ) {
						if ( $output_errors ) {
							echo '<p style="color:red;">' . __( 'Error in formula', 'woocommerce-jetpack' ) . ': ' . $e->getMessage() . '</p>';
						}
					}
					if ( 'no_rounding' != $this->rounding ) {
						$price = wcj_round( $price, $this->rounding_precision, $this->rounding );
					}
				}
			}
		}
		return $price;
	}

	/**
	 * get_variation_prices_hash.
	 *
	 * @version 3.6.0
	 * @since   2.5.0
	 */
	function get_variation_prices_hash( $price_hash, $_product, $display ) {
		if ( $this->is_price_by_formula_product( $_product ) ) {
			$the_formula = get_option( 'wcj_product_price_by_formula_eval', '' );
			$total_params = get_post_meta( wcj_get_product_id_or_variation_parent_id( $_product ), '_' . 'wcj_product_price_by_formula_total_params', true );
			$the_params = array();
			for ( $i = 1; $i <= $total_params; $i++ ) {
				$the_params[] = get_option( 'wcj_product_price_by_formula_param_' . $i, '' );
			}
			$price_hash['wcj_price_by_formula'] = array(
				'formula'            => $the_formula,
				'total_params'       => $total_params,
				'params'             => $the_params,
				'rounding'           => $this->rounding,
				'rounding_precision' => $this->rounding_precision,
			);
		}
		return $price_hash;
	}

	/**
	 * save_meta_box_value.
	 *
	 * @version 2.5.0
	 * @since   2.5.0
	 */
	function save_meta_box_value( $option_value, $option_name, $module_id ) {
		if ( true === apply_filters( 'booster_option', false, true ) ) {
			return $option_value;
		}
		if ( 'no' === $option_value ) {
			return $option_value;
		}
		if ( $this->id === $module_id && 'wcj_product_price_by_formula_enabled' === $option_name ) {
			$args = array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_' . 'wcj_product_price_by_formula_enabled',
				'meta_value'     => 'yes',
				'post__not_in'   => array( get_the_ID() ),
			);
			$loop = new WP_Query( $args );
			$c = $loop->found_posts + 1;
			if ( $c >= 2 ) {
				add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
				return 'no';
			}
		}
		return $option_value;
	}

	/**
	 * add_notice_query_var.
	 *
	 * @version 2.5.0
	 * @since   2.5.0
	 */
	function add_notice_query_var( $location ) {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
		return add_query_arg( array( 'wcj_product_price_by_formula_admin_notice' => true ), $location );
	}

	/**
	 * admin_notices.
	 *
	 * @version 2.5.0
	 * @since   2.5.0
	 */
	function admin_notices() {
		if ( ! isset( $_GET['wcj_product_price_by_formula_admin_notice'] ) ) {
			return;
		}
		?><div class="error"><p><?php
			echo '<div class="message">'
				. __( 'Booster: Free plugin\'s version is limited to only one price by formula product enabled at a time. You will need to get <a href="https://booster.io/plus/" target="_blank">Booster Plus</a> to add unlimited number of price by formula products.', 'woocommerce-jetpack' )
				. '</div>';
		?></p></div><?php
	}

	/**
	 * is_price_by_formula_product.
	 *
	 * @version 2.7.0
	 * @since   2.5.0
	 */
	function is_price_by_formula_product( $_product ) {
		return (
			'yes' === apply_filters( 'booster_option', 'no', get_option( 'wcj_product_price_by_formula_enable_for_all_products', 'no' ) ) ||
			'yes' === get_post_meta( wcj_get_product_id_or_variation_parent_id( $_product ), '_' . 'wcj_product_price_by_formula_enabled', true )
		);
	}

	/**
	 * create_meta_box.
	 *
	 * @version 4.2.0
	 * @since   2.5.0
	 */
	function create_meta_box() {

		parent::create_meta_box();

		$the_product = wc_get_product();
		if ( $this->is_price_by_formula_product( $the_product ) ) {
			$the_price   = $the_product->get_price();
			if ( "yes" === get_option( 'wcj_product_price_by_formula_admin_scope', 'yes' ) ) {
				$the_price = $this->change_price( $the_price, $the_product, true );
			}
			echo '<h4>' . __( 'Final Price Preview', 'woocommerce-jetpack' ) . '</h4>';
			echo wc_price( $the_price );
		}
	}

}

endif;

return new WCJ_Product_Price_by_Formula();
