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

	/**
	 * Test two posts notification with one subscriber
	 *
	 * @since  1.0
	 */
	public function testTwoNotificationsPostsOneSubscriber() {
		$successful_notifications = 0;
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

		wp_insert_post( array(
			'post_title'   => 'Test post',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		wp_insert_post( array(
			'post_title'   => 'Test post2',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		add_action( 'jras_successful_notification', function() use ( &$successful_notifications ) {
			$successful_notifications++;
		} );

		JRAS_Notifier::factory()->notify();

		$this->assertEquals( 2, $successful_notifications );
	}

	/**
	 * Test two posts notification with one subscriber both failed
	 *
	 * @since  1.0
	 */
	public function testTwoNotificationsPostsOneSubscriberFailedBadSignature() {
		$unsuccessful_notifications = 0;
		$signature = 'test';

		$this->_create_subscription( 'http://test.com', $signature );

		// Stub the wp_remote_request function
		\Patchwork\replace( 'wp_remote_request', function( $url, $args = array() ) use ( $signature ) {
			return array(
				'response' => array(
					'code'                        => 200,
				),
				'headers'  => array(
					'x-wp-subscription-signature' => $signature . 'bad',
				),
			);
		} );

		wp_insert_post( array(
			'post_title'   => 'Test post',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		wp_insert_post( array(
			'post_title'   => 'Test post2',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		wp_insert_post( array(
			'post_title'   => 'Test post3',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		add_action( 'jras_unsuccessful_notification', function() use ( &$unsuccessful_notifications ) {
			$unsuccessful_notifications++;
		} );

		JRAS_Notifier::factory()->notify();

		// Only one since signature gets deleted due to bad response
		$this->assertEquals( 1, $unsuccessful_notifications );
	}

	/**
	 * Test two posts notification with one subscriber both failed because of response code
	 *
	 * @since  1.0
	 */
	public function testThreeNotificationsPostsOneSubscriberFailedBadResponse() {
		$unsuccessful_notifications = 0;
		$signature = 'test';

		$this->_create_subscription( 'http://test.com', $signature );

		// Stub the wp_remote_request function
		\Patchwork\replace( 'wp_remote_request', function( $url, $args = array() ) use ( $signature ) {
			return array(
				'response' => array(
					'code'                        => 400,
				),
				'headers'  => array(
					'x-wp-subscription-signature' => $signature,
				),
			);
		} );

		wp_insert_post( array(
			'post_title'   => 'Test post',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		wp_insert_post( array(
			'post_title'   => 'Test post2',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		wp_insert_post( array(
			'post_title'   => 'Test post3',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		add_action( 'jras_unsuccessful_notification', function() use ( &$unsuccessful_notifications ) {
			$unsuccessful_notifications++;
		} );

		JRAS_Notifier::factory()->notify();

		// Only one since signature gets deleted due to bad response
		$this->assertEquals( 1, $unsuccessful_notifications );
	}

	/**
	 * Test multiple successful notifications to multiple subscribers
	 * 
	 * @since 1.0
	 */
	public function testMultipleNotificationsPostMultipleSubscribers() {
		$successful_notifications_subscriber_1 = 0;
		$successful_notifications_subscriber_2 = 0;
		$signature = 'test';

		$subscriber_1_id = $this->_create_subscription( 'http://test.com', $signature );
		$subscriber_2_id = $this->_create_subscription( 'http://test2.com', $signature );

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

		wp_insert_post( array(
			'post_title'   => 'Test post',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		wp_insert_post( array(
			'post_title'   => 'Test post2',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		add_action( 'jras_successful_notification', function( $subscription_id, $post, $action ) use ( &$successful_notifications_subscriber_1, &$successful_notifications_subscriber_2, &$subscriber_1_id, &$subscriber_2_id ) {
			if ( $subscriber_1_id === $subscription_id ) {
				$successful_notifications_subscriber_1++;
			} elseif ( $subscriber_2_id === $subscription_id ) {
				$successful_notifications_subscriber_2++;
			}
		}, 10, 3 );

		JRAS_Notifier::factory()->notify();

		$this->assertEquals( 2, $successful_notifications_subscriber_1 );
		$this->assertEquals( 2, $successful_notifications_subscriber_2 );
	}

	/**
	 * Test multiple mixed success notifications to multiple subscribers
	 * 
	 * @since 1.0
	 */
	public function testMultipleNotificationsPostMultipleSubscribersMixedSuccess() {
		$successful_notifications_subscriber_1 = 0;
		$successful_notifications_subscriber_2 = 0;
		$unsuccessful_notifications_subscriber_1 = 0;
		$unsuccessful_notifications_subscriber_2 = 0;
		$signature = 'test';

		$subscriber_1_id = $this->_create_subscription( 'http://test.com', $signature );
		$subscriber_2_id = $this->_create_subscription( 'http://test2.com', 'test2' );

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

		wp_insert_post( array(
			'post_title'   => 'Test post',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		wp_insert_post( array(
			'post_title'   => 'Test post2',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		add_action( 'jras_successful_notification', function( $subscription_id, $post, $action ) use ( &$successful_notifications_subscriber_1, &$successful_notifications_subscriber_2, &$subscriber_1_id, &$subscriber_2_id ) {
			if ( $subscriber_1_id === $subscription_id ) {
				$successful_notifications_subscriber_1++;
			} elseif ( $subscriber_2_id === $subscription_id ) {
				$successful_notifications_subscriber_2++;
			}
		}, 10, 3 );

		add_action( 'jras_unsuccessful_notification', function( $subscription_id, $post, $action ) use ( &$unsuccessful_notifications_subscriber_1, &$unsuccessful_notifications_subscriber_2, &$subscriber_1_id, &$subscriber_2_id ) {
			if ( $subscriber_1_id === $subscription_id ) {
				$unsuccessful_notifications_subscriber_1++;
			} elseif ( $subscriber_2_id === $subscription_id ) {
				$unsuccessful_notifications_subscriber_2++;
			}
		}, 10, 3 );

		JRAS_Notifier::factory()->notify();

		$this->assertEquals( 2, $successful_notifications_subscriber_1 );
		$this->assertEquals( 0, $successful_notifications_subscriber_2 );

		$this->assertEquals( 0, $unsuccessful_notifications_subscriber_1 );
		$this->assertEquals( 1, $unsuccessful_notifications_subscriber_2 );
	}
}
