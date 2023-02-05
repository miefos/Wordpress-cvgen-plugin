<?php

require_once("cvgen.php");

class CvGenLogin
{
    private $otp_length = 6;
    private $expire_time = "+2 hours";

    public function __construct()
    {
        global $wpdb;
	    add_shortcode( 'register_or_login', [$this, 'cvgen_register_login_shortcode_html'] );
        add_action('activate_' . CVGEN_PLUGIN_NAME, [$this, 'onActivate']);

        $this->nonce_name = 'wp_rest';
	    $this->table_name_otp = $wpdb->prefix . 'cvgen_otp';

	    add_action( 'rest_api_init', function () {
		    register_rest_route( 'cvgen/auth/', 'send_otp', array(
			    'methods' => 'POST',
			    'callback' => [$this, 'validate_email_and_send_otp'],
		    ) );

		    register_rest_route( 'cvgen/auth/', 'attempt_otp', array(
			    'methods' => 'POST',
			    'callback' => [$this, 'validate_otp_and_login'],
		    ) );
	    } );

        add_action('after_setup_theme', function () {
	        if (!current_user_can('administrator') && !is_admin()) {
		        show_admin_bar(false);
	        }
        });

	    add_action('wp_logout', function () {
            wp_safe_redirect( home_url() );
            exit;
        });
    }

    function data_to_javascript() {
	    return [
		    'nonce' => wp_create_nonce($this->nonce_name),
		    'nonce_name' => $this->nonce_name,

		    'waiting_time_until_can_be_resent' => 30,
		    'waiting_time_until_info_about_can_be_resent_is_shown' => 10,
		    'email_label' => __('Enter your email', 'cvgen'),
		    'wait_until_can_be_resent' => __('You can resend code in ', 'cvgen'),
		    'resend_label' => __('Resend code', 'cvgen'),
		    'otp_label' => __('Received code', 'cvgen'),
		    'submit_email' => __('Send OTP code', 'cvgen'),
		    'submit_attempt_email_otp' => esc_attr__('Authenticate', 'cvgen'),
	    ];
    }

    function validate_otp_and_login($data) {
	    if ($result = $this->nonce_or_email_invalid($data)) {
		    return $result;
	    }

	    $email = $data['email'];
	    $email = is_email(sanitize_email($email));
        $otp = $data['otp'];
        $otp = intval(substr($otp, 0, $this->otp_length));
        if (!$this->otp_valid($otp, $email)) {
	        return ['status' => 'fail', 'msg' => __('OTP code is invalid', 'cvgen')];
        }

        return ['status' => 'ok', 'msg' => __('Authentication successful', 'cvgen')];
    }

