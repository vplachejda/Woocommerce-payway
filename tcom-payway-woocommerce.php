<?php
/*
 * Date: January 2018
 * Plugin Name: WooCommerce T-Com PayWay
 * Plugin URI: https://github.com/marinsagovac/woocommerce-tcom-payway
 * Description: T-Com PayWay payment gateway
 * version: 1.3
 * Author: Marin Sagovac (Marin Šagovac)
 * Developers: Marin Sagovac (Marin Šagovac), Matija Kovacevic (Matija Kovačević), Danijel Gubic (Danijel Gubić), Ivan Švaljek, Alen Širola (Micemade)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

load_plugin_textdomain( 'tcom-payway-wc', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action( 'plugins_loaded', 'woocommerce_tpayway_gateway', 0 );

function woocommerce_tpayway_gateway() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class WC_TPAYWAY extends WC_Payment_Gateway {

		public function __construct() {

			$plugin_dir         = plugin_dir_url( __FILE__ );
			$this->id           = 'WC_TPAYWAY';
			$this->icon         = apply_filters( 'woocommerce_payway_icon', '' . $plugin_dir . 'payway.png' );
			$this->method_title = 'T-Com PayWay';
			$this->has_fields   = false;

			$this->init_form_fields();
			$this->init_settings();

			$this->title               = $this->settings['title'];
			$this->description         = $this->settings['description'];
			$this->shop_id             = $this->settings['mer_id'];
			$this->acq_id              = $this->settings['acq_id'];
			$this->pg_domain           = $this->settings['pg_domain'];
			$this->response_url_sucess = $this->settings['response_url_sucess'];
			$this->response_url_fail   = $this->settings['response_url_fail'];
			$this->checkout_msg        = $this->settings['checkout_msg'];
			$this->woo_active          = $this->settings['woo_active'];

			$this->msg['message'] = '';
			$this->msg['class']   = '';

			add_action( 'init', array( &$this, 'check_tcompayway_respose' ) );

			if ( version_compare( WOOCOMMERCE_version, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}
			add_action( 'woocommerce_receipt_WC_TPAYWAY', array( &$this, 'receipt_page' ) );
		}

		function init_form_fields() {

			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

			if ( is_plugin_active( 'woo-multi-currency/woo-multi-currency.php' ) ) {
				$woo_active = array(
					'title'   => __( 'Use Woo Multi Currency conversion', 'tcom-payway-wc' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable currency auto conversion for <a href="https://wordpress.org/plugins/woo-multi-currency/">Woo Multi Currency</a> plugin. Make sure that is checked "Allow Multi Currency" in plugin page.', 'tcom-payway-wc' ),
					'default' => 'no',
				);
			} else {
				$woo_active = array(
					'title'    => __( 'Use Woo Multi Currency conversion', 'tcom-payway-wc' ),
					'type'     => 'checkbox',
					'disabled' => true,
					'label'    => __( 'Enable currency auto conversion for <a href="https://wordpress.org/plugins/woo-multi-currency/">Woo Multi Currency</a> plugin. Make sure that is checked "Allow Multi Currency" in plugin page.', 'tcom-payway-wc' ),
					'default'  => 'no',
				);
			};

			$this->form_fields = array(
				'enabled'             => array(
					'title'   => __( 'Enable/Disable', 'tcom-payway-wc' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable T-Com PayWay Module.', 'tcom-payway-wc' ),
					'default' => 'no',
				),
				'title'               => array(
					'title'       => __( 'Title:', 'tcom-payway-wc' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'tcom-payway-wc' ),
					'default'     => __( 'T-Com PayWay', 'tcom-payway-wc' ),
				),
				'description'         => array(
					'title'       => __( 'Description:', 'tcom-payway-wc' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'tcom-payway-wc' ),
					'default'     => __( 'T-Com Payway is secure payment gateway in Croatia and you can pay using this payment in other currency.', 'tcom-payway-wc' ),
				),
				'pg_domain'           => array(
					'title'       => __( 'Authorize URL:', 'tcom-payway-wc' ),
					'type'        => 'text',
					'description' => __( 'T-Com PayWay data submiting to this URL', 'tcom-payway-wc' ),
					'default'     => __( 'https://pgw.ht.hr/services/payment/api/authorize-form', 'tcom-payway-wc' ),
				),
				'mer_id'               => array(
					'title'       => __( 'Shop ID:', 'tcom-payway-wc' ),
					'type'        => 'text',
					'description' => __( 'Unique id for the merchant acc, given by bank.', 'tcom-payway-wc' ),
					'default'     => '',
				),
				'acq_id'              => array(
					'title'       => __( 'Secret Key:', 'tcom-payway-wc' ),
					'type'        => 'text',
					'description' => '',
					'default'     => '',
				),
				'mer_id' => array(
					'title'       => __( 'Response URL success', 'tcom-payway-wc' ),
					'type'        => 'text',
					'description' => '',
					'default'     => '',
				),
				'response_url_fail'   => array(
					'title'       => __( 'Response URL fail:', 'tcom-payway-wc' ),
					'type'        => 'text',
					'description' => '',
					'default'     => '',
				),
				'checkout_msg'        => array(
					'title'       => __( 'Message redirect:', 'tcom-payway-wc' ),
					'type'        => 'textarea',
					'description' => __( 'Message to client when redirecting to PayWay page', 'tcom-payway-wc' ),
					'default'     => '',
				),
				'woo_active'          => $woo_active,
			);
		}

		public function admin_options() {
			echo '<h3>' . __( 'T-Com PayWay payment gateway', 'tcom-payway-wc' ) . '</h3>';
			echo '<p>' . __( '<a target="_blank" href="http://pgw.t-com.hr/">T-Com PayWay</a> is payment gateway from telecom T-Com who provides payment gateway services as dedicated services to clients in Croatia.', 'tcom-payway-wc' ) . '</p>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

		function payment_fields() {
			if ( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		}

		function receipt_page( $order ) {
			global $woocommerce;

			$order_details = new WC_Order( $order );

			echo $this->generate_ipg_form( $order );
			echo '<br>' . $this->checkout_msg . '</b>';
		}

		public function generate_ipg_form( $order_id ) {

			global $wpdb;
			global $woocommerce;

			$order       = new WC_Order( $order_id );
			$productinfo = "Order $order_id";

			$curr_symbole = get_woocommerce_currency();

			$table_name = $wpdb->prefix . 'tpayway_ipg';
			$check_oder = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE transaction_id = '" . $order_id . "'" );
			if ( $check_oder > 0 ) {
				$wpdb->update(
					$table_name,
					array(
						'response_code'      => '',
						'response_code_desc' => '',
						'reason_code'        => '',
						'amount'             => ( $order->order_total ),
						'or_date'            => date( 'Y-m-d' ),
						'status'             => '',
					),
					array( 'transaction_id' => $order_id )
				);
			} else {
				$wpdb->insert(
					$table_name,
					array(
						'transaction_id'     => $order_id,
						'response_code'      => '',
						'response_code_desc' => '',
						'reason_code'        => '',
						'amount'             => $order->order_total,
						'or_date'            => date( 'Y-m-d' ),
						'status'             => '',
					),
					array( '%s', '%d' )
				);
			}

			switch ( $woocommerce->customer->country ) {
				case 'HR':
				case 'BA':
				case 'RS':
					$pgw_language = 'hr';
					break;
				case 'DE':
					$pgw_language = 'de';
					break;
				case 'IT':
					$pgw_language = 'it';
					break;
				case 'FR':
					$pgw_language = 'fr';
					break;
				case 'RU':
					$pgw_language = 'ru';
					break;
				default:
					$pgw_language = 'en';
			}

			$order->order_total = $order->order_total;
			if ( 'HR' === $woocommerce->customer->country ) {
				if ( 'HRK' === $order->get_order_currency() ) {
					$order->order_total = $order->order_total;
				}
			} else {
				$order->order_total = $order->order_total;
			}

			$curr_symbole = get_woocommerce_currency();
			$hrk_rate     = apply_filters( 'jal_pw_currency_rate', false ); // filter can be used to input Currency rate for other currencies to HRK
			$convert      = 'HRK' !== $curr_symbole;

			if ( false === $convert && $hrk_rate ) {
				$wcml_settings = get_option( '_wcml_settings' ); // WooCommerce Multilingual - Multi Currency (WPML plugin)

				if ( $wcml_settings ) {
					$curr_rates = $wcml_settings['currency_options'];

					$hrk_rate = $curr_rates[ $curr_symbole ]['rate'];
				} elseif ( 'yes' === $this->woo_active ) {
					if ( isset( $_COOKIE['wmc_current_currency'] ) ) { // WooCommerce Multi Currency plugin
						$selected_c = get_option( 'wmc_selected_currencies' );

						if ( 'HRK' !== $_COOKIE['wmc_current_currency'] ) {
							$hrk_rate = $selected_c[ $_COOKIE['wmc_current_currency'] ]['rate'];
						}
					}
				}
			}

			if ( $convert && is_numeric( $hrk_rate ) ) {
				$order->order_total = $woocommerce->cart->total * ( 1 / $hrk_rate );
			}

			$order_format_value = str_pad( ( $order->order_total * 100 ), 12, '0', STR_PAD_LEFT );
			$total_amount       = number_format( $order->order_total, 2, '', '' );

			$method                 = 'authorize-form'; // method type
			$pwg_installments       = '1'; // broj rata
			$pgw_card_type_id       = '1'; // tip kartice
			$secret_key             = $this->acq_id; // Secret key
			$pgw_authorization_type = '0';

			$pgw_shop_id  = $this->shop_id;
			$pgw_order_id = $order_id;
			$pgw_amount   = $total_amount;

			$pgw_success_url = $this->mer_id;
			$pgw_failure_url = $this->response_url_fail;

			$order          = new WC_Order( $order_id );
			$pgw_first_name = $order->billing_first_name;
			$pgw_last_name  = $order->billing_last_name;
			$pgw_street     = $woocommerce->customer->address;
			$pgw_city       = $woocommerce->customer->city;
			$pgw_post_code  = $woocommerce->customer->postcode;
			$pgw_country    = $woocommerce->customer->country;
			$pgw_telephone  = $order->billing_phone;
			$pgw_email      = $order->billing_email;

			$pgw_signature = hash(
				'sha512',
				$method . $secret_key .
					$pgw_shop_id . $secret_key .
					$pgw_order_id . $secret_key .
					$pgw_amount . $secret_key .
					$pgw_authorization_type . $secret_key .
					$pgw_language . $secret_key .
					$pgw_success_url . $secret_key .
					$pgw_failure_url . $secret_key .
					$pgw_first_name . $secret_key .
					$pgw_last_name . $secret_key .
					$pgw_street . $secret_key .
					$pgw_city . $secret_key .
					$pgw_post_code . $secret_key .
					$pgw_country . $secret_key .
					$pgw_telephone . $secret_key .
					$pgw_email . $secret_key
			);

			$form_args = array(
				'version'                => $this->version,
				'pgw_shop_id'            => $pgw_shop_id,
				'pgw_order_id'           => $pgw_order_id,
				'pgw_amount'             => $pgw_amount,
				'pgw_authorization_type' => $pgw_authorization_type,
				'pgw_success_url'        => $this->mer_id,
				'pgw_failure_url'        => $this->response_url_fail,
				'pgw_language'           => $pgw_language,
				'pgw_signature'          => $pgw_signature,
				'pgw_first_name'         => $pgw_first_name,
				'pgw_last_name'          => $pgw_last_name,
				'pgw_street'             => $pgw_street,
				'pgw_city'               => $pgw_city,
				'pgw_post_code'          => $pgw_post_code,
				'pgw_country'            => $pgw_country,
				'pgw_telephone'          => $pgw_telephone,
				'pgw_email'              => $pgw_email,
				'acq_id'                 => $this > acq_id, // secret key
				'PurchaseAmt'            => $order_format_value,
			);

			$form_args_array = array();
			$form_args_joins = null;
			foreach ( $form_args as $key => $value ) {
				$form_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
				$form_args_joins   = $key . '=' . $value . '&';
			}

			return '<p>' . $percentage_msg . '</p>
		<p>Total amount will be <b>' . number_format( ( $order->order_total ) ) . ' ' . $curr_symbole . '</b></p>
		<form action="' . $this->pg_domain . '" method="post" name="payway-authorize-form" id="payway-authorize-form" type="application/x-www-form-urlencoded">
            ' . implode( '', $form_args_array ) . '
            <input type="submit" class="button-alt" id="submit_ipg_payment_form" value="' . __( 'Pay via PayWay', 'tcom-payway-wc' ) . '" />
                <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'tcom-payway-wc' ) . '</a>
            </form>
            <!-- autoform submit -->
            <script type="text/javascript">
                jQuery("#submit_ipg_payment_form").trigger("click");
            </script>
            ';
		}

		function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );
			return array(
				'result'   => 'success',
				'redirect' => add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink( woocommerce_get_page_id( 'pay' ) ) ) ),
			);
		}

		function get_response_codes( $id ) {
			$id = (int) $id;

			$res = array(
				0    => 'Akcija uspješna',
				1    => 'Akcija neuspješna',
				2    => 'Greška prilikom obrade',
				3    => 'Akcija otkazana',
				4    => 'Akcija neuspješna (3D Secure MPI)',
				1000 => 'Neispravan potpis (pwg_signature)',
				1001 => 'Neispravan ID dućana (pgw_shop_id)',
				1002 => 'Neispravan ID transakcija (pgw_transaction_id)',
				1003 => 'Neispravan iznos (pgw_amount)',
				1004 => 'Neispravan tip autorizacije (pgw_authorization_type)',
				1005 => 'Neispravno trajanje najave autorizacije (pgw_announcement_duration)',
				1006 => 'Neispravan broj rata (pgw_installments)',
				1007 => 'Neispravan jezik (pgw_language)',
				1008 => 'Neispravan autorizacijski token (pgw_authorization_token)',
				1100 => 'Neispravan broj kartice (pgw_card_number)',
				1101 => 'Neispravan datum isteka kartice (pgw_card_expiration_date)',
				1102 => 'Neispravan verifikacijski broj kartice (pgw_card_verification_data)',
				1200 => 'Neispravan ID narudžbe (pgw_order_id)',
				1201 => 'Neispravan info narudžbe (pgw_order_info)',
				1202 => 'Neispravne stavke narudžbe (pgw_order_items)',
				1300 => 'Neispravan način povrata na dućan (pgw_return_method)',
				1301 => 'Neispravan povratni url na dućan (pgw_success_url)',
				1302 => 'Neispravan povratni url na dućan (pgw_failure_url)',
				1304 => 'Neispravni podaci trgovca (pgw_merchant_data)',
				1400 => 'Neispravno ime kupca (pgw_first_name)',
				1401 => 'Neispravno prezime kupca (pgw_last_name)',
				1402 => 'Neispravna adresa (pgw_street)',
				1403 => 'Neispravni grad (pgw_city)',
				1404 => 'Neispravni poštanski broj (pgw_post_code)',
				1405 => 'Neispravna država (pgw_country)',
				1406 => 'Neispravan kontakt telefon (pgw_telephone)',
				1407 => 'Neispravna kontakt e-mail adresa (pgw_email)',
			);

			return $res[ $id ];
		}

		function check_tcompayway_respose() {
			global $woocommerce;

			if ( isset( $_POST['pgw_order_id'] ) && isset( $_POST['pgw_trace_ref'] ) ) {
				$order_id = $_POST['pgw_order_id'];

				if ( '' !== $order_id ) {

					$order  = new WC_Order( $order_id );
					$amount = $_POST['amount'];
					$status = isset( $_POST['pgw_result_code'] ) ? (int) $_POST['pgw_result_code'] : 0;

					if ( 0 === $status ) {

						global $wpdb;
						$table_name = $wpdb->prefix . 'tpayway_ipg';
						$wpdb->update(
							$table_name,
							array(
								'response_code'      => $status,
								'response_code_desc' => $this->get_response_codes( $status ),
								'reason_code'        => $status,
								'status'             => $status,
							),
							array( 'transaction_id' => $_POST['pgw_order_id'] )
						);

						$order->add_order_note( 'T-Com PAYWAY payment successful<br/>Unnique Id: ' . $_POST['pgw_order_id'] );
						$woocommerce->cart->empty_cart();

						// Mark as on-hold (we're awaiting the payment)
						$order->update_status( 'pending', __( 'Awaiting payment', 'tcom-payway-wc' ) );

						$mailer = $woocommerce->mailer();

						$admin_email = get_option( 'admin_email', '' );

						$message = $mailer->wrap_message(
							__( 'Payment successful', 'tcom-payway-wc' ),
							sprintf(
								__( 'Payment on T-Com PayWay is successfully completeted and order status is processed.', 'tcom-payway-wc' ),
								$order->get_order_number()
							)
						);
						$mailer->send(
							$admin_email,
							sprintf(
								__( 'Payment for order no. %s was sucessful.', 'tcom-payway-wc' ),
								$order->get_order_number()
							),
							$message
						);

						$order->payment_complete();

						wp_redirect( $this->mer_id, 200 );
					} else {

						if ( 3 === $status ) {

							$order->update_status( 'cancelled' );
							$order->add_order_note( $this->get_response_codes( $status ) . " (Code $status)" );

							$cart_url = $woocommerce->cart->get_cart_url();

							wp_redirect( $cart_url, 200 );

						} else {
							$order->update_status( 'failed' );
							$order->add_order_note( $this->get_response_codes( $status ) . " (Code $status)" );
							$woocommerce->cart->empty_cart();

						}

						global $wpdb;
						$table_name = $wpdb->prefix . 'tpayway_ipg';
						$wpdb->update(
							$table_name,
							array(
								'response_code'      => $status,
								'response_code_desc' => $this->get_response_codes( $status ),
								'reason_code'        => $status,
								'status'             => $status,
							),
							array( 'transaction_id' => $_POST['pgw_order_id'] )
						);

						$text  = '<html><meta charset="utf-8"><body><center style="font-family:Verdana">';
						$text .= __( 'A payment was not successfull or declined', 'tcom-payway-wc' ) . '<br>';
						$text .= __( 'Reason: ', 'tcom-payway-wc' );
						$text .= $this->get_response_codes( $status ) . '<br>';
						$text .= __( 'Order Id: ', 'tcom-payway-wc' );
						$text .= $_POST['pgw_order_id'] . '<br>';
						$text .= __( 'Redirecting...', 'tcom-payway-wc' );
						$text .= '</center><script>setTimeout(function(){ window.location.replace("' . $this->response_url_fail . '"); },3000);</script></body></html>';

						echo $text;

						exit;
					}
				}
			}
		}

		function get_pages( $title = false, $indent = true ) {
			$wp_pages  = get_pages( 'sort_column=menu_order' );
			$page_list = array();
			if ( $title ) {
				$page_list[] = $title;
			}
			foreach ( $wp_pages as $page ) {
				$prefix = '';
				if ( $indent ) {
					$has_parent = $page->post_parent;
					while ( $has_parent ) {
						$prefix    .= ' - ';
						$next_page  = get_page( $has_parent );
						$has_parent = $next_page->post_parent;
					}
				}
				$page_list[ $page->ID ] = $prefix . $page->post_title;
			}
			return $page_list;
		}

	}

	$wc = new WC_TPAYWAY();

	function woocommerce_add_tpayway_gateway( $methods ) {
		$methods[] = 'WC_TPAYWAY';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_tpayway_gateway' );
}

global $jal_db_version;
$jal_db_version = '0.1';

function jal_install_tpayway() {
	global $wpdb;
	global $jal_db_version;

	$table_name      = $wpdb->prefix . 'tpayway_ipg';
	$charset_collate = '';

	if ( ! empty( $wpdb->charset ) ) {
		$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
	}

	if ( ! empty( $wpdb->collate ) ) {
		$charset_collate .= " COLLATE {$wpdb->collate}";
	}

	$sql = "CREATE TABLE $table_name (
        id int(9) NOT NULL AUTO_INCREMENT,
        transaction_id int(9) NOT NULL,
        response_code int(6) NOT NULL,
        response_code_desc VARCHAR(20) NOT NULL,
        reason_code VARCHAR(20) NOT NULL,
        amount VARCHAR(20) NOT NULL,
        or_date DATE NOT NULL,
        status int(6) NOT NULL,
        UNIQUE KEY id (id)
    ) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'jal_db_version', $jal_db_version );
}

function jal_install_data_tpayway() {
	global $wpdb;

	$welcome_name = 'T-Com PayWay';
	$welcome_text = 'Congratulations, you just completed the installation!';

	$table_name = $wpdb->prefix . 'tpayway_ipg';

	$wpdb->insert(
		$table_name,
		array(
			'time' => current_time( 'mysql' ),
			'name' => $welcome_name,
			'text' => $welcome_text,
		)
	);
}

register_activation_hook( __FILE__, 'jal_install_tpayway' );
register_activation_hook( __FILE__, 'jal_install_data_tpayway' );

if ( is_admin() ) {
	require_once( 'admin_payway_wp_list_table.php' );
	new Payway_Wp_List_Table();
}
