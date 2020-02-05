<?php
/*
Plugin Name: WPLMS ActiveCampaign
plugin URI: http://www.vibethemes.com/
Description: This Plugin Interacates with the Active Campaign.
Version: 1.0
Author: Mr.Vibe
Author URI: http://www.vibethemes.com/
Text Domain: wplms-ac
Domain Path: /languages/
Copyright 2016 VibeThemes  (email: vibethemes@gmail.com) 
*/
if(!defined('ABSPATH'))
exit;

define('WPLMS_ACTIVECAMPAIGN_VERSION','1.0');
define('WPLMS_ACTIVECAMPAIGN_OPTION','wplms_activecampaign');

include_once 'includes/class.updater.php';
include_once 'includes/class.config.php';
include_once 'includes/class.activecampaign.php';
include_once 'includes/class.admin.php';
include_once 'includes/class.init.php';

add_action('plugins_loaded','wplms_activecampaign_translations');
function wplms_activecampaign_translations(){
    $locale = apply_filters("plugin_locale", get_locale(), 'wplms-ac');
    $lang_dir = dirname( __FILE__ ) . '/languages/';
    $mofile        = sprintf( '%1$s-%2$s.mo', 'wplms-ac', $locale );
    $mofile_local  = $lang_dir . $mofile;
    $mofile_global = WP_LANG_DIR . '/plugins/' . $mofile;

    if ( file_exists( $mofile_global ) ) {
        load_textdomain( 'wplms-ac', $mofile_global );
    } else {
        load_textdomain( 'wplms-ac', $mofile_local );
    }  
}


function Wplms_Activecampaign_Plugin_updater() {
    $license_key = trim( get_option( 'wplms_activecampaign_license_key' ) );
    $edd_updater = new Wplms_ActiveCampaign_Plugin_Updater( 'https://wplms.io', __FILE__, array(
            'version'   => WPLMS_ACTIVECAMPAIGN_VERSION,               
            'license'   => $license_key,        
            'item_name' => 'WPLMS ActiveCampaign',    
            'author'    => 'VibeThemes' 
        )
    );
}
add_action( 'admin_init', 'Wplms_ActiveCampaign_Plugin_updater', 0 );
