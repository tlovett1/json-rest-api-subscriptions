<?php

class JRAS_Notifier {

	/**
	 * Setup actions and filters
	 *
	 * @since 1.0
	 */
	private function setup() {
		add_action( 'jras_notify', array( $this, 'notify' ) );
		add_action( 'init', array( $this, 'schedule_events' ) );
		add_filter( 'cron_schedules', array( $this, 'filter_cron_schedules' ) );
	}

	/**
	 * Add custom cron schedule
	 *
	 * @param array $schedules
	 * @since 1.0
	 * @return array
	 */
	public function filter_cron_schedules( $schedules ) {
		$schedules['jras_fifteen_minutes'] = array(
			'interval' => ( MINUTE_IN_SECONDS * 15 ),
			'display'  => esc_html__( 'Every 15 minutes', 'json-rest-api-subscriptions' ),
		);

		return $schedules;
	}

	/**
	 * Setup cron jobs
	 *
	 * @since 1.0
	 */
	public function schedule_events() {
		$timestamp = wp_next_scheduled( 'jras_notify' );

		if ( ! $timestamp ) {
			wp_schedule_event( time(), 'jras_fifteen_minutes', 'jras_notify' );
		}
	}

	/**
	 * Prepare a date for an HTTP request. This is pulled from the JSON REST API class-wp-rest-posts-controller.php endpoint.
	 * The method couldn't be used directly because it is protected.
	 *
	 * @param  string $date_gmt
	 * @param  string $date
	 * @since  1.0
	 * @return string
	 */
	public function prepare_date_response( $date_gmt, $date = null ) {
		// Use the date if passed.
		if ( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}

		// Return null if $date_gmt is empty/zeros.
		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		// Return the formatted datetime.
		return mysql_to_rfc3339( $date_gmt );
	}

	/**
	 * Formats post for HTTP request. This is pulled from the JSON REST API class-wp-rest-posts-controller.php endpoint.
	 * The method couldn't be used directly because it returns a response object.
	 *
	 * @param  WP_POST $post
	 * @since  1.0
	 * @return array
	 */
	public function format_post_for_request( $post ) {
		return array(
			'id'           => $post->ID,
			'date'         => $this->prepare_date_response( $post->post_date_gmt, $post->post_date ),
			'date_gmt'     => $this->prepare_date_response( $post->post_date_gmt ),
			'guid'         => array(
				/** This filter is documented in wp-includes/post-template.php */
				'rendered' => apply_filters( 'get_the_guid', $post->guid ),
				'raw'      => $post->guid,
			),
			'modified'     => $this->prepare_date_response( $post->post_modified_gmt, $post->post_modified ),
			'modified_gmt' => $this->prepare_date_response( $post->post_modified_gmt ),
			'password'     => $post->post_password,
			'slug'         => $post->post_name,
			'status'       => $post->post_status,
			'type'         => $post->post_type,
			'link'         => $post->permalink,
		);
	}

