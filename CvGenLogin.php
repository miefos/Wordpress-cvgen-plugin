<?php

require_once("cvgen.php");

class CvGenLogin
{
    private WP_Error $errors;
    private $otp_length = 6;
    private $expire_time = "+2 hours";

    public function __construct()
    {
        global $wpdb;
	    add_shortcode( 'register_or_login', [$this, 'cvgen_register_login_shortcode_html'] );
        add_action('activate_' . CVGEN_PLUGIN_NAME, [$this, 'onActivate']);

        $this->nonce_name = 'wp_rest';
	    $this->table_name_otp = $wpdb->prefix . 'cvgen_otp';
	    $this->table_name_user_invitations = $wpdb->prefix . 'cvgen_user_invitations';
        $this->status_register_ok = "register_ok";
        $this->status_login_ok = "login_ok";
        $this->status_fail = "fail";
        $this->status_ok = "ok";
        $this->status_registration_completed = "registration_completed";
        $this->status_login_completed = "login_completed";

	    global $errors;
        if (!is_wp_error($errors)) {
            $errors = new WP_Error();
        }

        $this->errors = $errors;
	    add_action( 'rest_api_init', function () {
		    register_rest_route( 'cvgen/auth/', 'send_otp', array(
			    'methods' => 'POST',
			    'callback' => [$this, 'my_awesome_func'],
		    ) );
	    } );
	    add_action( 'rest_api_init', function () {
		    register_rest_route( 'cvgen/auth/', 'attempt_otp', array(
			    'methods' => 'POST',
			    'callback' => [$this, 'my_other_awesome_func'],
		    ) );
	    } );
    }

    function my_other_awesome_func($data) {
	    if ($result = $this->nonce_or_email_invalid($data)) {
		    return $result;
	    }

        return ['status' => 'ok', 'msg' => __('Attempt success!', 'cvgen')];
    }

