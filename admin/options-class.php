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
	protected $admin_notices;
	
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
	 * Setup plugin defaults and register with WordPress for use in Admin screens
	 **/
	function setup()
	{
		self::$dir = WP_PLUGIN_DIR . '/' . self::p . '/' ;
		self::$url =  WP_PLUGIN_URL . '/' . self::p . '/' ;
		$this->admin_notices = array();
		
		// Defaults for recipe head and footer display
		$this->recipe_head_fields_default = array(
			'Yield',
			'Difficulty',
			'Rating',
			'Category',
			'Duration',
			'Prep Time',
			'Cook Time',
		);
		$this->recipe_footer_fields_default = array(
			'Published',
			'Author',
			'Nutrition'
		);
						
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
			array_key_exists('recipe_unused_fields', $options) ? $options['recipe_unused_fields'] : array();
		
		// Init value for debug log
		$this->debug_log_enabled = array_key_exists('debug_log_enabled', $options) ? $options['debug_log_enabled'] : false;
		$this->debug_log = array_key_exists('debug_log',$options) ? $options['debug_log'] : array();
		if ($this->debug_log_enabled) {
			$this->admin_notices[] = sprintf(__('%s logging is enabled.  If left enabled, this can affect database performance.', self::p),'<a href="options.php?page=' . self::settings_page . '">' . self::p . '</a>');
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
		add_action('admin_print_scripts-' . $settings_page, array(&$this, 'options_scripts'));
		add_action('admin_print_styles-' . $settings_page, array(&$this, 'options_styles'));
			
		/**
		 * Add section controlling where recipes are displayed
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
			__('Add Post Class', self::p),
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
		add_action('admin_footer', array( &$this, 'admin_notice'));			//TODO Change to use an admin_notices_all action?

		// 		do_action('load-' . $page_hook); // Use to trigger events needed on the options screen
			
		// // Add plugin admin style
		// add_action( 'admin_print_styles-post.php', array(&$this, 'admin_styles'), 1000 );
		// add_action( 'admin_print_styles-post-new.php', array(&$this, 'admin_styles'), 1000 );
		// 
		// // Add plugin admin scripts
		// add_action( 'admin_print_scripts-post.php', array(&$this, 'admin_scripts'), 1000 );
		// add_action( 'admin_print_scripts-post-new.php', array(&$this, 'admin_scripts'), 1000 );

		// Register actions to use the receipe category in admin list view
		add_action('restrict_manage_posts', array(&$this, 'restrict_recipes_by_category'));
		add_action('parse_query', array(&$this, 'parse_recipe_category_query'));
		add_action('manage_' . self::post_type . '_posts_columns', array(&$this, 'add_recipe_category_to_recipe_list'));
		add_action('manage_posts_custom_column', array(&$this, 'show_column_for_recipe_list'),10,2);
		
		// Register the settings name
		register_setting( self::settings_page, self::settings, array (&$this, 'sanitize_settings') );
		
		// Register admin style sheet
		wp_register_style(self::p . 'admin', self::$url . 'admin/css/admin.css');
		
		// Register jQuery UI stylesheet
		wp_register_style(self::p . 'jquery-ui', self::$url . 'admin/css/jquery-ui.css');
		
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
		$meta_box = array(
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
												'1' => 'Easy',
												'3' => 'Medium',
												'5' => 'Hard',
					            ),
				),
			)
		);
		// Create the editor metaboxes
		$meta_box = new RW_Meta_Box($meta_box);
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
	function admin_styles() {
		global $post_type;
		if (self::post_type != $post_type) return;
	}

	/**
	 * Enqueue scripts used in admin screens for Recipe handling
	 *
	 * @return void
	 **/
	function admin_scripts() {
		global $post_type;
		if (self::post_type != $post_type) return;
	}
	
	/**
	 * Enqueue the stylesheets used in the plugin options page
	 *
	 * @return void
	 **/
	function options_styles()
	{
		wp_enqueue_style( self::p . 'admin');
		wp_enqueue_style( self::p . 'jquery-ui' );
	}
	
	/**
	 * Enqueue scripts used in the plugin options page
	 *
	 * @return void
	 **/
	function options_scripts()
	{
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
		if (count($this->admin_notices)) {
			echo '<div class="error">';
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
		_e('Add the post class to recipe posts.', self::p);
	}
	
	/**
	 * Emit HTML section used for configuring Recipe header and footer content
	 *
	 * @return void
	 **/
	function head_foot_section_html()
	{
		echo '<div id="recipe_head_foot_fields">';
			echo '<h4>Recipe Head</h4>';
			echo '<ul id="recipe_head_fields" class="menu recipe-fields">';
			foreach ($this->recipe_head_fields as $field) {
				echo '<li class="menu-item-handle">'. $field .'</li>';
			}
			echo '</ul>';
			echo '<h4>Recipe Footer</h4>';
			echo '<ul id="recipe_footer_fields" class="menu recipe-fields">';
			foreach ($this->recipe_footer_fields as $field) {
				echo '<li class="menu-item-handle">'. $field .'</li>';
			}
			echo '</ul>';
			echo '<h4>Unused</h4>';
			echo '<ul id="recipe_unused_fields" class="menu recipe-fields">';
			foreach ($this->recipe_unused_fields as $field) {
				echo '<li class="menu-item-handle">'. $field .'</li>';
			}
			echo '</ul>';
		echo '</div>';
		?>
			<script type="text/javascript">
				//<![CDATA[
				jQuery(document).ready( function($) {
					$('#recipe_head_fields, #recipe_footer_fields, #recipe_unused_fields').sortable({connectWith:'.recipe-fields'});
				});
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
	   array_push($buttons, 'hrecipeTitle', 'hrecipeYield', 'hrecipeDuration', 'hrecipePreptime', 'hrecipeCooktime',  'hrecipeAuthor', 'hrecipePublished', 'hrecipeCategory');
	// 'hrecipeIngredientList', 'hrecipeIngredient', 'hrecipeInstructions', 'hrecipeStep', 'hrecipeSummary',
	   return $buttons;
	}
 
	// Load the TinyMCE plugins : editor_plugin.js
	function add_tinymce_plugins($plugin_array) {
		$plugin_array['hrecipeTitle'] = self::$url.'lib/TinyMCE-plugins/info/editor_plugin.js';
		// $plugin_array['hrecipeTitle'] = self::$url.'lib/TinyMCE-plugins/ingredients/editor_plugin.js';
		// $plugin_array['hrecipeTitle'] = self::$url.'lib/TinyMCE-plugins/instructions/editor_plugin.js';
		// $plugin_array['hrecipeTitle'] = self::$url.'lib/TinyMCE-plugins/step/editor_plugin.js';
		// $plugin_array['hrecipeTitle'] = self::$url.'lib/TinyMCE-plugins/summary/editor_plugin.js';
	
		return $plugin_array;
	}
	
	/**
	 * Add plugin CSS to tinymce
	 *
	 * @return updated list of css files
	 **/
	function add_tinymce_css($mce_css){
		if (! empty($mce_css)) $mce_css .= ',';
		$mce_css .= self::$url . 'tinymce-editor.css';
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
	 * Update recipe database on version mismatches
	 *
	 * @return void
	 **/
	function handle_database_ver()
	{
		$this->admin_notices[] = sprintf(__('Recipe database version mismatch; using v%1$d, required v%2$d', self::p), $this->database_ver, self::required_db_ver);
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