	function otp_valid($otp, $email) {
		$email = is_email(sanitize_email($email));
		$user_id = get_user_by('email', $email)->ID;
		if (!$user_id) {
			return false;
		}

        global $wpdb;

		$query = $wpdb->prepare(
			"SELECT otp.otp, otp.ID, otp.user_id
         FROM $this->table_name_otp as otp
         WHERE 
             otp.otp = %d AND 
             otp.used_at IS NULL AND
             otp.deactivated = 0 AND
             otp.expire_at > UTC_TIMESTAMP AND
             otp.user_id = %d
         ORDER BY otp.created_at DESC
         ", $otp, $user_id);

        $row = $wpdb->get_row($query);

        if (!$row || !($row->otp == $otp)) { // the second scenario should not happen in any circumstances
            return false;
        }

        $wpdb->update($this->table_name_otp, ['used_at' => cvgen_mysql_time(time())], ['ID' => $row->ID]);

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        return true;
	}

    function validate_email_and_send_otp($data) {
        if ($result = $this->nonce_or_email_invalid($data)) {
            return $result;
        }

	    $email = $data['email'];
	    $email = is_email(sanitize_email($email));

	    if ($result = $this->send_otp($email)) {
		    return $result;
	    } else {
		    return ['status' => "fail", 'msg' => "OTP code could not be sent. Please contact system administrator."];
        }
    }

    function send_otp($email) {
        $email = is_email(sanitize_email($email));
        if (email_exists($email)) {
            return $this->send_otp_to_registered_user($email);
        } else {
            return $this->register_user_and_send_otp($email);
        }
    }

    function send_otp_to_registered_user($email) {
	    $email = is_email(sanitize_email($email));
	    $user_id = get_user_by('email', $email)->ID;
	    if (!$user_id) {
		    return ['status' => "fail", 'msg' => "Email is not registered. Please contact system administrator."];
	    }

	    if (!($otp = $this->insert_new_otp($user_id))) {
		    return ['status' => "fail", 'msg' => "Error generating OTP code. Please contact system administrator."];
        }

	    if (wp_mail($email, "Login attempt", "Login attempt. OTP is " . $otp)) {
		    return ['status' => 'ok', 'msg' => __("Login attempt, mail sent", "cvgen")];
	    } else {
		    return ['status' => 'fail', 'msg' => __("Error sending OTP code. Please contact system administrator.", "cvgen")];
	    }
    }

    function register_user_and_send_otp($email) {
	    $email = is_email(sanitize_email($email));
        if (email_exists($email)) {
	        return ['status' => "fail", 'msg' => "Email already registered. Please contact system administrator."];
        }

	    $username = sanitize_user($this->get_random_unique_username($email));
	    $pass = wp_generate_password(24, true, true);

	    $user_id = wp_create_user($username, $pass, $email);

        if (!($otp = $this->insert_new_otp($user_id))) {
	        return ['status' => "fail", 'msg' => "Error generating OTP code. Please contact system administrator."];
        }

	    if (is_wp_error($user_id)) {
		    return ['status' => "fail", 'msg' => "Error registering your user. Please contact system administrator."];
	    } else {
		    if (wp_mail($email, "Registration successful", "The registration is completed. Your OTP code is " . $otp)) {
			    return ['status' => "ok", 'msg' => "OTP code sent to your email."];
		    } else {
			    return ['status' => "fail", 'msg' => "Error sending OTP code to your email. Please contact system administrator."];
		    }
        }
    }

    function nonce_or_email_invalid($data) {
	    // Validate nonce
	    if (!isset($data[$this->nonce_name]) && !wp_verify_nonce($data[$this->nonce_name], $this->nonce_name)) {
		    return ['status' => "fail", 'msg' => __('Invalid nonce', 'cvgen')];
	    }

	    // Set email
	    if (!isset($data['email'])) {
		    return ['status' => "fail", 'msg' => __('Email not set', 'cvgen')];
	    }

	    $email = $data['email'];
	    $email = is_email(sanitize_email($email));

	    // validate email
	    if (!$email) {
		    return ['status' => "fail", 'msg' => __('Email format invalid', 'cvgen')];
	    }

        return false;
    }

    function insert_new_otp($user_id) {
	    global $wpdb;

        // deactivate previous user's OTP codes
	    if (get_user_by('ID', intval($user_id))) {
		    if ($wpdb->update($this->table_name_otp, ['deactivated' => 1], ['user_id' => $user_id]) === false) {
			    return false;
		    }
	    }

        // generate new code
	    $otp = $this->generate_numeric_otp($this->otp_length);
        $data = [
	        'otp' => $otp,
	        'created_at' => cvgen_mysql_time(time()),
	        'expire_at' => cvgen_mysql_time(strtotime($this->expire_time)),
	        'user_id' => intval( $user_id )
        ];

	    if ($wpdb->insert($this->table_name_otp, $data) !== 1) {
		    return false;
	    }

	    return $otp;
    }

    /**
     * Function that returns HTML for login
     *
     * @return false|string
     */
    function cvgen_register_login_shortcode_html() {
        if (!is_user_logged_in()) {
            if (!is_admin()) {
                wp_enqueue_style("cvgen_auth_frontend_style", plugin_dir_url(__FILE__) . 'build/main.css', [], '1.0');
                wp_enqueue_script( "cvgen_auth_frontend_react", plugin_dir_url( __FILE__ ) . 'build/auth.js', array(
                    'wp-element'
                ));
            }

            ob_start(); ?>

            <div id="auth_form" class="alignleft" style="width:100%">
                <pre style="display: none">
                    <?= wp_json_encode($this->data_to_javascript())?>
                </pre>
            </div>

            <?php
            return ob_get_clean();
        } else {
            return "<a href='" . wp_logout_url() . "'>" . __("Logout", "cvgen") . "</a>";
        }
    }

	/**
	 * Function to generate OTP
	 */
	function generate_numeric_otp($n) {
		$generator = "123456789";
		$result = "";

		for ($i = 1; $i <= $n; $i++) {
			$result .= substr($generator, (rand()%(strlen($generator))), 1);
		}

		return intval($result);
	}

	/**
	 * Function that generates random `username`
	 *
	 * @param string $prefix
	 *
	 * @return string
	 */
	function get_random_unique_username($email, string $prefix = '' ){
		$prefix .= substr(strstr($email, '@', true), 0, 5); // from namename@user.com would return namen (the first five chars)
		do {
			$rnd_str = sprintf("%06d", mt_rand(1, 999999));
			$user_exists = username_exists( $prefix . $rnd_str );
		} while( $user_exists > 0 );
		return $prefix . $rnd_str;
	}

	/**
	 * Setup plugin
	 *
	 * @return void
	 */
	function onActivate() {
		global $wpdb;
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		// create OTP table.
		// user_id - used for existing users
		dbDelta("CREATE TABLE $this->table_name_otp (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned,
            otp int(6) NOT NULL,
            expire_at datetime NOT NULL,
            created_at datetime NOT NULL,
            used_at datetime,
            deactivated tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            FOREIGN KEY  (user_id) REFERENCES $wpdb->users(id)
        )");

		// validate that tables were created
		// if not present in db, then deactivate plugin and die with error
		$table_show_otp = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->table_name_otp ) );

		if ($wpdb->get_var($table_show_otp) !== $this->table_name_otp) {
			deactivate_plugins(CVGEN_PLUGIN_NAME);
			$referer_url = wp_get_referer();
			wp_die("There was error setting up the plugin. Plugin deactivated. <br /><a href='$referer_url'>Back</a>");
		}
	}
}