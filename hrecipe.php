<?php
/*
Plugin Name: hRecipe Microformat
Plugin URI: http://action-a-day/hrecipe-microformat
Description: TinyMCE add-in to supply hRecipe Microformat editing
Version: 0.1
Author: Kenneth J. Brucker
Author URI: http://action-a-day.com
License: GPL2

    Copyright 2011  Kenneth J. Brucker  (email : ken@pumastudios.com)

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

TODO Create widget for Recipe Categories
TODO How to have recipes show up in blog posts
TODO Create a shortcode to place recipe content into a post
TODO Provide mechanism to remove plugin data from database
TODO Provide mechanism to import recipes from external sources
*/

// If the class already exists, all setup is complete
if ( ! class_exists('hrecipe_microformat')) :
/**
 * Load the required libraries - use a sub-scope to protect global variables
 *		meta-box class used to create new post type
 *		Plugin options class
 **/		
{
	$required_libs = array('lib/meta-box-3.1/meta-box.php', 'admin/options.php');
	foreach ($required_libs as $lib) {
		if (!include_once($lib)) {
			hrecipe_microformat_error_log('Unable to load required library:  "' . $lib . '"');
			return;  // A required module is not available
		}
	}
}

class hrecipe_microformat extends hrecipe_microformat_options {
	/**
	 * some constants
	 **/
	private static $dir; // Base directory for Plugin
	private static $url; // Base URL for plugin directory
	
	/**
	 * meta box for recipe editing
	 *
	 * @access private
	 * @var object
	 **/
	private $meta_box;
	
	/**
	 * Plugin Options Array
	 *
	 * @access private
	 * @var array
	 **/
	private $options;
	
	/**
	 * undocumented function
	 *
	 * @author Kenneth J. Brucker <ken@pumastudios.com>
	 */
	
	function __construct() {
		parent::__construct();

		self::$dir = WP_PLUGIN_DIR . '/' . self::p . '/' ;
		self::$url =  WP_PLUGIN_URL . '/' . self::p . '/' ;
				
		// Register custom taxonomies
		add_action( 'init', array( &$this, 'register_taxonomies'), 0);		

		// Add recipe custom post type
		add_action( 'init', array( &$this, 'create_post_type' ) );
		
		// Callback to put recipes into the page stream
		add_filter('pre_get_posts', array(&$this, 'pre_get_posts_filter'));

		// If on the admin screen ...
		if (is_admin()) {
			// Add the buttons for TinyMCE during WP init
			add_action( 'init', array( &$this, 'add_buttons' ) );
		
			// Add editor stylesheet
			add_filter( 'mce_css', array( &$this, 'add_tinymce_css' ) );
		
			// Register actions to use the receipe category in admin list view
			add_action('restrict_manage_posts', array(&$this, 'restrict_recipes_by_category'));
			add_action('parse_query', array(&$this, 'parse_recipe_category_query'));
			add_action('manage_hrecipe_recipe_posts_columns', array(&$this, 'add_recipe_category_to_recipe_list'));
			add_action('manage_posts_custom_column', array(&$this, 'show_column_for_recipe_list'),10,2);
		}
		
	}

	function add_buttons() {
	   // Don't bother doing this stuff if the current user lacks permissions
	   if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
	     return;
 
	   // Add only in Rich Editor mode
	   if ( get_user_option('rich_editing') == 'true') {
	     add_filter('mce_external_plugins', array(&$this, 'add_tinymce_plugins'));
	     add_filter('mce_buttons_3', array(&$this, 'register_buttons'));
	   }
	}
 
	function register_buttons($buttons) {
	   array_push($buttons, 'hrecipeTitle', 'hrecipeYield', 'hrecipeDuration', 'hrecipePreptime', 'hrecipeCooktime',  'hrecipeAuthor', 'hrecipePublished', 'hrecipeCategory', 'hrecipeDifficulty' );
	// 'hrecipeIngredientList', 'hrecipeIngredient', 'hrecipeInstructions', 'hrecipeStep', 'hrecipeSummary',
	   return $buttons;
	}
 
	// Load the TinyMCE plugins : editor_plugin.js
	function add_tinymce_plugins($plugin_array) {
		$plugin_array['hrecipeTitle'] = self::$url.'TinyMCE-plugins/info/editor_plugin.js';
		// $plugin_array['hrecipeTitle'] = self::$url.'TinyMCE-plugins/ingredients/editor_plugin.js';
		// $plugin_array['hrecipeTitle'] = self::$url.'TinyMCE-plugins/instructions/editor_plugin.js';
		// $plugin_array['hrecipeTitle'] = self::$url.'TinyMCE-plugins/step/editor_plugin.js';
		// $plugin_array['hrecipeTitle'] = self::$url.'TinyMCE-plugins/summary/editor_plugin.js';
	
		return $plugin_array;
	}
	
