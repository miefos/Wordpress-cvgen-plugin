<?php

class CvGenPostType {

	public function __construct() {
		add_action( 'init', [$this, 'cv_post_type'], 0);
		add_action( 'transition_post_status', [$this, 'wpse118970_post_status_new'], 10, 3 );
		add_action( 'get_header', [$this, 'am_do_acf_form_head'], 1 );
		add_shortcode( 'cv_frontend_fields', [$this, 'wpdocs_footag_func'] );
	}

	/**
	 * Add required acf_form_head() function to head of page
    */
	function am_do_acf_form_head() {
		if ( !is_admin() )
			acf_form_head();
	}

	/**
	 * This method prevents publishing posts publicly
	 * - instead they are published privately
	 *
	 * @param $new_status
	 * @param $old_status
	 * @param $post
	 * @return void
	 */
	function wpse118970_post_status_new( $new_status, $old_status, $post ) {
		if ( $post->post_type == 'cv' && $new_status == 'publish' && $old_status  != $new_status ) {
			$post->post_status = 'private';
			wp_update_post( $post );
		}
	}

	public function get_current_users_cv_id() {
		$current_user_id = wp_get_current_user()->ID;

		if ($current_user_id) {
			$users_cvs = get_posts([
				'post_type'     => 'cv',
				'author'        =>  $current_user_id,
				'post_status' => array('publish', 'pending', 'draft', 'future', 'private', 'inherit')
			]);

			if (!empty($users_cvs)) {
				$the_cv = $users_cvs[0]; // should not be more than one but it is not restricted also

				return $the_cv->ID;
			} else {
				return null;
			}
		} else {
			return null;
		}
	}

	function wpdocs_footag_func( $atts ) {
		// Register form.
		acf_register_form(array(
			'id'       => 'new-event',
			'post_id'  => 'new_post',
			'new_post' => array(
				'post_type'   => 'cv'
			),
			'post_title'  => false,
			'post_content'=> false,
		));

		acf_form_head();
		$cv_id = $this->get_current_users_cv_id();

		ob_start();
		if (!$cv_id) {
			echo "NEW";
			acf_form('new-event'); // new form
		} else {
			echo "EXISTING" . $cv_id;
			acf_form(['id' => 'new-event', 'post_id' => $cv_id]); // existing form
		}
		$ret = ob_get_contents();
		ob_end_clean();

		return $ret;
	}

	/**
	 * Register CV post type
	 *
	 * @return void
	 */
	public function cv_post_type() {
		$labels = array(
			'name'                  => _x( 'Curriculum Vitaes', 'Post Type General Name', 'cv_generator' ),
			'singular_name'         => _x( 'Curriculum Vitae', 'Post Type Singular Name', 'cv_generator' ),
			'menu_name'             => __( 'Curriculum Vitae', 'cv_generator' ),
			'name_admin_bar'        => __( 'Curriculum Vitae', 'cv_generator' ),
			'archives'              => __( 'Curriculum Vitae Archives', 'cv_generator' ),
			'attributes'            => __( 'Curriculum Vitae Attributes', 'cv_generator' ),
			'parent_item_colon'     => __( '', 'cv_generator' ),
			'all_items'             => __( 'All Curriculum Vitae', 'cv_generator' ),
			'add_new_item'          => __( 'Add New Curriculum Vitae', 'cv_generator' ),
			'add_new'               => __( 'Add Curriculum Vitae', 'cv_generator' ),
			'new_item'              => __( 'New Curriculum Vitae', 'cv_generator' ),
			'edit_item'             => __( 'Edit Curriculum Vitae', 'cv_generator' ),
			'update_item'           => __( 'Update Curriculum Vitae', 'cv_generator' ),
			'view_item'             => __( 'View Curriculum Vitae', 'cv_generator' ),
			'view_items'            => __( 'View Curriculum Vitae', 'cv_generator' ),
			'search_items'          => __( 'Search Curriculum Vitae', 'cv_generator' ),
			'not_found'             => __( 'Curriculum Vitae not found', 'cv_generator' ),
			'not_found_in_trash'    => __( 'Curriculum Vitae not found in Trash', 'cv_generator' ),
			'insert_into_item'      => __( '', 'cv_generator' ),
			'uploaded_to_this_item' => __( '', 'cv_generator' ),
			'items_list'            => __( 'Curriculum Vitae list', 'cv_generator' ),
			'items_list_navigation' => __( 'Items list navigation', 'cv_generator' ),
			'filter_items_list'     => __( 'Filter items list', 'cv_generator' ),
		);
		$args = array(
			'label'                 => __( 'Curriculum Vitae', 'cv_generator' ),
			'description'           => __( 'Curriculum Vitae', 'cv_generator' ),
			'labels'                => $labels,
			'supports'              => array( 'author', 'title' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 5,
			'menu_icon'             => 'dashicons-text-page',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'capability_type'       => 'curriculum_vitae',
			'show_in_rest'          => false,
		);
		register_post_type( 'cv', $args );
	}
}