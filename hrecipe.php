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
*/

// Include the meta-box class code
include_once ('lib/meta-box-3.1/meta-box.php');

// If the class already exists, all setup is complete
if ( ! class_exists('hrecipe_microformat')) :

class hrecipe_microformat {
	/**
	 * some constants
	 **/
	const p = 'hrecipe-microformat';  // Plugin name
	
	private static $dir; // Base directory for Plugin
	private static $url; // Base URL for plugin directory
	
	/**
	 * Setup prefix to use for id's to avoid name collision
	 *
	 * @var string
	 **/
	private static $_prefix = 'hrecipe_';
	
	/**
	 * meta box for recipe editing
	 *
	 * @var object
	 **/
	private $_meta_box;
	
	/**
	 * undocumented function
	 *
	 * @author Kenneth J. Brucker <ken@pumastudios.com>
	 */
	
	function __construct() {
		self::$dir = WP_PLUGIN_DIR . '/' . self::p . '/' ;
		self::$url =  WP_PLUGIN_URL . '/' . self::p . '/' ;
		
		// Add the buttons for TinyMCE during WP init
		add_action( 'init', array( &$this, 'add_buttons' ) );
		
		// Add editor stylesheet
		add_filter( 'mce_css', array( &$this, 'add_tinymce_css' ) );
		
		// Register custom taxonomies
		add_action( 'init', array( &$this, 'register_taxonomies'), 0);
		
		// Add recipe custom post type
		add_action( 'init', array( &$this, 'create_post_type' ) );
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
	 * @author Kenneth J. Brucker <ken@pumastudios.com>
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
	 * @author Kenneth J. Brucker <ken@pumastudios.com>
	 **/
	static function register_taxonomies()
	{
		$post_type = self::$_prefix . 'recipe';
		
		// Create a taxonomy for the Recipe Difficulty
		register_taxonomy(
			self::$_prefix . 'difficulty',  // Internal name
			$post_type,
			array(
				'hierarchical' => true,
				'label' => __('Level of Difficulty', self::p),
				'query_var' => true,
				'rewrite' => true
			)
		);
		
		// Create a taxonomy for the Recipe Category
		register_taxonomy(
			self::$_prefix . 'category',
			$post_type,
			array(
				'hierarchical' => true,
				'label' => __('Recipe Category', self::p),
				'query_var' => true,
				'rewrite' => true
			)
		);
	}
	
	/**
	 * Create recipe post type and associated panels in the edit screen
	 *
	 * @return void
	 **/
	function create_post_type()
	{		
		$post_type = self::$_prefix . 'recipe';
		
		$meta_box = array(
			'id' => self::$_prefix . 'meta-box',
			'title' => __('Recipe Information', self::p),
			'pages' => array($post_type), // Only display for post type recipe
			'context' => 'normal',
			'priority' => 'high',
			'fields' => array(
				array(
					'name' => __('Recipe Title', self::p),
					'id' => self::$_prefix . 'fn',
					'type' => 'text'
				),
				array(
					'name' => __( 'Yield', self::p),
					'id' => self::$_prefix . 'yield',
					'type' => 'text',
				),
				array(
					'name' => __( 'Duration', self::p),
					'id' => self::$_prefix . 'duration',
					'type' => 'text',
					'desc' => 'Total time required to complete this recipe'
				),
				array(
					'name' => __( 'Prep Time', self::p),
					'id' => self::$_prefix . 'preptime',
					'type' => 'text',
					'desc' => __( 'Time required for prep work', self::p)
				),
				array(
					'name' => __( 'Cook Time', self::p ),
					'id' => self::$_prefix . 'cooktime',
					'type' => 'text',
					'desc' => __( 'Time required to cook', self::p )
				),
			)
		);
		// Create the editor metaboxes
		$this->_meta_box = new RW_Meta_Box($meta_box);
		
		// Register the Recipe post type
		register_post_type($post_type,
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
				'rewrite' => array('slug' => 'recipes'),
				'menu_position' => 7,
				'supports' => array('title', 'editor', 'author', 'thumbnail', 'trackbacks', 'comments', 'revisions'),
				'taxonomies' => array('post_tag'),
			)
		);
	}
	
	/**
	 * Perform Plugin Activation handling
	 *
	 * @return void
	 **/
	public static function plugin_activation()
	{
		self::register_taxonomies();  // Register the needed taxonomies so they can be populated
		
		// Create the difficulty taxonomy
		wp_insert_term(__('Easy', self::p), self::$_prefix . 'difficulty');
		wp_insert_term(__('Medium', self::p), self::$_prefix . 'difficulty');
		wp_insert_term(__('Hard', self::p), self::$_prefix . 'difficulty');
		
		// Create the Recipe Category taxonomy
		wp_insert_term(__('Dessert', self::p), self::$_prefix . 'category');
		wp_insert_term(__('Entrée', self::p), self::$_prefix . 'category');
		wp_insert_term(__('Main', self::p), self::$_prefix . 'category');
		wp_insert_term(__('Meat', self::p), self::$_prefix . 'category');
		wp_insert_term(__('Soup', self::p), self::$_prefix . 'category');
	}
	
}
endif; // End Class Exists

if (! defined($hrecipe_microformat)) $hrecipe_microformat = new hrecipe_microformat();

// Setup plugin activation function to populate the taxonomies
register_activation_hook( __FILE__, array('hrecipe_microformat', 'plugin_activation'));

?>