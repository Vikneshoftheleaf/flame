<?php
/*
Plugin Name: Attach files to WooCommerce order email
Plugin URI: https://www.damiencarbery.com/2021/05/attach-files-to-woocommerce-order-email/
Description: Upload files to an order to attach them to the WooCommerce Order Completed email.
Author: Damien Carbery
Author URI: https://www.damiencarbery.com
Version: 0.7
WC tested to: 8.1.0
*/

defined( 'ABSPATH' ) || exit;

class AttachFilesToWooCommerceOrderEmail {
	private static $instance;
	
	private $privateUploads;  // Whether to upload files to WooCommerce protected area.


	// Returns an instance of this class. 
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		} 
		return self::$instance;
	}


	// Initialize the plugin variables.
	private function __construct() {
		$this->privateUploads = false;

		$this->init();
	}


	// Set up WordPress specfic actions.
	private function init() {
		// Declare that this plugin supports WooCommerce HPOS.
		add_action( 'before_woocommerce_init', function() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		} );

		// Verify that CMB2 plugin is active.
		add_action( 'admin_notices', array( $this, 'verify_cmb2_active' ) );

		// Add the metabox to allow for adding files to attach to the "Completed order" email.
		add_action( 'cmb2_admin_init', array( $this, 'order_files_metabox' ) );
		
		add_filter( 'woocommerce_email_attachments', array( $this, 'conditionally_attach_order_files_to_order_email' ), 10, 4 );
		
		// List the attached documents in the customer's View Order page.
		add_action( 'woocommerce_view_order', array( $this, 'list_order_docs' ), 20 );

		// For testing.
		//add_action( 'woocommerce_email_order_details', array( $this, 'testing_order_attachments_code' ), 10, 4 );

		if ( $this->privateUploads ) {
			add_filter( 'plupload_default_params', array( $this, 'add_page_type_to_upload_field' ) );
			add_filter( 'upload_dir', array( $this, 'change_upload_dir_for_shop_order_uploads' ), 20 );
		}

	}


	// Verify that CMB2 plugin is active.
	function verify_cmb2_active() {
		if ( ! defined( 'CMB2_LOADED' ) ) {
			$plugin_data = get_plugin_data( __FILE__ );
			$plugin_name = $plugin_data['Name'];
	?>
	<div class="notice notice-warning is-dismissible"><p>Plugin <strong><?php echo $plugin_name; ?></strong> requires <a href="https://wordpress.org/plugins/cmb2/">CMB2 plugin</a>.</p></div>
	<?php
			//error_log( 'CMB2 is not active.' );
		}
	}

	
	// Add the metabox to allow for adding files to attach to the "Completed order" email.
	function order_files_metabox() {
		// When private uploads is enabled previews may not be available so mention this in the metabox description text.
		$privateUploadsMessage = '';
		if ( $this->privateUploads ) {
			$privateUploadsMessage = '<br/><br/>Note: Private uploads is enabled so previews of attachments may not be available.';
		}
		// Set different 'object_types' if HPOS active.
		$woo_hpos_active = get_option( 'woocommerce_custom_orders_table_enabled' );
		$object_types = ( 'yes' == $woo_hpos_active ) ? array( 'woocommerce_page_wc-orders' ) : array( 'shop_order' );

		$cmb = new_cmb2_box( array(
			'id'            => 'order_attachments',
			'title'         => 'Order Attachments',
			'object_types'  => $object_types,
			'context'       => 'side',
			'priority'      => 'high',
			'show_names'    => true, // Show field names on the left
		) );
		$cmb->add_field( array(
			'desc' => 'Upload the files that will be attached to the "Completed order" email.<br/><br/>The files must be uploaded <strong>before</strong> marking the order Complete.' . $privateUploadsMessage,
			'id'   => 'order_file_list',
			'type' => 'file_list',
			'preview_size' => array( 100, 100 ), // Default: array( 50, 50 )
			'query_args' => array( 'type' => array( 'image', 'application/pdf' ) ), // Set to only allow image attachments. This can be disabled or edited.
		) );
	}


	function conditionally_attach_order_files_to_order_email( $attachments, $email_id, $object, $email_obj ) {
		// Only attach files to Completed Order email, otherwise return early.
		if ( 'customer_completed_order' != $email_id ) {
			return $attachments;
		}
		
		$files = $object->get_meta( 'order_file_list' );
		foreach ( (array) $files as $attachment_id => $attachment_url ) {
			$attachments[] = get_attached_file( $attachment_id );
		}

		return $attachments;
	}
	
	
	function list_order_docs( $order_id ) {
		if ( ! $order_id ) {
			return;
		}
		
		$order = wc_get_order( $order_id );
		if ( ! $order ) { return; }

		$files = $order->get_meta( 'order_file_list' );
		if ( $files ) {
			echo '<h2>Attached documents</h2>';
			echo '<ul>';
			foreach ( (array) $files as $attachment_id => $attachment_url ) {
				printf( '<li><a href= "%s">%s</a></li>', $attachment_url, get_the_title( $attachment_id ) );
			}
			echo '</ul>';
		}
	}


	// Add the post type as a field that will be available when the file is uploaded. This
	// will be used to change the upload destination directory for uploads from 'shop_order' type.
	function add_page_type_to_upload_field( $params ) {
		if ( ! is_admin() ) { return $params; }
		
		$screen = get_current_screen();
		if ( isset( $screen ) ){
			$params['post_type'] = $screen->post_type;
			//error_log( 'add_page_type_to_upload_field: ' . $screen->post_type );
		}
		
		return $params;
	}


	// Put uploads from a 'shop_order' into the same directory as downloadable files. This
	// directory is protected with a .htaccess file to prevent direct access.
	function change_upload_dir_for_shop_order_uploads( $pathdata ) {
		//error_log( 'upload_dir $_POST: ' . var_export( $_POST, true ) );

		// This code is (almost) identical to that in upload_dir() in woocommerce/includes/admin/class-wc-admin-post-types.php.
		if( (isset( $_POST['post_type'] ) && 'shop_order' === $_POST['post_type'] )) {
			if ( empty( $pathdata['subdir'] ) ) {
				$pathdata['path']   = $pathdata['path'] . '/woocommerce_uploads';
				$pathdata['url']    = $pathdata['url'] . '/woocommerce_uploads';
				$pathdata['subdir'] = '/woocommerce_uploads';
				//error_log( 'Empty subdir: ' . var_export( $pathdata, true ) );
			} else {
				$new_subdir = '/woocommerce_uploads' . $pathdata['subdir'];
				$pathdata['path']   = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['path'] );
				$pathdata['url']    = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['url'] );
				$pathdata['subdir'] = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['subdir'] );
				//error_log( 'Non-empty subdir: ' . var_export( $pathdata, true ) );
			}
		}
		
		return $pathdata;
	}


	// Test the code - list the paths to the attachments.
	function testing_order_attachments_code( $order, $sent_to_admin, $plain_text, $email ) {
		$files = $order->get_meta( 'order_file_list' );

		echo '<pre>', var_export( $files, true ), '</pre>';

		foreach ( (array) $files as $attachment_id => $attachment_url ) {
			echo '<p>', get_attached_file( $attachment_id ), '</p>';
		}
	}
}

$AttachFilesToWooCommerceOrderEmail = AttachFilesToWooCommerceOrderEmail::get_instance();
