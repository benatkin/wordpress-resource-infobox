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
	function __construct( $url, $plugin ) {
		$this->url = $url;
		$this->plugin = $plugin;
	}

	function fetch_resource_definition() {
		$description_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'descriptions.json';
		$this->resource_definition = json_decode(file_get_contents($description_file))->github->repository;
		$this->resource_definition = $this->plugin->resource_definition_collection->find($this->url);
	}

	function replace_url_params($url) {
		foreach ($this->params as $param => $param_value) {
			$url = preg_replace(sprintf("/:%s/", $param), $param_value, $url);
		}
		return $url;
	}

	function find_resource() {
		$this->params = $this->resource_definition->extract_url_params($this->url);
		if ($this->params) {
			$this->api_url = $this->replace_url_params($this->resource_definition->api_url);
		}
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

		$safe_url = esc_url($url);
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
		foreach ($this->resource_definition->fields as $field) {
			$fields_html .= $this->render_field($field);
		}

		$clear_html = '<div class="resource-infobox-clear"></div>';

		return '<div class="resource-infobox">' . $fields_html . $clear_html . '</div>';
	}
}

class ResourceInfoboxDefinition {
	function __construct($data) {
		$this->url = $data->url;
		$this->api_url = $data->api_url;
		$this->fields = $data->fields;
	}

	function variable_matched($matches) {
		array_push($this->url_variables, substr($matches[0], 2));
		return '([\w-]+)';
	}

	function extract_url_params($url) {
		$pattern = $this->url;
		$params = (object) array();
		$this->url_variables = array();

		$pattern = preg_quote($pattern, '/');
		$pattern = preg_replace_callback('/\\\\:[\w-]+/', array(&$this, 'variable_matched'), $pattern);
		$pattern = sprintf('/%s/', $pattern);

		preg_match($pattern, $url, $matches);
		if (count($matches) === 0) {
			return 0;
		}

		array_shift($matches);

		$n = min(count($matches), count($this->url_variables));
		for ($i = 0; $i < $n; $i++) {
			$params->{$this->url_variables[$i]} = $matches[$i];
		}

		return $params;
	}

}

class ResourceInfoboxDefinitionCollection {
	function __construct() {
		$description_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'descriptions.json';
		$this->data = json_decode(file_get_contents($description_file));
	}

	function find($url) {
		foreach ($this->data as $service_name => $service_data) {
			foreach ($service_data as $definition_name => $definition_data) {
				$definition = new ResourceInfoboxDefinition($definition_data);
				if ($definition->extract_url_params($url)) {
					return $definition;
				}
			}
		}
	}
}

class ResourceInfoboxPlugin {
	function __construct() {
		$this->resource_definition_collection = new ResourceInfoboxDefinitionCollection();
	}

	function setup() {
		add_shortcode('resource-infobox', array(&$this, 'shortcode'));
		add_action('wp_print_styles', array(&$this, 'styles'));
		add_action('admin_menu', array(&$this, 'setup_admin_menu'));
	}

	function shortcode($atts) {
		$atts = shortcode_atts(array(
			'url' => ''
		), $atts);

		$url = $atts['url'];

		$infobox = new ResourceInfobox($url, $this);
		$infobox->fetch_resource_definition();
		if (! $infobox->resource_definition) {
			return "";
		}

		$infobox->find_resource();
		$infobox->fetch_data();
		$html = $infobox->render();

		return $html;
	}

	function styles() {
		$css_url = plugins_url('resource-infobox.css', __FILE__);
		wp_register_style('resource-infobox', $css_url);
		wp_enqueue_style('resource-infobox');
	}

	function setup_admin_menu() {
		$page = add_options_page('Resource Infobox', 'Resource Infobox',
			'manage_options', 'resource-infobox', array(&$this, 'settings'));
		add_action('admin_print_styles-' . $page, array(&$this, 'admin_enqueue_scripts'));
	}

	function admin_enqueue_scripts() {
		$js_url = plugins_url('resource-infobox-admin.js', __FILE__);
		wp_register_script('resource-infobox-admin', $js_url);
		wp_enqueue_script('resource-infobox-admin');

		$css_url = plugins_url('resource-infobox-admin-styles.css', __FILE__);
		wp_register_style('resource-infobox-admin-styles', $css_url);
		wp_enqueue_style('resource-infobox-admin-styles');
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