	/**
	 * Add plugin CSS to tinymce
	 *
	 * @return updated list of css files
	 **/
	function add_tinymce_css($mce_css){
		if (! empty($mce_css)) $mce_css .= ',';
		$mce_css .= self::$url . 'editor.css';
		return $mce_css; 
	}
		
	/**
	 * Register the custom taxonomies for recipes
	 *
	 * @return void
	 **/
	static function register_taxonomies()
	{
		// Create a taxonomy for the Recipe Difficulty
		register_taxonomy(
			self::prefix . 'difficulty',  // Internal name
			self::post_type,
			array(
				'hierarchical' => true,
				'label' => __('Level of Difficulty', self::p),
				'query_var' => self::prefix . 'difficulty',
				'rewrite' => true,
				'show_ui' => false,
				'show_in_nav_menus' => false,
			)
		);
		
		// Create a taxonomy for the Recipe Category
		register_taxonomy(
			self::prefix . 'category',
			self::post_type,
			array(
				'hierarchical' => true,
				'label' => __('Recipe Category', self::p),
				'query_var' => self::prefix . 'category',
				'rewrite' => true,
				'show_ui' => true,
			)
		);
	}
	
	/**
	 * Hook to add pull-down menu to restrict recipe list by category
	 *
	 * @return void
	 **/
	function restrict_recipes_by_category()
	{
		global $typenow;
		global $wp_query;
		
		// Make sure we're working with a listing
		if ($typenow='listing') {
			$taxonomy = self::prefix . 'category';
			$category_taxonomy = get_taxonomy($taxonomy);
			wp_dropdown_categories(array(
				'show_option_all' => __("Show all {$category_taxonomy->label}", self::p),
				'taxonomy' => $taxonomy,
				'name' => $taxonomy,
				'orderby' => 'name',
				'selected' => $wp_query->query[$taxonomy],
				'hierarchical' => true,
				'depth' => 2,
				'show_count' => true, // Show count of recipes in the category
				'hide_empty' => true // Don't show categories with no recipes
			));
		}
	}
	
	/**
	 * Hook to filter query results based on recipe category.  Turns query based on id to query based on name.
	 *
	 * @return object query
	 **/
	function parse_recipe_category_query($query)
	{
		global $pagenow;
		
		$taxonomy = self::prefix . "category";
		if ('edit.php' == $pagenow && $query->get('post_type') == self::post_type && is_numeric($query->get($taxonomy))) {
			$term = get_term_by('id', $query->get($taxonomy), $taxonomy);
			$query->set($taxonomy, $term->slug);
		}
		
		return $query;
	}
	
	/**
	 * Add the recipe category to the post listings column
	 *
	 * @param array $list_columns Array of columns for listing
	 * @return array Updated array of columns
	 **/
	function add_recipe_category_to_recipe_list($list_columns)
	{
		$taxonomy = self::prefix . 'category';
		if (!isset($list_columns['author'])) {
			$new_list_columns = $list_columns;
		} else {
			$new_list_columns = array();
			foreach($list_columns as $key => $list_column) {
				if ('author' == $key) {
					$new_list_columns[$taxonomy] = '';
				}
				$new_list_columns[$key] = $list_column;
			}			
		}
		$new_list_columns[$taxonomy] = 'Recipe Category';
		return $new_list_columns;
	}
	
	/**
	 * Display the recipe categories in the custom list column
	 *
	 * @return void Emits HTML
	 **/
	function show_column_for_recipe_list($column_name, $post_id)
	{
		global $typenow;
		
		if ('listing' == $typenow) {
			$taxonomy = self::prefix . 'category';
			switch ($column_name) {
			case $taxonomy:
				$categories = get_the_terms($post_id, $taxonomy);
				if (is_array($categories)) {
					foreach ($categories as $key => $category) {
						$edit_link = get_term_link($category, $taxonomy);
						$categories[$key] = '<a href="'. $edit_link . '">' . $category->name . '</a>';
					}
					echo implode (' | ', $categories);
				}
				break; // End of 
			}
		}
	}