	/**
	 * Do subscription notifications
	 *
	 * @since 1.0
	 */
	public function notify() {
		// Attempt to prevent a race condition from multiple people executing the cron job at once
		$lock = get_option( 'jras_notifier_lock', false );

		if ( ! $lock ) {
			update_option( 'jras_notifier_lock', true );

			$deleted_post_ids = array();

			$changed_posts = array();

			// We use three options to decrease the chances of hitting the options table size limit
			$deleted_posts = get_option( 'jras_deleted_posts', array() );
			$updated_posts = get_option( 'jras_updated_posts', array() );
			$created_posts = get_option( 'jras_created_posts', array() );

			foreach ( $deleted_posts as $post_id => $post ) {
				$post->action = 'delete';
				$changed_posts[] = $post;
			}

			foreach ( $created_posts as $post_id => $post ) {
				$post->action = 'create';
				$changed_posts[] = $post;
			}

			foreach ( $updated_posts as $post_id => $post ) {
				$post->action = 'update';
				$changed_posts[] = $post;
			}

			foreach ( $changed_posts as $post ) {
				if ( ! in_array( $post->ID, $deleted_post_ids ) ) {
					/**
					 * It's possible someone is subscribed to a single piece of content and a content type. Let's
					 * not notify them twice.
					 */
					$delete_notified_targets = array();

					/**
					 * Unfortunately, we have to do a meta query to find content type subscriptions
					 */
					$content_type_subscriptions = new WP_Query( array(
						'post_type'     => 'jras_subscription',
						'no_found_rows' => true,
						'fields'        => 'ids',
						'meta_key'      => 'jras_content_type',
						'meta_value'    => $post->post_type,
					) );

					if ( $content_type_subscriptions->have_posts() ) {
						foreach ( $content_type_subscriptions->posts as $subscription_id ) {
							$events = get_post_meta( $subscription_id, 'jras_events', true );

							// Only notify if they are subscribed to delete. Faster than doing a multi-dimensional meta query
							if ( in_array( $post->action, $events ) ) {
								$target = get_post_meta( $subscription_id, 'jras_target', true );

								$this->send_notify_request( $subscription_id, $post, $post->action );

								$notified_targets[ $target ] = true;
							}
						}
					}

					$content_item_subscriptions = new WP_Query( array(
						'post_type'     => 'jras_subscription',
						'no_found_rows' => true,
						'fields'        => 'ids',
						'post_parent'   => $post->ID,
					) );

					if ( $content_item_subscriptions->have_posts() ) {

						foreach ( $content_item_subscriptions->posts as $subscription_id ) {
							$events = get_post_meta( $subscription_id, 'jras_events', true );

							// Only notify if they are subscribed to delete. Faster than doing a multi-dimensional meta query
							if ( in_array( $post->action, $events ) ) {
								$target = get_post_meta( $subscription_id, 'jras_target', true );

								// Don't delete notify twice
								if ( empty( $delete_notified_targets[ $target ] ) ) {

									$this->send_notify_request( $subscription_id, $post, $post->action );

									if ( 'delete' === $post->action ) {
										$delete_notified_targets[ $target ] = true;
									}
								}

								// Delete subscription since post is gone
								if ( 'delete' === $post->action ) {
									wp_delete_post( $subscription_id, true );
								}
							}
						}
					}

					$deleted_post_ids[] = $post->ID;
				}
			}

			delete_option( 'jras_deleted_posts' );
			delete_option( 'jras_updated_posts' );
			delete_option( 'jras_created_posts' );

			delete_option( 'jras_notifier_lock' );
		}

	}

	/**
	 * Send a notification request. If the request fails, we delete the subscription
	 *
	 * @param  int     $subscription_id
	 * @param  WP_Post $post
	 * @param  string  $action
	 * @since  1.0
	 */
	public function send_notify_request( $subscription_id, $post, $action ) {
		$target = get_post_meta( $subscription_id, 'jras_target', true );
		$signature = get_post_meta( $subscription_id, 'jras_signature', true );

		$body = array(
			'action' => $action,
			'item'   => $this->format_post_for_request( $post ),
		);

		$try = 1;
		$max_tries = apply_filters( 'jras_notification_max_tries', 2, $subscription_id, $post, $action );

		$valid_response_codes = apply_filters( 'jras_notification_valid_response_codes', array( 200 ), $subscription_id, $post, $action );

		$valid_response = false;

		while ( true ) {
			if ( $try > $max_tries ) {
				// We failed
				break;
			}

			$response = wp_remote_request( $target, apply_filters( 'jras_notification_request_args', array(
				'method'      => 'POST',
				'timeout'     => 7,
				'redirection' => 4,
				'body'        => json_encode( $body ),
				'headers'     => array(
					'Content-Type'      => 'application/json',
					'X-WP-Notification' => esc_url_raw( home_url() ),
				),
			), $subscription_id, $post, $action ) );

			$response_code = wp_remote_retrieve_response_code( $response );

			// Verify valid response code
			if ( in_array( $response_code, $valid_response_codes ) ) {

				// Verify matching signature
				if ( ! empty( $response['headers']['x-wp-subscription-signature'] ) && md5( $response['headers']['x-wp-subscription-signature'] ) === $signature ) {
					$valid_response = true;
					break;
				}
			}

			$try++;
		}

		if ( ! $valid_response ) {
			wp_delete_post( $subscription_id, true );

			do_action( 'jras_unsuccessful_notification', $subscription_id, $post, $action );
		} else {
			do_action( 'jras_successful_notification', $subscription_id, $post, $action );
		}
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
