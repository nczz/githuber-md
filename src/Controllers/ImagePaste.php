<?php
/**
 * Class ImagePaste
 *
 * @author Terry Lin
 * @link https://terryl.in/
 *
 * @package Githuber
 * @since 1.0.1
 * @version 1.2.0
 */

namespace Githuber\Controller;

class ImagePaste extends ControllerAbstract {

	/**
	 * The version of inline-attachment.js we are using.
	 *
	 * @var string
	 */
	public $imagepaste_version = '2.0.3';

	/**
	 * Constructer.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Initialize.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	/**
	 * Initalize to WP `admin_init` hook.
	 */
	public function admin_init() {
		$user          = wp_get_current_user();
		$allowed_roles = array( 'editor', 'administrator', 'author' );

		// For security reasons, only authorized logged-in users can upload images.
		if ( array_intersect( $allowed_roles, $user->roles ) || is_super_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'wp_ajax_githuber_image_paste', array( $this, 'admin_githuber_image_paste' ) );
		}
	}

	/**
	 * Register CSS style files.
	 */
	public function admin_enqueue_styles( $hook_suffix ) {

	}

	/**
	 * Register JS files.
	 */
	public function admin_enqueue_scripts( $hook_suffix ) {
		wp_enqueue_script( 'image-paste', $this->githuber_plugin_url . 'assets/vendor/inline-attachment/inline-attachment.min.js', array(), $this->imagepaste_version, true );
		wp_enqueue_script( 'image-paste-codemirror', $this->githuber_plugin_url . 'assets/vendor/inline-attachment/codemirror-4.inline-attachment.min.js', array(), $this->imagepaste_version, true );
	}

	/**
	 * Do action hook for image paste.
	 */
	public function admin_githuber_image_paste() {
		$upload_dir  = wp_upload_dir();
		$upload_path = $upload_dir['path'];
		$online_path = $upload_dir['url'];
		$response    = array();
		
		if ( isset( $_FILES['file'], $_GET['_wpnonce'], $_GET['post_id'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'image_paste_action_' . $_GET['post_id'] ) && current_user_can( 'edit_post', $_GET['post_id'] ) ) {
			$file = $_FILES['file'];
			$filename = uniqid() . '.' . ( pathinfo( $file['name'], PATHINFO_EXTENSION ) ? : 'png' );

			$image_src       = githuber_get_option( 'image_paste_src', 'githuber_modules' );
			$imgur_client_id = githuber_get_option( 'imgur_client_id', 'githuber_modules' );

			if ( 'imgur' === $image_src && ! empty( $imgur_client_id ) ) {
				
				if ( function_exists( 'curl_init') ) {
					$image = file_get_contents( $file['tmp_name'] );
					$data  = $this->upload_to_imgur( $image, $imgur_client_id );

					if ( true === $data['success'] ) {
						$response['filename'] = $data['data']['link'];
					} else {
						$response['error'] = __( 'Error while processing your request to Imgur!', 'wp-githuber-md' );
					}
				} else {
					$response['error'] = __( 'PHP Curl is not installed on your system.', 'wp-githuber-md' );
				}
			
			} else {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$attachment_id = media_handle_upload('file', $_GET['post_id']);
				$response['filename'] = wp_get_attachment_url($attachment_id);;
			}
		} else {
			$response['error'] = __( 'Error while uploading file.', 'wp-githuber-md' );
		}
		echo json_encode( $response );

		// To avoid wp_ajax return "0" string to break the vaild json string.
		wp_die();
	}

	/**
	 * Upload images to Imgur.com
	 * 
	 * @param string $image     Image binary string.
	 * @param string $client_id Imgur application Client ID.
	 * @return array Response from Imgur image API.
	 */
	public function upload_to_imgur( $image, $client_id ) {
		$header_data = array( "Authorization: Client-ID $client_id" );
		$post_data   = array( 'image' => base64_encode( $image ) );

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, 'https://api.imgur.com/3/image.json' );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)' );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_data );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header_data );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		
		$result = curl_exec( $ch );
		curl_close( $ch );

		return json_decode( $result, true );
	}
}
