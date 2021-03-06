<?php /*

**************************************************************************

Plugin Name:  Storyform
Plugin URI:   http://storyform.co/docs/wordpress
Version:      0.4.0
Description:  Plugin to enable Storyform on select posts. Works with both SEO and non-SEO permalinks.
Author:       Storyform
Author URI:   http://storyform.co

**************************************************************************/

global $storyform_version;
$storyform_version = '0.4.0';

require_once( dirname( __FILE__ ) . '/config.php');
require_once( dirname( __FILE__ ) . '/class-storyform-options.php');
require_once( dirname( __FILE__ ) . '/editor/storyform-editor.php' );
require_once( dirname( __FILE__ ) . '/media/storyform-media.php' );
require_once( dirname( __FILE__ ) . '/class-storyform.php');
require_once( dirname( __FILE__ ) . '/class-storyform-color.php');
require_once( dirname( __FILE__ ) . '/class-storyform-settings-page.php');
require_once( dirname( __FILE__ ) . '/class-storyform-admin-meta-box.php');

$storyform_plugin_identifier = 'storyform-wp-plugin-' . $storyform_version;

$storyform = Storyform::get_instance()->init();

if( is_admin() ) {
	$storyform_settings_page = new Storyform_Settings_Page();
}

function storyform_init() {
	load_plugin_textdomain( Storyform_Api::get_instance()->get_textdomain(), false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'storyform_init' ); 

Storyform_Admin_Meta_Box::init();


/**
 * Storyform theme setup.
 *
 * Does some basic theme stuff like establishing support for RSS feed links and hiding the admin bar
 *
 */
if ( ! function_exists( 'storyform_setup' ) ) :
function storyform_setup() {
	if( Storyform::template_in_use() ) {
		add_theme_support( 'automatic-feed-links' ); // Add RSS feed links to <head> for posts and comments.
		show_admin_bar( false );  
	}

	register_nav_menus( array(
		'storyform_navbar'   => __( 'Storyform navigation bar (flat)' )
	) );
}
endif; // storyform_setup
add_action( 'after_setup_theme', 'storyform_setup' );


/**
 * Migrates old plugin data (Narrative plugin up to 0.3.7) to this one.
 *
 */
if ( ! function_exists( 'storyform_activation' ) ) :
function storyform_activation() {
	Storyform_Options::get_instance()->migrate();

}
endif; // storyform_setup
register_activation_hook( __FILE__, 'storyform_activation' );


/**
 * Simple wrapper for use in the template file to get the layout type for the article element.
 *
 */
if ( ! function_exists( 'storyform_layout_type' ) ) :
function storyform_layout_type() {
	return Storyform_Options::get_instance()->get_layout_type_for_post( get_the_ID() ); 
}
endif; // storyform_setup

?>
