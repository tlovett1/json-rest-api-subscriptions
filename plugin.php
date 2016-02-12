<?php
/**
 * Plugin Name: JSON REST API Subscriptions
 * Plugin URI: http://www.taylorlovett.com
 * Description: Enable webhooks style subscriptions for posts, pages, and custom post types via the JSON REST API.
 * Author: Taylor Lovett
 * Version: 1.0
 * Text Domain: json-rest-api-subscriptions
 * Author URI: http://www.taylorlovett.com
 */

/**
 * Register routes for content types
 *
 * @since  1.0
 */
function jras_register_routes() {
	global $wp_rest_server;

	require_once( dirname( __FILE__ ) . '/lib/endpoints/class-jras-subscriptions-controller.php' );

	$namespace_post_types = jras_subscription_namespace_post_types();

	foreach ( $namespace_post_types as $namespace_post_type ) {
		$controller = new JRAS_Subscriptions_Controller( $namespace_post_type['namespace'], $namespace_post_type['rest_base'] );
		$controller->register_routes();
	}

	/**
	 * @todo: Support comments
	 */
}
add_action( 'rest_api_init', 'jras_register_routes' );

/**
 * Get available subscription post types
 *
 * @since  1.0
 * @return array
 */
function jras_subscription_namespace_post_types() {
	return apply_filters( 'jras_subscription_namespace_post_types', array(
		array(
			'namespace' => 'wp/v2',
			'rest_base' => 'posts',
			'post_type' => 'post',
		),
		array(
			'namespace' => 'wp/v2',
			'rest_base' => 'pages',
			'post_type' => 'page',
		),
	) );
}

require_once( dirname( __FILE__ ) . '/lib/class-jras-subscriptions-cpt.php' );
require_once( dirname( __FILE__ ) . '/lib/class-jras-notifier.php' );
require_once( dirname( __FILE__ ) . '/lib/class-jras-listener.php' );

JRAS_Subscription_CPT::factory();
JRAS_Notifier::factory();
JRAS_Listener::factory();
