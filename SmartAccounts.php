<?php

include_once( 'SmartAccountsClient.php' );
include_once( 'SmartAccountsSalesInvoice.php' );
include_once( 'SmartAccountsApi.php' );
include_once( 'SmartAccountsPayment.php' );
include_once( 'SmartAccountsArticle.php' );
include_once( dirname( __FILE__ ) . '/../woocommerce/woocommerce.php' );

class SmartAccounts {

	public static function orderStatusProcessing( $orderId ) {
		//try catch makes sure your store will operate even if there are errors
		try {
			$order          = new WC_Order( $orderId );
			$saClient       = new SmartAccountsClient( $order );
			$client         = $saClient->getClient();
			$saSalesInvoice = new SmartAccountsSalesInvoice( $order, $client );

			$invoice   = $saSalesInvoice->saveInvoice();
			$saPayment = new SmartAccountsPayment( $order, $invoice );
			$saPayment->createPayment();
		} catch ( Exception $exception ) {

		}
	}


	function registerSettings() {
		register_setting( 'smartaccounts_options', 'sa_api_pk' );
		register_setting( 'smartaccounts_options', 'sa_api_sk' );
		register_setting( 'smartaccounts_options', 'sa_api_payment_account' );
	}

	function optionsPage() {
		add_submenu_page( 'woocommerce', 'SmartAccounts settings', "SmartAccounts", 'manage_woocommerce', 'smartaccounts', 'SmartAccounts::options_page_html' );
	}

	function options_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
        <div class="wrap">
            <h1><?= esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <table class="form-table">
                    <tr valign="top">
                        <th>SmartAccounts public key</th>
                        <td>
                            <input name="sa_api_pk" size="50"
                                   value="<?php echo esc_attr( get_option( 'sa_api_pk' ) ); ?>"/>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th>SmartAccounts private key</th>
                        <td>
                            <input name="sa_api_sk" size="50"
                                   value="<?php echo esc_attr( get_option( 'sa_api_sk' ) ); ?>"/>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th>SmartAccounts payments Bank account name</th>
                        <td>
                            <input name="sa_api_payment_account" size="50"
                                   value="<?php echo esc_attr( get_option( 'sa_api_payment_account' ) ); ?>"/>
                        </td>
                    </tr>

                </table>
				<?php

				settings_fields( 'smartaccounts_options' );
				submit_button( 'Salvesta seaded' );
				?>
            </form>
        </div>
		<?php
	}

}


