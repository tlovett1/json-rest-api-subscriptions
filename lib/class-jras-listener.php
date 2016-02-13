<?php

class JRAS_Listener {

	/**
	 * Setup actions and filters
	 *
	 * @since 1.0
	 */
	private function setup() {
		add_action( 'transition_post_status', array( $this, 'update_post' ), 999, 3 );
		add_action( 'delete_post', array( $this, 'delete_post' ) );
	}

	/**
	 * Mark post for delete notifications. This method runs the risk of filling up options space with posts.
	 *
	 * @param int $post_id
	 * @since 1.0
	 */
	public function delete_post( $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$valid_post_types = wp_list_pluck( jras_subscription_namespace_post_types(), 'post_type' );

		if ( ! in_array( $post_type, $valid_post_types ) ) {
			return;
		}

		$deleted_posts = get_option( 'jras_deleted_posts', array() );

		$post = get_post( $post_id );
		$post->permalink = get_permalink( $post_id );

		$deleted_posts[ $post->ID ] = $post;

		update_option( 'jras_deleted_posts', $deleted_posts );
	}

	/**
	 * Mark post for create/update notifications. Unfortunately this is a slight race condition since posts
	 * can be updated simultaneously. This method also runs the risk of filling up options space with posts.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 * @since 1.0
	 */
	public function update_post( $new_status, $old_status, $post ) {
		global $importer;

		// If we have an importer we must be doing an import - let's abort
		if ( ! empty( $importer ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			// Bypass saving if doing autosave or post type is revision
			return;
		} elseif ( ! current_user_can( 'edit_post', $post->ID ) && ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) ) {
			// Bypass saving if user does not have access to edit post and we're not in a cron process
			return;
		}

		$post_type = get_post_type( $post->ID );

		$valid_post_types = wp_list_pluck( jras_subscription_namespace_post_types(), 'post_type' );

		if ( ! in_array( $post_type, $valid_post_types ) ) {
			return;
		}

		$non_deleted_post_statuses = array( 'publish' );

		// Store this for later in case we are deleting the post
		$post->permalink = get_permalink( $post->ID );
		$post->featured_image = wp_get_attachment_url( $post->ID );

		$user = get_user_by( 'id', $post->post_author );

		$post->author = array(
			'user_login'    => $user->user_login,
			'user_nicename' => $user->user_nicename,
			'user_url'      => $user->user_url,
			'display_name'  => $user->display_name,
		);

		if ( in_array( $new_status, $non_deleted_post_statuses ) ) {
			if ( in_array( $old_status, $non_deleted_post_statuses ) ) {
				// Updated post. non-deleted to non-deleted
				$updated_posts = get_option( 'jras_updated_posts', array() );
				$updated_posts[ $post->ID ] = $post;

				update_option( 'jras_updated_posts', $updated_posts );
			} else {
				// Created post. deleted to non-deleted
				$created_posts = get_option( 'jras_created_posts', array() );
				$created_posts[ $post->ID ] = $post;

				update_option( 'jras_created_posts', $created_posts );
			}
		} else {
			if ( in_array( $old_status, $non_deleted_post_statuses ) ) {
				// deleted to deleted
			} else {
				// Deleted posts. non-deleted to deleted
				$deleted_posts = get_option( 'jras_deleted_posts', array() );
				$deleted_posts[ $post->ID ] = $post;

				update_option( 'jras_deleted_posts', $created_posts );
			}
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
