<?php
/**
 * hrecipe_admin class
 *
 * Extends hrecipe_microformat to manage options
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2018 Kenneth J. Brucker (ken@pumastudios.com)
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

// TODO Move meta data recipe published date to use the Post published date - Data is redundant
// TODO Add last modified date to recipe list screen
// TODO Add cuisine types - mexican, spanish, indian, etc.
// TODO Create admin widget for Recipe Categories - only allow one category to be selected
// TODO Phone-home with error log
// TODO Quick edit of recipe post_type does not update the list view without a screen refresh

// Protect from direct execution
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die( 'I don\'t think you should be here.' );
}

// Load additional classes
foreach (
	array(
		'class-hrecipe-importer.php',
		'class-hrecipe-ingredient-table.php'
	) as $lib
) {
	if ( ! include_once( $lib ) ) {
		return false;
	}
}

class hrecipe_admin extends hrecipe_microformat {
	/**
	 * Define some shorthand
	 */
	const settings_page = 'hrecipe_microformat_settings_page';
	/**
	 * Messsage constants
	 */
	const msg_added_new_ingredient = 1;
	const msg_updated_ingredient = 2;
	const msg_ingredient_update_error = 3;
	const msg_ingredient_deleted = 4;
	const msg_ingredient_blank = 5;

	/**
	 * @var array $admin_notices Warnings to display on admin screens - Update level (Yellow)
	 **/
	protected $admin_notices;

	/**
	 * @var array $admin_notice_errors Errors to display on admin screens - Error messages (Red)
	 **/
	protected $admin_notice_errors;

	/**
	 * @var array $difficulty_description Description text for each level of recipe difficulty
	 **/
	protected $difficulty_description;

	/**
	 * Recipe Importer Instance
	 *
	 * @access private
	 * @var object
	 **/
	private $hrecipe_importer;

	/**
	 * @var array $screen_options Array of admin screen options defined by plugin
	 **/
	private $screen_options;

	/**
	 * @var string $slug_ingredients_table_page Slug for the admin screen ingredients table page
	 **/
	private $slug_ingredients_table_page;

	/**
	 * @var int ingredients_per_page Default number of ingredients to display per page
	 */
	const ingredients_per_page = 20;

	/**
	 * @var string $nutrient_db_path Path to nutrient DB files
	 */
	private $nutrient_db_path;

	/**
	 * @var hrecipe_ingredients_Table $ingredients_table
	 */
	private $ingredients_table;

	/**
	 * @var array $message Array of messages to be displayed on admin screen
	 */
	private $message;

	/**
	 * Setup plugin defaults and register with WordPress for use in Admin screens
	 *
	 * @return void
	 **/
	function __construct() {
		// Do parent class work too!
		parent::__construct();

		$this->admin_notices       = array();
		$this->admin_notice_errors = array();

		// Define array of admin screen options used
		$this->screen_options = array( 'hrecipe_ingrds_per_page' );

		$this->message = array(
			self::msg_added_new_ingredient    => 'Added new ingredient.',
			self::msg_updated_ingredient      => 'Updated ingredient.',
			self::msg_ingredient_update_error => 'Database update failed for ingredient.',
			self::msg_ingredient_deleted      => 'Deleted ingredient(s).',
			self::msg_ingredient_blank        => 'Blank ingredient specified, not added to database'
		);

		// Setup Importer Class
		$this->hrecipe_importer = new hrecipe_importer( self::p, $this->recipe_category_taxonomy, $this->ingrd_db );

		// Setup path to the nutrient database files
		$this->nutrient_db_path = WP_PLUGIN_DIR . '/' . self::p . '/db/';
	}

	/**
	 * register callbacks with WP
	 *
	 * This function is called to establish the initial WP callbacks needed to set everything up for use
	 *
	 * @return void
	 **/
	function register_wp_callbacks() {
		// Do parent class work
		parent::register_wp_callbacks();

		// Filter needed for admin screen options on custom screens to save - filter is executed early in processing.
		add_filter( 'set-screen-option', array( $this, 'filter_set_screen_option' ), 10, 3 );

		// Add menu item for plugin options page
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Register for admin_init
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// If logging is enabled, setup save in the footer.
		if ( $this->options['debug_log_enabled'] ) {
			// If logging is enabled, warn admin as it affects DB performance
			$this->log_admin_notice( "red", sprintf( __( '%s logging is enabled.  If left enabled, this can affect database performance.', self::p ), '<a href="options-general.php?page=' . self::settings_page . '">' . self::p . '</a>' ) );

			add_action( 'admin_footer', array( $this, 'save_debug_log' ) );
		}

		// Check DB versions that are loaded and report error on mismatch
		if ( $this->nutrient_db->update_needed() ) {
			$this->log_admin_notice( "red", self::p . ': A newer nutrient DB is available and may be loaded from the <a href="options-general.php?page=' . self::settings_page . '">settings page</a>.' );
		}

		if ( $this->ingrd_db->update_needed() ) {
			$this->log_admin_notice( "red", self::p . ': For correct function, please update the ingredient database from the <a href="options-general.php?page=' . self::settings_page . '">settings page</a>.' );
		}
	}

	/**
	 * When plugin is activated, populate taxonomy and flush the rewrite rules
	 * TODO Save default version of options that includes any TRUE boolean values.
	 *
	 * @return boolean, true ==> Activated OK
	 **/
	function on_activation() {
		/*
		 * Setup the USDA Standard Reference Nutrition Database
		 * TODO Run database load out of band from activation!  Makes activation take too long
		 */
		if ( ! $this->nutrient_db->setup_nutrient_db( $this->nutrient_db_path ) ) {
			// There was a problem creating DB
			$this->log_err( "Unable to setup Nutrient DB, aborting activation" );

			return false;
		}

		/*
		 * Setup Ingredient Database
		 */
		if ( ! $this->ingrd_db->create_schema() ) {
			$this->nutrient_db->drop_nutrient_schema();
			$this->log_err( "Unable to setup Ingredients DB, aborting activation" );

			return false;
		}

		// Only insert terms if the category taxonomy doesn't already exist.
		if ( 0 == count( get_terms( $this->recipe_category_taxonomy, 'hide_empty=0&number=1' ) ) ) {
			wp_insert_term( __( 'Appetizer', self::p ), $this->recipe_category_taxonomy );
			wp_insert_term( __( 'Soup', self::p ), $this->recipe_category_taxonomy );
			wp_insert_term( __( 'Salad', self::p ), $this->recipe_category_taxonomy );
			wp_insert_term( __( 'Side Dish', self::p ), $this->recipe_category_taxonomy );

			wp_insert_term( __( 'Entrée', self::p ), $this->recipe_category_taxonomy );
			$entree_term    = term_exists( __( 'Entrée', self::p ), $this->recipe_category_taxonomy );
			$entree_term_id = $entree_term['term_id'];
			wp_insert_term( __( 'Pasta', self::p ), $this->recipe_category_taxonomy, array( 'parent' => $entree_term_id ) );
			wp_insert_term( __( 'Meat', self::p ), $this->recipe_category_taxonomy, array( 'parent' => $entree_term_id ) );
			wp_insert_term( __( 'Fish', self::p ), $this->recipe_category_taxonomy, array( 'parent' => $entree_term_id ) );
			wp_insert_term( __( 'Poultry', self::p ), $this->recipe_category_taxonomy, array( 'parent' => $entree_term_id ) );
			wp_insert_term( __( 'Vegetarian', self::p ), $this->recipe_category_taxonomy, array( 'parent' => $entree_term_id ) );

			wp_insert_term( __( 'Dessert', self::p ), $this->recipe_category_taxonomy );
		}

		// On activation, flush rewrite rules to make sure plugin is setup correctly.
		flush_rewrite_rules();

		return true;
	}

	/**
	 * Create admin menu item and fields of the options page
	 *
	 * @return void
	 **/
	function admin_menu() {
		/**
		 * Register the recipe importer to display in the import menu
		 */
		if ( function_exists( 'register_importer' ) ) {
			register_importer( $this->hrecipe_importer->id, $this->hrecipe_importer->name, $this->hrecipe_importer->desc, array(
				$this->hrecipe_importer,
				'dispatch'
			) );
		}

		/**
		 * Create sub-menu to manage ingredient list
		 *
		 * Use the slug for custom post type to place sub-menu under the post type parent menu
		 */

		$slug = add_submenu_page( 'edit.php?post_type=' . self::post_type,
			'List of Available Ingredients',
			'Ingredients',
			'edit_posts', // If user can edit_posts, show this menu
			self::prefix . 'ingredients-table',  // Slug for this menu
			array( $this, 'render_ingredients_table_page' ) // Method to create page for list of defined ingredients
		);
		// Do actions for ingredients table
		add_action( 'load-' . $slug, array( $this, 'action_ingredients_table_page' ) );
		// Add help & options to sub-menu page
		add_action( 'load-' . $slug, array( $this, 'ingredients_table_page_options' ) );
		// Add style sheet and scripts needed in the options page
		add_action( 'admin_print_scripts-' . $slug, array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_print_styles-' . $slug, array( $this, 'enqueue_admin_styles' ) );
		$this->slug_ingredients_table_page = $slug; // Save identifier for later

		/**
		 * Create sub-menu to add new ingredients
		 */

		$slug = add_submenu_page( 'edit.php?post_type=' . self::post_type,
			'Add/Edit Ingredient',
			'Add Ingredient',
			'edit_posts', // If user can edit_posts, show this menu
			self::prefix . 'add-ingredient',  // Slug for this menu
			array( $this, 'render_add_ingredient_page' )
		);
		// Save ingredients provided by admin
		add_action( 'load-' . $slug, array( $this, 'save_ingredient' ) );
		// Add style sheet and scripts needed in the options page
		add_action( 'admin_print_scripts-' . $slug, array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_print_styles-' . $slug, array( $this, 'enqueue_admin_styles' ) );

		/**
		 * Create Plugin Options Page as a sub-menu in Settings Section
		 */

		// Create page
		$settings_page = add_options_page(
			__( 'hRecipe Microformat Plugin Settings', self::p ),
			__( 'hRecipe Microformat', self::p ),
			'manage_options',
			self::settings_page,
			array( $this, 'render_options_page' )
		);

		// Add style sheet and scripts needed in the options page
		add_action( 'admin_print_scripts-' . $settings_page, array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_print_styles-' . $settings_page, array( $this, 'enqueue_admin_styles' ) );

		/**
		 * Add section controlling how recipes are displayed
		 **/
		$settings_section = self::settings . '-display';
		add_settings_section(
			$settings_section,
			__( 'Recipe Display Settings', self::p ),
			array( $this, 'render_settings_section_display' ),
			self::settings_page
		);

		// Display in Home field
		add_settings_field(
			self::settings . '[display_in_home]',
			__( 'Display In Home', self::p ),
			array( $this, 'render_display_in_home' ),
			self::settings_page,
			$settings_section
		);

		// Display in Feed field
		add_settings_field(
			self::settings . '[display_in_feed]',
			__( 'Display In Feed', self::p ),
			array( $this, 'render_display_in_feed' ),
			self::settings_page,
			$settings_section
		);

		// How many recipes to display in an index page
		add_settings_field(
			self::settings . '[posts_per_page]',
			__( 'Recipes per page', self::p ),
			array( $this, 'render_posts_per_page' ),
			self::settings_page,
			$settings_section
		);

		/**
		 * Add section to configure recipe header and footer
		 */
		$settings_section = self::settings . '-headfoot';
		add_settings_section(
			$settings_section,
			__( 'Recipe Header and Footer Contents', self::p ),
			array( $this, 'render_settings_section_head_foot' ),
			self::settings_page
		);

		/**
		 * Add section for debug logging
		 **/
		$settings_section = self::settings . '-debug';
		add_settings_section(
			$settings_section,
			__( 'Debug Logging', self::p ),
			array( $this, 'render_settings_section_debug' ),
			self::settings_page
		);

		// Add Plugin Error Logging
		add_settings_field(
			self::settings . '[debug_log_enabled]',
			__( 'Enable Debug Log', self::p ),
			array( $this, 'render_debug_log_enabled' ),
			self::settings_page,
			$settings_section
		);
	}

	/**
	 * Setup the admin screens
	 *
	 * To add Page hooks - format: load-PostType_page_PageName
	 *
	 **/
	function admin_init() {
		// Add section for reporting configuration errors and notices
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );

		// Add plugin admin style
		add_action( 'admin_print_styles-post.php', array( $this, 'enqueue_admin_styles' ), 1000 );
		add_action( 'admin_print_styles-post-new.php', array( $this, 'enqueue_admin_styles' ), 1000 );

		// Add plugin admin scripts
		add_action( 'admin_print_scripts-post.php', array( $this, 'enqueue_admin_scripts' ), 1000 );
		add_action( 'admin_print_scripts-post-new.php', array( $this, 'enqueue_admin_scripts' ), 1000 );

		// Register actions to use the receipe category in admin list view
		add_action( 'restrict_manage_posts', array( $this, 'restrict_recipes_by_category' ) );
		add_action( 'parse_query', array( $this, 'parse_recipe_category_query' ) );
		add_action( 'manage_' . self::post_type . '_posts_columns', array( $this, 'add_columns_to_recipe_table' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'render_recipe_table_column' ), 10, 2 );

		// Register the settings name
		register_setting( self::settings_page, self::settings, array( $this, 'sanitize_settings' ) );

		// Register admin style sheet
		wp_register_style( self::prefix . 'admin', self::$url . 'admin/css/admin.css', [], HRECIPE_PLUGIN_VER );

		// Register admin javascript, place in footer so it can be localized as needed
		wp_register_script( self::prefix . 'admin', self::$url . 'admin/js/admin.js',
			array( 'jquery-ui-autocomplete', 'jquery-ui-sortable', 'jquery-ui-button' ), HRECIPE_PLUGIN_VER, true );

		/*
			Setup the Recipe Post Editing page
		*/
		add_filter( 'default_content', array( $this, 'filter_default_content' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::post_type, array(
			$this,
			'configure_tinymce'
		) ); // TODO Best place for this?
		add_action( 'add_meta_boxes_' . self::post_type, array(
			$this,
			'setup_meta_boxes'
		) );  // Setup plugin metaboxes
		add_action( 'save_post_' . self::post_type, array(
			$this,
			'action_save_post_hrecipe'
		), 10, 3 ); // Save post metadata
		add_action( 'save_post_revision', array(
			$this,
			'action_save_post_revision'
		), 10, 3 ); // Save metadata for revisions
		add_action( 'wp_restore_post_revision', array( $this, 'action_wp_restore_post_revision' ), 10, 2 );

		// Cleanup when deleting a recipe
		add_action( 'delete_post', array( $this, 'action_delete_post' ) );

		// When post loads for edit upgrade the recipe contents from table in recipe to ingredients database
		//   (Priority 10, 2 parameters expected)
		add_filter( 'edit_post_content', array( $this, 'upgrade_recipe_ingrds_table' ), 10, 2 );

		// Add handler for database update requests
		add_action( "admin_post_hrecipe-db-update", array( $this, 'action_database_update' ) );
	}

	/**
	 * Add the metaboxes needed in the admin screens
	 *   Use add_meta_box( $id, $title, $callback, $page, $context, $priority, $callback_args )
	 *
	 * @return void
	 **/
	function setup_meta_boxes() {
		/**
		 * Add Recipe Ingredients metabox
		 */
		add_meta_box( self::prefix . 'ingredients', __( 'Recipe Ingredients', self::p ),
			array( $this, 'render_metabox_ingrd' ), self::post_type, 'normal', 'high', null );

		/**
		 * Add metabox for the Recipe Metadata
		 */
		add_meta_box( self::prefix . 'info', __( 'Additional Recipe Information', self::p ),
			array( $this, 'render_info_metabox' ), self::post_type, 'normal', 'high', null );
	}

	/**
	 * Emit HTML for Recipe Ingredients Metabox on Admin Recipe Edit Page
	 *
	 * @uses $post
	 * @return void
	 **/
	function render_metabox_ingrd() {
		global $post;

		/**
		 * Provide some input fields to the admin script for recipe editing
		 */
		$this->admin_js_params();

		/**
		 * Get ingredient list titles from post meta.  If no data found, this is a new recipe
		 */
		$ingrd_list_title = get_post_meta( $post->ID, self::prefix . 'ingrd-list-title', true );
		if ( '' == $ingrd_list_title ) {
			$ingrd_list_title = array( 1 => 'Ingredients' );
		}

		/**
		 * For each ingredient list, output a table
		 */
		// RFE If Lists can be deleted/reordered, array indexes might get mucked up
		?>
        <div class="ingrd-list-container">
			<?php
			foreach ( $ingrd_list_title as $list_id => $list_title ) {
				$ingrds = $this->ingrd_db->get_ingrds_for_recipe( $post->ID, $list_id );
				?>
                <div class="ingrd-list">
					<?php $this->render_recipe_ingrd_list( $list_id, $list_title, $ingrds ); ?>
                </div>
				<?php
			} // End foreach
			?>
        </div>
        <div class="ingrd-list-template">
			<?php $this->render_recipe_ingrd_list( 'template_id', 'Ingredients', array() ); ?>
        </div>
        <span class="insert-ingrd-list">Add New Ingredient List</span>
		<?php
	}

	/**
	 * Render HTML for an ingredient table on admin recipe edit screen
	 *
	 * @param array $list_id index for list to generate
	 * @param string $title List title
	 * @param array $ingrds saved ingredients to include in list
	 *
	 * @return void
	 */
	function render_recipe_ingrd_list( $list_id, $title, $ingrds ) {
		?>
        <p class="ingrd-list-title">
            <label for="<?php echo self::prefix; ?>ingrd-list-name[<?php echo $list_id; ?>]" class="field-label">List
                Title:</label>
            <input type="text" name="<?php echo self::prefix; ?>ingrd-list-name[<?php echo $list_id; ?>]"
                   value="<?php echo $title; ?>"/>
            <!-- TODO Make copy-text highlightable for easy copy to clipboard (jquery.selectable?)-->
            <span class="field-description">Use <span class="copy-text">[ingrd-list id="<?php echo $list_id; ?>"]</span> in recipe text to display this list.</span>
        </p>
        <table class="ingredients">
            <thead>
            <tr>
                <th class="col-interaction"></th>
                <th class="col-quantity">Amount</th>
                <th class="col-unit">Unit</th>
                <th class="col-ingrd">Ingredient</th>
                <th class="col-comment">Comment</th>
                <th class="col-status"></th>
                <th class="col-gpcup">g/cup</th>
                <th class="col-measure">preferred measure</th>
            </tr>
            </thead>
            <tbody>
			<?php
			// Setup template ingredient row for addition of new rows via javascript
			$this->render_recipe_edit_ingrd_row( $list_id, 'template', array() );

			// Add rows for recipe ingredients
			if ( count( $ingrds ) == 0 ) {
				// Setup a few empty rows in the edit screen for new posts
				for ( $i = 0; $i < 4; $i ++ ) {
					$this->render_recipe_edit_ingrd_row( $list_id, '', array() );
				}
			} else {
				foreach ( $ingrds as $d ) {
					$this->render_recipe_edit_ingrd_row( $list_id, '', $d );
				} // foreach $ingrds
			}
			?>
            </tbody>
        </table>
		<?php
	}

	/**
	 * Emit HTML for a row in the ingredients table on admin recipe edit screen
	 *
	 * @param int $list_id Containing recipe list ID for this ingredient
	 * @param string $class additional classes (space separated) to add to row
	 * @param array $data ingredient information
	 *
	 * @return void
	 **/
	function render_recipe_edit_ingrd_row( $list_id, $class, $data ) {
		/**
		 * @var string $quantity Quantity of recipe ingredient
		 * @var string $unit unit of measure for recipe ingredient
		 * @var string $ingrd ingredient name
		 * @var string $comment comments for this ingredient row
		 * @var int $food_id ID of linked ingredient in the ingredient database
		 * @var int $gpcup grams per cup for linked ingredient
		 * @var string $measure requested measurement style for linked ingredient
		 */
		extract( $data );

		// If $food_id is specified, show this row as linked and make ingredient input field readonly
		$linked_state = isset( $food_id ) && $food_id ? "food-linked" : "";
		$readonly     = isset( $food_id ) && $food_id ? "readonly" : "";

		?>
        <tr class="recipe-ingrd-row <?php echo $class; ?> <?php echo $linked_state; ?>">
            <td class="col-interaction">
                <ul>
                    <li class="ui-state-default ui-corner-all">
                        <span class="sort-handle ui-icon ui-icon-arrow-2-n-s" title="Drag to Sort ingredients"></span>
                    </li>
                    <li class="ui-state-default ui-corner-all">
                        <span class="insert ui-icon ui-icon-plusthick" title="Insert New Ingredient"></span>
                    </li>
                    <li class="ui-state-default ui-corner-all">
                        <span class="delete ui-icon ui-icon-minusthick" title="Delete this Ingredient"></span>
                    </li>
                </ul>
            </td>
            <td class="col-quantity">
                <input type="text" name="<?php echo self::prefix; ?>quantity[<?php echo $list_id; ?>][]"
                       class="quantity value" value="<?php if ( isset( $quantity ) )
					echo esc_attr( $quantity ) ?>"/>
            </td>
            <td class="col-unit">
                <input type="text" name="<?php echo self::prefix; ?>unit[<?php echo $list_id; ?>][]"
                       class="unit type ui-widget" value="<?php if ( isset( $unit ) )
					echo esc_attr( $unit ) ?>"/>
            </td>
            <td class="col-ingrd">
                <input type="text" name="<?php echo self::prefix; ?>ingrd[<?php echo $list_id; ?>][]" class="ingrd"
                       value="<?php if ( isset( $ingrd ) )
					       echo esc_attr( $ingrd ) ?>" <?php echo $readonly ?>/>
                <input type="hidden" name="<?php echo self::prefix ?>food_id[<?php echo $list_id; ?>][]"
                       value="<?php if ( isset( $food_id ) )
					       echo esc_attr( $food_id ) ?>" class="food_id">
            </td>
            <td class="col-comment">
                <input type="text" name="<?php echo self::prefix; ?>comment[<?php echo $list_id; ?>][]" class="comment"
                       value="<?php if ( isset( $comment ) )
					       echo esc_attr( $comment ) ?>"/>
            </td>
            <td class="col-status">
                <p class="food-linked-status ui-state-highlight">
                    <span class="ui-icon ui-icon-alert"
                          title="Ingredient is not associated with ingredient from database."></span>
                </p>
                <p class="ingrd-locked ui-state-default">
                    <span class="ui-icon ui-icon-unlocked" title="Unlink ingredient from database for edit"></span>
                </p>
            </td>
            <td class="col-gpcup">
                <!-- FIXME Javascript needs to fill in gpcup and measure when it matches a food id -->
				<?php $gpcup = isset( $gpcup ) ? $gpcup : ''; ?>
                <span class="gpcup linked"><?php echo esc_attr( $gpcup ); ?></span>
                <input type="number" step="any" min="0"
                       name="<?php echo self::prefix; ?>gpcup[<?php echo $list_id; ?>][]" class="gpcup unlinked"
                       value="<?php echo esc_attr( $gpcup ); ?>">
            </td>
            <td class="col-measure">
				<?php $measure = isset( $measure ) ? $measure : ''; ?>
                <span class="measure linked"><?php echo esc_attr( $measure ); ?></span>
				<?php self::render_select( self::prefix . "measure[$list_id][]", "measure unlinked", array(
					'weight' => 'Weight',
					'volume' => 'Volume'
				), esc_attr( $measure ) ); ?>
            </td>
        </tr>
		<?php
	}

	/**
	 * Upgrades the post content for a recipe post to be consistent with latest formatting
	 *
	 * Original version had a table embedded in the recipe post with ingredient content.
	 * New version uses an ingredient database so the table must be converted to database entries and the
	 * table text replace with an ingredient table shortcode to retrieve the ingredients when the recipe is displayed.
	 *
	 * <table class="ingredients">
	 *  <thead>
	 *   <tr><th><span class="ingredients-title">Title</span></th></tr>
	 *  </thead>
	 *  <tbody>
	 *   <tr>
	 *    <td><span class="value">value</span><span class="type">measure</span></td>
	 *    <td><span class="ingrd">ingredient</span><span class="comment"</span></td>
	 *   </tr>
	 *   ... Repeat <tr> as needed
	 *  </tbody>
	 *
	 * @param string $post_content Post content being edited
	 * @param int $post_id Post ID
	 *
	 * @return string modified $post_content
	 **/
	function upgrade_recipe_ingrds_table( $post_content, $post_id ) {
		// Only do this for recipe posts
		if ( get_post_type( $post_id ) != self::post_type ) {
			return $post_content;
		}

		/*
			Use the saved recipe version to determine if an upgrade is needed, and if so, what upgrade(s) to do
		*/
		$recipe_ver = get_post_meta( $post_id, self::prefix . 'recipe_version', true );
		if ( $recipe_ver == self::recipe_version ) {
			return $post_content;
		}

		// Wrap the content in tags for XML to handle it properly.  Must be removed at the back-end.
		$content                     = new DOMDocument();
		$content->preserveWhiteSpace = false;

		// Use loadHTML for loose interpretation of DOM, text will be in DOM <body>
		// XXX Until loadHTML supports HTML5, must mask errors
		libxml_use_internal_errors( true );
		$content->loadHTML( $post_content );
		libxml_clear_errors();
		$xpath = new DOMXPath( $content );

		/*
			For each ingredients table found, convert it to Database entries and change the table to a short code
		*/

		// Locate tables in the content marked with ingredients class
		$tables = $xpath->query( "//table[contains(concat(' ', normalize-space(@class), ' '), ' ingredients ')]" );

		// Start with list id 1 and empty array of titles
		$ingrds_list_id    = 1;
		$ingrds_list_title = array();

		foreach ( $tables as $table ) {
			// Grab title for this table and add to the list
			$ingrd_titles                         = $xpath->query( "./thead//span[contains(concat(' ', normalize-space(@class), ' '), ' ingredients-title ')]", $table );
			$ingrds_list_title[ $ingrds_list_id ] = $ingrd_titles->length > 0 ? $ingrd_titles->item( 0 )->nodeValue : '';

			// For each row in the table, create a DB entry in the ingredients db
			$ingrds_list = array();
			$ingrd_rows  = $xpath->query( ".//tr[contains(concat(' ', normalize-space(@class), ' '), ' ingredient ')]", $table );
			foreach ( $ingrd_rows as $row ) {
				// Extract value, type, ingrd and comment from each table row
				$result = $xpath->query( "td/span[contains(concat(' ', normalize-space(@class), ' '), ' ingrd ')]", $row );
				if ( $result->length > 0 ) {
					// Move some standard ingredient descriptors to comments – Sets both 'ingrd' and 'comment' values
					$ingrd = $this->hrecipe_importer->normalize_ingrd( $result->item( 0 )->nodeValue );
				} else {
					$ingrd['ingrd'] = '';
				}

				$result            = $xpath->query( "td/span[contains(concat(' ', normalize-space(@class), ' '), ' value ')]", $row );
				$ingrd['quantity'] = $result->length > 0 ? $result->item( 0 )->nodeValue : '';

				$result        = $xpath->query( "td/span[contains(concat(' ', normalize-space(@class), ' '), ' type ')]", $row );
				$ingrd['unit'] = $result->length > 0 ? $result->item( 0 )->nodeValue : '';

				$result  = $xpath->query( "td/span[contains(concat(' ', normalize-space(@class), ' '), ' comment ')]", $row );
				$comment = $result->length > 0 ? $result->item( 0 )->nodeValue : '';
				if ( array_key_exists( 'comment', $ingrd ) ) {
					// concat comment extracted from ingredient and part found in the comment if present
					if ( strlen( $comment > 0 ) ) {
						$ingrd['comment'] .= ", " . $comment;
					}
				} else {
					$ingrd['comment'] = $comment;
				}

				/**
				 * Try locating ingredient in the ingredients database
				 * TODO Also try singular form if 's' present at end of name
				 */
				// Request 1 return result with exact match
				$ingrd_db_rows = $this->ingrd_db->get_ingrds_by_name( $ingrd['ingrd'], 1, true );
				if ( $ingrd_db_rows ) {
					$ingrd['food_id'] = $ingrd_db_rows[0]->food_id;
				}

				$ingrds_list[] = $ingrd;
			}

			if ( count( $ingrds_list ) > 0 ) {
				// Add ingredients to the DB
				$this->ingrd_db->insert_ingrds_for_recipe( $post_id, $ingrds_list_id, $ingrds_list );
			}

			// Replace table with short code text to complete the conversion
			$sc_text = $content->createTextNode( "[ingrd-list id='" . $ingrds_list_id . "']" );
			$table->parentNode->replaceChild( $sc_text, $table );

			$ingrds_list_id ++;
		} // End processing for each table

		// If at least one list was processed, content was updated
		if ( $ingrds_list_id > 1 ) {
			// Extract the Body portion of the DOM object as new version of recipe content
			$innerHTML = "";
			$children  = $xpath->query( "//body" )->item( 0 )->childNodes;

			foreach ( $children as $child ) {
				$innerHTML .= $content->saveXML( $child );
			}

			// Save new version of content but convert <br/> back into <br /> -- WP seems to not like the former
			$post_content = str_replace( "<br/>", "<br />", $innerHTML );

			// Add a notification that the post content has been updated and should be saved.
			$this->log_admin_notice( "yellow", "Recipe content format upgraded to new ingredient format!  Please Save the updated recipe after review." );
		} else {
			/*
				Content was not updated, but recipe versions don't match.
			    Upgrade the recipe version to current release since the recipe text is already in compliance
			*/
			update_post_meta( $post_id, self::prefix . 'recipe_version', self::recipe_version );
		}

		return $post_content;
	}

	/**
	 * Emit HTML for the recipe information metabox displayed in the admin recipe editing screen
	 *
	 * @uses $post To retrieve post meta data
	 * @return void
	 **/
	function render_info_metabox() {
		global $post;

		// Use nonce for verification
		wp_nonce_field( 'info_metabox', self::prefix . 'nonce' );

		// Create the editor metaboxes
		foreach ( $this->recipe_field_map as $key => $field ) {
			// Include 'info' fields in this metabox
			if ( 'info' == $field['metabox'] ) {
				$value = get_post_meta( $post->ID, $field['key'], true ) | '';
				echo '<p id="' . self::prefix . 'info_' . $key . '"><label><span class="field-label">' . $field['label'] . '</span>';
				switch ( $field['type'] ) {
					case 'text':
						self::render_text( $field['key'], $value );
						break;

					case 'radio':
						self::render_radio( $field['key'], $field['options'], $field['option_descriptions'], $value );
						break;
				}
				if ( isset( $field['description'] ) ) {
					echo '<span class="field-description">' . $field['description'] . '</span>';
				}
				echo '</label></p>';
			}
		} // End foreach
	}

	/**
	 * WP Filter to add default recipe content to a new recipe
	 *
	 * @param string $content Input content to be modified
	 * @param WP_Post $post New Post object
	 *
	 * @return string $content updated content string
	 **/
	function filter_default_content( $content, $post ) {
		// Only apply default content for hrecipe posts
		if ( self::post_type != $post->post_type ) {
			return $content;
		}

		return $content . "[ingrd-list id=\"1\"]";
	}

	/**
	 * Save Recipe Post meta data and Ingredients
	 *
	 * Hook this function using add_action(save_post_<post_type>) so it only gets called for recipe posts
	 *
	 * @param int $post_id The Post id
	 * @param WP_Post $post Post Object
	 * @param boolean $update false => this is a new post, true => post is being updated
	 *
	 * @return void
	 **/
	function action_save_post_hrecipe( $post_id, $post, $update ) {
		// Hooking via save_post_<post_type> action ensures post type matches
		assert( get_post_type( $post_id ) == self::post_type, 'Post Type must be ' . self::post_type );
		// revisions have post_type 'revision' so will not end up in here
		assert( wp_is_post_revision( $post_id ) == 0, 'Post parent id must be 0' );

		/*
			Confirm nonce field for recipe content - nonce is only available on the post edit screen
			helps protect from incomplete $_POST data such as during a bulk edit, autosave or AJAX processing
		*/
		$nonce_field = self::prefix . 'nonce';
		if ( ! ( isset( $_REQUEST[ $nonce_field ] ) && wp_verify_nonce( $_REQUEST[ $nonce_field ], 'info_metabox' ) ) ) {
			return;
		}

		$this->_action_save_post( $post_id, $post, $update );
	}

	/**
	 * The work horse used to save recipe post data using the provided post ID
	 *
	 * Called either to save a parent recipe or to perform save for a preview (using a child post ID)
	 *
	 * @uses  array    $_POST    Submitted data being saved
	 *
	 * @param int $post_id The Post id (can be either child or parent post)
	 * @param WP_Post $post Post Object
	 * @param boolean $update false => this is a new post, true => post is being updated
	 *
	 * @return void
	 **/
	private function _action_save_post(
		$post_id,
		/* @noinspection PhpUnusedParameterInspection */
		$post,
		/* @noinspection PhpUnusedParameterInspection */
		$update
	) {
		/**
		 * Save Ingredient List Titles and ingredients
		 */
		$ingrd_list_titles = array();
		// TODO Need some error checking to make sure the various items are really present in $_POST before using them
		foreach ( $_POST[ self::prefix . 'ingrd-list-name' ] as $list_id => $title ) {
			if ( ! is_numeric( $list_id ) ) {
				continue;
			} // Non-numeric list id implies values are for the template list

			// Save list title to add to Post Meta Data
			$ingrd_list_titles[ $list_id ] = $title;

			// Strip slashes from input values - Write to DB will do the appropriate escaping of text

			$_POST[ self::prefix . 'quantity' ] = stripslashes_deep( $_POST[ self::prefix . 'quantity' ] );
			$_POST[ self::prefix . 'unit' ]     = stripslashes_deep( $_POST[ self::prefix . 'unit' ] );
			$_POST[ self::prefix . 'ingrd' ]    = stripslashes_deep( $_POST[ self::prefix . 'ingrd' ] );
			$_POST[ self::prefix . 'comment' ]  = stripslashes_deep( $_POST[ self::prefix . 'comment' ] );
			$_POST[ self::prefix . 'food_id' ]  = stripslashes_deep( $_POST[ self::prefix . 'food_id' ] );

			$ingrds = array();
			// Ingredient lists stored in Ingredient Database
			for ( $ingrd_row = 0; isset( $_POST[ self::prefix . 'quantity' ][ $list_id ][ $ingrd_row ] ); $ingrd_row ++ ) {
				// Only add row to list if it has content
				if ( $_POST[ self::prefix . 'quantity' ][ $list_id ][ $ingrd_row ] != '' ||
				     $_POST[ self::prefix . 'unit' ][ $list_id ][ $ingrd_row ] != '' ||
				     $_POST[ self::prefix . 'ingrd' ][ $list_id ][ $ingrd_row ] != '' ||
				     $_POST[ self::prefix . 'comment' ][ $list_id ][ $ingrd_row ] != '' ) {
					$ingrds[] = array(
						'quantity' => $_POST[ self::prefix . 'quantity' ][ $list_id ][ $ingrd_row ],
						'unit'     => $_POST[ self::prefix . 'unit' ][ $list_id ][ $ingrd_row ],
						'ingrd'    => $_POST[ self::prefix . 'ingrd' ][ $list_id ][ $ingrd_row ],
						'comment'  => $_POST[ self::prefix . 'comment' ][ $list_id ][ $ingrd_row ],
						'food_id'  => $_POST[ self::prefix . 'food_id' ][ $list_id ][ $ingrd_row ],
						'gpcup'    => $_POST[ self::prefix . 'gpcup' ][ $list_id ][ $ingrd_row ],
						'measure'  => $_POST[ self::prefix . 'measure' ][ $list_id ][ $ingrd_row ]
					);
				}
			}

			$this->ingrd_db->insert_ingrds_for_recipe( $post_id, $list_id, $ingrds );
		}

		/*
			Use update_metadata vs. update_post_meta below because the later will internally
			convert to parent post ID.  $post_id could be a child post (save for a preview)
		*/

		// Record format used to store this recipe
		update_metadata( 'post', $post_id, self::prefix . 'recipe_version', self::recipe_version );

		// List Titles saved as Post Meta Data
		update_metadata( 'post', $post_id, self::prefix . 'ingrd-list-title', $ingrd_list_titles );

		// Save meta data for each part of the info metabox
		foreach ( $this->recipe_field_map as $field ) {
			if ( 'info' == $field['metabox'] ) {
				$meta_key  = $field['key'];
				$meta_data = isset( $_POST[ $meta_key ] ) ? $_POST[ $meta_key ] : '';
				if ( '' == $meta_data ) {
					delete_metadata( 'post', $post_id, $meta_key );
				} else {
					update_metadata( 'post', $post_id, $meta_key, $meta_data );
				}
			}
		}
	}

	/**
	 * Save meta information for recipe posts related to revisions
	 *
	 * Called in various contexts during a post save operation:
	 *
	 *  - Saving a post
	 *    Global $action = "editpost"
	 *    DOING_AUTOSAVE and DOING_AJAX both undefined
	 *    $_REQUEST has full content of the page edit screen
	 *  - Autosave
	 *    Global $action = ""
	 *    DOING_AUTOSAVE == true, DOING_AJAX == true
	 *    $_REQUEST has specific limited content, no custom meta fields are available
	 *  - Preview
	 *    Global $action = "preview"
	 *    DOING_AUTOSAVE and DOING_AJAX both undefined
	 *    $_REQUEST has full content of the page edit screen
	 *  - Bulk Edit
	 *    Global $action = ""
	 *    DOING_AUTOSAVE and DOING_AJAX both undefined
	 *    $_REQUEST has limited content related to the bulk edit context
	 *    $_REQUEST[bulk_edit] = 'Update'
	 *  - Quick Edit
	 *    Global $action = ""
	 *    DOING_AUTOSAVE undefined, DOING_AJAX == true
	 *    $_REQUEST has limited content related to the quick edit context
	 *    $_REQUEST[action] = 'inline-save'
	 *
	 * In the case of save for preview, the $_POST[] content should be saved vs. making copy of parent
	 *
	 * Should autosave data be made available, the restore operation should be revisited to allow restore
	 * of the autosave data.
	 *
	 * Some options on how to make autosave work:
	 *  1) Hook into the data that autosave provides in the AJAX request in wp-includes/js/autosave.js
	 *     The data is gathered by the function wp.autosave.getPostData().  The function could be replaced
	 *     with a routine that first calls the original and then adds more content to the data object to be
	 *     included in the autosave request.
	 *  2) Hook the send operation using jQuery.ajaxSend() to add a function that is called before the
	 *     event is submitted.  The additional data could be added to the stream at this point
	 *  3) Build a private version of autosave.
	 *
	 * Initial implementation based on https://lud.icro.us/post-meta-revisions-wordpress
	 *
	 * @uses $action            wordpress action, set to 'preview' when a post preview has been requested
	 *
	 * @param int $post_id The Post id revision being saved
	 * @param WP_Post $post Post Object
	 * @param boolean $update false => this is a new post, true => post is being updated
	 *
	 * @return void
	 **/
	function action_save_post_revision( $revision_id, $post, $update ) {
		global $action;

		// Autosaves do not pass in the meta data so there's nothing to process
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$parent_id = wp_is_post_revision( $revision_id );
		if ( ! $parent_id ) {
			return;
		}  // Shouldn't happen, but just in case

		// Only interested in recipe posts - make sure parent is a recipe post
		if ( get_post_type( $parent_id ) != self::post_type ) {
			return;
		}

		/*
			Need to save new content on preview - not copy old
		*/
		if ( "preview" == $action ) {
			// Save provided browser data for the preview
			$this->_action_save_post( $revision_id, $post, $update );

			return;
		}

		/*
			Make a copy of the parent post to this child
		*/

		// Copy recipe version
		$recipe_ver = get_post_meta( $parent_id, self::prefix . 'recipe_version', true );
		update_metadata( 'post', $revision_id, self::prefix . 'recipe_version', $recipe_ver );

		// Save copy of ingredients lists
		$ingrd_list_titles = get_post_meta( $parent_id, self::prefix . 'ingrd-list-title', true );

		if ( is_array( $ingrd_list_titles ) ) {
			// If we have a list of titles, save in the recipe revision
			update_metadata( 'post', $revision_id, self::prefix . 'ingrd-list-title', $ingrd_list_titles );

			// For each list, copy the ingredients over too
			foreach ( $ingrd_list_titles as $list_id => $list_title ) {
				// Retrieve ingredients for the list from the parent
				$ingrds = $this->ingrd_db->get_ingrds_for_recipe( $parent_id, $list_id );

				// Save list for the revision
				$this->ingrd_db->insert_ingrds_for_recipe( $revision_id, $list_id, $ingrds );
			}
		} else {
			// Delete title list if present
			delete_metadata( 'post', $revision_id, self::prefix . 'ingrd-list-title' );

			// Remove any ingredients associated with the recipe
			$this->ingrd_db->delete_all_ingrds_for_recipe( $revision_id );
		}

		// Copy meta data from the parent to save for the child
		foreach ( $this->recipe_field_map as $field ) {
			if ( 'info' == $field['metabox'] ) {
				$meta_key  = $field['key'];
				$meta_data = get_post_meta( $parent_id, $meta_key, true );
				if ( false === $meta_data ) {
					delete_metadata( 'post', $revision_id, $meta_key );
				} else {
					update_metadata( 'post', $revision_id, $meta_key, $meta_data );
				}
			}
		}
	}

	/**
	 * Restore Recipe fields from saved revision
	 *
	 * @param int $post_id The Post ID
	 * @param int $revision_id Revision ID being restored
	 *
	 * @return void
	 **/
	function action_wp_restore_post_revision( $post_id, $revision_id ) {
		// Only interested in doing this for recipe posts
		if ( get_post_type( $post_id ) != self::post_type ) {
			return;
		}

		// If the revision is an autosave, bail out since there's nothing to recover
		if ( wp_is_post_autosave( $revision_id ) ) {
			return;
		}

		// Copy recipe version
		$recipe_ver = get_post_meta( $revision_id, self::prefix . 'recipe_version', true );
		update_metadata( 'post', $post_id, self::prefix . 'recipe_version', $recipe_ver );

		// Restore recipe ingredients
		$ingrd_list_titles = get_post_meta( $revision_id, self::prefix . 'ingrd-list-title', true );

		if ( is_array( $ingrd_list_titles ) ) {
			// If we have a list of titles, save in the recipe revision
			update_metadata( 'post', $post_id, self::prefix . 'ingrd-list-title', $ingrd_list_titles );

			// For each list, copy the ingredients over too
			foreach ( $ingrd_list_titles as $list_id => $list_title ) {
				// Retrieve ingredients for the list from the parent
				$ingrds = $this->ingrd_db->get_ingrds_for_recipe( $revision_id, $list_id );

				// Save list for the revision
				$this->ingrd_db->insert_ingrds_for_recipe( $post_id, $list_id, $ingrds );
			}
		} else {
			// Delete title list if present
			delete_metadata( 'post', $post_id, self::prefix . 'ingrd-list-title' );

			// Remove any ingredients associated with the recipe
			$this->ingrd_db->delete_all_ingrds_for_recipe( $post_id );
		}

		// Copy meta data from saved revision to the recipe
		foreach ( $this->recipe_field_map as $field ) {
			if ( 'info' == $field['metabox'] ) {
				$meta_key  = $field['key'];
				$meta_data = get_metadata( 'post', $revision_id, $meta_key, true );
				if ( false === $meta_data ) {
					delete_post_meta( $post_id, $meta_key );
				} else {
					update_post_meta( $post_id, $meta_key, $meta_data );
				}
			}
		}
	}

	/**
	 * Housekeeping when deleting a recipe post.
	 *
	 * Post Meta data is handled by core.
	 *
	 * @return void
	 **/
	function action_delete_post( $post_id ) {
		/**
		 * Delete ingredients associated with this post
		 */
		$this->ingrd_db->delete_all_ingrds_for_recipe( $post_id );
	}

	/**
	 * Setup page options and help content for the ingredients table page
	 *
	 * @return void
	 **/
	function ingredients_table_page_options() {
		$screen = WP_Screen::get( $this->slug_ingredients_table_page );

		$screen->add_option(
			'per_page',
			array(
				'label'   => 'Entries per page',
				'default' => self::ingredients_per_page,
				'option'  => 'hrecipe_ingrds_per_page' // Must list in $this->screen_options for screen option filter
			)
		);
	}

	/**
	 * Filter used to save option values for admin screens
	 *
	 * If the option is one that has been defined by plugin, return the value
	 *
	 * @return string
	 **/
	function filter_set_screen_option( $status, $option, $value ) {
		if ( in_array( $option, $this->screen_options ) ) {
			$status = $value;
		}

		return $status;
	}

	/**
	 * Perform actions associated with the ingredients table page
	 *
	 * Actions are separated out so that they can be done before admin notices are displayed
	 *
	 * @return void
	 **/
	function action_ingredients_table_page() {
		$this->ingredients_table = new hrecipe_ingredients_Table(
			$this->ingrd_db, self::post_type, self::prefix . 'add-ingredient' );
		$doaction                = $this->ingredients_table->current_action();

		/**
		 * Perform bulk table actions
		 *
		 * Nonce is generated by WP_List_Table class as bulk-pluralName
		 */
		if ( $doaction && ! empty( $_REQUEST ) && check_admin_referer( 'bulk-wp_list_ingredients', '_wpnonce' ) ) {
			// Does user have the needed credentials?
			if ( ! current_user_can( 'manage_categories' ) ) {
				wp_die( 'You are not allowed to manage ingredients' );
			}

			$sendback = esc_url( remove_query_arg( array(
				'action',
				'action2',
				'_wpnonce',
				'_wp_http_referer'
			), wp_get_referer() ) );

			switch ( $doaction ) {
				case 'delete':
					foreach ( $_REQUEST['wp_list_ingredient'] as $food_id ) {
						$this->ingrd_db->delete_ingrd( $food_id );
					}

					$sendback = esc_url( remove_query_arg( 'wp_list_ingredient', $sendback ) );
					$message  = self::msg_ingredient_deleted;
					break;

				default:
					wp_die( 'Invalid action specified for ingredient table.' );
					break;
			}

			$sendback = esc_url( add_query_arg( 'message', $message, $sendback ) );
			wp_redirect( $sendback );
			exit;
		} elseif ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
			// Cleanup URL after a NULL submit action.
			wp_redirect( esc_url( remove_query_arg( array(
				'_wp_http_referer',
				'_wpnonce'
			), stripslashes( $_SERVER['REQUEST_URI'] ) ) ) );
			exit;
		}

		// Display message from save operation
		if ( isset( $_REQUEST['message'] ) ) {
			$this->log_admin_notice( "green", $this->message[ $_REQUEST['message'] ] );
		}
	}

	/**
	 * Display HTML for page to manage ingredients
	 *
	 * @uses $plugin_page, WP global defines plugin page name
	 * @uses $this->ingredients_table, WP_List_Table Object
	 * @return void
	 **/
	function render_ingredients_table_page() {
		global $plugin_page;
		?>

        <div class="wrap">
            <div id="icon-edit" class="icon32 icon32-hrecipe-ingredients-table">
                <br>
            </div>
            <h2>Ingredients <a
                        href="?post_type=<?php echo self::post_type; ?>&page=<?php echo self::prefix ?>add-ingredient"
                        class="add-new-h2">Add Ingredient</a></h2>
            <form action method="get" accept-charset="utf-8">
                <input type="hidden" name="post_type" value="<?php echo self::post_type; ?>">
                <input type="hidden" name="page" value="<?php echo $plugin_page ?>">
				<?php
				$this->ingredients_table->prepare_items();

				$this->ingredients_table->search_box( 'search', 'search_id' ); // Must follow prepare_items() call
				$this->ingredients_table->display();
				?>
            </form>
        </div>
		<?php
	}

	/**
	 * Save user input to ingredients table
	 *
	 * @return void
	 **/
	function save_ingredient() {
		/**
		 * Add ingredient if this was a submit action
		 */
		if ( isset( $_REQUEST['submit'] ) &&
		     ( 'Add' == $_REQUEST['submit'] || 'Update' == $_REQUEST['submit'] ) ) {
			// Prepare redirection URL
			$sendback = esc_url( remove_query_arg( array( 'food_id' ), wp_get_referer() ) );

			// Is nonce valid?  check_admin_referer will die if invalid
			if ( ! empty( $_REQUEST ) && check_admin_referer( 'add_ingredient', self::prefix . 'nonce' ) ) {
				// Does user have required credentials?
				if ( ! current_user_can( 'manage_categories' ) ) {
					wp_die( 'You are not allowed to add ingredients' );
				}

				$message = $this::msg_added_new_ingredient;
				/**
				 * Collect fields from request
				 */
				$row           = array();
				$row['ingrd']  = isset( $_REQUEST['ingrd'] ) ? $_REQUEST['ingrd'] : '';
				$row['gpcup']  = isset( $_REQUEST['gpcup'] ) ? intval( $_REQUEST['gpcup'] ) : '';
				$row['NDB_No'] = isset( $_REQUEST['NDB_No'] ) ? $_REQUEST['NDB_No'] : '';

				// Default measure to volume radio button
				$row['measure'] = isset( $_REQUEST['measure'] ) ? $_REQUEST['measure'] : 'volume';

				// Updates also need food_id
				if ( 'Update' == $_REQUEST['submit'] && isset( $_REQUEST['food_id'] ) ) {
					$row['food_id'] = $_REQUEST['food_id'];
					$message        = $this::msg_updated_ingredient;
				}

				// Only insert when given a non-blank ingredient
				if ( '' != $row['ingrd'] ) {
					$result = $this->ingrd_db->insert_ingrd( $row );
					if ( $result < 1 ) {
						$message = $this::msg_ingredient_update_error;
					}
				} else {
					$message = $this::msg_ingredient_blank;
				}


				// Add result message to the query
				$sendback = esc_url( add_query_arg( 'message', $message, $sendback ) );
			}

			// After adding ingredient, reset form input and redirect to redisplay a clean form.
			wp_redirect( $sendback );
			exit;
		}

		// Display message from save operation
		if ( isset( $_REQUEST['message'] ) ) {
			$this->log_admin_notice( "green", $this->message[ $_REQUEST['message'] ] );
		}
	}

	/**
	 * Display HTML for page to add/edit ingredients
	 *
	 * TODO After completion of add ingredient, return to ingredients table if it was the previous screen.
	 *
	 * @uses $plugin_page, WP global defines plugin page name
	 * @return void
	 **/
	function render_add_ingredient_page() {
		global $plugin_page;

		// Use thickbox to display modal dialogs
		add_thickbox();

		// Setup needed javascript parameters
		$this->admin_js_params();

		/**
		 * Collect fields from request
		 */
		if ( isset( $_REQUEST['food_id'] ) ) {
			$food_id     = intval( $_REQUEST['food_id'] );
			$submit_type = 'Update';
			$row         = $this->ingrd_db->get_ingrd_by_id( $food_id );
		} else {
			$food_id        = '';
			$submit_type    = 'Add';
			$row['ingrd']   = '';
			$row['measure'] = 'volume'; // Default measure radio button to volume
			$row['gpcup']   = '';
			$row['NDB_No']  = '';
		}

		$ingrd = isset( $_REQUEST['ingrd'] ) ? esc_attr( $_REQUEST['ingrd'] ) : '';
		$gpcup = isset( $_REQUEST['gpcup'] ) ? intval( $_REQUEST['gpcup'] ) : '';
		// Default measure to volume radio button
		$measure = isset( $_REQUEST['measure'] ) ? esc_attr( $_REQUEST['measure'] ) : 'volume';

		if ( $row['NDB_No'] != '' ) {
			$NDB_ingrd   = $this->nutrient_db->get_name_by_NDB_No( $row['NDB_No'] );
			$hide_unlink = "";
		} else {
			$NDB_ingrd   = '';
			$hide_unlink = "hidden";
		}

		?>
        <div class="wrap" id="col-container">
            <div id="icon-edit" class="icon32 icon32-hrecipe-ingredients-table">
                <br>
            </div>
            <h2>Add Ingredient</h2>
            <div class="NDB" id="col-right">
                <!-- Display linked NDB Ingredient -->
                <table class="NDB_linked widefat">
                    <caption>Data from USDA National Nutrition Database<br/>Linked Ingredient: <span
                                id="NDB_ingrd"><?php echo $NDB_ingrd; ?></span></caption>
                    <thead>
                    <tr>
                        <td>Measure</td>
                        <td>Grams</td>
                    </tr>
                    </thead>
                    <tr class="prototype tr_measure">
                        <td>
                            <span class="Amount">Amount</span> <span class="Msre_Desc">Msre_Desc</span>
                        </td>
                        <td><span class="Gm_Wgt">Gm_Wgt</span>g</td>
                    </tr>
					<?php
					// If ingredient is linked with nutrition DB, display the available measures
					if ( $row['NDB_No'] != '' ) {
						$measures = $this->nutrient_db->get_measures_by_NDB_No( $row['NDB_No'] );
						foreach ( $measures as $measure ) {
							echo "<tr class='tr_measure'><td>";
							echo "<span class='Amount'>" . $measure->Amount . "</span> ";
							echo "<span class='Msre_Desc'>" . $measure->Msre_Desc . "</span>";
							echo "</td><td>";
							echo "<span class='Gm_Wgt'>" . $measure->Gm_Wgt . "</span>g";
							echo "</td></tr>";
						}
					}
					?>
                </table>
            </div>
            <div id="col-left">
                <form name="add_ingredient" action method="get" accept-charset="utf-8">
					<?php wp_nonce_field( 'add_ingredient', self::prefix . 'nonce' ); ?>
                    <input type="hidden" name="post_type" value="<?php echo self::post_type; ?>">
                    <input type="hidden" name="page" value="<?php echo $plugin_page ?>">
                    <input type="hidden" name="food_id" value="<?php echo $food_id; ?>" id="food_id">
                    <input type="hidden" name="NDB_No" value="<?php echo $row['NDB_No']; ?>" id="NDB_No">
                    <table border="0" cellspacing="5" cellpadding="5">
                        <tr>
                            <td>Ingredient</td>
                            <td><?php self::render_text( 'ingrd', $row['ingrd'] ); ?><a
                                        href="#TB_inline?width=600&height=550&inlineId=NDB_search_modal"
                                        class="thickbox add-new-h2" id="link-nutrition">Search USDA Nutrition
                                    Database</a></td>
                        </tr>
                        <tr>
                            <td>Measure</td>
                            <td><?php
								self::render_radio( 'measure', array(
									'volume' => 'Volume',
									'weight' => 'Weight'
								), array(), $row['measure'] );
								?></td>
                        </tr>
                        <tr>
                            <td>Grams per Cup</td>
                            <td><?php self::render_text( 'gpcup', $row['gpcup'] ) ?></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td><a class="<?php echo $hide_unlink; ?>" id="unlink-nutrition">Unlink Nutrition Info</a>
                            </td>
                        </tr>
                    </table>
                    <input type="submit" name="submit" value="<?php echo $submit_type ?>">
                </form>
            </div>
        </div>
        <!-- Modal Dialog box to search Nutrition DB for matching ingredients -->
        <div id="NDB_search_modal" class="hidden">
            <form id="NDB_search_form" name="NDB_search_form">
                <p>
                    <label for="NDB_search_ingrd">Search for Ingredient: </label><input type="text"
                                                                                        name="NDB_search_ingrd"
                                                                                        value="<?php echo $row['ingrd']; ?>"
                                                                                        id="NDB_search_ingrd"><span
                            class="ui-state-default ui-corner-all"><span class="search_button ui-icon ui-icon-search"
                                                                         style="display:inline-block"></span></span><img
                            class="waiting hidden"
                            src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" width="16"/>

                </p>
                <div id="NDB_search_results" class="hidden">
                    <div class="tablenav top">
                        <div class="tablenav-pages">
                            <span class="displaying-num"></span>
                            <span class="pagination-links">
								<a class="first-page disabled" title="Go to the first page"
                                   onclick="hrecipe.addIngrdPage.NDBSearch(1)">«</a>
								<a class="prev-page disabled" title="Go to the previous page"
                                   onclick="hrecipe.addIngrdPage.NDBSearch(hrecipe.pagination.page - 1)">‹</a>
								<span class="paging-input"><input class="current-page" title="Current page" type="text"
                                                                  name="paged" value="1" size="1"> of <span
                                            class="total-pages"></span></span>
								<a class="next-page disabled" title="Go to the next page"
                                   onclick="hrecipe.addIngrdPage.NDBSearch(hrecipe.pagination.page + 1)">›</a>
								<a class="last-page disabled" title="Go to the last page"
                                   onclick="hrecipe.addIngrdPage.NDBSearch(hrecipe.pagination.pages)">»</a>
							</span>
                        </div>
                    </div>
                    <table class="NDB_ingredients" border="0" cellspacing="5" cellpadding="5">
                        <thead></thead>
                        <tfoot></tfoot>
                        <tbody>
                        <tr class="prototype tr_ingrd">
                            <td>
                                <input type="radio" name="NDB_No" value="" class="NDB_No">
                            </td>
                            <td>
                                <span class="ingrd">Ingredient</span>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <input type="submit" name="selectIngrd" value="Select Ingredient" id="selectIngrd">
                    <input type="submit" name="cancel" value="Cancel" id="cancel">
                </div>
            </form>
        </div>
		<?php
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
	function configure_tinymce() {
		// Don't bother doing this stuff if the current user lacks permissions
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		// Add only in Rich Editor mode
		if ( get_user_option( 'rich_editing' ) != 'true' ) {
			return;
		}

		// Add editor stylesheet
		add_filter( 'mce_css', array( $this, 'add_tinymce_css' ) );

		// Add custom styles
		// FIXME tiny_mce_before_init changed in WP 4.0
		add_filter( 'tiny_mce_before_init', array( $this, 'tinymce_init_array' ) );
	}

	/**
	 * Enqueue stylesheets used for Recipe Handling
	 *
	 * @return void
	 **/
	function enqueue_admin_styles() {
		// Style the admin pages
		wp_enqueue_style( self::prefix . 'admin' );

		// jQuery UI style
		wp_enqueue_style( self::prefix . 'jquery-ui' );
	}

	/**
	 * Enqueue scripts used in admin screens for Recipe handling
	 *
	 * @return void
	 **/
	function enqueue_admin_scripts() {
		// TODO Move localization (providing params) to here for the admin javascript?
		// Load the plugin admin scripts
		wp_enqueue_script( self::prefix . 'admin' );

		// Need the jquery sortable support
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	/**
	 * Add a message to notice messages
	 *
	 * @param string $class "red", "yellow", "green".  Selects log message type
	 * @param strin $msg HTML content to display.  User input should be scrubbed by caller
	 *
	 * @return void
	 **/
	function log_admin_notice( $class, $msg ) {
		$this->admin_notices[] = array( $class, $msg );
	}

	/**
	 * Display Notice messages at head of admin screen
	 *
	 * @return void
	 **/
	function render_admin_notices() {
		/*
			WP defines the following classes for display:
				- error  (Red)
				- updated  (Green)
				- update-nag  (Yellow)
		*/

		static $notice_class = array(
			'red'    => 'error',
			'yellow' => 'update-nag',
			'green'  => 'updated'
		);

		if ( count( $this->admin_notices ) ) {
			foreach ( $this->admin_notices as $notice ) {
				// TODO Handle undefined notice class
				echo '<div class="' . $notice_class[ $notice[0] ] . '">';
				echo '<p>' . $notice[1] . '</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Callback for handling database update requests via admin-post.php
	 *
	 * @return void
	 */
	function action_database_update() {
		$url = urldecode( $_POST['_wp_http_referer'] );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		if ( ! empty( $_POST ) && check_admin_referer( 'hrecipe-db-update' ) ) {
			if ( array_key_exists( 'hrecipe-nutrient-update', $_POST ) && 'Update' === $_POST['hrecipe-nutrient-update'] ) {
				if ( ! $this->nutrient_db->setup_nutrient_db( $this->nutrient_db_path ) ) {
					// There was a problem creating DB
					$msg = "nutrient-db-update-fail";
				} else {
					$msg = "nutrient-db-updated";
				}
			}

			if ( array_key_exists( 'hrecipe-ingrd-update', $_POST ) && 'Update' === $_POST['hrecipe-ingrd-update'] ) {
				if ( ! $this->ingrd_db->create_schema() ) {
					// There was a problem creating DB
					$msg = "ingrd-db-update-fail";
				} else {
					$msg = "ingrd-db-updated";
				}
			}

			if ( defined( $msg ) ) {
				$url = esc_url( add_query_arg( 'msg', $msg, urldecode( $_POST['_wp_http_referer'] ) ) );
			}
		}

		wp_safe_redirect( $url );
	}

	/**
	 * Emit HTML to create the Plugin settings page
	 *
	 * @access public
	 * @return void
	 **/
	public function render_options_page() {
		$action = 'hrecipe-db-update';

		// If a database update was requested, strings will hold results
		$nutrient_db_return = "";
		$ingrd_db_return    = "";

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		if ( array_key_exists( 'msg', $_REQUEST ) ) {
			switch ( $_REQUEST['msg'] ) {
				case 'nutrient-db-update-fail':
					$nutrient_db_return = "Nutrient Database update request failed, please review server error logs.";
					break;

				case 'nutrient-db-updated':
					$nutrient_db_return = "Nutrient Database updated successfully";
					break;

				case 'ingrd-db-update-fail':
					$ingrd_db_return = "Ingredient Database update request failed, please review server error logs.";
					break;

				case 'ingrd-db-updated':
					$ingrd_db_return = "Ingredient Database updated successfully";
					break;

				default:
					// Unknown code - silently ignore
					break;
			}
		}

		?>
        <div class="wrap">
            <h2>hRecipe Microformat Plugin Settings</h2>

            <!-- Render section for database maintenance -->

            <h3>Database Maintenance</h3>
            <p>Manage plugin databases.</p>
            <form method="POST" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                <input type="hidden" name="action" value="<?php echo $action; ?>">
				<?php wp_nonce_field( $action ); ?>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row">Nutrient Database</th>
                        <td>
							<?php
							if ( $nutrient_db_return ) {
								echo "<p>$nutrient_db_return</p>";
							}

							if ( $this->nutrient_db->update_needed() ) {
								?>
                                <p>
                                    An updated Nutrition database is available. Please update.
                                </p>
                                <p>
                                    Note that the update may take a few minutes, please only press the button once.
                                </p>
								<?php
								submit_button( 'Update', 'secondary', 'hrecipe-nutrient-update' );
							} else {
								echo '<p>OK</p>';
							}
							?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Ingredient Database</th>
                        <td>
							<?php
							if ( $ingrd_db_return ) {
								echo "<p>$ingrd_db_return</p>";
							}

							if ( $this->ingrd_db->update_needed() ) {
								?>
                                <p>
                                    The ingredient Database requires an update for proper function.
                                </p>
								<?php
								submit_button( 'Update', 'secondary', 'hrecipe-ingrd-update' );
							} else {
								echo '<p>OK</p>';
							}
							?>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </form>

            <!-- Render sections for plugin settings -->

            <form method="post" action="options.php">
				<?php
				settings_fields( self::settings_page );
				do_settings_sections( self::settings_page );
				submit_button();
				?>
            </form>
        </div>
		<?php
	}

	/**
	 * Emit HTML to create the Display section for the plugin in admin screen.
	 **/
	function render_settings_section_display() {
		echo '<p>';
		_e( 'Configure how Recipes are included in the blog and feeds.', self::p );
		echo '</p>';
	}

	/**
	 * Emit HTML for plugin field
	 *
	 * @return void
	 **/
	function render_display_in_home() {
		self::render_checkbox( self::settings . '[display_in_home]', $this->options['display_in_home'] );
		_e( 'Display Recipes on the home (blog) page.', self::p );
	}

	/**
	 * Emit HTML for plugin field
	 *
	 * @return void
	 **/
	function render_display_in_feed() {
		self::render_checkbox( self::settings . '[display_in_feed]', $this->options['display_in_feed'] );
		_e( 'Include Recipes in the main feed.', self::p );
		echo ' ';
		_e( 'This change might not take effect for a client until a new post or recipe is added.', self::p );
	}

	/**
	 * Emit HTML for number of recipes to display on an index page
	 *
	 * @return void
	 **/
	function render_posts_per_page() {
		self::render_integer( self::settings . '[posts_per_page]', $this->options['posts_per_page'] );
		_e( 'How many Recipes should be displayed on index pages', self::p );
	}

	/**
	 * Emit HTML section used for configuring Recipe header and footer content
	 *
	 * @return void
	 **/
	function render_settings_section_head_foot() {
		$head_fields   = explode( ',', $this->options['recipe_head_fields'] );
		$footer_fields = explode( ',', $this->options['recipe_footer_fields'] );

		// Build list of unused fields
		$unused_fields = array();
		foreach ( $this->recipe_field_map as $key => $row ) {
			if ( ! in_array( $key, $head_fields ) && ! in_array( $key, $footer_fields ) ) {
				// Add unused fields to the list
				array_push( $unused_fields, $key );
			}
		}

		$sections = array(
			array(
				'field-name' => 'recipe_head_fields',
				'title'      => __( 'Recipe Head Section', self::p ),
				'list'       => $head_fields,
			),
			array(
				'field-name' => 'recipe_footer_fields',
				'title'      => __( 'Recipe Footer Section', self::p ),
				'list'       => $footer_fields,
			),
			array(
				'field-name' => 'recipe_unused_fields',
				'title'      => __( 'Unused Fields', self::p ),
				'list'       => $unused_fields,
			),
		);

		echo '<div id="recipe_head_foot_fields">';

		self::render_checkbox( self::settings . '[include_metadata]', $this->options['include_metadata'] );
		_e( 'Include Header and Footer Recipe Metadata in Content.', self::p );

		foreach ( $sections as $row ) {
			// Emit the HTML for each section
			echo '<div id="' . $row['field-name'] . '" class="recipe-fields">';
			echo '<h4>' . $row['title'] . '</h4>';
			self::render_input_hidden( $row['field-name'], join( ',', $row['list'] ) );
			echo '<ul>';
			foreach ( $row['list'] as $field ) {
				echo '<li class="menu-item-handle" name="' . $field . '">' . $this->recipe_field_map[ $field ]['label'] . '</li>';
			}
			echo '</ul>';
			echo '</div>'; // Close each field section
		}
		echo '</div>'; // Close entire section
	}

	/**
	 * Emit HTML to create the Debug section for the plugin in admin screen.
	 **/
	function render_settings_section_debug() {
		echo '<p>';
		_e( 'Configure debug settings.', self::p );
		echo '</p>';
	}

	/**
	 * Emit HTML to create form field used to enable/disable Debug Logging
	 **/
	function render_debug_log_enabled() {
		self::render_checkbox( self::settings . '[debug_log_enabled]', $this->options['debug_log_enabled'] );
		_e( 'Enable Plugin Debug Logging. When enabled, log will display below.', self::p );
		if ( $this->options['debug_log_enabled'] ) {
			echo '<dl class=hmf-debug-log>';
			echo '<dt>Log:';
			foreach ( $this->options['debug_log'] as $line ) {
				echo '<dd></dd>' . esc_attr( $line );
			}
			echo '</dl>';
		}
	}

	/**
	 * Emit HTML for a checkbox
	 *
	 * @param string $field Name of field
	 * @param boolean $checked True if the checkbox should be checked
	 *
	 * @return void
	 **/
	function render_checkbox( $field, $checked ) {
		$checked = $checked ? " checked" : "";
		echo '<input type="checkbox" name="' . $field . '" value="1"' . $checked . '>';
	}

	/**
	 * Emit HTML for a text field
	 *
	 * @param string $field Name of field
	 * @param string $value Default value for the input field
	 *
	 * @return void
	 **/
	function render_text( $field, $value ) {
		echo '<input type="text" name="' . $field . '" value="' . esc_attr( $value ) . '" id="' . $field . '" />';
	}

	/**
	 * Emit HTML for an integer field
	 *
	 * @param string $field Name of field
	 * @param string $value Default value for the input field
	 *
	 * @return void
	 **/
	function render_integer( $field, $value ) {
		echo '<input type="number" name="' . $field . '" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * Emit HTML for a radio button field
	 *
	 * @param string $field Name of field
	 * @param array $options array of value=>label pairs for radio button option
	 * @param array $option_descr array of value=>option descriptions for each radio button option
	 * @param string $value Default value for selected radio button
	 *
	 * @return void
	 **/
	function render_radio( $field, $options, $option_descr, $value ) {
		foreach ( $options as $key => $option ) {
			$checked = ( $value == $key ) ? ' checked' : '';
			$descr   = isset( $option_descr[ $key ] ) ? $option_descr[ $key ] : '';
			echo '<span class="radio-button" title="' . $descr . '"><input type="radio" name="' . $field . '" value="' . $key . '"' . $checked . ' />' . $option . '</span>';
		}
	}

	/**
	 * Emit HTML for a select field
	 *
	 * @param string $field Name of field
	 * @param string $class Class(es) to apply to select field
	 * @param array $options array of value->label pairs for select options
	 * @param string $value Default value for select
	 *
	 * @return void
	 **/
	function render_select( $field, $class, $options, $value ) {
		echo '<select name="' . $field . '" size="1" class="' . $class . '">';
		foreach ( $options as $key => $option ) {
			$selected = ( $value == $key ) ? ' selected' : '';
			echo '<option value="' . $key . '"' . $selected . '>' . $option . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Emit HTML for a hidden field
	 *
	 * @return void
	 **/
	function render_input_hidden( $field, $value ) {
		echo '<input type="hidden" name="' . self::settings . '[' . $field . ']" value="' . $value . '">';
	}

	/**
	 * Add plugin CSS to tinymce
	 *
	 * @return string updated list of css files
	 **/
	function add_tinymce_css( $mce_css ) {
		if ( ! empty( $mce_css ) ) {
			$mce_css .= ',';
		}
		$mce_css .= self::$url . 'admin/css/editor.css';

		return $mce_css;
	}

	/**
	 * Add plugin styles to tinymce
	 *
	 * Called prior to tinymce init to modify tinymce init parameters
	 *
	 * @return array tinyMCE init hash array
	 **/
	function tinymce_init_array( $initArray ) {
		// Preserve formats set by other plugins
		$style_formats = isset( $initArray['style_formats'] ) ? json_decode( $initArray['style_formats'] ) : array();

		// Recipe Instruction Steps
		// $style_formats[] = array('title' => 'Step', 'block' => 'div', 'wrapper' => true, 'classes' => 'step', 'exact' => true);
		$style_formats[] = array( 'title' => 'Step', 'block' => 'div', 'classes' => 'step' );

		// Recipe Hints
		$style_formats[] = array( 'title' => 'Hint', 'block' => 'p', 'classes' => 'hrecipe-hint' );

		// $initArray['theme_advanced_blockformats'] = 'Step,Hint,p,pre,address,h1,h2,h3,h4,h5,h6';
		// $initArray['formats'] = json_encode($formats);

		$initArray['style_formats'] = json_encode( $style_formats );

		return $initArray;
	}

	/**
	 * Hook to add pull-down menu to restrict recipe list by category
	 *
	 * @return void
	 **/
	function restrict_recipes_by_category() {
		global $typenow;

		if ( 'hrecipe' != $typenow ) {
			return;
		}

		$taxonomy          = $this->recipe_category_taxonomy;
		$category_taxonomy = get_taxonomy( $taxonomy );
		$selected          = filter_input( INPUT_GET, $taxonomy, FILTER_SANITIZE_STRING );

		wp_dropdown_categories( array(
			'show_option_all' => __( "Show all {$category_taxonomy->label}", self::p ),
// TODO How to get uncategorized to show up?				'show_option_none' => __("Show uncategorized", self::p),
			'taxonomy'        => $taxonomy,
			'name'            => $taxonomy,
			'orderby'         => 'name',
			'selected'        => $selected,
			'hierarchical'    => true,
			'depth'           => 2,
			'show_count'      => false, // Show count of recipes in the category
			'hide_empty'      => true // Don't show categories with no recipes
		) );
	}

	/**
	 * Hook to filter query results based on recipe category.  Turns query based on id to query based on name.
	 *
	 * @return object query
	 **/
	function parse_recipe_category_query( $query ) {
		global $pagenow;

		$taxonomy = self::prefix . "category";
		if ( 'edit.php' == $pagenow && $query->get( 'post_type' ) == self::post_type && is_numeric( $query->get( $taxonomy ) ) ) {
			$term = get_term_by( 'id', $query->get( $taxonomy ), $taxonomy );
			if ( $term ) {
				$query->set( $taxonomy, $term->slug );
			}
		}

		return $query;
	}

	/**
	 * Add the recipe columns to the Admin screen post listings
	 *
	 * @param array $list_columns Array of columns for listing
	 *
	 * @return array Updated array of columns
	 **/
	function add_columns_to_recipe_table( $list_columns ) {
		$taxonomy = $this->recipe_category_taxonomy;
		if ( ! isset( $list_columns['author'] ) ) {
			$new_list_columns = $list_columns;
		} else {
			$new_list_columns = array();
			foreach ( $list_columns as $key => $list_column ) {
				if ( 'author' == $key ) {
					$new_list_columns[ $taxonomy ] = '';
				}
				$new_list_columns[ $key ] = $list_column;
			}
		}

		$new_list_columns[ self::prefix . 'rating' ] = _x( 'Rating', 'Recipe Rating', self::p );
		$new_list_columns[ $taxonomy ]               = _x( 'Recipe Category', 'taxonomy singular name', self::p );

		return $new_list_columns;
	}

	/**
	 * Display the recipe categories in the custom list column
	 *
	 * @return void Emits HTML
	 **/
	function render_recipe_table_column( $column_name, $post_id ) {
		global $typenow;

		if ( 'hrecipe' == $typenow ) {
			$taxonomy = $this->recipe_category_taxonomy;

			switch ( $column_name ) {
				/**
				 * Recipe Category Taxonomy Column
				 */
				case $taxonomy:
					$categories = get_the_terms( $post_id, $taxonomy );
					if ( is_array( $categories ) ) {
						foreach ( $categories as $key => $category ) {
							$edit_link          = get_term_link( $category, $taxonomy );
							$categories[ $key ] = '<a href="' . $edit_link . '">' . esc_attr( $category->name ) . '</a>';
						}
						echo implode( ' | ', $categories );
					}
					break; // End of Category Taxonomy

				/**
				 * Recipe Rating
				 */
				case self::prefix . 'rating':
					echo $this->get_recipe_rating_html( $post_id );
					break; // End of Recipe Rating
			}
		}
	}

	/**
	 * Return WordPress Post Meta Key field name for specified hrecipe microformat key
	 *
	 * @access public
	 *
	 * @param string $microformat Microformat tag
	 *
	 * @return string or false if microformat tag not defined
	 **/
	public function post_meta_key( $microformat ) {
		return isset( $this->recipe_field_map[ $microformat ] ) ? $this->recipe_field_map[ $microformat ]['key'] : false;
	}

	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	private function admin_js_params() {
		wp_localize_script(
			self::prefix . 'admin',
			'hrecipeAdminVars',
			array(
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				// URL to file handling AJAX request (wp-admin/admin-ajax.php)
				'pluginPrefix' => self::prefix,
				// Prefix for actions, etc.
				'maxRows'      => 12,
			)
		);
	}

	/**
	 * Return contents of named file in filesystem
	 *
	 * @access public
	 *
	 * @param string $path File name to retrieve
	 *
	 * @return string File contents
	 **/
	function get_file( $path ) {
		if ( function_exists( 'realpath' ) ) {
			$path = realpath( $path );
		}

		if ( ! $path || ! @is_file( $path ) ) {
			return '';
		}

		if ( function_exists( 'file_get_contents' ) ) {
			return @file_get_contents( $path );
		}

		$content = '';
		$fp      = @fopen( $path, 'r' );
		if ( ! $fp ) {
			return '';
		}

		while ( ! feof( $fp ) ) {
			$content .= fgets( $fp );
		}

		fclose( $fp );

		return $content;
	}

	/**
	 * Cleanup database if uninstall is requested
	 *
	 * @uses string $table_prefix WP database table prefix defined for site
	 * @return void
	 **/
	function uninstall() {
		global $table_prefix;

		delete_option( self::settings ); // Remove the plugin settings

		/** Delete the recipe posts, including all draft and unpublished versions **/
		$arg     = array(
			'post_type'   => self::post_type,
			'post_status' => 'publish,pending,draft,auto-draft,future,private,inherit,trash',
			'nopaging'    => true,
		);
		$recipes = new WP_Query( $arg );
		foreach ( $recipes->posts as $recipe ) {
			wp_delete_post( $recipe->ID, false ); // Allow the posts to go into the trash, just in case...
		}

		/** Delete taxonomies **/
		// TODO -- Need to sort out how to do this without doing a register - If that's possible
		self::register_taxonomies();  // Need to register the taxonmies so the uninstall can find them to remove
		foreach ( self::$taxonomies as $taxonomy ) {
			global $wp_taxonomies;
			$terms = get_terms( $taxonomy, array( 'hide_empty' => false ) ); // Get all terms for the taxonomy to remove
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					wp_delete_term( $term->term_id, $taxonomy );
				}
			}
			unset( $wp_taxonomies[ $taxonomy ] );
		}

		/** Drop nutritional tables **/
		$nutrient_db = new hrecipe_nutrient_db( $table_prefix . self::prefix, '' );
		$nutrient_db->drop_food_schema();

		/** Drop ingredient table **/
		$ingrd_db = new hrecipe_ingrd_db( self::prefix );
		$ingrd_db->drop_schema();
	}

	/**
	 * Log an error message for display
	 * TODO Support the WP Debug Bar Plugin
	 **/
	function debug_log( $msg ) {
		if ( $this->options['debug_log_enabled'] ) {
			array_push( $this->options['debug_log'], date( "Y-m-d H:i:s" ) . " " . $msg );
		}
	}

	/**
	 * Save the error log if it's enabled
	 **/
	function save_debug_log() {
		if ( $this->options['debug_log_enabled'] ) {
			$options              = get_option( self::settings );
			$options['debug_log'] = $this->options['debug_log'];
			update_option( self::settings, $options );
		}
	}

	/**
	 * Log errors to server log and debug log
	 *
     * @param string $msg Error message
	 * @return void
	 **/
	function log_err( $msg ) {
		error_log( self::p . ": " . $msg );
		$this->debug_log( $msg );
	}
} // END class

