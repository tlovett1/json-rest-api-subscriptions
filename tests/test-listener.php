<?php

class CCFTestPostCreation extends WP_UnitTestCase {

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
	 * Make sure created posts are properly marked for notifications
	 *
	 * @since  1.0
	 */
	public function testCreateMarkForNotification() {
		$post_id = wp_insert_post( array(
			'post_title'   => 'Test post',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$marked_for_create_notifications = get_option( 'jras_created_posts', array() );

		$this->assertTrue( ! empty( $marked_for_create_notifications[$post_id] ) );

		// Add a second post

		wp_insert_post( array(
			'post_title'   => 'Test post',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$marked_for_create_notifications = get_option( 'jras_created_posts', array() );

		$this->assertEquals( 2, count( $marked_for_create_notifications ) );
	}

	/**
	 * Make sure created posts are properly marked for notifications
	 *
	 * @since  1.0
	 */
	public function testUpdateMarkForNotification() {
		$post_id1 = wp_insert_post( array(
			'post_title'   => 'Test post',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$post_id2 = wp_insert_post( array(
			'post_title'   => 'Test post',
			'post_content' => 'test',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		wp_insert_post( array(
			'ID'          => $post_id2,
			'post_title'  => 'update',
			'post_status' => 'publish',
		) );

		$marked_for_update_notifications = get_option( 'jras_updated_posts', array() );

		$this->assertTrue( ! empty( $marked_for_update_notifications[$post_id2] ) );

		// Make sure if we update the same post twice, there is only one update

		wp_insert_post( array(
			'ID'          => $post_id2,
			'post_title'  => 'update',
			'post_status' => 'publish',
		) );

		$marked_for_update_notifications = get_option( 'jras_updated_posts', array() );

		$this->assertEquals( 1, count( $marked_for_update_notifications ) );

		// Add a second post

		wp_insert_post( array(
			'ID'          => $post_id1,
			'post_title'  => 'update',
			'post_status' => 'publish',
		) );

		$marked_for_update_notifications = get_option( 'jras_updated_posts', array() );

		$this->assertEquals( 2, count( $marked_for_update_notifications ) );
	}
}
