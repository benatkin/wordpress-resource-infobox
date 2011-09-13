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

class ResourceInfobox {
	function __construct( $url ) {
		$this->url = $url;
	}

	function fetch_rules() {
		$description_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'descriptions.json';
		$this->rules = json_decode(file_get_contents($description_file))->github->repository;
	}

	function extract_params() {
		$pattern = '/^https?:\\/\\/github\\.com\\/([\w-]+)\\/([\w-]+)/';
		preg_match( $pattern, $this->url, $matches );
		$this->params = (object) array(
			'owner' => $matches[1],
			'repo' => $matches[2]
		);

		$this->api_url = 'http://github.com/api/v2/json/repos/show/' . $this->params->{'owner'} . '/' . $this->params->{'repo'};
	}

	function fetch_data() {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500);
		curl_setopt($ch, CURLOPT_URL, $this->api_url);
		$result = curl_exec($ch);
		$this->data = json_decode($result);
	}

	function data_at_path( $path ) {
		$keys = explode('/', $path);
		$value = $this->data;
		while (count($keys) > 0) {
			$key = urldecode(array_shift($keys));
			if (! is_object($value)) {
				return null;
			}
			$value = $value->{$key};
		}
		return $value;
	}

	function render_field( $field ) {
		$label = $field->label;
		$value = '';
		$url = '';
		if ( property_exists( $field, 'param' ) ) {
			if ( property_exists( $this->params, $field->param ) ) {
				$value = $this->params->{ $field->param };
			}
		} else if ( property_exists( $field, 'path' ) ) {
			$value = $this->data_at_path( $field->path );
		}
		if (! $value) {
			return "";
		}

		if (property_exists($field, 'type')) {
			if ($field->type == "date") {
				$value = strtotime($value);
				if (property_exists($field, 'format')) {
					$format = $field->format;
				} else {
					$format = 'Y-m-d';
				}
				$value = date($format, $value);
			}
		}

		if (property_exists($field, 'url')) {
			$url = $field->url;
			foreach ($this->params as $param => $param_value) {
				$url = preg_replace(sprintf("/:%s/", $param), $param_value, $url);
			}
		}

		$safe_url = esc_url( $url );
		if ($safe_url) {
			return '<div class="resource-infobox-field">'
				. '<span class="resource-infobox-label">' . esc_attr( $label ) . '</span>'
				. '<a class="resource-infobox-value" href="' . $safe_url . '">'
				. esc_attr($value) . '</a></div>';
		} else {
			return '<div class="resource-infobox-field">'
				. '<span class="resource-infobox-label">' . esc_attr( $label ) . '</span>'
				. '<span class="resource-infobox-value">' . esc_attr( $value ) . '</span></div>';
		}
	}

	function render() {
		$fields_html = '';
		foreach ($this->rules->fields as $field) {
			$fields_html .= $this->render_field($field);
		}

		$clear_html = '<div class="resource-infobox-clear"></div>';

		return '<div class="resource-infobox">' . $fields_html . $clear_html . '</div>';
	}
}

function resource_infobox_shortcode( $atts ) {
	$atts = shortcode_atts(array(
		'url' => ''
	), $atts);

	$url = $atts['url'];

	$infobox = new ResourceInfobox($url);
	$infobox->fetch_rules();
	$infobox->extract_params();
	$infobox->fetch_data();
	$html = $infobox->render();

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

	.resource-infobox-label {
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
