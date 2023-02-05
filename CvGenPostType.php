<?php
require_once "CVMetaBox.php";

class CvGenPostType {
	public function __construct() {
		add_action( 'init', [$this, 'cv_post_type'], 0);
		add_action( 'transition_post_status', [$this, 'prevent_publishing_posts_publicly'], 10, 3 );
		add_action( 'get_header', [$this, 'am_do_acf_form_head'], 1 );
		add_shortcode( 'cv_frontend_fields', [$this, 'wpdocs_footag_func'] );

        $this->nonce_name = 'wp_rest';
		$this->CVMetaBox = new CVMetabox();

		add_action( 'rest_api_init', function () {
			register_rest_route( 'cvgen/cvpost/', 'update', array(
				'methods' => 'POST',
				'callback' => [$this, 'api_update_post'],
				'current_user_id' => get_current_user_id(), // This will be pass to the rest API callback
			) );
		} );
	}

    function api_update_post($data) {
	    $current_user_id = $data->get_attributes()['current_user_id']; // !! this should come from php not js

	    // Validate nonce
	    if (!isset($data[$this->nonce_name]) && !wp_verify_nonce($data[$this->nonce_name], $this->nonce_name)) {
		    return ['status' => "fail", 'msg' => __('Invalid nonce', 'cvgen')];
	    }

        $cv = $this->get_current_users_cv($current_user_id);

        $fields = $this->CVMetaBox->meta_fields;
        $result = [];
        foreach($fields as $field) {
//            if ($field['type'] === 'repeatable') {
//                $data[$field['id']] = wp_json_encode($data[$field['id']]);
//            }

            if (update_post_meta($cv->ID, $field['id'], $data[$field['id']])) { // do update
                $result[] = $field['id'];
	        }
        }

	    return ['status' => "ok", 'msg' => "CV Updated", "updated_fields" => $result];
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
	function prevent_publishing_posts_publicly( $new_status, $old_status, $post ) {
		if ( $post->post_type == 'cv' && $new_status == 'publish' && $old_status  != $new_status ) {
			$post->post_status = 'private';
			wp_update_post( $post );
		}
	}

	public function get_current_users_cv($current_user_id_backup = null) {
		$current_user_id = wp_get_current_user()->ID;
        if (!$current_user_id)
            $current_user_id = $current_user_id_backup;

		if ($current_user_id) {
			$users_cvs = get_posts([
				'post_type'     => 'cv',
				'author'        =>  $current_user_id,
				'post_status' => array('publish', 'pending', 'draft', 'future', 'private', 'inherit')
			]);

			return $users_cvs[0] ?? null; // should not be more than one
		} else {
			return null;
		}
	}

	function data_to_javascript($cv) {
		return [
			'fields' => $this->CVMetaBox->meta_fields,
            'cv' => $cv,
            'nonce' => wp_create_nonce($this->nonce_name),
            'nonce_name' => $this->nonce_name,
            'meta' => get_post_meta($cv->ID)
		];
	}

	function wpdocs_footag_func( $atts ) {
		$cv = $this->get_current_users_cv();

		if (is_user_logged_in()) {
            if (!$cv) {
	            $current_user = wp_get_current_user();
                wp_insert_post([
                    'post_type' => 'cv',
                    'post_title' => 'CV: ' . $current_user->user_email,
                    'post_status' => 'private',
                    'post_author' => $current_user->ID
                ]);

	            $cv = $this->get_current_users_cv();

                if (!$cv) {
                    wp_die("Something went wrong!");
                }
            }

			if (!is_admin()) {
				wp_enqueue_style("cvgen_cvpost_frontend_style", plugin_dir_url(__FILE__) . 'build/main.css', [], '1.0');
				wp_enqueue_script( "cvgen_cvpost_frontend_react", plugin_dir_url( __FILE__ ) . 'build/cvpost.js', array(
					'wp-element'
				));
			}

			ob_start(); ?>

			<div id="cvpost_form">
                <pre style="display: none">
                    <?= wp_json_encode($this->data_to_javascript($cv))?>
                </pre>
			</div>

			<?php
			return ob_get_clean();
		} else {
			return "Authenticate to create or edit your CV";
		}
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