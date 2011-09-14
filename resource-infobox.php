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

	function variable_matched($matches) {
		array_push($this->url_variables, substr($matches[0], 2));
		return '([\w-]+)';
	}

	function extract_url_params($pattern, $url) {
		$params = (object) array();
		$this->url_variables = array();

		$pattern = preg_quote($pattern, '/');
		$pattern = preg_replace_callback('/\\\\:[\w-]+/', array(&$this, 'variable_matched'), $pattern);
		$pattern = sprintf('/%s/', $pattern);

		preg_match($pattern, $this->url, $matches);
		array_shift($matches);

		$n = min(count($matches), count($this->url_variables));
		for ($i = 0; $i < $n; $i++) {
			$params->{$this->url_variables[$i]} = $matches[$i];
		}

		return $params;
	}

	function replace_url_params($url) {
		foreach ($this->params as $param => $param_value) {
			$url = preg_replace(sprintf("/:%s/", $param), $param_value, $url);
		}
		return $url;
	}

	function find_resource() {
		$this->params = $this->extract_url_params($this->rules->url, $this->url);
		$this->api_url = $this->replace_url_params($this->rules->api_url);
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
			if (is_object($value) && property_exists($value, $key)) {
				$value = $value->{$key};
			} else {
				return null;
			}
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
			$url = $this->replace_url_params($field->url);
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

class ResourceInfoboxPlugin {
	function __construct() {
	}

	function setup() {
		add_shortcode('resource-infobox', array(&$this, 'shortcode'));
		add_action('wp_head', array(&$this, 'styles'));
		add_action('admin_menu', array(&$this, 'setup_admin_menu'));
	}

	function shortcode($atts) {
		$atts = shortcode_atts(array(
			'url' => ''
		), $atts);

		$url = $atts['url'];

		$infobox = new ResourceInfobox($url);
		$infobox->fetch_rules();
		$infobox->find_resource();
		$infobox->fetch_data();
		$html = $infobox->render();

		return $html;
	}

	function styles() {
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

	function setup_admin_menu() {
		add_submenu_page('options-general.php', 'Resource Infobox', 'Resource Infobox',
			'manage_options', 'resource-infobox', array(&$this, 'settings'));
	}

	function settings() {
		$description_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'descriptions.json';
		$rules = file_get_contents($description_file);
?>
<div class="wrap">
<h2>Resource Infobox Settings</h2>
<textarea readonly style="height: 30em; width: 95%;"><?php echo esc_attr($rules); ?></textarea>
</div>
<?php
	}
}

$resource_infobox_plugin = new ResourceInfoboxPlugin();
$resource_infobox_plugin->setup();

?>
