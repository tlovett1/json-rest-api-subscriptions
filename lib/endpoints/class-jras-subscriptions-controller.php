<?php

class JRAS_Subscriptions_Controller extends WP_REST_Controller {

	/**
	 * Setup new subscription controller
	 *
	 * @since  1.0
	 * @param string $namespace    wp/v2 is the default for WordPress
	 * @param string $content_type Usually custom post type slug
	 */
	public function __construct( $namespace, $content_type ) {
		$this->namespace = $namespace;
		$this->rest_base = $content_type;
	}

	/**
	 * Register routes for collection as well single content type
	 *
	 * @since  1.0
	 */
	public function register_routes() {
		$routes = array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_subscriptions' ),
				'permission_callback' => array( $this, 'get_subscriptions_permissions_check' ),
				'args'                => array(
					'offset' => array(
						'description'        => esc_html__( 'Offset the result set by a specific number of items.', 'json-rest-api-subscriptions' ),
						'type'               => 'integer',
						'sanitize_callback'  => 'absint',
						'validate_callback'  => 'rest_validate_request_arg',
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_subscription' ),
				'permission_callback' => array( $this, 'create_subscriptions_permissions_check' ),
				'args'                => array(
					'events' => array(
						'description'        => esc_html__( 'Event(s) to subscribe to.', 'json-rest-api-subscriptions' ),
						'type'               => 'array',
						'sanitize_callback'  => 'rest_sanitize_request_arg',
						'validate_callback'  => 'rest_validate_request_arg',
						'required'           => true,
					),
					'target' => array(
						'description'        => esc_html__( 'URL to send subscription notifications.', 'json-rest-api-subscriptions' ),
						'type'               => 'string',
						'sanitize_callback'  => 'rest_sanitize_request_arg',
						'validate_callback'  => 'rest_validate_request_arg',
						'required'           => true,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_subscription' ),
				'permission_callback' => array( $this, 'update_subscriptions_permissions_check' ),
				'args'                => array(
					'events' => array(
						'description'        => esc_html__( 'Event(s) to subscribe to.', 'json-rest-api-subscriptions' ),
						'type'               => 'array',
						'sanitize_callback'  => 'rest_sanitize_request_arg',
						'validate_callback'  => 'rest_validate_request_arg',
						'required'           => true,
					),
					'target' => array(
						'description'        => esc_html__( 'URL to send subscription notifications.', 'json-rest-api-subscriptions' ),
						'type'               => 'string',
						'sanitize_callback'  => 'rest_sanitize_request_arg',
						'validate_callback'  => 'rest_validate_request_arg',
						'required'           => true,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_subscription' ),
				'permission_callback' => array( $this, 'delete_subscriptions_permissions_check' ),
				'args'                => array(
					'target' => array(
						'description'       => esc_html__( 'URL of subscription to delete.', 'json-rest-api-subscriptions' ),
						'type'              => 'string',
						'sanitize_callback' => 'rest_sanitize_request_arg',
						'validate_callback' => 'rest_validate_request_arg',
						'required'          => true,
					),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		);

		/**
		 * Subscribe to all events from this content type
		 */
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/subscriptions', $routes );

		/**
		 * Subscribe to events from a specific piece of content
		 */
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<content_id>[\d]+)/subscriptions', $routes );
	}

	/**
	 * Check if API call for getting subscriptions is allowed
	 *
	 * @since  1.0
	 * @param  WP_REST_Request $request
	 * @return boolean
	 */
	public function get_subscriptions_permissions_check( $request ) {
		$post_type = get_post_type_object( 'jras_subscription' );

		$check = true;

		if ( ! current_user_can( $post_type->cap->edit_posts ) ) {
			// $check = new WP_Error( 'jras_get_subscriptions_forbidden', esc_html__( 'Sorry, you are not allowed to view subscriptions', 'json-rest-api-subscriptions' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return apply_filters( 'jras_get_subscriptions_permissions_check', $check, $request );
		;
	}

	/**
	 * Check if API call for creating subscriptions is allowed. This is mostly a placeholder
	 * since we do the real checks later.
	 *
	 * @since  1.0
	 * @param  WP_REST_Request $request
	 * @return boolean
	 */
	public function create_subscriptions_permissions_check( $request ) {
		return apply_filters( 'jras_create_subscriptions_permissions_check', true, $request );
	}

	/**
	 * Check if API call for updating subscriptions is allowed. This is mostly a placeholder
	 * since we do the real checks later.
	 *
	 * @since  1.0
	 * @param  WP_REST_Request $request
	 * @return boolean
	 */
	public function update_subscriptions_permissions_check( $request ) {
		return apply_filters( 'jras_update_subscriptions_permissions_check', true, $request );
	}

	/**
	 * Check if API call for deleting subscriptions is allowed. This is mostly a placeholder
	 * since we do the real checks later.
	 *
	 * @since  1.0
	 * @param  WP_REST_Request $request
	 * @return boolean
	 */
	public function delete_subscriptions_permissions_check( $request ) {
		return apply_filters( 'jras_delete_subscriptions_permissions_check', true, $request );
	}

	/**
	 * Delete a subscription given a request
	 *
	 * @since  1.0
	 * @param  WP_REST_Request $request
	 * @return  WP_REST_Response
	 */
	public function delete_subscription( $request ) {

		$clean_target = untrailingslashit( esc_url_raw( $request['target'] ) );

		$content_type = preg_replace( '#^.*/([^/]*)/subscriptions/?$#', '$1', $request->get_route() );

		/**
		 * Account for if we are subscribing to a single piece of content
		 */
		$content_id = ( ! empty( $request['content_id'] ) ) ? (int) $request['content_id'] : 0;

		if ( ! empty( $content_id ) ) {
			$content_subscription = get_post( $content_id );

			if ( empty( $content_subscription ) ) {
				return new WP_Error( 'delete_subscription_content_not_found', esc_html__( 'Content not found for subscription.', 'json-rest-api-subscriptions' ), array( 'status' => 400 ) );
			}
		}

		/**
		 * Unfortunately, we have to do a meta query to find existing targets.
		 *
		 * @var WP_Query
		 */
		$existing_targets = new WP_Query( array(
			'post_type'     => 'jras_subscription',
			'no_found_rows' => true,
			'fields'        => 'ids',
			'meta_key'      => 'jras_target',
			'meta_value'    => $clean_target,
			'post_parent'   => $content_id,
		) );

		$delete_id = false;

		if ( $existing_targets->have_posts() ) {

			// Better than doing a two dimensional meta query
			foreach ( $existing_targets->posts as $existing_target_id ) {
				$existing_target_type = get_post_meta( $existing_target_id, 'jras_content_type', true );

				if ( $content_type === $existing_target_type ) {
					$delete_id = $existing_target_id;
				}
			}
		}

		if ( empty( $delete_id ) ) {
			return new WP_Error( 'create_subscription_target_not_found', esc_html__( 'Subscription target not found.', 'json-rest-api-subscriptions' ), array( 'status' => 400 ) );
		}

		$headers = $request->get_headers();

		$signature_header = ( ! empty( $headers['x_wp_subscription_signature'] ) && ! empty( $headers['x_wp_subscription_signature'][0] ) ) ? $headers['x_wp_subscription_signature'][0] : '';
		$signature = get_post_meta( $existing_target_id, 'jras_signature', true );

		if ( empty( $signature ) || $signature !== md5( $signature_header ) ) {
			return new WP_Error( 'create_subscription_signature_mismatch', esc_html__( 'Subscription signature mismatch.', 'json-rest-api-subscriptions' ), array( 'status' => 400 ) );
		}

		$response = $this->prepare_subscription_for_response( get_post( $delete_id ), $request );

		do_action( 'jras_delete_subscription', $clean_target, $delete_id, $request );

		wp_delete_post( $delete_id, true );

		return $response;
	}

	/**
	 * Update a subscription given a request
	 *
	 * @since  1.0
	 * @param  WP_REST_Request $request
	 * @return  WP_REST_Response
	 */
	public function update_subscription( $request ) {
		$clean_target = untrailingslashit( esc_url_raw( $request['target'] ) );

		$content_type = preg_replace( '#^.*/([^/]*)/subscriptions/?$#', '$1', $request->get_route() );

		$content_id = ( ! empty( $request['content_id'] ) ) ? (int) $request['content_id'] : 0;

		$valid_events = apply_filters( 'jras_valid_events', array( 'create', 'update', 'delete' ), $request );

		$events = $request['events'];
		if ( ! is_array( $events ) ) {
			$events = explode( ',', $events );
		}

		$clean_events = array();

		foreach ( $events as $event ) {
			$clean_event = str_replace( ' ', '', $event );
			if ( in_array( $clean_event, $valid_events ) ) {
				if ( ! empty( $content_id ) ) {
					if ( 'create' === $clean_event ) {
						// Can't create an existing piece of content
						continue;
					}
				}

				$clean_events[] = $clean_event;
			}
		}

		if ( empty( $clean_events ) ) {
			return new WP_Error( 'update_subscription_no_events', esc_html__( 'No subscription events.', 'json-rest-api-subscriptions' ), array( 'status' => 400 ) );
		}

		/**
		 * Account for if we are subscribing to a single piece of content
		 */

		if ( ! empty( $content_id ) ) {
			$content_subscription = get_post( $content_id );

			if ( empty( $content_subscription ) ) {
				return new WP_Error( 'update_subscription_content_not_found', esc_html__( 'Content not found for subscription.', 'json-rest-api-subscriptions' ), array( 'status' => 400 ) );
			}
		}

		/**
		 * Unfortunately, we have to do a meta query to find existing targets.
		 *
		 * @var WP_Query
		 */
		$existing_targets = new WP_Query( array(
			'post_type'     => 'jras_subscription',
			'no_found_rows' => true,
			'fields'        => 'ids',
			'meta_key'      => 'jras_target',
			'meta_value'    => $clean_target,
			'post_parent'   => $content_id,
		) );

		$update_id = false;

		if ( $existing_targets->have_posts() ) {

			// Better than doing a two dimensional meta query
			foreach ( $existing_targets->posts as $existing_target_id ) {
				$existing_target_type = get_post_meta( $existing_target_id, 'jras_content_type', true );

				if ( $content_type === $existing_target_type ) {
					$update_id = $existing_target_id;
				}
			}
		}

		if ( empty( $update_id ) ) {
			return new WP_Error( 'update_subscription_target_not_found', esc_html__( 'Subscription target not found.', 'json-rest-api-subscriptions' ), array( 'status' => 400 ) );
		}

		$headers = $request->get_headers();

		$signature_header = ( ! empty( $headers['x_wp_subscription_signature'] ) && ! empty( $headers['x_wp_subscription_signature'][0] ) ) ? $headers['x_wp_subscription_signature'][0] : '';
		$signature = get_post_meta( $existing_target_id, 'jras_signature', true );

		if ( empty( $signature ) || $signature !== md5( $signature_header ) ) {
			return new WP_Error( 'create_subscription_signature_mismatch', esc_html__( 'Subscription signature mismatch.', 'json-rest-api-subscriptions' ), array( 'status' => 400 ) );
		}

		do_action( 'jras_update_subscription', $clean_events, $clean_target, $update_id, $request );

		update_post_meta( $update_id, 'jras_events', $clean_events );

		$response = $this->prepare_subscription_for_response( get_post( $update_id ), $request );

		return $response;
	}

	/**
	 * Create a subscription given a request
	 *
	 * @since  1.0
	 * @param  WP_REST_Request $request
	 * @return  WP_REST_Response
	 */
	public function create_subscription( $request ) {

		$content_id = ( ! empty( $request['content_id'] ) ) ? (int) $request['content_id'] : 0;

		$valid_events = apply_filters( 'jras_valid_events', array( 'create', 'update', 'delete' ), $request );

		$events = $request['events'];
		if ( ! is_array( $events ) ) {
			$events = explode( ',', $events );
		}

		$clean_events = array();

		foreach ( $events as $event ) {
			$clean_event = str_replace( ' ', '', $event );
			if ( in_array( $clean_event, $valid_events ) ) {
				if ( ! empty( $content_id ) ) {
					if ( 'create' === $clean_event ) {
						// Can't create an existing piece of content
						continue;
					}
				}

				$clean_events[] = $clean_event;
			}
		}

		if ( empty( $clean_events ) ) {
			return new WP_Error( 'create_subscription_no_events', esc_html__( 'No subscription events.', 'json-rest-api-subscriptions' ), array( 'status' => 400 ) );
		}

		$clean_target = untrailingslashit( esc_url_raw( $request['target'] ) );

		$content_type = preg_replace( '#^.*/([^/]*)/subscriptions/?$#', '$1', $request->get_route() );

		/**
		 * Account for if we are subscribing to a single piece of content
		 */

		if ( ! empty( $content_id ) ) {
			$content_subscription = get_post( $content_id );

			if ( empty( $content_subscription ) ) {
				return new WP_Error( 'create_subscription_content_not_found', esc_html__( 'Content not found for subscription.', 'json-rest-api-subscriptions' ), array( 'status' => 400 ) );
			}
		}

		/**
		 * Unfortunately, we have to do a meta query to find existing targets.
		 */
		$existing_targets = new WP_Query( array(
			'post_type'     => 'jras_subscription',
			'no_found_rows' => true,
			'fields'        => 'ids',
			'meta_key'      => 'jras_target',
			'meta_value'    => $clean_target,
			'post_parent'   => $content_id,
		) );

		if ( $existing_targets->have_posts() ) {

			// Better than doing a two dimensional meta query
			foreach ( $existing_targets->posts as $existing_target_id ) {
				$existing_target_type = get_post_meta( $existing_target_id, 'jras_content_type', true );

				if ( $content_type === $existing_target_type ) {
					return new WP_Error( 'create_subscription_target_exists', esc_html__( 'Subscription target already exists.', 'json-rest-api-subscriptions' ), array( 'status' => 400 ) );
				}
			}
		}

		$subscription_id = wp_insert_post( array(
			'post_title'  => 'subscription ' . esc_url_raw( $request['target'] ),
			'post_type'   => 'jras_subscription',
			'post_status' => 'publish',
			'post_parent' => $content_id,
		) );

		if ( ! is_wp_error( $subscription_id ) ) {

			do_action( 'jras_create_subscription', $clean_events, $clean_target, $update_id, $request );

			$content_type_map = array(
				'posts' => 'post',
				'pages' => 'page',
			);

			update_post_meta( $subscription_id, 'jras_events', $clean_events );
			update_post_meta( $subscription_id, 'jras_target', $clean_target );
			update_post_meta( $subscription_id, 'jras_content_type', $content_type_map[$content_type] );

			$signature = wp_generate_password( 26, false, false );

			/**
			 * @todo: MD5 is insecure
			 */
			update_post_meta( $subscription_id, 'jras_signature', md5( $signature ) );

			$response = $this->prepare_subscription_for_response( get_post( $subscription_id ), $request );
			$response->header( 'X-WP-Subscription-Signature', $signature );
			$response->set_status( 201 );

			return $response;
		}

		return new WP_Error( 'create_subscription_fail', esc_html__( 'Cannot create subscription.', 'json-rest-api-subscriptions' ), array( 'status' => 500 ) );
	}

	/**
	 * Get a subscription given a request
	 *
	 * @since  1.0
	 * @param  WP_REST_Request $request
	 * @return  WP_REST_Response
	 */
	public function get_subscriptions( $request ) {
		$content_type = preg_replace( '#^.*/([^/]*)/subscriptions/?$#', '$1', $request->get_route() );

		/**
		 * Account for if we are subscribing to a single piece of content
		 */
		$content_id = ( ! empty( $request['content_id'] ) ) ? (int) $request['content_id'] : 0;

		if ( ! empty( $content_id ) ) {
			$content_subscription = get_post( $content_id );

			if ( empty( $content_subscription ) ) {
				return new WP_Error( 'get_subscriptions_content_not_found', esc_html__( 'Content not found for subscription.', 'json-rest-api-subscriptions' ), array( 'status' => 400 ) );
			}
		}

		$subscriptions_query = new WP_Query( array(
			'post_type'     => 'jras_subscription',
			'post_status'   => 'publish',
			'no_found_rows' => true,
			'meta_key'      => 'jras_content_type',
			'meta_value'    => $content_type,
			'post_parent'   => $content_id,
		) );

		$subscriptions = array();

		foreach ( $subscriptions_query->posts as $subscription ) {
			$data = $this->prepare_subscription_for_response( $subscription, $request );
			$subscriptions[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $subscriptions );

		return $response;
	}

	/**
	 * Format a subscription response given a subscription object
	 *
	 * @since  1.0
	 * @param  WP_Post         $subscription
	 * @param  WP_REST_Request $request
	 * @return  WP_REST_Response
	 */
	public function prepare_subscription_for_response( $subscription, $request ) {
		$data = array(
			'events' => get_post_meta( $subscription->ID, 'jras_events', true ),
			'target' => get_post_meta( $subscription->ID, 'jras_target', true ),
		);

		$response = rest_ensure_response( $data );

		/**
		 * @todo: Add links
		 */

		return apply_filters( 'jras_prepare_subscription', $response, $post, $request );
	}

	/**
	 * Get subscription schema which describes available fields
	 *
	 * @since  1.0
	 * @return  array
	 */
	public function get_subscription_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'subscription',
			'type'       => 'object',
			'properties' => array(
				'events' => array(
					'description' => esc_html__( 'List of events to subscribe to.' ),
					'type'        => 'array',
					'context'     => array( 'edit' ),
					'readonly'    => false,
				),
				'target' => array(
					'description' => __( 'URL to the object.' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'edit' ),
					'readonly'    => false,
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}
}

