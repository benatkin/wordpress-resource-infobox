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
Plugin Name: Resource Infobox
Plugin URI: https://github.com/benatkin/wordpress-resource-infobox
Description: Displays an infobox for a resource, based on rules
Version: 0.1
Author: Ben Atkin
Author URI: http://benatkin.com/
License: GPL2
*/

function resource_infobox_field( $name, $value, $url="" ) {
	$safe_url = esc_url( $url );
	if ($safe_url) {
		return '<div class="resource-infobox-field">'
			. '<span class="resource-infobox-name">' . esc_attr( $name ) . '</span>'
			. '<a class="resource-infobox-value" href="' . $safe_url . '">'
			. esc_attr($value) . '</a></div>';
	} else {
		return '<div class="resource-infobox-field">'
			. '<span class="resource-infobox-name">' . esc_attr( $name ) . '</span>'
			. '<span class="resource-infobox-value">' . esc_attr( $value ) . '</span></div>';
	}
}

function resource_infobox_get_data( $url ) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500);
	curl_setopt($ch, CURLOPT_URL, $url);
	$result = curl_exec($ch);
	return json_decode($result);
}

function resource_infobox_shortcode( $atts ) {
	$atts = shortcode_atts(array(
		'url' => ''
	), $atts);

	$pattern = '/^https?:\\/\\/github\\.com\\/([\w-]+)\\/([\w-]+)/';
	preg_match( $pattern, $atts['url'], $matches );
	$owner = $matches[1];
	$repo = $matches[2];

	$json_url = 'http://github.com/api/v2/json/repos/show/' . $owner . '/' . $repo;
	$data = resource_infobox_get_data( $json_url );
	$watchers = '';
	$last_commit = '';
	if ( ! ( property_exists( $data, 'error' ) ) ) {
		$watchers = $data->{'repository'}->{'watchers'};
		$last_commit = date('Y-m-d', strtotime($data->{'repository'}->{'pushed_at'}));
	}

	$repo_html  = resource_infobox_field('Repository',  $repo,  'https://github.com/' . $owner . '/' . $repo . '/');
	$owner_html = resource_infobox_field('Owner',       $owner, 'https://github.com/' . $owner . '/');
	$last_html  = resource_infobox_field('Last Commit', $last_commit);
	$watch_html = resource_infobox_field('Watchers',    $watchers);
	$clear_html = '<div class="resource-infobox-clear"></div>';

	$html = '<div class="resource-infobox">' . $repo_html . $owner_html . $last_html . $watch_html . $clear_html . '</div>';
	return $html;
}

add_shortcode( 'resource-infobox', 'resource_infobox_shortcode' );

function resource_infobox_styles() {
?>
<style type="text/css" id="resource-infobox-styles">
	.resource-infobox {
		padding: 5px;
		margin-top: 5px;
		margin-bottom: 5px;
		background-color: #ddd;
		width: 95%;
	}

	.resource-infobox-field {
		clear: both;
	}

	.resource-infobox-clear {
		clear: both;
	}

	.resource-infobox-name {
		font-weight: bold;
		float: left;
		width: 25%;
		margin-right: 0.2em;
	}
</style>
<?php
}

add_action( 'wp_head', 'resource_infobox_styles' );

?>
