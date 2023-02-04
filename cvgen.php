<?php
/**
 * Plugin Name: CV generator plugin
 * Description: CV generator plugin
 * Version: 0.1
 * Author: MX
 **/
require __DIR__ . '/vendor/autoload.php';
require "CvGenLogin.php";
require "CvGenPostType.php";

if ( ! defined( 'ABSPATH' ) ) {exit; /* Exit if accessed directly.*/ }
const CVGEN_PLUGIN_NAME = 'cvgen/cvgen.php';

function dd(...$args) {
    foreach ($args as $arg) {
        dump($arg);
    }
    die();
}

function cvgen_mysql_time($unix_timestamp) {
	return wp_date("Y-m-d H:i:s", $unix_timestamp);
}

class cvgen {
    public function __construct()
    {
        $this->cvgenauth = new CvGenLogin();
//		$this->cvgen_post_type = new CvGenPostType();
    }

}

new cvgen();





//        add_action( 'admin_menu', [$this, 'cvgen_add_settings_page'], 0);


/**
public function settings_page() {
acf_form_head(); ?>

<div id="primary" class="content-area">
<div id="content">
<?php acf_form('new-event'); ?>
</div><!-- #content -->
</div><!-- #primary -->
<?php
}
 */


// add submenu page in the cv post type
//    public function cvgen_add_settings_page() {
//        add_submenu_page(
//            'edit.php?post_type=cv', //$parent_slug
//            'CV options', //$page_title
//            'CV options', //$menu_title
//            'edit_curriculum_vitaes', //$capability
//            'cv-something-special', //$menu_slug
//            [$this, 'settings_page'] //$function
//        );
//    }
