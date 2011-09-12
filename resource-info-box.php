<?php
/*  Copyright 2011  Benjamin Atkin  (email : ben@benatkin.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
Plugin Name: Resource Info Box
Plugin URI: https://github.com/benatkin/resource-info-box
Description: Displays an info box for a resource, based on rules
Version: 0.1
Author: Ben Atkin
Author URI: http://benatkin.com/
License: GPL2
*/

function resource_info_box_url( $url ) {
	// TODO: sanitize URLs
	return $url;
}

function resource_info_box_field( $name, $value, $url="" ) {
	if ($url) {
		return '<div class="resource-info-box-field">'
			. '<label class="resource-info-box-name">' . esc_attr($name) . '</label>'
			. '<a class="resource-info-box-value" href="' . esc_attr(resource_info_box_url($url)) . '">'
			. esc_attr($value) . '</a></div>';
	} else {
		return '<div class="resource-info-box-field">'
			. '<label class="resource-info-box-name">' . esc_attr($name) . '</label>'
			. '<span class="resource-info-box-value">' . esc_attr($value) . '</span></div>';
	}
}

function resource_info_box_shortcode( $atts ) {
	$repo  = resource_info_box_field('Repository',  'wordpress-resource-info-box', 'https://github.com/benatkin/wordpress-resource-info-box');
	$owner = resource_info_box_field('Owner',       'benatkin',                    'https://github.com/benatkin');
	$last  = resource_info_box_field('Last Commit', 'September 11, 2011');
	$watch = resource_info_box_field('Watchers',    '1');
	return '<div class="resource-info-box">' . $repo . $owner . $last . $watch . '</div>';
}

add_shortcode( 'resource-info-box', 'resource_info_box_shortcode' );

function resource_info_box_styles() {
?>
<style type="text/css" id="resource-info-box-styles">
	.resource-info-box {
		padding: 5px;
		margin-top: 5px;
		margin-bottom: 5px;
		background-color: #ddd;
		width: 95%;
	}

	.resource-info-box-name {
		font-weight: bold;
		float: left;
		width: 25%;
		margin-right: 0.2em;
	}
</style>
<?php
}

add_action('wp_head', 'resource_info_box_styles');

?>
