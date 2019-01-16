<?php
/**
Plugin Name: Gravity Forms ActiveCampaign Add-On
Plugin URI: https://www.gravityforms.com
Description: Integrates Gravity Forms with ActiveCampaign, allowing form submissions to be automatically sent to your ActiveCampaign account.
Version: 1.6
Author: rocketgenius
Author URI: https://www.rocketgenius.com
License: GPL-2.0+
Text Domain: gravityformsactivecampaign
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009-2016 Rocketgenius, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/


// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

define( 'GF_ACTIVECAMPAIGN_VERSION', '1.6' );

// If Gravity Forms is loaded, bootstrap the ActiveCampaign Add-On.
add_action( 'gform_loaded', array( 'GF_ActiveCampaign_Bootstrap', 'load' ), 5 );

/**
 * Class GF_ActiveCampaign_Bootstrap
 *
 * Handles the loading of the ActiveCampaign Add-On and registers with the Add-On framework.
 */
class GF_ActiveCampaign_Bootstrap {

	/**
	 * If the Feed Add-On Framework exists, Post Creation Add-On is loaded.
	 *
	 * @access public
	 * @static
	 */
	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-activecampaign.php' );

		GFAddOn::register( 'GFActiveCampaign' );

	}

}

/**
 * Returns an instance of the GFActiveCampaign class
 *
 * @see    GFActiveCampaign::get_instance()
 *
 * @return object GFActiveCampaign
 */
function gf_activecampaign() {
	return GFActiveCampaign::get_instance();
}
