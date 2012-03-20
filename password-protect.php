<?php
/**
 * @package Password_Protect
 * @version 1.0
 */
/*
Plugin Name: Password Protect
Plugin URI:
Description: Allows site admins to password protect site with one or more passwords
Author: Dan Quinn
Version: 1.0
Author URI: http://github.com/danielpquinn
*/

// Add new login page when plugin activates
register_activation_hook( __FILE__, 'pp_activate' );

function pp_activate() {
	pp_create_login_page();
}

function pp_create_login_page() {
	$new_page_title = 'Login';
	$new_page_content = '';
	$new_page_template = 'template-login.php';
	$page_check = get_page_by_title($new_page_title);
	$new_page = array(
		'post_type' => 'page',
		'post_title' => $new_page_title,
		'post_status' => 'publish',
		'post_author' => 1,
	);
	if(!isset($page_check->ID)){
		$new_page_id = wp_insert_post($new_page);
		if(!empty($new_page_template)){
			update_post_meta($new_page_id, '_wp_page_template', $new_page_template);
		}
		update_option( 'pp_login_page_id', $new_page_id, '', 'yes' );
	}
}

add_action('admin_menu', 'pp_menu');

function pp_menu() {
	add_options_page('Password Protect Options', 'Password Protect', 'manage_options', 'password-protect', 'pp_options');
}

function pp_options() {
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	echo '<div class="wrap">';
	echo '<h2>Password Protect</h2>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'pp_options' );
	do_settings_sections('pp');
	echo '<input class="button-primary" name="submit" type="submit" value="Save Passwords"></input>';
	echo '</form>';
	echo '</div>';
}

// Check if user is logged in on init
add_action( 'get_header', 'pp_authenticate');

// Check if user is logged in
function pp_authenticate() {
	$login_page = get_page( get_option( 'pp_login_page_id' ) );

	// If there's no login page, create a new one and update
	// pp_login_page_id option
	if( !$login_page ) {
		pp_create_login_page();
		$login_page = get_page( get_option( 'pp_login_page_id' ) );
	}
	session_start();
	if( !isset($_SESSION['pp_authenticated']) && !is_page( $login_page->ID ) ) {
		wp_redirect( get_bloginfo('url') . '/?p=' . $login_page->ID );
	}
}

// Template fallback
add_action("template_redirect", 'pp_theme_redirect');

function pp_theme_redirect() {
	global $wp_query;
	if( $wp_query->post->ID == get_option( 'pp_login_page_id' ) ) {
		$plugindir = dirname( __FILE__ );
		$templatefilename = 'template-login.php';
		if (file_exists(TEMPLATEPATH . '/' . $templatefilename)) {
			$return_template = TEMPLATEPATH . '/' . $templatefilename;
		} else {
			$return_template = $plugindir . '/' . $templatefilename;
		}

		// Get array of passwords
		$passwords = str_replace(' ','', get_option( 'pp_options' ) );
		$passwords = explode( ',', $passwords['passwords'] );

		// If password was correct, authenticate and redirect to home page
		$pass = $_GET['pass'];
		if( in_array( $pass, $passwords ) ) {
			session_start();
			$_SESSION['pp_authenticated'] = true;
			wp_redirect( get_bloginfo('url') );
		}
		
		do_theme_redirect($return_template);
	}
}

function do_theme_redirect($url) {
	global $post, $wp_query;
	if (have_posts()) {
		include($url);
		die();
	} else {
		$wp_query->is_404 = true;
	}
}

// Add the password protect settings
add_action('admin_init', 'pp_admin_init');

function pp_admin_init() {
	register_setting( 'pp_options', 'pp_options', 'pp_options_validate' );
	add_settings_section('pp_main', 'Password Protect Settings', 'pp_text', 'pp');
	add_settings_field('pp_passwords', 'Passwords', 'pp_setting_string', 'pp', 'pp_main');
}

function pp_text() {
	echo '<p>Enter passwords below, separated by commas. Whitespace will be ignored.';
}

function pp_setting_string() {
	$options = get_option('pp_options');
	echo "<input id='pp_passwords' name='pp_options[passwords]' size='40' type='text' value='{$options['passwords']}' />";
}

function pp_options_validate($input) {
	// Todo - add form validation
	return $input;
}

?>