	/**
	 * Create recipe post type and associated panels in the edit screen
	 *
	 * @return void
	 **/
	function create_post_type()
	{		
		$meta_box = array(
			'id' => self::prefix . 'meta-box',
			'title' => __('Recipe Information', self::p),
			'pages' => array(self::post_type), // Only display for post type recipe
			'context' => 'normal',
			'priority' => 'high',
			'fields' => array(
				array(
					'name' => __('Recipe Title', self::p),
					'id' => self::prefix . 'fn',
					'type' => 'text'
				),
				array(
					'name' => __( 'Yield', self::p),
					'id' => self::prefix . 'yield',
					'type' => 'text',
				),
				array(
					'name' => __( 'Duration', self::p),
					'id' => self::prefix . 'duration',
					'type' => 'text',
					'desc' => 'Total time required to complete this recipe'
				),
				array(
					'name' => __( 'Prep Time', self::p),
					'id' => self::prefix . 'preptime',
					'type' => 'text',
					'desc' => __( 'Time required for prep work', self::p)
				),
				array(
					'name' => __( 'Cook Time', self::p ),
					'id' => self::prefix . 'cooktime',
					'type' => 'text',
					'desc' => __( 'Time required to cook', self::p )
				),
			)
		);
		// Create the editor metaboxes
		$this->meta_box = new RW_Meta_Box($meta_box);
		
		// Register the Recipe post type
		register_post_type(self::post_type,
			array(
				'labels' => array (
					'name' => _x('Recipes', 'post type general name', self::p),
					'singular_name' => _x('Recipe', 'post type singular name', self::p),
					'add_new' => _x('Add Recipe', 'recipe', self::p),
					'add_new_item' => __('Add New Recipe', self::p),
					'edit_item' => __('Edit Recipe', self::p),
					'new_item' => __('New Recipe', self::p),
					'view_item' => __('View Recipe', self::p),
					'search_items' => __('Search Recipes', self::p),
					'not_found' => __('No recipes found', self::p),
					'not_found_in_trash' => __('No recipes found in Trash', self::p),
					'menu_name' => __('Recipes', self::p),
				),
				'public' => true,
				'has_archive' => true,
				'rewrite' => array('slug' => 'Recipes'),
				'menu_position' => 7,
				'supports' => array('title', 'editor', 'author', 'thumbnail', 'trackbacks', 'comments', 'revisions'),
				'taxonomies' => array('post_tag'),
			)
		);
	}
	
	/**
	 * Update the WP query to include additional post types as needed
	 *
	 * @param object $query WP query
	 * @return object Updated WP query
	 **/
	function pre_get_posts_filter($query)
	{
		// Add plugin post type only on main query - don't add if filters should be suppressed
		if ((!$query->query_vars['suppress_filters']) 
		&& ((is_home() && $this->display_in_home) || (is_feed() && $this->display_in_feed))) {
			$query_post_type = $query->get('post_type');
			if (is_array($query_post_type)) {
				$query_post_type[] = self::post_type;
			} else {
				if ('' == $query_post_type) $query_post_type = 'post';
				$query_post_type = array($query_post_type, self::post_type);
			}
			$query->set('post_type', $query_post_type);
		} 
		return $query;
	}
	
	/**
	 * Perform Plugin Activation handling
	 *	 * Populate the plugin taxonomies with defaults
	 *
	 * @return void
	 **/
	public static function plugin_activation()
	{
		self::register_taxonomies();  // Register the needed taxonomies so they can be populated
		
		// Create the difficulty taxonomy
		wp_insert_term(__('Easy', self::p), self::prefix . 'difficulty');
		wp_insert_term(__('Medium', self::p), self::prefix . 'difficulty');
		wp_insert_term(__('Hard', self::p), self::prefix . 'difficulty');
		
		// Create the Recipe Category taxonomy
		wp_insert_term(__('Dessert', self::p), self::prefix . 'category');
		wp_insert_term(__('Entrée', self::p), self::prefix . 'category');
		wp_insert_term(__('Main', self::p), self::prefix . 'category');
		wp_insert_term(__('Meat', self::p), self::prefix . 'category');
		wp_insert_term(__('Soup', self::p), self::prefix . 'category');
	}
	
}
endif; // End Class Exists

function hrecipe_microformat_error_log($msg) {
	global $hrecipe_microformat_errors;

	if ( ! is_array( $hrecipe_microformat_errors ) ) {
		add_action('admin_footer', 'hrecipe_microformat_error_log_display');
		$hrecipe_microformat_errors = array();
	}
	
	array_push($hrecipe_microformat_errors, $msg);
}

// Display errors logged when the plugin options module is not available.
function hrecipe_microformat_error_log_display() {
	global $hrecipe_microformat_errors;
	
	echo "<div class='error'><p><a href='plugins.php'>hrecipe-microformat</a> unable to initialize correctly.  Error(s):<br />";
	foreach ($hrecipe_microformat_errors as $line) {
		echo "$line<br/>\n";
	}
	echo "</p></div>";
}


if (! defined($hrecipe_microformat)) $hrecipe_microformat = new hrecipe_microformat();

// Setup plugin activation function to populate the taxonomies
register_activation_hook( __FILE__, array('hrecipe_microformat', 'plugin_activation'));

?>