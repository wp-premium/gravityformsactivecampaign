<?php

/*
Plugin Name: Gravity Forms ActiveCampaign Add-On
Plugin URI: http://www.gravityforms.com
Description: Integrates Gravity Forms with ActiveCampaign allowing form submissions to be automatically sent to your ActiveCampaign account.
Version: 1.4
Author: rocketgenius
Author URI: http://www.rocketgenius.com
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

define( 'GF_ACTIVECAMPAIGN_VERSION', '1.4' );

add_action( 'gform_loaded', array( 'GF_ActiveCampaign_Bootstrap', 'load' ), 5 );

class GF_ActiveCampaign_Bootstrap {

	public static function load() {
		require_once( 'class-gf-activecampaign.php' );
		GFAddOn::register( 'GFActiveCampaign' );
	}

}

function gf_activecampaign() {
	return GFActiveCampaign::get_instance();
}