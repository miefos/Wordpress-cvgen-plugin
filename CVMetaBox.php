<?php

// Meta Box Class: CVMetaBox
// Get the field value: $metavalue = get_post_meta( $post_id, $field_id, true );
class CVMetaBox{
	private array $screen = array( 'cv', );

	public array $meta_fields = array(
		array(
			'label' => 'Name',
			'id' => 'Name_text',
			'type' => 'text',
		),
		array(
			'label' => 'Surname',
			'id' => 'Surname_text',
			'type' => 'text',
		),
		array(
			'label' => 'Phone',
			'id' => 'Phone_tel',
			'type' => 'tel',
		),
		array(
			'label' => 'Languages',
			'id' => 'Languages_repeatable',
			'type' => 'repeatable',
			'inner_fields' => array(
				array(
					'label' => 'Language name',
					'id' => 'Language_name_select',
					'type' => 'select',
					'options' => array(
						'English', 'Latvian', 'Russian'
					)
				),
				array(
					'label' => 'Proficiency',
					'id' => 'Proficiency_select',
					'type' => 'select',
					'options' => array(
						'Native', 'Excellent', 'Good', 'Average', 'Basics'
					)
				)
			)
		)
	);

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_fields' ) );
	}

	public function add_meta_boxes() {
		foreach ( $this->screen as $single_screen ) {
			add_meta_box(
				'CV',
				__( 'CV', 'textdomain' ),
				array( $this, 'meta_box_callback' ),
				$single_screen,
				'normal',
				'default'
			);
		}
	}

	public function meta_box_callback( $post ) {
		wp_nonce_field( 'CV_data', 'CV_nonce' );
		$this->field_generator( $post );
	}

	public function field_generator( $post ) {
		echo $this->generate_fields($post);
	}

	public function generate_fields( $post ) {
		$output = '';
		foreach ( $this->meta_fields as $meta_field ) {
			$label = '<label for="' . $meta_field['id'] . '">' . $meta_field['label'] . '</label>';
			$meta_value = get_post_meta( $post->ID, $meta_field['id'], true );
			if ( empty( $meta_value ) ) {
				if ( isset( $meta_field['default'] ) ) {
					$meta_value = $meta_field['default'];
				}
			}
			switch ( $meta_field['type'] ) {
				case 'radio':
					$input = '<fieldset>';
					$input .= '<legend class="screen-reader-text">' . $meta_field['label'] . '</legend>';
					$i = 0;
					foreach ( $meta_field['options'] as $key => $value ) {
						$meta_field_value = !is_numeric( $key ) ? $key : $value;
						$input .= sprintf(
							'<label><input %s id=" %s" name="%s" type="radio" value="%s"> %s</label>%s',
							$meta_value === $meta_field_value ? 'checked' : '',
							$meta_field['id'],
							$meta_field['id'],
							$meta_field_value,
							$value,
							$i < count( $meta_field['options'] ) - 1 ? '<br>' : ''
						);
						$i++;
					}
					$input .= '</fieldset>';
					break;

				case 'tel':
				case 'text':
					$input = sprintf(
						'<input %s id="%s" name="%s" type="%s" value="%s">',
						$meta_field['type'] !== 'color' ? 'style="width: 100%"' : '',
						$meta_field['id'],
						$meta_field['id'],
						$meta_field['type'],
						$meta_value
					);
					break;

				default:
					$input = "Field type not recognized!";
			}
			$output .= $this->format_rows( $label, $input );
		}
		return '<table class="form-table"><tbody>' . $output . '</tbody></table>';
	}

	public function format_rows( $label, $input ) {
		return '<tr><th>'.$label.'</th><td>'.$input.'</td></tr>';
	}

	public function save_fields( $post_id ) {
		if ( ! isset( $_POST['CV_nonce'] ) )
			return $post_id;
		$nonce = $_POST['CV_nonce'];
		if ( !wp_verify_nonce( $nonce, 'CV_data' ) )
			return $post_id;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;
		foreach ( $this->meta_fields as $meta_field ) {
			if ( isset( $_POST[ $meta_field['id'] ] ) ) {
				switch ( $meta_field['type'] ) {
					case 'email':
						$_POST[ $meta_field['id'] ] = sanitize_email( $_POST[ $meta_field['id'] ] );
						break;
					case 'text':
						$_POST[ $meta_field['id'] ] = sanitize_text_field( $_POST[ $meta_field['id'] ] );
						break;
				}
				update_post_meta( $post_id, $meta_field['id'], $_POST[ $meta_field['id'] ] );
			} else if ( $meta_field['type'] === 'checkbox' ) {
				update_post_meta( $post_id, $meta_field['id'], '0' );
			}
		}
	}
}