    function my_awesome_func($data) {
        if ($result = $this->nonce_or_email_invalid($data)) {
            return $result;
        }

        //
        // TODO should send OTP here
        //

	    return ['status' => "ok", 'msg' => null];
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




    function cvgen_register_user_from_invitation($email) {
	    $username = substr(strstr($email, '@', true), 0, 5); // from namename@user.com would return namen (the first five chars)
	    $username = sanitize_user($this->get_random_unique_username($username));
	    $pass = wp_generate_password(24, true, true);

	    $user = wp_create_user($username, $pass, $email);
        if (is_wp_error($user)) {
            wp_die("could not create an user!");
        } else {
            if (wp_mail($email, "Registration successful", "The registration is completed.")) {
                wp_die("registered and mail sent");
            } else {
                wp_die("err mail sending");
            }
        }
    }

    function insert_new_otp($user_id = null, $email = null) {
	    global $wpdb;

        $this->deactivate_previous_otp_tokens($user_id, $email);
	    $otp = $this->generate_numeric_otp($this->otp_length);
        $data = [
	        'otp' => $otp,
	        'created_at' => cvgen_mysql_time(time()),
	        'expire_at' => cvgen_mysql_time(strtotime($this->expire_time)),
        ];

        if ($user_id) {
	        $data['user_id'] = intval( $user_id );
        }

	    $otp_rows_inserted = $wpdb->insert($this->table_name_otp, $data);

	    if ($otp_rows_inserted !== 1) {
		    return false;
	    }

	    return [$wpdb->insert_id, $otp];
    }

	/**
	 * @param $user_id
	 * @param $email
	 */
    function deactivate_previous_otp_tokens($user_id, $email) {
        global $wpdb;

        // deactivate otp tokens with user_id attached
        $user_id = intval($user_id);
        if ($user_id && get_user_by('ID', $user_id)) {
            if ($wpdb->update($this->table_name_otp, ['deactivated' => 1], ['user_id' => $user_id]) === false) {
                wp_die(__("Error happened 13000", "cvgen"));
            }
        }

	    // deactivate otp tokens from invitations
	    $email = sanitize_email($email);
        if ($email) {
            $sql = $wpdb->prepare("
                UPDATE $this->table_name_otp as otp 
                SET deactivated = 1
                WHERE EXISTS (
                    SELECT 1 FROM $this->table_name_user_invitations as inv
                    WHERE inv.email = %s AND inv.otp_id = otp.id
                );
            ", $email);
            $result = $wpdb->query($sql);
            if ($result === false) {
	            wp_die(__("Error happened 13001", "cvgen"));
            }
        }
    }
    
    /**
     * Function that creates new invitation and sends OTP token to user's email
     *
     * @param $email
     *
     * @return string[]
     */
    function create_user_invitation($email) {
        $email = sanitize_email($email);

        global $wpdb;
        [$otp_id, $otp] = $this->insert_new_otp(null, $email);

        if (!$otp_id || !$otp) {
            wp_die("Unexpected error happened 2300");
        }

        $invitation_rows_inserted = $wpdb->insert($this->table_name_user_invitations, [
            'otp_id' => $otp_id,
            'email' => $email,
            'created_at' => cvgen_mysql_time(time()),
        ]);

        if ($invitation_rows_inserted !== 1) {
	        wp_die("Unexpected error happened 1200!");
        }

        if (wp_mail($email, "Registration successful", "Registration successful. OTP is " . $otp)) {
            $status = $this->status_register_ok;
            $msg = __("Registration successful. OTP is sent to email.", "cvgen");
        } else {
	        $this->errors->add("error_sending_email", "Error sending mail. Please contact system administrator.");
        }

	    return [$status ?? $this->status_fail, $msg ?? ""];
    }

    /**
     * Function that logins user and sends OTP token to user's email
     *
     * @param $email
     *
     * @return string[]
     */
    function cvgen_login_user($email) {
        $email = sanitize_email($email);
        $user_id = get_user_by_email($email)->ID;
        [$otp_id, $otp] = $this->insert_new_otp($user_id);
        if (wp_mail($email, "Login attempt", "Login attempt. OTP is " . $otp)) {
            return [$this->status_login_ok, __("Login attempt, mail sent", "cvgen")];
        } else {
	        return [$this->status_fail, __("Error sending OTP code. Please contact system administrator.", "cvgen")];
        }
    }

	/**
	 * @param $email
	 *
	 * @return array
	 */
    function use_otp($email) {
        $email = sanitize_email($email);
	    $otp = $this->sanitize_otp($_POST['otp']);
        global $wpdb;
        $invitations_query = $wpdb->prepare(
	        "SELECT otp.otp, otp.ID, inv.ID as inv_ID
         FROM $this->table_name_user_invitations as inv
         LEFT JOIN $this->table_name_otp as otp
         ON inv.otp_id = otp.id
         WHERE 
             inv.email = %s AND 
             inv.created_user_id IS NULL AND
             otp.otp = %d AND 
             otp.used_at IS NULL AND
             otp.deactivated = 0 AND
             otp.expire_at > UTC_TIMESTAMP
         ORDER BY otp.created_at DESC
         ", $email, $otp);


	    $user = get_user_by('email', $email);
	    $login_query = $wpdb->prepare(
		    "SELECT otp.otp, otp.ID, otp.user_id
         FROM $this->table_name_otp as otp
         WHERE 
             otp.otp = %d AND 
             otp.used_at IS NULL AND
             otp.deactivated = 0 AND
             otp.expire_at > UTC_TIMESTAMP AND
             otp.user_id = %d
         ORDER BY otp.created_at DESC
         ", $otp, $user ? $user->ID : null);

	    if (isset($_POST['action_type']) || !in_array($_POST['action_type'], [$this->status_register_ok, $this->status_login_ok])) {
		    $action_type = $_POST['action_type'];
		    $query = match ($action_type) {
			    $this->status_login_ok => $login_query,
			    $this->status_register_ok => $invitations_query,
		    };

            $row = $wpdb->get_row($query);

            if (!$row) {
	            return [$this->status_fail, __("Incorrect OTP code", "cvgen")];
            }

            if (!$row->otp == $otp) { // this should not happen in any scenario
                wp_die(__("Unexpected error happened 13020!", "cvgen"));
            }

            $wpdb->update($this->table_name_otp, ['used_at' => cvgen_mysql_time(time())], ['ID' => $row->ID]);

            if ($this->status_register_ok === $action_type) {
                $username = $this->get_random_unique_username($email);
	            $pass = wp_generate_password(24, true, true);
                $user_id = wp_create_user($username, $pass, $email);
                if (is_wp_error($user_id)) {
	                return [$this->status_fail, __("Unexpected error 1220", "cvgen")];
                }
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
                $invitation_ID = intval($row->inv_ID);
                $wpdb->update($this->table_name_user_invitations, ['created_user_id' => $user_id], ['ID' => $invitation_ID]);
	            wp_redirect("/");
                die();
	            return [$this->status_registration_completed, null];
            } else if ($this->status_login_ok === $action_type) {
	            if ($user->ID != $row->user_id) {
		            return [$this->status_fail, __("Unexpected error 10000 happened.", "cvgen")];
                }
                $user_id = $user->ID;
	            wp_set_current_user($user_id);
	            wp_set_auth_cookie($user_id);
                wp_redirect("/");
                die();
	            return [$this->status_login_completed, null];
            } else {
	            return [$this->status_fail, __("Invalid action type 1210", "cvgen")];
            }
	    } else {
		    return [$this->status_fail, __("Invalid action type 1210", "cvgen")];
	    }
    }

	/**
	 * @return array|null[]|string[]
	 */
    function validate_request() {
        // Not POST request
	    if ( 'POST' !== filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_STRING ) ) {
		    return [null, null];
	    }

        // Validate nonce
        if (!$this->wpa_isset_valid_nonce()) {
	        return [$this->status_fail, __("Invalid nonce", "cvgen")];
	    }

        // Set email
        if (!isset($_POST['email'])) {
	        return [$this->status_fail, __("Email address not set", "cvgen")];
        }

        $email = sanitize_email($_POST['email']);

	    // validate email
	    if (!is_email($email)) {
		    return [$this->status_fail, __("Invalid email", "cvgen")];
	    }

        $_SESSION['email'] = $email;

	    // If OTP set
	    if (isset($_POST['otp'])) {
            [$status, $msg] = $this->use_otp($email);
            if ($status === $this->status_fail) {
                $this->errors->add("invalid otp", $msg);
            } else if ($status === $this->status_register_ok) {
                dd("Register ok!");
            } else if ($status === $this->status_login_ok) {
                dd("Login ok!");
            }
	    }

        // OTP is not set
        else {
	        // check if email exists
	        if ($this->wpa_exists_email($email)) {
		        return $this->cvgen_login_user($email);
	        } else {
                return $this->create_user_invitation($email);
	        }
        }

//	    return [$this->status_fail, __("Invalid OTP code", "cvgen")];
        return [null, null];
    }

    /**
     * Function that returns HTML for login
     *
     * @return false|string
     */
    function cvgen_register_login_shortcode_html() {
        if (!is_admin()) {
	        wp_enqueue_style("cvgen_auth_frontend_style", plugin_dir_url(__FILE__) . 'build/auth.css', [], rand(0, 100));
	        wp_enqueue_script( "cvgen_auth_frontend_react", plugin_dir_url( __FILE__ ) . 'build/auth.js', array(
		        'wp-element'
	        ));
        }

	    [$status, $msg] = $this->validate_request();
        ob_start(); ?>

        <div id="auth_form">
            <pre style="display: none">
                <?= wp_json_encode([
                        'nonce' => wp_create_nonce($this->nonce_name),
                        'nonce_name' => $this->nonce_name,

                        'email_label' => __('Enter your email', 'cvgen'),
                        'otp_label' => __('Received code', 'cvgen'),
                        'submit_email' => __('Submit email', 'cvgen'),
                        'submit_attempt_email_otp' => esc_attr__('Authenticate', 'cvgen'),
                    ])?>
            </pre>
        </div>

        <?php
        return ob_get_clean();

	    [$status, $msg] = $this->validate_request();
        ob_start(); ?>

        <form name="cvgen_login" id="cvgen_login" action="" method="post">
            <?php
            global $errors;
            if ($errors->has_errors()):
                foreach ($errors->errors as $error): ?>
                    <div style="color:red;">
                        <?=$error[0]?>
                    </div>
            <?php
                endforeach;
            endif;
            if ($status) {
                echo $msg;
            }
            ?>

            <p>
                <?php
                    // login form
                    if (isset($status) && in_array($status, [$this->status_register_ok, $this->status_login_ok])): ?>
                        <label for="otp"><?= __("OTP code", "cvgen"); ?></label>
                        <input type="number" name="otp" id="otp" />
                        <input type="text" name="email" id="email" value="<?= $_SESSION['email'] ?? ''?>" hidden />
                        <input type="text" name="action_type" id="action_type" value="<?= $status ?>" hidden />
                    <?php
                    else: ?>
                        <label for="email"><?= __("Email", "cvgen"); ?></label>
                        <input type="text" name="email" id="email" value="email@email.com" />
                    <?php endif;
                ?>
                <input type="submit" name="wpa-submit" id="wpa-submit" class="button-primary" value="<?php esc_attr_e('Log In', 'cvgen'); ?>" />
            </p>
            <?php do_action('wpa_login_form'); ?>
            <?php wp_nonce_field('cvgen_auth', 'cvgen_nonce') ?>

        </form>

        <?php
        return ob_get_clean();
    }

	/**
	 * @param $otp
	 *
	 * @return mixed
	 */
    function sanitize_otp($otp) {
	    $otp = substr($otp, 0, $this->otp_length);
        return intval($otp);
    }

    /**
     * Function that validates nonce
     *
     * @return bool
     */
    function wpa_isset_valid_nonce() {
        if (isset($_POST['cvgen_nonce']) && $nonce = sanitize_key($_POST['cvgen_nonce'])) {
            if (wp_verify_nonce($nonce, 'cvgen_auth')) {
                return true;
            } else {
                $this->errors->add('invalid_account', __('Some error happened. Please try again.', 'cvgen'));
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Function that checks if a user with such email exists
     *
     * @param $email
     * @return false|true
     */
    function wpa_exists_email($email){
        return email_exists(sanitize_email($email));
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
	 * Function that generates random username
	 *
	 * @param $prefix
	 * @return string
	 */
	function get_random_unique_username($email, $prefix = '' ){
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

		// create user invitations table
		dbDelta("CREATE TABLE $this->table_name_user_invitations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            otp_id bigint(20) unsigned NOT NULL,
            email varchar(100) NOT NULL,
            created_at datetime NOT NULL,
            created_user_id bigint(20) unsigned,
            PRIMARY KEY  (id),
            FOREIGN KEY  (otp_id) REFERENCES $this->table_name_otp(id),
            FOREIGN KEY  (created_user_id) REFERENCES $wpdb->users(id)
        )");

		// validate that tables were created
		// if not present in db, then deactivate plugin and die with error
		$table_show_otp = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->table_name_otp ) );
		$table_show_invitations = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->table_name_user_invitations ) );

		if ($wpdb->get_var($table_show_otp) !== $this->table_name_otp || $wpdb->get_var($table_show_invitations) !== $this->table_name_user_invitations) {
			deactivate_plugins(CVGEN_PLUGIN_NAME);
			$referer_url = wp_get_referer();
			wp_die("There was error setting up the plugin. Plugin deactivated. <br /><a href='$referer_url'>Back</a>");
		}
	}
}