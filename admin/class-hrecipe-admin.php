<?php
/**
 * Class to manage options
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2012 Kenneth J. Brucker (email: ken@pumastudios.com)
 * 
 * This file is part of hRecipe Microformat, a plugin for Wordpress.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 **/

// TODO Add cuisine types - mexican, spanish, indian, etc.
// TODO Create admin widget for Recipe Categories - only allow one category to be selected
// TODO Phone-home with error log

// Protect from direct execution
if (!defined('WP_PLUGIN_DIR')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

// On admin screens, load additional classes
if (is_admin()) {
	$required_libs = array('class-hrecipe-importer.php');
	foreach ($required_libs as $lib) {
		if (!include_once($lib)) {
			return false;
		}
	}
}

class hrecipe_admin
{
	/**
	 * Define some shorthand
	 */
	const p = 'hrecipe-microformat';  // Plugin name
	const prefix = 'hrecipe_';				// prefix for ids, names, etc.
	const post_type = 'hrecipe';				// Applied to entry as a class
	const settings = 'hrecipe_microformat_settings';
	const settings_page = 'hrecipe_microformat_settings_page';
	const required_db_ver = 1;
	
	protected static $dir; // Base directory for Plugin
	protected static $url; // Base URL for plugin directory
	
	/**
	 * Array holding plugin options
	 *	'database_ver'     :  Database Structure version in use - Used to upgrade old versions to new format as required
	 *	'display_in_home'  :  True if recipes should be displayed in the home page
	 *	'display_in_feed'  :  True if recipes should be displayed in the main feed
	 *  'add_post_class' : True if the 'post' class should be added to recipe posts
	 *	'recipe_head_fields' : Ordered list of fields to include in the recipe head
	 *	'recipe_footer_fields' : Ordered list of fields to include in the recipe footer
	 *	'debug_log_enabled' : True if logging plugin debug messages
	 *	'debug_log' : Array of debug messages when debug_log_enabled is true
	 *
	 * @var array of strings
	 **/
	protected $options;
	
	/**
	 * Array of default option values
	 *
	 * @var array
	 **/
	var $options_defaults;
	
	/**
	 * Array of taxonomy names registered by the plugin
	 *
	 * @var Array
	 * @access protected
	 **/
	protected static $taxonomies;
		
	/**
	 * Errors and warnings to display on admin screens
	 *
	 * @var array 
	 **/
	protected $admin_notices;  // Update level (Yellow)
	protected $admin_notice_errors;  // Error messages (Red)
	
	/**
	 * Map internal field names to displayed names, description
	 *
	 * Index into the primary array is the hrecipe microformat field name
	 *
	 * Each row contains:
	 *	label - Name to use to label the related value
	 *  description - 1-line description of the field
	 *  format - data storage format:  tax --> Taxonomy, meta --> Post Metadata, nutrition --> Special class of Post Meta
	 *  type - HTML INPUT format to use
	 *
	 * @var array of arrays
	 **/
	protected $recipe_field_map;
	
	/**
	 * Description text for each level of recipe difficulty
	 *
	 * @var array of strings 
	 **/
	protected $difficulty_description;
	
	/**
	 * Setup plugin defaults and register with WordPress for use in Admin screens
	 **/
	function setup()
	{
		self::$dir = WP_PLUGIN_DIR . '/' . self::p . '/' ;
		self::$url =  plugins_url(self::p) . '/' ;
		$this->admin_notices = array();
		$this->admin_notice_errors = array();

		/**
		 *	Define Recipe meta data fields
		 **/
		$this->recipe_field_map = array(
			'fn' 				 => array( 'label' => __('Recipe Title', self::p),
														 'description' => __('Recipe Title', self::p),
														 'id' => self::prefix . 'fn',
														 'metabox' => 'info',
														 'type' => 'text'),
			'yield'      => array( 'label' => __('Yield', self::p), # TODO Use value, unit for yield (x cookies, x servings, ...)?
														 'description' => __('Amount the recipe produces, generally the number of servings.', self::p),
														 'type' => 'text',
														 'id' => self::prefix . 'yield',
														 'metabox' => 'info',
														 'format' => 'text'),
			'difficulty' => array( 'label' => __('Difficulty', self::p),
														 'description' => __('Difficulty or complexity of the recipe.', self::p),
														 'type' => 'radio',
														 'id' => self::prefix . 'difficulty',
														 'metabox' => 'info',
														 'options' => array(
																'1' => __('Basic', self::p),
																'2' => __('Easy', self::p),
																'3' => __('Average', self::p),
																'4' => __('Hard', self::p),
																'5' => __('Challenging', self::p)
															),
															'option_descriptions' => array(
																'0' => __('The difficulty of this recipe has not been entered.', self::p),
																'1' => __('Basic recipe with a few common ingredients, no alcohol, a few simple steps and no heat source required.  This is a safe recipe that can be done by children with limited assistance.', self::p),
																'2' => __('Easy recipe with easy to find ingredients and a small number of steps that might contain alcohol and may require a heat source.  The recipe might be able to be made by older children and is generally appropriate for someone with little cooking experience.', self::p),
																'3' => __('Average difficulty that might require some skills (chopping, dicing, slicing, measuring, small appliances, etc.).  Ingredients are available to most home cooks at their local grocery store.  Cooking time is usually no more than about an hour.', self::p),
																'4' => __('Above Average recipes might contain harder to find ingredients (specialty stores), advanced cooking techniques or unusual tools not found in the typical home kitchen.  Some recipes in this category might use basic ingredients while requiring advanced techniques or special tools.  These recipes might have significantly more preparation steps, longer processes or difficult assembly steps.', self::p),
																'5' => __('These are challenging recipes, particularly for the home cook.  Special techniques or tools might be required and recipes might contain multiple hard to find ingredients.', self::p)
															),
														 'format' => 'difficulty'),
			'rating'     => array( 'label' => __('Rating', self::p),
														 'description' => __('Rating of the recipe out of five stars.', self::p),
														 'metabox' => '',
														 'format' => 'rating'),
			'category'   => array( 'label' => __('Category', self::p),
														 'description' => __('Type of recipe', self::p),
														 'metabox' => 'category',
														 'format' => 'tax'),
			'duration'   => array( 'label' => __('Duration', self::p),
														 'description' => __('Total time it takes to make the recipe.', self::p),
														 'type' => 'text',
														 'id' => self::prefix . 'duration',
														 'metabox' => 'info',
														 'format' => 'text'),
			'preptime'   => array( 'label' => __('Prep Time', self::p),
														 'description' => __('Time it takes in the preparation step of the recipe.', self::p),
														 'type' => 'text',
														 'id' => self::prefix . 'preptime',
														 'metabox' => 'info',
														 'format' => 'text'),
			'cooktime'   => array( 'label' => __('Cook Time', self::p),
														 'description' => __('Time it takes in the cooking step of the recipe.', self::p),
														 'type' => 'text',
														 'id' => self::prefix . 'cooktime',
														 'metabox' => 'info',
														 'format' => 'text'),
			'published'  => array( 'label' => __('Published', self::p),
														 'description' => __('Date of publication of the recipe', self::p),
														 'type' => 'text',
														 'id' => self::prefix . 'published',
														 'metabox' => 'info',
														 'format' => 'text'),
			'author'     => array( 'label' => __('Author', self::p),
														 'description' => __('Recipe Author, if different from person posting the recipe.', self::p),
														 'type' => 'text',
														 'id' => self::prefix . 'author',
														 'metabox' => 'info',
														 'format' => 'text'),
			'nutrition'  => array( 'label' => __('Nutrition', self::p),
														 'description' => __('Recipe nutrition information', self::p),
														 'metabox' => 'nutrition', // TODO How is nutrition managed?
														 'format' => 'nutrition'),
		);
		
		$options_defaults = array(
			'database_ver' => self::required_db_ver,
			'display_in_home' => true,
			'display_in_feed' => true,
			'add_post_class' => true,
			'recipe_head_fields' => 'yield,difficulty,rating,category,duration,preptime,cooktime',
			'recipe_footer_fields' => 'published,author,nutrition',
			'debug_log_enabled' => false,
			'debug_log' => array(),
		);
		
		// Retrieve Plugin Options
		$this->options = (array) wp_parse_args(get_option(self::settings), $options_defaults);
		
		// If database version does not match, an upgrade is needed
		if (self::required_db_ver != $this->options['database_ver']) {
			$this->handle_database_ver($this->options['database_ver']);
		}
		
		// // Make sure the database version is available in the options
		// if (! array_key_exists('database_ver', $options)) {
		// 	$options['database_ver'] = self::required_db_ver;
		// }
		// 
		// If logging is enabled, warn admin as it affects DB performance
		if ($this->options['debug_log_enabled']) {
			$this->admin_notice_errors[] = sprintf(__('%s logging is enabled.  If left enabled, this can affect database performance.', self::p),'<a href="options.php?page=' . self::settings_page . '">' . self::p . '</a>');
		}
		
		// Perform plugin actions needed during WP init
		add_action('init', array(&$this, 'plugin_init'));
		
		add_action( 'after_setup_theme', array( $this, 'add_featured_image_support' ), 11 );
		
		// When displaying admin screens ...
		if ( is_admin() ) {
			// Add menu item for plugin options page
			add_action('admin_menu', array(&$this, 'admin_menu'));

			add_action('admin_init', array( &$this, 'admin_init'));
		}		
		
		// If logging is enabled, setup save in the footers.
		if ($this->options['debug_log_enabled']) {
			add_action('admin_footer', array( &$this, 'save_debug_log'));
			add_action('wp_footer', array( &$this, 'save_debug_log'));				
		}
	}
	
	/**
	 * When plugin is activated, populate taxonomy and flush the rewrite rules
	 *
	 * @return void
	 **/
	function on_activation()
	{
		self::register_taxonomies();  // Register the needed taxonomies so they can be populated
		self::create_post_type();			// Create the hrecipe post type so that rewrite rules can be flushed.
		
		// Only insert terms if the category taxonomy doesn't already exist.
		if (0 == count(get_terms(self::prefix . 'category', 'hide_empty=0&number=1'))) {
			wp_insert_term(__('Appetizer', self::p), self::prefix . 'category');
			wp_insert_term(__('Soup', self::p), self::prefix . 'category');
			wp_insert_term(__('Salad', self::p), self::prefix . 'category');
			wp_insert_term(__('Side Dish', self::p), self::prefix . 'category');
			
			wp_insert_term(__('Entrée', self::p), self::prefix . 'category');
			$entree_term = term_exists( __('Entrée', self::p), self::prefix . 'category');
			$entree_term_id = $entree_term['term_id'];
			wp_insert_term(__('Pasta', self::p), self::prefix . 'category', array('parent' => $entree_term_id));
			wp_insert_term(__('Meat', self::p), self::prefix . 'category', array('parent' => $entree_term_id));
			wp_insert_term(__('Fish', self::p), self::prefix . 'category', array('parent' => $entree_term_id));
			wp_insert_term(__('Poultry', self::p), self::prefix . 'category', array('parent' => $entree_term_id));
			wp_insert_term(__('Vegetarian', self::p), self::prefix . 'category', array('parent' => $entree_term_id));
			
			wp_insert_term(__('Dessert', self::p), self::prefix . 'category');
		}
		
		// On activation, flush rewrite rules to make sure plugin is setup correctly. 
		flush_rewrite_rules();
	}
	
	/**
	 * Run during WP init phase
	 *
	 * @return void
	 **/
	function plugin_init()
	{
		// Register custom taxonomies
		self::register_taxonomies();

		// Add recipe custom post type
		self::create_post_type();
	}

	/**
	 * Create admin menu item and fields of the options page
	 *
	 * @return void
	 **/
	function admin_menu()
	{	
		// Create the sub-menu item in the Settings section
		$settings_page = add_options_page(
			__('hRecipe Microformat Plugin Settings', self::p), 
			__('hRecipe Microformat', self::p), 
			'manage_options', 
			self::settings_page, 
			array(&$this, 'options_page_html')
		);
		
		// Add style sheet and scripts needed in the options page
		add_action('admin_print_scripts-' . $settings_page, array(&$this, 'enqueue_admin_scripts'));
		add_action('admin_print_styles-' . $settings_page, array(&$this, 'enqueue_admin_styles'));
			
		/**
		 * Add section controlling how recipes are displayed
		 **/
		$settings_section = self::settings . '-display';
		add_settings_section( 
			$settings_section, 
			__('Recipe Display Settings', self::p), 
			array( &$this, 'display_section_html'), 
			self::settings_page 
		);

		// Display in Home field
		add_settings_field( 
			self::settings . '[display_in_home]', 
			__('Display In Home', self::p), 
			array( &$this, 'display_in_home_html' ), 
			self::settings_page, 
			$settings_section 
		);
		
		// Display in Feed field
		add_settings_field( 
			self::settings . '[display_in_feed]', 
			__('Display In Feed', self::p), 
			array( &$this, 'display_in_feed_html' ), 
			self::settings_page, 
			$settings_section 
		);
		
		// Add post class field
		add_settings_field(
			self::settings . '[add_post_class]',
			__('Recipe Format', self::p),
			array(&$this, 'add_post_class_html'),
			self::settings_page,
			$settings_section
		);
		
		/**
	   * Add section to configure recipe header and footer
	   */
		$settings_section = self::settings . '-headfoot';
		add_settings_section(
			$settings_section,
			__('Recipe Header and Footer Contents', self::p),
			array(&$this, 'head_foot_section_html'),
			self::settings_page
		);

		/**
		 * Add section for debug logging
		 **/
		$settings_section = self::settings . '-debug';
		add_settings_section(
			$settings_section,
			__('Debug Logging', self::p),
			array(&$this, 'debug_section_html'),
			self::settings_page
		);
		
		// Add Plugin Error Logging
		add_settings_field( 
			self::settings . '[debug_log_enabled]', 
			__('Enable Debug Log', self::p), 
			array( &$this, 'debug_log_enabled_html'), 
			self::settings_page, 
			$settings_section 
		);
	}
	
	/**
	 * Setup the admin screens
	 **/
	function admin_init ()
	{
		// Add section for reporting configuration errors and notices
		add_action('admin_notices', array( &$this, 'admin_notice'));

		// 		add_action('load-' . $page_hook,...); // Use to trigger events needed on the options screen
			
		// Add plugin admin style
		add_action( 'admin_print_styles-post.php', array(&$this, 'enqueue_admin_styles'), 1000 );
		add_action( 'admin_print_styles-post-new.php', array(&$this, 'enqueue_admin_styles'), 1000 );
		
		// Add plugin admin scripts
		add_action( 'admin_print_scripts-post.php', array(&$this, 'enqueue_admin_scripts'), 1000 );
		add_action( 'admin_print_scripts-post-new.php', array(&$this, 'enqueue_admin_scripts'), 1000 );

		// Register actions to use the receipe category in admin list view
		add_action('restrict_manage_posts', array(&$this, 'restrict_recipes_by_category'));
		add_action('parse_query', array(&$this, 'parse_recipe_category_query'));
		add_action('manage_' . self::post_type . '_posts_columns', array(&$this, 'add_recipe_category_to_recipe_list'));
		add_action('manage_posts_custom_column', array(&$this, 'show_column_for_recipe_list'),10,2);
		
		// Register the settings name
		register_setting( self::settings_page, self::settings, array (&$this, 'sanitize_settings') );
		
		// Register admin style sheet and javascript
		wp_register_style(self::prefix . 'admin', self::$url . 'admin/css/admin.css');
		wp_register_script(self::prefix . 'admin', self::$url . 'admin/js/admin.js');
		
		// Register jQuery UI stylesheet
		wp_register_style(self::prefix . 'jquery-ui', self::$url . 'admin/css/jquery-ui.css');
		
		// Setup the Recipe Post Editing page
		add_action('add_meta_boxes_' . self::post_type, array(&$this, 'configure_tinymce')); // TODO Best place for this?
		add_action('add_meta_boxes_' . self::post_type, array(&$this, 'setup_meta_boxes'));  // Setup plugin metaboxes
		add_action('save_post' , array(&$this, 'save_post_meta')); // Save the post metadata
	}
	
	/**
	 * Register the custom taxonomies for recipes
	 *
	 * @return void
	 **/
	static function register_taxonomies()
	{
		if (!isset(self::$taxonomies)) {
			self::$taxonomies = array();
			
			// Create a taxonomy for the Recipe Category
			self::$taxonomies[] = self::prefix . 'category';
			register_taxonomy(
				self::prefix . 'category',
				self::post_type,
				array(
					'hierarchical' => true,
					'label' => __('Recipe Category', self::p),
					'labels' => array(
						'name' => _x('Recipe Types', 'taxonomy general name', self::p),
						'singular_name' => _x('Recipe Type', 'taxonomy singular name', self::p),
						'search_items' => __('Search Recipe Types', self::p),
						'popular_items' => __('Popular Recipe Types', self::p),
				    'all_items' => __('All Recipe Types', self::p),
				    'parent_item' => __('Parent Recipe Type', self::p),
				    'parent_item_colon' => __('Parent Recipe Type:', self::p),
				    'edit_item' => __('Edit Recipe Type', self::p),
				    'update_item' => __('Update Recipe Type', self::p),
				    'add_new_item' => __('Add New Recipe Type', self::p),
				    'new_item_name' => __('New Recipe Type Name', self::p),
					),
					'query_var' => self::prefix . 'category',
					'rewrite' => true,
					'show_ui' => true,
				)
			);			
		}
	}
	
	/**
	 * Create recipe post type and associated panels in the edit screen
	 *
	 * @return void
	 **/
	function create_post_type()
	{		
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
				'supports' => array('title', 'editor', 'excerpt', 'author', 'thumbnail', 'trackbacks', 'comments', 'revisions'),
				'taxonomies' => array('post_tag'), // FIXME Setup Taxonomy to allow only a single selection
			)
		);
	}
	
	/**
	 * Enabled featured images (post thumbnail) for Recipe Post type
	 *
	 * @access public
	 * @return void
	 */
	public function add_featured_image_support()
	{
		$supported_types = get_theme_support( 'post-thumbnails' );

		if( $supported_types === false )
			add_theme_support( 'post-thumbnails', array( self::post_type ) );               
		elseif( is_array( $supported_types ) )
		{
			$supported_types[0][] = self::post_type;
			add_theme_support( 'post-thumbnails', $supported_types[0] );
		}
	}

	/**
	 * Add the metaboxes needed in the admin screens
	 *   Use add_meta_box( $id, $title, $callback, $page, $context, $priority, $callback_args )
	 *
	 * @return void
	 **/
	function setup_meta_boxes()
	{

		$meta_boxes = array();
		
		// Add metabox for the Recipe Metadata
		add_meta_box(self::prefix . 'info', __('Additional Recipe Information', self::p), array(&$this, 'info_metabox'), self::post_type, 'normal', 'high', null);		
	}
	
	/**
	 * Emit HTML for the recipe information metabox
	 *
	 * @uses $post To retrieve post meta data
	 * @return void
	 **/
	function info_metabox()
	{
		global $post;
		
		// Use nonce for verification
		wp_nonce_field( plugin_basename(__FILE__), self::prefix . 'noncename' );
		
		// Create the editor metaboxes
		foreach ($this->recipe_field_map as $key => $field) {
			// Include 'info' fields in this metabox
			if ('info' == $field['metabox']) {
				$value = get_post_meta($post->ID, $field['id'], true) | '';
				echo '<p id="' . self::prefix . 'info_' . $key . '"><label><span class="field-label">' . $field['label'] . '</span>';
				switch ($field['type']) {
					case 'text':
						self::text_html($field['id'], $value);
						break;
					
					case 'radio':
						self::radio_html($field['id'], $field['options'], $field['option_descriptions'], $value);
						break;
				}
				if (isset($field['description'])) echo '<span class="field-description">' . $field['description'] . '</span>';
				echo '</label></p>';
			}
		} // End foreach
	}
	
	/**
	 * Save Recipe Post meta data
	 *
	 * @uses $post Post data
	 * @param $post_id int post id
	 * @return void
	 **/
	function save_post_meta($post_id)
	{
		global $post;
		
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
			return $post_id;
			
		// Confirm nonce field
		if ( !isset($_POST[self::prefix . 'noncename']) 
				|| !wp_verify_nonce( $_POST[self::prefix . 'noncename'], plugin_basename(__FILE__) )) {
			return $post_id;
		}
		
		// User allowed to edit?
		if ( self::post_type != $_POST['post_type'] || !current_user_can( 'edit_post', $post_id) ) {
			return $post_id;
		}		
		
		$the_post = wp_is_post_revision($post_id);
		if (! $the_post) $the_post = $post_id;

		// Save meta data for the info metabox
		foreach ($this->recipe_field_map as $field) {
			if ('info' == $field['metabox']) {
				$id = $field['id'];
				$new_data = isset($_POST[$id]) ? $_POST[$id] : '';
				$old_data = get_post_meta($the_post, $id, true);
				if ('' == $old_data && $new_data != '') {
					// New meta data to save
					add_post_meta($the_post, $id, $new_data, true);
				} elseif ('' == $new_data) {
					// New data is empty - remove related meta data
					delete_post_meta($the_post, $id);
				}	elseif ($new_data != $old_data) {
					// New and old don't match, update
					update_post_meta($the_post, $id, $new_data);
				}
			} 
		}
		
		return $post_id;
	}

	/**
	 * Configure tinymce
	 *
	 * TinyMCE Filters:
	 *  mce_external_plugins - Adds plugins to tinymce init handling
	 *  mce_buttons_x - Add buttons to a button row
	 *  mce_css - Applies style sheets to tinymce config
	 *  tiny_mce_before_init - modify tinymce init array
	 *
	 * @return void
	 **/
	function configure_tinymce()
	{
	   // Don't bother doing this stuff if the current user lacks permissions
	   if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
	     return;

		// Add only in Rich Editor mode
		if ( get_user_option('rich_editing') != 'true')
			return;

		// Setup the TinyMCE buttons
    add_filter('mce_external_plugins', array(&$this, 'add_tinymce_plugins'));
		add_filter('mce_external_languages', array(&$this, 'add_tinymce_langs'));
    add_filter('mce_buttons_3', array(&$this, 'register_buttons'));

		// Add editor stylesheet
		add_filter( 'mce_css', array( &$this, 'add_tinymce_css' ) );
		
		// Add custom styles
		add_filter( 'tiny_mce_before_init', array(&$this, 'tinymce_init_array'));
	}
	
	/**
	 * Enqueue stylesheets used for Recipe Handling
	 *
	 * @return void
	 **/
	function enqueue_admin_styles() {
		// Style the admin pages
		wp_enqueue_style( self::prefix . 'admin');
		
		// jQuery UI style
		wp_enqueue_style( self::prefix . 'jquery-ui' );
	}

	/**
	 * Enqueue scripts used in admin screens for Recipe handling
	 *
	 * @return void
	 **/
	function enqueue_admin_scripts() {
		// Load the plugin admin scripts
		wp_enqueue_script( self::prefix . 'admin');
		
		// Need the jquery sortable support
		wp_enqueue_script( 'jquery-ui-sortable' );
	}
	
	/**
	 * Display Notice messages at head of admin screen
	 *
	 * @return void
	 **/
	function admin_notice()
	{
		if (count($this->admin_notice_errors)) {
			echo '<div class="error">';
			foreach ($this->admin_notice_errors as $notice) {
				echo '<p>' . $notice . '</p>';
			}
			echo '</div>';						
		}
				
		if (count($this->admin_notices)) {
			echo '<div class="updated fade">';
			foreach ($this->admin_notices as $notice) {
				echo '<p>' . $notice . '</p>';
			}
			echo '</div>';			
		}
	}
	
	/**
	 * Sanitize the Plugin Options received from the user
	 *
	 * @return hash Sanitized hash of plugin options
	 **/
	function sanitize_settings($options)
	{
		// Display Recipes on home page? -- Default to true
		$options['display_in_home'] = self::sanitize_an_option($options, 'display_in_home', 'bool');
		
		// Display Recipes in main feed?  -- Default to true
		$options['display_in_feed'] = self::sanitize_an_option($options, 'display_in_feed', 'bool');
		
		// Add post class to recipes?
		$options['add_post_class'] = self::sanitize_an_option($options, 'add_post_class', 'bool');
		
		// Recipe Header content (ordered list)
		$options['recipe_head_fields'] = self::sanitize_an_option($options, 'recipe_head_fields', 'text');
		
		// Recipe Footer content (ordered list)
		$options['recipe_footer_fields'] = self::sanitize_an_option($options, 'recipe_footer_fields', 'text');
		
		// Init value for debug log
		$options['debug_log_enabled'] = self::sanitize_an_option($options, 'debug_log_enabled', 'bool');

		// Cleanup error log if it's disabled
		if ( ! (array_key_exists('debug_log_enabled', $options) && $options['debug_log_enabled']) ) {
			$options['debug_log'] = array();
		}

		return $options;
	}
	
	/**
	 * Sanitize an option based on field type
	 *
	 * @return value to use for empty option
	 **/
	function sanitize_an_option($options, $key, $type)
	{
		switch($type) {
			case 'bool' :
			  return array_key_exists($key, $options) && $options[$key] ? true : false;
			
			case 'text' :
				$val = array_key_exists($key, $options) && $options[$key] ? $options[$key] : '';
				return wp_filter_nohtml_kses($val);  // HTML not allowed in options
		}
	}
		
	/**
	 * Emit HTML to create the Plugin settings page
	 *
	 * @access public
	 * @return void
	 **/
	public function options_page_html()
	{
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}
		echo '<div class="wrap">';
		echo '<h2>';
		_e('hRecipe Microformat Plugin Settings',self::p);
		echo '</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields(self::settings_page);
		do_settings_sections(self::settings_page);
		echo '<p class=submit>';
		echo '<input type="submit" class="button-primary" value="' . __('Save Changes') . '" />';
		echo '</p>';
		echo '</form>';
		echo '</div>';
	}
	
	/**
	 * Emit HTML to create the Display section for the plugin in admin screen.
	 **/
	function display_section_html()
	{	
		echo '<p>';
			_e('Configure how Recipes are included in the blog and feeds', self::p);
		echo '</p>';
	}
	
	/**
	 * Emit HTML for plugin field
	 *
	 * @return void
	 **/
	function display_in_home_html()
	{
		self::checkbox_html(self::settings . '[display_in_home]', $this->options['display_in_home']);
		_e('Display Recipes on the home (blog) page.', self::p);
	}
	
	/**
	 * Emit HTML for plugin field
	 *
	 * @return void
	 **/
	function display_in_feed_html()
	{
		self::checkbox_html(self::settings . '[display_in_feed]', $this->options['display_in_feed']);
		_e('Include Recipes in the main feed.', self::p);
		echo ' ';
		_e('This change might not take effect for a client until a new post or recipe is added.', self::p);
	}
	
	/**
	 * Emit HTML for plugin field
	 *
	 * @return void
	 **/
	function add_post_class_html()
	{
		self::checkbox_html(self::settings . '[add_post_class]', $this->options['add_post_class']);
		_e('Format Recipes like Posts.', self::p);
	}
	
	/**
	 * Emit HTML section used for configuring Recipe header and footer content
	 *
	 * @return void
	 **/
	function head_foot_section_html()
	{
		$head_fields = explode(',', $this->options['recipe_head_fields']);
		$footer_fields = explode(',', $this->options['recipe_footer_fields']);

		// Build list of unused fields
		$unused_fields = array();
		foreach($this->recipe_field_map as $key => $row) {
			if (! in_array($key, $head_fields) && ! in_array($key, $footer_fields)) {
				// Add unused fields to the list
				array_push($unused_fields, $key); 
			}
		}
		
		$sections = array(
			array(
				'field-name' => 'recipe_head_fields', 
				'title' => __('Recipe Head Section', self::p),
				'list' => $head_fields,
			),
			array(
				'field-name' => 'recipe_footer_fields', 
				'title' => __('Recipe Footer Section', self::p),
				'list' => $footer_fields,
			),
			array(
				'field-name' => 'recipe_unused_fields', 
				'title' => __('Unused Fields', self::p),
				'list' => $unused_fields,
			),
		);
		
		echo '<div id="recipe_head_foot_fields">';
		foreach ($sections as $row) {
			// Emit the HTML for each section
			echo '<div id="' . $row['field'] . '" class="recipe-fields">';
			echo '<h4>' . $row['title'] . '</h4>';
			self::input_hidden_html($row['field-name'], join(',', $row['list']));
			echo '<ul>';
			foreach ($row['list'] as $field) {
				echo '<li class="menu-item-handle" name="' . $field . '">' . $this->recipe_field_map[$field]['label'] . '</li>';
			}				
			echo '</ul>';
			echo '</div>'; // Close each field section
		}
		echo '</div>'; // Close entire section
	}
	
	/**
	 * Emit HTML to create the Debug section for the plugin in admin screen.
	 **/
	function debug_section_html()
	{	
		echo '<p>';
			_e('Configure debug settings.', self::p);
		echo '</p>';
	}
	
	/**
	 * Emit HTML to create form field used to enable/disable Debug Logging
	 **/
	function debug_log_enabled_html()
	{ 
		self::checkbox_html(self::settings . '[debug_log_enabled]', $this->options['debug_log_enabled']);
		_e('Enable Plugin Debug Logging. When enabled, log will display below.', self::p);
		if ( $this->options['debug_log_enabled']) {
			echo '<dl class=hmf-debug-log>';
			echo '<dt>Log:';
			foreach ($this->options['debug_log'] as $line) {
				echo '<dd></dd>' . esc_attr($line);
			}
			echo '</dl>';
		}
	}
	
	/**
	 * Emit HTML for a checkbox
	 *
	 * @param string $field Name of field
	 * @param boolean $checked True if the checkbox should be checked
	 * @return void
	 **/
	function checkbox_html($field, $checked)
	{
		$checked = $checked ? " checked" : "";
		echo '<input type="checkbox" name="' . $field . '" value="1"' . $checked . '>';
	}
	
	/**
	 * Emit HTML for a text field
	 *
	 * @param string $field Name of field
	 * @param string $value Default value for the input field
	 * @return void
	 **/
	function text_html($field, $value)
	{
		echo '<input type="text" name="' . $field . '" value="' . esc_html($value) . '" />';
	}
	
	/**
	 * Emit HTML for a radio button field
	 *
	 * @param string $field Name of field
	 * @param array $options array of value=>label pairs for radio button option
	 * @param array $option_descr array of value=>option descriptions for each radio button option
	 * @param string $value Default value for selected radio button
	 * @return void
	 **/
	function radio_html($field, $options, $option_descr, $value)
	{
		foreach ($options as $key => $option) {
			$checked = ($value == $key) ? ' checked' : '';
			$descr = isset($option_descr[$key]) ? $option_descr[$key] : '';
			echo '<span class="radio-button" title="'. $descr . '"><input type="radio" name="'. $field . '" value="'. $key . '"' . $checked . ' />' . $option . '</span>';	
		}
	}
	
	/**
	 * Emit HTML for a hidden field
	 *
	 * @return void
	 **/
	function input_hidden_html($field, $value)
	{
		echo '<input type="hidden" name="' . self::settings . '[' . $field . ']" value="' . $value . '">';
	}
	
	function register_buttons($buttons) {
	   array_push($buttons, 'hrecipeTitle', 'hrecipeIngredientList');
	// TODO 'hrecipeIngredient', 'hrecipeInstructions'
	   return $buttons;
	}
 
	// Load the TinyMCE plugins
	function add_tinymce_plugins($plugin_array) {
		foreach(array('hrecipeMicroformat', 'noneditable') as $plugin) {
			$url = $this->locate_tinymce_plugin($plugin);
			if ($url) {
				$plugin_array[$plugin] = $url;
			}
		}
	
		return $plugin_array;
	}
	
	/**
	 * Add language files for tinymce popups
	 *
	 * @access public
	 * @param $langs array of language files
	 * @return array language files to run
	 **/
	function add_tinymce_langs($langs)
	{
		// file system path to the TinyMCE plugins
		$mce_plugins = self::$dir . 'admin/TinyMCE-plugins/';
		
		// for each plugin, add the language file
		foreach (array('hrecipeMicroformat') as $plugin) {
			$langs[$plugin] = $mce_plugins . $plugin . '/langs/langs.php';
		}
		
		return $langs;
	}
	
	/**
	 * Locate tinymce plugin file, use either the dev src or the minified version if available
	 *
	 * @return string URL path to TinyMCE javascript plugin
	 **/
	function locate_tinymce_plugin($plugin)
	{
		foreach (array('TinyMCE-plugins', 'TinyMCE-utils') as $plugin_dir) {
			$plugin_path = 'admin/' . $plugin_dir . '/' . $plugin . '/';
			foreach (array('editor_plugin.js', 'editor_plugin_src.js') as $js) {
				if (file_exists(self::$dir . $plugin_path . $js)) {
					return self::$url . $plugin_path . $js;
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Add plugin CSS to tinymce
	 *
	 * @return updated list of css files
	 **/
	function add_tinymce_css($mce_css){
		if (! empty($mce_css)) $mce_css .= ',';
		$mce_css .= self::$url . 'admin/css/editor.css';
		$mce_css .= ',' . self::$url . 'admin/css/jquery-ui.css';
		return $mce_css; 
	}
	
	/**
	 * Add plugin styles to tinymce
	 *
	 * Called prior to tinymce init to modify tinymce init parameters
	 *
	 * @return array tinyMCE init hash array
	 **/
	function tinymce_init_array($initArray)
	{
		// Preserve formats set by other plugins
		$style_formats = isset($initArray['style_formats']) ? json_decode($initArray['style_formats']) : array();
				
		// Recipe Instruction Steps
		// $style_formats[] = array('title' => 'Step', 'block' => 'div', 'wrapper' => true, 'classes' => 'step', 'exact' => true);
		$style_formats[] = array('title' => 'Step', 'block' => 'div', 'classes' => 'step');
		
		// Recipe Hints
		$style_formats[] = array('title' => 'Hint', 'block' => 'p', 'classes' => 'hrecipe-hint');		
		
		// $initArray['theme_advanced_blockformats'] = 'Step,Hint,p,pre,address,h1,h2,h3,h4,h5,h6';
		// $initArray['formats'] = json_encode($formats);
		
		$initArray['style_formats'] = json_encode($style_formats);
		return $initArray;
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
			$selected = array_key_exists($taxonomy, $wp_query->query) ? $wp_query->query[$taxonomy] : '';
			wp_dropdown_categories(array(
				'show_option_all' => __("Show all {$category_taxonomy->label}", self::p),
				'taxonomy' => $taxonomy,
				'name' => $taxonomy,
				'orderby' => 'name',
				'selected' => $selected,
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
			if ($term) {
				$query->set($taxonomy, $term->slug);		
			}
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
		$new_list_columns[$taxonomy] = _x('Recipe Type', 'taxonomy singular name', self::p);
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
	 * Update recipe database on version mismatches
	 *
	 * @return void
	 **/
	function handle_database_ver()
	{
		$this->admin_notice_errors[] = sprintf(__('Recipe database version mismatch; using v%1$d, required v%2$d', self::p), $this->options['database_ver'], self::required_db_ver);
	}
	
	/**
	 * Return WordPress Post Meta Key field ID for specified hrecipe microformat key
	 *
	 * @access public
	 * @param string $microformat Microformat tag
	 * @return string or false if microformat tag not defined
	 **/
	public function post_meta_key($microformat)
	{
		return isset($this->recipe_field_map[$microformat]) ? $this->recipe_field_map[$microformat]['id'] : false;
	}
	
	/**
	 * Return contents of named file in filesystem
	 *
	 * @access public
	 * @param $path string File name to retrieve
	 * @return string File contents
	 **/
	function get_file($path)
	{
		if ( function_exists('realpath') )
			$path = realpath($path);

		if ( ! $path || ! @is_file($path) )
			return '';

		if ( function_exists('file_get_contents') )
			return @file_get_contents($path);

		$content = '';
		$fp = @fopen($path, 'r');
		if ( ! $fp )
			return '';

		while ( ! feof($fp) )
			$content .= fgets($fp);

		fclose($fp);
		return $content;
	}
	
	/**
	 * Cleanup database if uninstall is requested
	 *
	 * @return void
	 **/
	function uninstall() {
		delete_option(self::settings); // Remove the plugin settings
		
		/** Delete the recipe posts, including all draft and unpublished versions **/
		$arg=array(
			'post_type' => self::post_type,
			'post_status' => 'publish,pending,draft,auto-draft,future,private,inherit,trash',
			'nopaging' => true,
		);
		$recipes = new WP_Query($arg);
		foreach ($recipes->posts as $recipe){
			wp_delete_post($recipe->ID, false); // Allow the posts to go into the trash, just in case...
		}
		
		/** Delete taxonomies **/
		self::register_taxonomies();  // Need to register the taxonmies so the uninstall can find them to remove
		foreach (self::$taxonomies as $taxonomy) {
			global $wp_taxonomies;
			$terms = get_terms($taxonomy, array('hide_empty'=>false)); // Get all terms for the taxonomy to remove
			if (is_array($terms)) {
				foreach ($terms as $term) {
					wp_delete_term( $term->term_id, $taxonomy );
				}				
			} 
			unset($wp_taxonomies[$taxonomy]);
		}		
	}
	
	/**
	 * Log an error message for display
	 * TODO Support the WP Debug Bar Plugin
	 **/
	function debug_log($msg)
	{
		if ( $this->options['debug_log_enabled'] )
			array_push($this->options['debug_log'], date("Y-m-d H:i:s") . " " . $msg);
	}
	
	/**
	 * Save the error log if it's enabled
	 **/
	function save_debug_log()
	{
		if ( $this->options['debug_log_enabled'] ) {
			$options = get_option(self::settings);
			$options['debug_log'] = $this->options['debug_log'];
			update_option(self::settings, $options);
		}
	}
	
	/**
	 * Log errors to server log and debug log
	 *
	 * @return void
	 * @author Kenneth J. Brucker <ken@pumastudios.com>
	 **/
	function log_err($msg)
	{
		error_log(self::p . ": " . $msg);
		$this->debug_log($msg);
	}
} // END class 
?>
