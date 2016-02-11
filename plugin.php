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

	$content_types = apply_filters( 'jras_subscription_type_endpoints', array(
		'posts',
		'pages',
	) );

	foreach ( $content_types as $content_type ) {
		$controller = new JRAS_Subscriptions_Controller( 'wp/v2', $content_type );
		$controller->register_routes();
	}

	/**
	 * @todo: Support comments
	 */
}
add_action( 'rest_api_init', 'jras_register_routes' );

function jras_subscription_post_types() {
	return apply_filters( 'jras_subscription_post_types', array(
		'post',
		'page',
	) );
}

require_once( dirname( __FILE__ ) . '/lib/class-jras-subscriptions-cpt.php' );
require_once( dirname( __FILE__ ) . '/lib/class-jras-notifier.php' );
require_once( dirname( __FILE__ ) . '/lib/class-jras-listener.php' );

JRAS_Subscription_CPT::factory();
JRAS_Notifier::factory();
JRAS_Listener::factory();
