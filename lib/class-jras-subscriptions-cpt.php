<?php

class JRAS_Subscription_CPT {

	/**
	 * Dummy constructor
	 *
	 * @since  1.0
	 */
	public function __construct() {}

	/**
	 * Setup post type
	 *
	 * @since 1.0
	 */
	public function setup() {
		add_action( 'init', array( $this, 'setup_cpt' ) );
	}

	/**
	 * Register subscription post type. Used only for data storage and is completely hidden
	 *
	 * @since 1.0
	 */
	public function setup_cpt() {
		$args = array(
			'label' => esc_html__( 'JSON REST API Subscription', 'json-rest-api-subscriptions' ),
			'public' => false,
			'query_var' => false,
			'rewrite' => false,
			'capability_type' => 'post',
			'hierarchical' => false,
			'supports' => false,
			'has_archive' => false,
		);

		register_post_type( 'jra_subscription', $args );
	}

	/**
	 * Return singleton instance of the class
	 *
	 * @since 1.0
	 * @return object
	 */
	public static function factory() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}


