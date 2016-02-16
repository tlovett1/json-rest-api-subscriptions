<?php

class CCFTestNotifier extends WP_UnitTestCase {

	/**
	 * Setup each test making sure we are logged in as an admin
	 *
	 * @since  1.0
	 */
	public function setUp() {
		parent::setUp();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
	}

	/**
	 * Create a subscription for testing
	 * 
	 * @param  string  $target
	 * @param  array   $events
	 * @param  string  $post_type
	 * @param  integer $content_id
	 * @since  1.0
	 * @return int|bool
	 */
	private function _create_subscription( $target, $signature, $events = array( 'delete', 'create', 'update' ), $post_type = 'post', $content_id = 0 ) {
		$subscription_id = wp_insert_post( array(
			'post_title'  => 'subscription ' . esc_url_raw( $target ),
			'post_type'   => 'jras_subscription',
			'post_status' => 'publish',
			'post_parent' => $content_id,
		) );

		if ( ! is_wp_error( $subscription_id ) ) {

			update_post_meta( $subscription_id, 'jras_events', $events );
			update_post_meta( $subscription_id, 'jras_target', $target );
			update_post_meta( $subscription_id, 'jras_content_type', $post_type );
			update_post_meta( $subscription_id, 'jras_signature', md5( $signature ) );

			return $subscription_id;
		}

		return false;
	}

	/**
	 * Test one posts notification with one subscriber
	 *
	 * @since  1.0
	 */
	public function testNotificationPostsOneSubscriber() {
		$successful_notification = false;
		$signature = 'test';

		$this->_create_subscription( 'http://test.com', $signature );

		// Stub the wp_remote_request function
		\Patchwork\replace( 'wp_remote_request', function( $url, $args = array() ) use ( $signature ) {
			return array(
				'response' => array(
					'code'                        => 200,
				),
				'headers'  => array(
					'x-wp-subscription-signature' => $signature,
				),
			);
		} );

		$post_id = wp_insert_post( array(
			'post_title'   => 'Test post',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		add_action( 'jras_successful_notification', function() use ( &$successful_notification ) {
			$successful_notification = true;
		} );

		JRAS_Notifier::factory()->notify();

		$this->assertTrue( $successful_notification );
	}

	/**
	 * Test failed one posts notification with one subscriber with a bad signature
	 *
	 * @since  1.0
	 */
	public function testFailedNotificationPostsOneSubscriberBadSignature() {
		$unsuccessful_notification = false;
		$signature = 'test';

		$this->_create_subscription( 'http://test.com', $signature );

		// Stub the wp_remote_request function
		\Patchwork\replace( 'wp_remote_request', function( $url, $args = array() ) use ( $signature ) {
			return array(
				'response' => array(
					'code'                        => 200,
				),
				'headers'  => array(
					'x-wp-subscription-signature' => $signature . 'BAD',
				),
			);
		} );

		$post_id = wp_insert_post( array(
			'post_title'   => 'Test post',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		add_action( 'jras_unsuccessful_notification', function() use ( &$unsuccessful_notification ) {
			$unsuccessful_notification = true;
		} );

		JRAS_Notifier::factory()->notify();

		$this->assertTrue( $unsuccessful_notification );
	}

	/**
	 * Test failed one posts notification with one subscriber with a bad response code
	 *
	 * @since  1.0
	 */
	public function testFailedNotificationPostsOneSubscriberBadResponse() {
		$unsuccessful_notification = false;
		$signature = 'test';

		$this->_create_subscription( 'http://test.com', $signature );

		// Stub the wp_remote_request function
		\Patchwork\replace( 'wp_remote_request', function( $url, $args = array() ) use ( $signature ) {
			return array(
				'response' => array(
					'code'                        => 500,
				),
				'headers'  => array(
					'x-wp-subscription-signature' => $signature,
				),
			);
		} );

		$post_id = wp_insert_post( array(
			'post_title'   => 'Test post',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		add_action( 'jras_unsuccessful_notification', function() use ( &$unsuccessful_notification ) {
			$unsuccessful_notification = true;
		} );

		JRAS_Notifier::factory()->notify();

		$this->assertTrue( $unsuccessful_notification );
	}
}
