<?php
/**
 * Class to manage options
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2011 Kenneth J. Brucker (email: ken@pumastudios.com)
 * 
 * This file is part of hRecipe Microformat, a plugin for Wordpress.
 *
 * Copyright 2011  Kenneth J. Brucker  (email : ken@pumastudios.com)
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

// TODO Setup Difficulty as 1-5 level - use more chef hats for harder.
// Protect from direct execution
if (!defined('WP_PLUGIN_DIR')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

class hrecipe_microformat_options
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
	 * Indicate whether or not recipes should be included in the home page
	 *
	 * @var boolean True if recipes should be displayed in the home page
	 * @access protected
	 **/
	protected $display_in_home;
	
	/**
	 * Indicate whether or not recipes should be included in the main feed
	 *
	 * @var boolean True if recipes should be displayed in the main feed
	 * @access public
	 **/
	protected $display_in_feed;
	
	/**
	 * Array of taxonomy names registered by the plugin
	 *
	 * @var Array
	 * @access protected
	 **/
	protected static $taxonomies;
	
	/**
	 * Database version in use - Used to upgrade old versions to new format as required
	 *
	 * @var int
	 **/
	protected $database_ver;
	
	/**
	 * Errors and warnings to display on admin screens
	 *
	 * @var array 
	 **/
	protected $admin_notices;  // Update level (Yellow)
	protected $admin_notice_errors;  // Error messages (Red)
	
	/**
	 * Default ordered list of fields to include in the recipe header
	 *
	 * @var array
	 **/
	protected $recipe_head_fields_default;
	
	/**
	 * Default ordered list of fields to include in the recipe footer
	 *
	 * @var array
	 **/
	protected $recipe_footer_fields_default;
	
	/**
	 * Map internal field names to displayed names, description
	 *
	 * Index into the primary array is the hrecipe microformat field name
	 *
	 * Each row contains:
	 *	label - Name to use to label the related value
	 *  description - 1-line description of the field
	 *  type - data storage format:  tax --> Taxonomy, meta --> Post Metadata, nutrition --> Special class of Post Meta
	 *
	 * @var array of arrays
	 **/
	protected $recipe_field_map;
	
	/**
	 * Setup plugin defaults and register with WordPress for use in Admin screens
	 **/
	function setup()
	{
		self::$dir = WP_PLUGIN_DIR . '/' . self::p . '/' ;
		self::$url =  WP_PLUGIN_URL . '/' . self::p . '/' ;
		$this->admin_notices = array();
		$this->admin_notice_errors = array();
		
		$this->recipe_field_map = array(
			'yield'      => array( 'label' => __('Yield', self::p), # TODO Use value, unit for yield (x cookies, x servings, ...)?
														 'description' => __('Amount the recipe produces, generally the number of servings.', self::p),
														 'type' => 'meta'),
			'difficulty' => array( 'label' => __('Difficulty', self::p),
														 'description' => __('Difficulty or complexity of the recipe.', self::p),
														 'type' => 'meta'),
			'rating'     => array( 'label' => __('Rating', self::p),
														 'description' => __('Rating of the recipe out of five stars.', self::p),
														 'type' => 'rating'),
			'category'   => array( 'label' => __('Category', self::p),
														 'description' => __('Type of recipe', self::p),
														 'type' => 'tax'),
			'duration'   => array( 'label' => __('Duration', self::p),
														 'description' => __('Total time it takes to make the recipe.', self::p),
														 'type' => 'meta'),
			'preptime'   => array( 'label' => __('Prep Time', self::p),
														 'description' => __('Time it takes in the preparation step of the recipe.', self::p),
														 'type' => 'meta'),
			'cooktime'   => array( 'label' => __('Cook Time', self::p),
														 'description' => __('Time it takes in the cooking step of the recipe.', self::p),
														 'type' => 'meta'),
			'published'  => array( 'label' => __('Published', self::p),
														 'description' => __('Date of publication of the recipe', self::p),
														 'type' => 'meta'),
			'author'     => array( 'label' => __('Author', self::p),
														 'description' => __('Recipe Author, if different from person posting the recipe.', self::p),
														 'type' => 'meta'),
			'nutrition'  => array( 'label' => __('Nutrition', self::p),
														 'description' => __('Recipe nutrition information', self::p),
														 'type' => 'nutrition'),
			'summary'    => array( 'label' => __('Summary', self::p),
														 'description' => __('Short recipe summary', self::p),
														 'type' => 'meta'),		
			
		);
		
		// Defaults for recipe head and footer display
		$this->recipe_head_fields_default = 'yield,difficulty,rating,category,duration,preptime,cooktime';
		$this->recipe_footer_fields_default = 'published,author,nutrition';
						
		// Retrieve Plugin Options
		$options = (array) get_option(self::settings);		
		
		// Display Recipes on home page? -- Default to true
		$this->display_in_home = array_key_exists('display_in_home', $options) ? $options['display_in_home'] : false;
		
		// Display Recipes in main feed?  -- Default to true
		$this->display_in_feed = array_key_exists('display_in_feed', $options) ? $options['display_in_feed'] : false;
		
		// Add post class to recipes?
		$this->add_post_class = array_key_exists('add_post_class', $options) ? $options['add_post_class'] : false;
		
		// Recipe Header content (ordered list)
		$this->recipe_head_fields = 
			array_key_exists('recipe_head_fields', $options) ? $options['recipe_head_fields'] : $this->recipe_head_fields_default;
		
		// Recipe Footer content (ordered list)
		$this->recipe_footer_fields = 
			array_key_exists('recipe_footer_fields', $options) ? $options['recipe_footer_fields'] : $this->recipe_footer_fields_default;
			
		// Unused recipe fields
		$this->recipe_unused_fields =
			array_key_exists('recipe_unused_fields', $options) ? $options['recipe_unused_fields'] : '';
		
		// Init value for debug log
		$this->debug_log_enabled = array_key_exists('debug_log_enabled', $options) ? $options['debug_log_enabled'] : false;
		$this->debug_log = array_key_exists('debug_log',$options) ? $options['debug_log'] : array();
		if ($this->debug_log_enabled) {
			$this->admin_notice_errors[] = sprintf(__('%s logging is enabled.  If left enabled, this can affect database performance.', self::p),'<a href="options.php?page=' . self::settings_page . '">' . self::p . '</a>');
		}
		
		// Init value for the database version
		$this->database_ver = array_key_exists('database_ver', $options) ? $options['database_ver'] : self::required_db_ver;
		if (self::required_db_ver != $this->database_ver) {
			$this->handle_database_ver($this->database_ver);
		}
		
		// Perform plugin actions needed during WP init
		add_action('init', array(&$this, 'plugin_init'));
		
		// When displaying admin screens ...
		if ( is_admin() ) {
			// Add menu item for plugin options page
			add_action('admin_menu', array(&$this, 'admin_menu'));

			add_action('admin_init', array( &$this, 'admin_init'));
		}
		
		
		// If logging is enabled, setup save in the footers.
		if ($this->debug_log_enabled) {
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
			// FIXME Populate the default Category taxonomy
			wp_insert_term(__('Dessert', self::p), self::prefix . 'category');
			wp_insert_term(__('Entrée', self::p), self::prefix . 'category');
			wp_insert_term(__('Main', self::p), self::prefix . 'category');
			wp_insert_term(__('Meat', self::p), self::prefix . 'category');
			wp_insert_term(__('Soup', self::p), self::prefix . 'category');			
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
		add_action('add_meta_boxes_' . self::post_type, array(&$this, 'configure_tinymce'));
		self::setup_meta_boxes();  // Setup the plugin metaboxes
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
				'supports' => array('title', 'editor', 'author', 'thumbnail', 'trackbacks', 'comments', 'revisions'),
				'taxonomies' => array('post_tag'),
			)
		);
	}
	
	/**
	 * Add the metaboxes needed in the admin screens
	 *
	 * @return void
	 **/
	function setup_meta_boxes()
	{
		$meta_boxes = array();
		
		// Define HTML used for manipulating tables
		$handles = '<span class="sort-handle ui-icon ui-icon-arrow-2-n-s ui-state-active"></span>' .
							 '<span class="insert ui-icon ui-icon-plusthick ui-state-active"></span>'.
							 '<span class="delete ui-icon ui-icon-minusthick ui-state-active"></span>';
		
		/**
		 * Define a Metabox for Recipe Instructions
		 */		
		
		$meta_boxes[] = array(
			'id' => self::prefix . 'ingredients',
			'title' => __('Ingredients', self::p),
			'pages' => array(self::post_type), // Only display for post type recipe
			'context' => 'normal',
			'priority' => 'high',
			'fields' => array(
				array(
					'name' => $handles . __('Recipe Step', self::p),
					'id' => self::prefix . 'step-1',
					'type' => 'textarea' // FIXME Use wysiwyg for tinymce
				),
			), // End fields
		);  // End Ingredients Metabox

		/**
		 * Define a Metabox for the Recipe Infomation
		 */
		$meta_boxes[] = array(
			'id' => self::prefix . 'recipe-info',
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
					'name' => __('Recipe Summary', self::p),
					'id' => self::prefix . 'summary',
					'type' => 'text',
					'desc' => implode(' ', array(
						__('Short description of the recipe.',self::p), 
						__('(Might not display in all areas.)', self::p)))
				),
				array(
					'name' => __('Author', self::p),
					'id' => self::prefix . 'author',
					'type' => 'text'
				),
				array(
					'name' => __('Published', self::p),
					'id' => self::prefix . 'published',
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
				array(
					'name' => __('Difficulty', self::p),
					'id' => self::prefix . 'difficulty',
					'type' => 'radio',
					'desc' => __('Recipe level of difficulty', self::p),
					'options' => array(
												'1' => __('Easy', self::p),
												'3' => __('Medium', self::p),
												'5' => __('Hard', self::p),
					            ),
				),
			)
		);
		
		// Create the editor metaboxes
		foreach ($meta_boxes as $meta_box) {
			$new_box = new RW_Meta_Box($meta_box);
		}
	}
	
	/**
	 * Configure tinymce
	 *
	 * @return void
	 **/
	function configure_tinymce()
	{
		// Add editor stylesheet
		add_filter( 'mce_css', array( &$this, 'add_tinymce_css' ) );
		
		// Setup the TinyMCE buttons
		self::add_buttons();
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
		// Cleanup error log if it's disabled
		if ( ! (array_key_exists('debug_log_enabled', $options) && $options['debug_log_enabled']) ) {
			$options['debug_log'] = array();
		}
		
		// Make sure the database version is available in the options
		if (! array_key_exists('database_ver', $options)) {
			$options['database_ver'] = self::required_db_ver;
		}

		return $options;
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
		self::checkbox_html('display_in_home', $this->display_in_home);
		_e('Display Recipes on the home (blog) page.', self::p);
	}
	
	/**
	 * Emit HTML for plugin field
	 *
	 * @return void
	 **/
	function display_in_feed_html()
	{
		self::checkbox_html('display_in_feed', $this->display_in_feed);
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
		self::checkbox_html('add_post_class', $this->add_post_class);
		_e('Format Recipes like Posts.', self::p);
	}
	
	/**
	 * Emit HTML section used for configuring Recipe header and footer content
	 *
	 * @return void
	 **/
	function head_foot_section_html()
	{
		$fields = array(
			array('field' => 'recipe_head_fields', 'title' => __('Recipe Head Section', self::p)),
			array('field' => 'recipe_footer_fields', 'title' => __('Recipe Footer Section', self::p)),
			array('field' => 'recipe_unused_fields', 'title' => __('Unused Fields', self::p)),
		);
		$recipe_field_sections = '';
		
		echo '<div id="recipe_head_foot_fields">';
		foreach ($fields as $row) {
			// Collect section names for javacript
			$comma = ('' == $recipe_field_sections ? '' : ',');
			$recipe_field_sections .= $comma . '"#' . $row['field'] . '"';
			
			// Emit the HTML for each section
			echo '<div id="' . $row['field'] . '" class="recipe-fields">';
			echo '<h4>' . $row['title'] . '</h4>';
			self::input_hidden_html($row['field'], $this->$row['field']);
			echo '<ul>';
			if ('' != $this->$row['field']) {
				foreach (explode(',', $this->$row['field']) as $field) {
					echo '<li class="menu-item-handle" name="' . $field . '">' . $this->recipe_field_map[$field]['label'] . '</li>';
				}				
			}
			echo '</ul>';
			echo '</div>'; // Close each field section
		}
		echo '</div>'; // Close entire section
		?>
			<script type="text/javascript">
				//<![CDATA[
				var recipe_field_sections=new Array(<?php echo $recipe_field_sections; ?>);
				//]]>
			</script>
		<?php
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
		self::checkbox_html('debug_log_enabled', $this->debug_log_enabled);
		_e('Enable Plugin Debug Logging. When enabled, log will display below.', self::p);
		if ( $this-> debug_log_enabled ) {
			echo '<dl class=hmf-debug-log>';
			echo '<dt>Log:';
			foreach ($this->debug_log as $line) {
				echo '<dd></dd>' . esc_attr($line);
			}
			echo '</dl>';
		}
	}
	
	/**
	 * Emit HTML for a checkbox
	 *
	 * @param string $field Name of field in the settings array
	 * @param string $checked True if the checkbox should be checked
	 * @return void
	 **/
	function checkbox_html($field, $checked)
	{
		$checked = $checked ? " checked" : "";
		echo '<input type="checkbox" name="' . self::settings . '['. $field . ']" value="1"' . $checked . '>';
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
	   array_push($buttons, 'hrecipeTitle', 'hrecipeIngredientList', 'hrecipeHint');
	// TODO 'hrecipeIngredient'
	   return $buttons;
	}
 
	// Load the TinyMCE plugins : editor_plugin.js
	function add_tinymce_plugins($plugin_array) {
		$plugin_array['hrecipeMicroformat'] = $this->locate_tinymce_plugin('hrecipe');
	
		return $plugin_array;
	}
	
	/**
	 * Locate tinymce plugin file, use either the dev src or the minified version if available
	 *
	 * @return string URL path to TinyMCE javascript plugin
	 **/
	function locate_tinymce_plugin($plugin)
	{
		$plugin_dir = 'admin/TinyMCE-plugins/' . $plugin . '/';
		if (file_exists(self::$dir . $plugin_dir . 'editor_plugin.js')) {
			$url = self::$url . $plugin_dir . 'editor_plugin.js';
		} else {
			$url = self::$url . $plugin_dir . 'editor_plugin_src.js';
		}
		
		return $url;
	}
	/**
	 * Add plugin CSS to tinymce
	 *
	 * @return updated list of css files
	 **/
	function add_tinymce_css($mce_css){
		if (! empty($mce_css)) $mce_css .= ',';
		$mce_css .= self::$url . 'admin/css/editor.css';
		return $mce_css; 
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
		$this->admin_notice_errors[] = sprintf(__('Recipe database version mismatch; using v%1$d, required v%2$d', self::p), $this->database_ver, self::required_db_ver);
	}
	
	/**
	 * Cleanup database if uninstall is requested
	 *
	 * @return void
	 * @author Kenneth J. Brucker <ken@pumastudios.com>
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
	 **/
	function debug_log($msg)
	{
		if ( $this->debug_log_enabled )
			array_push($this->debug_log, date("Y-m-d H:i:s") . " " . $msg);
	}
	
	/**
	 * Save the error log if it's enabled
	 **/
	function save_debug_log()
	{
		if ( $this->debug_log_enabled ) {
			$options = get_option(self::settings);
			$options['debug_log'] = $this->debug_log;
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
