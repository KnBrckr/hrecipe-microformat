<?php
/**
 * hrecipe_microformat Class
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2018 Kenneth J. Brucker (email: ken@pumastudios.com)
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

// Protect from direct execution
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die( 'I don\'t think you should be here.' );
}

// TODO List Formatting for Recipe Steps
/*
	TODO Follow this model to handle database errors
		if ( false === $wpdb->update( $wpdb->posts, $data, $where ) ) {
			if ( $wp_error )
				return new WP_Error('db_update_error', __('Could not update post in the database'), $wpdb->last_error);
			else
				return 0;
        }
*/

// TODO Include recipe ratings in archive formats

class hrecipe_microformat {
	/**
	 * Minimum version of WordPress required by plugin
	 **/
	const wp_version_required = '4.0';

	/**
	 * Minimum version of PHP required by the plugin
	 **/
	const php_version_required = '5.3.6';

	/**
	 * Version of recipe format used in saved recipe post.
	 *
	 * The version used for a recipe is saved in the recipe meta data
	 *
	 * Undefined    Original release used tables embedded in recipe text for ingredients.
	 * 1.0          short codes for ingredient lists backed by database using numeric list ids
	 **/
	const recipe_version = '1.0';

	/**
	 * Define some shorthand
	 */
	const p = 'hrecipe-microformat';                // Plugin Name
	const prefix = 'hrecipe_';                        // prefix for ids, names, etc.
	const post_type = 'hrecipe';                    // Applied to entry as a class
	const settings = 'hrecipe_microformat_settings';// Name for plugin settings in WP DB

	protected static $dir; // Base directory for Plugin
	protected static $url; // Base URL for plugin directory

	/**
	 * Map internal field names to displayed names, description
	 *
	 * Index into the primary array is the hrecipe microformat field name
	 *
	 * Each row contains:
	 *    label - Name to use to label the related value
	 *  description - 1-line description of the field
	 *  format - data storage format:  tax --> Taxonomy, meta --> Post Metadata, nutrition --> Special class of Post Meta
	 *  type - HTML INPUT format to use
	 *
	 * @var array of arrays
	 **/
	protected $recipe_field_map;

	/**
	 * Hash holding plugin options
	 *    'display_in_home'        : True if recipes should be displayed in the home page
	 *    'display_in_feed'        : True if recipes should be displayed in the main feed
	 *    'include_metadata'       : True if recipe meta data should be added to content section
	 *    'recipe_head_fields'     : Ordered list of fields to include in the recipe head
	 *    'recipe_footer_fields'   : Ordered list of fields to include in the recipe footer
	 *    'posts_per_page'         : Number of recipes to display on an index page
	 *    'debug_log_enabled'      : True if logging plugin debug messages
	 *    'debug_log'              : Array of debug messages when debug_log_enabled is true
	 *
	 * @access protected
	 * @var array hash of option values
	 **/
	protected $options;

	/**
	 * Array of taxonomy names registered by the plugin
	 *
	 * @access protected
	 * @var array $taxonomies
	 **/
	protected $taxonomies;

	/**
	 * Name of recipe category taxonomy
	 *
	 * @access protected
	 * @var string $recipe_category_taxonomy
	 **/
	protected $recipe_category_taxonomy;

	/**
	 * Container for government database of food nutritional information
	 *
	 * @access protected
	 * @var hrecipe_nutrient_db instance
	 **/
	var $nutrient_db;

	/**
	 * Container for ingredients database
	 *
	 * Used to access full list of defined ingredients and ingredients associated with each recipe
	 *
	 * @var hrecipe_ingrd_db instance
	 **/
	var $ingrd_db;

	/**
	 * For single pages, remember the post ID for processing in widgets
	 *
	 * @access protected
	 * @var integer
	 **/
	protected $post_id;

	/**
	 * Class contructor
	 *
	 * init the module during wp_init phase
	 *
	 * @uses $table_prefix - Wordpress database table prefix
	 */

	function __construct() {
		global $table_prefix;

		self::$dir                      = WP_PLUGIN_DIR . '/' . self::p . '/';
		self::$url                      = plugins_url( self::p ) . '/';
		$this->recipe_category_taxonomy = self::prefix . 'category';

		/**
		 *    Define Recipe meta data fields
		 **/
		$this->recipe_field_map = array(
			'yield'      => array(
				'label'       => __( 'Yield', self::p ), # TODO Use value, unit for yield (x cookies, x servings, ...)?
				'description' => __( 'Amount the recipe produces, generally the number of servings.', self::p ),
				'type'        => 'text',
				'key'         => self::prefix . 'yield',
				'metabox'     => 'info',
				'format'      => 'text'
			),
			'difficulty' => array(
				'label'               => __( 'Difficulty', self::p ),
				'description'         => __( 'Difficulty or complexity of the recipe.', self::p ),
				'type'                => 'radio',
				'key'                 => self::prefix . 'difficulty',
				'metabox'             => 'info',
				'options'             => array(
					'1' => __( 'Basic', self::p ),
					'2' => __( 'Easy', self::p ),
					'3' => __( 'Average', self::p ),
					'4' => __( 'Hard', self::p ),
					'5' => __( 'Challenging', self::p )
				),
				'option_descriptions' => array(
					'0' => __( 'The difficulty of this recipe has not been entered.', self::p ),
					'1' => __( 'Basic recipe with a few common ingredients, no alcohol, a few simple steps and no heat source required.  This is a safe recipe that can be done by children with limited assistance.', self::p ),
					'2' => __( 'Easy recipe with easy to find ingredients and a small number of steps that might contain alcohol and may require a heat source.  The recipe might be able to be made by older children and is generally appropriate for someone with little cooking experience.', self::p ),
					'3' => __( 'Average difficulty that might require some skills (chopping, dicing, slicing, measuring, small appliances, etc.).  Ingredients are available to most home cooks at their local grocery store.  Cooking time is usually no more than about an hour.', self::p ),
					'4' => __( 'Above Average recipes might contain harder to find ingredients (specialty stores), advanced cooking techniques or unusual tools not found in the typical home kitchen.  Some recipes in this category might use basic ingredients while requiring advanced techniques or special tools.  These recipes might have significantly more preparation steps, longer processes or difficult assembly steps.', self::p ),
					'5' => __( 'These are challenging recipes, particularly for the home cook.  Special techniques or tools might be required and recipes might contain multiple hard to find ingredients.', self::p )
				),
				'format'              => 'difficulty'
			),
			'rating'     => array(
				'label'       => __( 'Rating', self::p ),
				'description' => __( 'Rating of the recipe out of five stars.', self::p ),
				'metabox'     => '',
				'format'      => 'rating'
			),
			'category'   => array(
				'label'       => __( 'Category', self::p ),
				'description' => __( 'Type of recipe', self::p ),
				'metabox'     => 'category',
				'format'      => 'tax'
			),
			'duration'   => array(
				'label'       => __( 'Duration', self::p ),
				'description' => __( 'Total time it takes to make the recipe.', self::p ),
				'type'        => 'text',
				'key'         => self::prefix . 'duration',
				'metabox'     => 'info',
				'format'      => 'text'
			),
			'preptime'   => array(
				'label'       => __( 'Prep Time', self::p ),
				'description' => __( 'Time it takes in the preparation step of the recipe.', self::p ),
				'type'        => 'text',
				'key'         => self::prefix . 'preptime',
				'metabox'     => 'info',
				'format'      => 'text'
			),
			'cooktime'   => array(
				'label'       => __( 'Cook Time', self::p ),
				'description' => __( 'Time it takes in the cooking step of the recipe.', self::p ),
				'type'        => 'text',
				'key'         => self::prefix . 'cooktime',
				'metabox'     => 'info',
				'format'      => 'text'
			),
			'published'  => array(
				'label'       => __( 'Published', self::p ),
				'description' => __( 'Date of publication of the recipe', self::p ),
				'type'        => 'text',
				'key'         => self::prefix . 'published',
				'metabox'     => 'info',
				'format'      => 'text'
			),
			'author'     => array(
				'label'       => __( 'Author', self::p ),
				'description' => __( 'Recipe Author, if different from person posting the recipe.', self::p ),
				'type'        => 'text',
				'key'         => self::prefix . 'author',
				'metabox'     => 'info',
				'format'      => 'text'
			),
			'nutrition'  => array(
				'label'       => __( 'Nutrition', self::p ),
				'description' => __( 'Recipe nutrition information', self::p ),
				'metabox'     => 'nutrition', // TODO How is nutrition managed?
				'format'      => 'nutrition'
			),
		);

		/*
		 * Retrieve Plugin Options
		 */
		$this->options = (array) $this->sanitize_settings( get_option( self::settings ) );

		// Create instance of the ingredients and USDA Nutrient Database
		$this->nutrient_db = new hrecipe_nutrient_db( $table_prefix . self::prefix );
		$this->ingrd_db    = new hrecipe_ingrd_db( $table_prefix . self::prefix );
	}

	/**
	 * Register callbacks with wordpress
	 *
	 * This function is called to establish the initial WP callbacks needed to set everything up for use
	 *
	 * @return void
	 **/
	function register_wp_callbacks() {
		add_action( 'init', array( $this, 'action_init' ) );

		// If logging is enabled, setup save in the footers.
		if ( $this->options['debug_log_enabled'] ) {
			add_action( 'wp_footer', array( $this, 'save_debug_log' ) );
		}
	}

	/**
	 * Executes during WP init phase
	 *
	 * @uses $wp_scripts, To retrieve version of jQuery for auto-loading of proper style sheets
	 * @return void
	 */
	function action_init() {
		global $wp_scripts;

		// Register custom taxonomies
		$this->register_taxonomies();

		// Add recipe custom post type
		$this->create_post_type();

		// Adjust WP Query to include recipe posts in post queries
		add_filter( 'pre_get_posts', array( $this, 'filter_pre_get_posts' ) );

		// Do template setup after posts have been loaded
		add_action( 'wp', array( $this, 'action_wp' ) );

		// Register Plugin CSS
		wp_register_style( self::prefix . 'style', self::$url . 'hrecipe.css', [],HRECIPE_PLUGIN_VER );

		// Register Plugin javascript, but put it in the footer so that it can be localized if needed
		wp_register_script( self::prefix . 'js', self::$url . 'js/hrecipe.js', array(
			'jquery',
			'jquery-ui-button'
		), HRECIPE_PLUGIN_VER, true );

		// Register jQuery UI stylesheet - use googleapi version based on what version of core is running
		wp_register_style( self::prefix . 'jquery-ui', "//ajax.googleapis.com/ajax/libs/jqueryui/{$wp_scripts->registered['jquery-ui-core']->ver}/themes/smoothness/jquery-ui.min.css" );

		// Register jQuery UI plugin for Ratings
		// XXX Might need to upgrade to supported Star Rating plugin in the future
		wp_register_script( 'jquery.ui.stars', self::$url . 'lib/jquery.ui.stars-3.0/jquery.ui.stars.min.js', array(
			'jquery-ui-core',
			'jquery-ui-widget'
		), '3.0.1' );
		wp_register_style( 'jquery.ui.stars', self::$url . 'lib/jquery.ui.stars-3.0/jquery.ui.stars.min.css', array(), '3.0.1' );

		// Register AJAX action for recipe ratings (action: hrecipe_recipe_rating)
		add_action( 'wp_ajax_' . self::prefix . 'recipe_rating', array( $this, 'ajax_recipe_rating' ) );
		add_action( 'wp_ajax_nopriv_' . self::prefix . 'recipe_rating', array( $this, 'ajax_recipe_rating' ) );

		// Register AJAX action used for ingredient name auto-completion
		add_action( 'wp_ajax_' . self::prefix . 'ingrd_auto_complete', array( $this, 'ajax_ingrd_auto_complete' ) );

		// Register AJAX action for searching USDA Nutrition database (NDB) for ingredients
		add_action( 'wp_ajax_' . self::prefix . 'NDB_search', array( $this, 'ajax_NDB_search' ) );
		add_action( 'wp_ajax_' . self::prefix . 'NDB_measures', array( $this, 'ajax_NDB_measures' ) );
	}

	/**
	 * Setup actions, filters, etc. needed during template processing if recipes will be handled
	 *
	 * Executes after query has been parsed and posts are loaded and before template actions
	 *
	 * @uses $post Post data
	 * @return void
	 **/
	function action_wp() {
		// Not needed on admin pages
		if ( is_admin() ) {
			return;
		}

		// Enqueue scripts and style sheets
		add_action( 'wp_enqueue_scripts', array( $this, 'action_plugin_enqueue_scripts' ) );

		// Update classes applied to body element
		add_filter( 'body_class', array( $this, 'filter_body_class' ), 10, 2 );

		// Hook into the post processing to localize elements needing access to $post
		add_action( 'the_post', array( $this, 'action_the_post' ) );

		// Must mark recipe titles with appropriate hrecipe microformat class
		add_filter( 'the_title', array( $this, 'filter_the_title' ), 10, 2 ); // priority 10 (WP default), 2 arguments

		// Add recipe meta data to the post content
		add_filter( 'the_content', array( $this, 'filter_the_content' ) );

		// During handling of footer in the body ...
		add_action( 'wp_footer', array( $this, 'action_wp_footer' ) );

		/*
		 * Register plugin supported shortcodes
		 */
		add_shortcode( 'ingrd-list', array( $this, 'sc_ingrd_list' ) );
		add_shortcode( 'instructions', array( $this, 'sc_instructions' ) );
		add_shortcode( 'step', array( $this, 'sc_step' ) );
		add_shortcode( 'instruction', array( $this, 'sc_step' ) );  // Allow instruction shortcode as an alternate
		add_shortcode( self::p . '-category-list', array( $this, 'sc_category_list' ) );
	}

	/**
	 * Enqueues scripts for delivery on front-end
	 *
	 * @return void
	 **/
	function action_plugin_enqueue_scripts() {
		// Include Ratings JS module
		wp_enqueue_script( 'jquery.ui.stars' );
		wp_enqueue_style( 'jquery.ui.stars' );

		// Include JSON processing javascript module
		wp_enqueue_script( 'json2' );

		// Include the plugin styling
		wp_enqueue_style( self::prefix . 'style' );

		// Load plugin javascript
		wp_enqueue_script( self::prefix . 'js' );

		// jQuery UI style
		wp_enqueue_style( self::prefix . 'jquery-ui' );
	}

	/**
	 * Add plugin defined classes to body
	 *
	 * Update body class to flag as no-js.  If javascript is available, the class will be cleared.
	 * The filter is called after WP has added contents of $class to the $classes array.
	 *
	 * @param array $classes Array of classes defined for <body>
	 * @param string|array $class String or array of classes to be added to class array
	 *
	 * @return array Updated class Array
	 */
	function filter_body_class(
		$classes, /* @noinspection PhpUnusedParameterInspection */
		$class
	) {
		/**
		 * Add no-js to class list if not already there
		 */
		if ( ! in_array( 'no-js', $classes ) ) {
			$classes[] = "no-js";
		}

		/**
		 * If displaying a single recipe, add recipe class
		 */
		if ( is_singular( self::post_type ) ) {
		    if ( ! in_array( self::post_type, $classes ) ) {
		        $classes[] = self::post_type;
            }
        }

		return $classes;
	}

	/**
	 * Use plugin provided page templates as defaults for certain page types
	 *
	 * Plugin provides templates for:
	 *    Recipe Page (single-hrecipe)
	 *    Archive Recipe Page (archive-hrecipe)
	 *    Taxonomy List for recipe category (taxonomy-hrecipe_category)
	 *
	 * FIXME Whole site Search returns an index page of articles, searching only recipes works - Theme issue?
	 *
	 * @return string, template path
	 **/
	function filter_template_include( $template ) {
		global $post;

		if ( is_singular( self::post_type ) ) {
			$template_name = 'single-' . get_post_type( $post );
		} elseif ( is_post_type_archive( self::post_type ) ) {
			$template_name = 'archive-' . get_post_type( $post );
		} elseif ( is_tax( $this->recipe_category_taxonomy ) ) {
			$template_name = 'taxonomy-' . $this->recipe_category_taxonomy;
		} else {
			return $template;
		}

		$template_name .= '.php';

		// Look for version of template in the theme
		$new_template = locate_template( array( $template_name ), true );
		if ( ! empty( $new_template ) ) {
			return $new_template;
		}

		// Return the plugin version of the template if available
		$template_name = self::$dir . 'lib/template/' . $template_name;
		if ( is_readable( $template_name ) ) {
			return self::$dir . 'lib/template/' . $template_name;
		} else {
			return $template;
		}
	}

	/**
	 * Action Hook for the_post processing
	 *
	 * @return void
	 **/
	function action_the_post() {
		global $post;

		if ( is_single() ) {
			/**
			 * Provide some data to javascript for browser handling
			 */
			wp_localize_script(
				self::prefix . 'js',
				'hrecipeVars',
				array(
					'ajaxurl'      => admin_url( 'admin-ajax.php' ),
					// URL to file handling AJAX request (wp-admin/admin-ajax.php)
					'pluginPrefix' => self::prefix,
					// Prefix for actions, etc.
					'postID'       => $post->ID,
					'userRating'   => self::user_rating( $post->ID ),
					// How has the user rated this recipe?
					'ratingNonce'  => wp_create_nonce( self::prefix . 'recipe-rating-nonce' )
				)
			);
		}
	}

	/**
	 * Plugin processing in the footer section of the body element.
	 * - Add javascript to remove 'no-js' class when javascript is available
	 *
	 * @return void
	 **/
	function action_wp_footer() {
		?>
        <script>
            // If javascript can run, remove the no-js class from the body
            let el = document.getElementsByTagName('body')[0];
            el.className = el.className.replace('no-js', '');
        </script>
		<?php
	}

	/**
	 * Update the WP query
	 *
	 * @param object $query WP query
	 *
	 * @return object Updated WP query
	 **/
	function filter_pre_get_posts( $query ) {
		// If not main query or on admin page, bail
		if ( ! $query->is_main_query() || is_admin() ) {
			return $query;
		}

		// Add plugin post type to home and feed queries
		if ( ( is_home() && $this->options['display_in_home'] ) || ( is_feed() && $this->options['display_in_feed'] ) ) {
			$query_post_type = $query->get( 'post_type' );
			if ( is_array( $query_post_type ) ) {
				$query_post_type[] = self::post_type;
			} else {
				if ( '' == $query_post_type ) {
					$query_post_type = 'post';
				}
				$query_post_type = array( $query_post_type, self::post_type );
			}
			$query->set( 'post_type', $query_post_type );
		}

		/**
		 * Display specified number of recipe titles per recipe archive page, sorted by title in ascending order
		 */
		if ( is_post_type_archive( self::post_type ) || is_tax( $this->recipe_category_taxonomy ) ) {
			$query->set( 'posts_per_page', $this->options['posts_per_page'] );
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
		}

		return $query;
	}

	/**
	 * Add span to Post Title to mark it as a recipe title
	 *
	 * @param string $title Post title
	 * @param integer $post_id Post ID
	 *
	 * @return string Updated title string
	 **/
	function filter_the_title( $title, $post_id ) {
		// Only do this for single recipe posts, must be in the main loop!
		if ( ! in_the_loop() || ! is_single() || get_post_type( $post_id ) != self::post_type ) {
			return $title;
		}

		$title = '<span class="fn">' . $title . "</span>";

		return $title;
	}

	/**
	 * Add recipe head and footer to recipe content
	 *
	 * Save post_id for widgets if this is a single post page
	 *
	 * @uses $post
	 *
	 * @param $content string post content
	 *
	 * @return string Updated post content
	 **/
	function filter_the_content( $content ) {
		global $post;

		// Only do this for recipe posts
		if ( get_post_type() != self::post_type ) {
			return $content;
		}

		// Save post id for later processing by widgets
		if ( is_single() ) {
			$this->post_id = $post->ID;
		}

		$result = '';

		if ( $this->options['include_metadata'] ) {
			$result .= $this->recipe_meta_html( 'head', $this->options['recipe_head_fields'] );
		}
		$result .= '<section class="instructions">' . $content . '</section>';
		if ( $this->options['include_metadata'] ) {
			$result .= $this->recipe_meta_html( 'footer', $this->options['recipe_footer_fields'] );
		}

		return $result;
	}

	/**
	 * Prints HTML with meta information for the current post recipe (date/time and author.)
	 *
	 * Called during The Loop
	 *
	 * @return void
	 */
	function posted_on() { // TODO Confirm microformat of Author field
		printf( __( '<span class="%1$s">Posted on</span> %2$s <span class="meta-sep">by</span> %3$s', hrecipe_microformat::p ),
			'meta-prep meta-prep-author',
			sprintf( '<a href="%1$s" title="%2$s" rel="bookmark"><span class="entry-date">%3$s</span></a>',
				get_permalink(),
				esc_attr( get_the_time() ),
				get_the_date()
			),
			sprintf( '<span class="author vcard"><a class="url fn n" href="%1$s" title="%2$s">%3$s</a></span>',
				hrecipe_microformat::get_author_recipes_url( get_the_author_meta( 'ID' ) ),
				sprintf( esc_attr__( 'View all recipes by %s', hrecipe_microformat::p ), get_the_author() ),
				get_the_author()
			)
		);
	}

	/**
	 * Prints HTML with meta information for the current recipe (category, tags and permalink).
	 *
	 * Called during The Loop
	 *
	 * @uses $post Uses Post ID to locate recipe category assisgned to the post
	 * @return void
	 */
	// Hook into category display
	function posted_in() {
		global $post;

		// Retrieves tag list of current post, separated by commas.
		$tag_list        = get_the_tag_list( '', ', ' );
		$recipe_category = get_the_term_list( $post->ID, $this->recipe_category_taxonomy, '', ', ', '' );
		if ( $tag_list ) {
			$posted_in = __( 'This recipe is posted in %1$s and tagged %2$s. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.', self::p );
		} elseif ( ! empty( $recipe_category ) ) {
			$posted_in = __( 'This recipe is posted in %1$s. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.', self::p );
		} else {
			$posted_in = __( 'Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.', self::p );
		}
		// Prints the string, replacing the placeholders.
		printf(
			$posted_in,
			$recipe_category,
			$tag_list,
			get_permalink(),
			the_title_attribute( 'echo=0' )
		);
	}

	/**
	 * Retrieve the URL to the recipe author page for the user with the ID provided.
	 *
	 * Lifted from wp-includes/author-template.php get_author_posts_url()
	 *
	 * @return string The URL to the author's recipe page.
	 */
	// TODO Hook into author generation
	function get_author_recipes_url( $author_id, $author_nicename = '' ) {
		global $wp_rewrite;
		$auth_ID = (int) $author_id;
		$link    = $wp_rewrite->get_author_permastruct();

		if ( empty( $link ) ) {
			$file  = home_url( '/' );
			$link  = $file . '?author=' . $auth_ID;
			$token = '&';
		} else {
			if ( '' == $author_nicename ) {
				$user = get_userdata( $author_id );
				if ( ! empty( $user->user_nicename ) ) {
					$author_nicename = $user->user_nicename;
				}
			}
			$link  = str_replace( '%author%', $author_nicename, $link );
			$link  = home_url( user_trailingslashit( $link ) );
			$token = '?';
		}

		// Add post_type to the query
		$link .= $token . 'post_type=' . self::post_type;

		$link = apply_filters( 'author_recipes_link', $link, $author_id, $author_nicename );

		return $link;
	}

	/**
	 * Return recipe post type
	 *
	 * @return string Recipe Post Type
	 **/
	public function get_post_type() {
		return self::post_type;
	}

	/**
	 * Return saved post_id
	 *
	 * @return integer Post ID
	 **/
	public function get_post_id() {
		return isset( $this->post_id ) ? $this->post_id : false;
	}

	/**
	 * Provide list of available recipe fields
	 *
	 * @return array "recipe-field" => "label"
	 **/
	public function get_recipe_fields() {
		$retval = array();
		foreach ( $this->recipe_field_map as $index => $field ) {
			$retval[ $index ] = $field['label'];
		}

		return $retval;
	}

	/**
	 * Generate recipe meta data section when called with The Loop
	 *
	 * @access private
	 * @uses $post
	 * @return string HTML
	 **/
	private function recipe_meta_html( $section, $list ) {
		global $post;

		$content = '<div class="' . self::post_type . '-' . $section . '">';
		foreach ( explode( ',', $list ) as $field ) {
			$content .= $this->get_recipe_field_html( $field, $post->ID );
		}
		$content .= '</div>';

		return $content;
	}

	/**
	 * Provide HTML for a named recipe field
	 *
	 * @access public
	 *
	 * @param string $field recipe meta data field name
	 * @param int $post_id Post ID
	 *
	 * @return string HTML
	 **/
	public function get_recipe_field_html( $field, $post_id ) {
		if ( empty( $this->recipe_field_map[ $field ] ) || ! isset( $this->recipe_field_map[ $field ]['format'] ) ) {
			return '';
		}

		// Produce the field value based on format of the meta data
		switch ( $this->recipe_field_map[ $field ]['format'] ) {
			case 'text':  // default Post Meta Data
				$value = get_post_meta( $post_id, self::prefix . $field, true );
				$value = esc_attr( $value );
				break;

			case 'tax': // Taxonomy data
				$terms = get_the_terms( $post_id, self::prefix . $field );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$names[] = '<a href="' . get_term_link( $term->slug, $this->recipe_category_taxonomy ) . '">' . $term->name . '</a>';
					}
					$value = implode( ', ', $names );
				} else {
					$value = '';
				}
				break;

			case 'difficulty': // Recipe difficulty
				$value = $this->get_recipe_difficulty_html( $post_id );
				break;

//			FIXME Recipe Rating is not working reliably
//			case 'rating': // Recipe rating based on reader response
//				$value = $this->get_recipe_rating_html( $post_id );
//				break;

			case 'nutrition': // Recipe nutrition as calculated from ingredients
				$value = $this->get_nutrition_html( $post_id );
				break;

			default:
				$value = '';
		}

		/**
		 * Do not display field if the value is empty
		 */
		if ( '' == $value ) {
		    return '';
        }

		$content = '<div class="' . self::post_type . '-field ' . self::prefix . $field . '">';
		$content .= $this->recipe_field_map[ $field ]['label'] . ': <span class="' . $field . '">' . $value . '</span>';
		$content .= '</div>';

		return $content;
	}

	/**
	 * Format Recipe Difficulty
	 *
	 * @access private
	 *
	 * @param int $post_id Post ID
	 *
	 * @return string HTML
	 **/
	private function get_recipe_difficulty_html( $post_id ) {
		$difficulty  = get_post_meta( $post_id, self::prefix . 'difficulty', true ) || 0;
		$description = $this->recipe_field_map['difficulty']['option_descriptions'][ $difficulty ] || '';

		/**
		 * Nothing to display if difficulty is not set
		 */
		if ( $difficulty == 0 ) {
		    return '';
        }

		// Microformat encoding of difficulty (x out of 5)
		$content = '<span class="value-title" title="' . $difficulty . '/5"></span>';

		// Display difficulty graphically - Difficulty array is 0 based
		$content .= '<div class="recipe-difficulty-off" title="' . $description . '">';
		$content .= '<div class="recipe-difficulty-on recipe-difficulty-' . $difficulty . '"></div>';
		$content .= '</div>';

		return $content;
	}

	/**
	 * Generate HTML for recipe rating and provide method for user to vote
	 *
	 * @access protected
	 *
	 * @param int $post_id Post ID
	 *
	 * @return string HTML
	 **/
	protected function get_recipe_rating_html( $post_id ) {
		// Get rating votes from meta data if available
		$ratings = get_post_meta( $post_id, self::prefix . 'ratings', true );
		if ( $ratings ) {
			$ratings = json_decode( $ratings );
			$avg     = self::rating_avg( $ratings );
			$unrated = '';
		} else {
			$avg['avg'] = 0;
			$avg['cnt'] = 0;
			$unrated    = ' unrated';
		}

		// Microformat encoding of the recipe rating (x out of 5)
		$content = '<span class="value-title" title="' . $avg['avg'] . '/5"></span>';

		$content .= '<div id="recipe-stars-' . $post_id . '" class="recipe-stars' . $unrated . '">';
		$content .= '<div class="recipe-avg-rating">';

		// Display average # of stars
		$stars_on_width = round( $avg['avg'] * 16 ); // Stars are 16px wide, how many are turned on?
		$content        .= '<div class="recipe-stars-off"><div class="recipe-stars-on" style="width:' . $stars_on_width . 'px"></div></div>';

		// Text based display of average rating and vote count
		$content .= sprintf( '<div class="recipe-stars-text"><span class="recipe-stars-avg">%.2f</span> (<span class="recipe-stars-cnt">%d</span> %s)</div>',
			$avg['avg'], $avg['cnt'], _n( 'vote', 'votes', $avg['cnt'] ) );
		$content .= '</div>'; // End <div class="recipe-avg-rating">

		// In the event the recipe is unrated...
		if ( ! $avg['avg'] > 0 ) {
			$content .= sprintf( '<div class="recipe-unrated">%s</div>', __( "Unrated", self::p ) );
		}

		if ( is_single() ) {
			// Give user a way to rate the recipe		
			$content     .= '<form class="recipe-user-rating"><div>';
			$content     .= __( 'Your Rating: ', self::p ) . '<select name="recipe-rating">';
			$user_rating = self::user_rating( $post_id );
			for ( $i = 1; $i <= 5; $i ++ ) {
				$selected = ( $user_rating == $i ) ? 'selected' : ''; // Show user rating
				$content  .= '<option value="' . $i . '"' . $selected . '>' . sprintf( _n( '%d star', '%d stars', $i, self::p ), $i ) . '</option>';
			}
			$content .= '</select><div class="thank-you">' . __( 'Thank you for your vote!', self::p ) . '</div></div></form>';
		}

		$content .= '</div>'; // Close entire ratings div

		return $content;
	}

	/**
	 * Retrieve the user rating for a recipe from cookies
	 *
	 * @param int $post_id
	 *
	 * @return int rating value 0-5
	 **/
	function user_rating( $post_id ) {
		$index = 'recipe-rating-' . $post_id;

		return isset( $_COOKIE[ $index ] ) ? $_COOKIE[ $index ] : 0;
	}

	/**
	 * Calculate a rating average from array of rating counts
	 *
	 * @return array hash array with members 'avg' and 'cnt'
	 **/
	function rating_avg( $ratings ) {
		$total = $sum_count = 0;

		foreach ( $ratings as $key => $count ) {
			$total     += $key * $count;
			$sum_count += $count;
		}

		return array( 'avg' => $total / $sum_count, 'cnt' => $sum_count );
	}

	/**
	 * Generate HTML for recipe nutrition block
	 *
	 * @access private
	 *
	 * @param int $post_id Post ID
	 *
	 * @return string HTML
	 **/
	private function get_nutrition_html( $post_id ) {
		// TODO Add nutrition calculation on save		
		return '';
	}

	/**
	 * Generate HTML for the instructions shortcode
	 * Example usage:
	 *  [instructions]
	 *  [step]Step 1[/step]
	 *  [step]Step ...[/step]
	 *  [/instructions]
	 *
	 * @param array $atts shortcode attributes
	 * @param string $content shortcode contents
	 *
	 * @return string HTML
	 **/
	function sc_instructions(
		/** @noinspection PhpUnusedParameterInspection */
		$atts, $content = ''
	) {
		$content = '<div class="instructions">' . do_shortcode( $content ) . '</div>';

		return $content;
	}

	/**
	 * Generate HTML for the step shortcode
	 * Example usage: [step]An instruction step[/step]
	 *
	 * @param array $atts shortcode attributes
	 * @param string $content shortcode contents
	 *
	 * @return string HTML
	 **/
	function sc_step(
		/** @noinspection PhpUnusedParameterInspection */
		$atts, $content = ''
	) {
		return '<div class="step">' . do_shortcode( $content ) . '</div>';
	}

	/**
	 * Generate list of recipe categories
	 * Example usage:
	 *  [hrecipe-microformat-category-list]    <-- Lists all defined Recipe Categories
	 *
	 * @param array $atts shortcode attributes
	 * @param string $content shortcode contents
	 *
	 * @return string HTML
	 **/
	function sc_category_list(
		/** @noinspection PhpUnusedParameterInspection */
		$atts, $content = ''
	) {
		$content = '<ul>';
		$content .= wp_list_categories( array(
			'echo'     => false,
			'taxonomy' => $this->recipe_category_taxonomy,
			'title_li' => '',
		) );
		$content .= '</ul>';

		return $content;
	}

	/**
	 * Extract Ingredients from DB and create table for display in recipe
	 * Example usage:
	 *  [ingrd-list id=1] <-- Displays ingredient list 1 for recipe
	 *
	 * Ingredient list structure
	 * <section>
	 *  <header>
	 *   <h1>title</h1>
	 *  </header>
	 *  <ul>
	 *   <li>ingredient</li>
	 *   ...
	 *  </ul>
	 * </section>
	 *
	 * @param array $atts shortcode attributes
	 * @param string $content shortcode contents
	 *
	 * @uses $post
	 * @return string HTML
	 **/
	function sc_ingrd_list(
		$atts, /** @noinspection PhpUnusedParameterInspection */
		$content = ''
	) {
		global $post;

		// If this is a preview, try to get the autosave post to use its ID when looking up meta data
		$post_id = $post->ID;
		if ( is_preview() ) {
			$preview = wp_get_post_autosave( $post_id );
			if ( is_object( $preview ) ) {
				$post_id = $preview->ID;
			}
		}

		$text = ''; // Init Output HTML text

		/**
		 * @var int $id
		 */
		extract( shortcode_atts( array(
			'id' => 1,
		), $atts ) );
		// Sanitize input
		$list_id = intval( $id );

		/**
		 * Get ingredient list titles.  List indexed by available list ids.
		 *
		 * Empty replacement if id does not exist
		 */
		$ingrd_list_title = get_post_meta( $post_id, self::prefix . 'ingrd-list-title', true );

		if ( ! array_key_exists( $id, $ingrd_list_title ) ) {
			return $text;
		}

		/**
		 * Get the ingredients for the given list id
		 */
		$ingrds = $this->ingrd_db->get_ingrds_for_recipe( $post_id, $list_id );

		/**
		 * Generate HTML table for the ingredient list
		 */
		$text .= '<section class="ingredients" id="ingredients-' . $list_id . '">';
		$text .= '<header>';
		$text .= '<h1 class="ingredients-title">' . $ingrd_list_title[ $list_id ] . '</h1>'; // List Title
		$text .= '<ul class="ingredients-display-as">';
		$text .= '<li><button class="measure-button" value="original">original</button></li>';
		$text .= '<li><button class="measure-button" value="us">US</button></li>';
		$text .= '<li><button class="measure-button" value="metric">metric</button></li>';
		$text .= '</ul>';
		$text .= '</header>';

		/**
		 * Add element for each ingredient
		 *
		 * Input: $d['food_id], $d['quantity'], $d['unit'], $d['ingrd'], $d['comment']
		 *
		 * hrecipe microformat definition (see readme for source):
		 * ingredient. required. 1 or more. text with optional valid (x)HTML markup.
		 * value and type. optional. child of ingredient. [experimental]
		 * comment. optional. text. child of ingredient. [ziplist extension]
		 */
		$text .= '<ul class="ingredients-list">';
		foreach ( $ingrds as $d ) {
			$text         .= '<li class="ingredient">';
			$measure_text = $this->quantity_html( $d );
			if ( $measure_text ) {
				$text .= '<span class="measure">' . $measure_text . '</span> ';
			}
			if ( '' != $d['ingrd'] ) {
				$text .= '<span class="ingrd">' . esc_attr( $d['ingrd'] ) . '</span>';
			}
			if ( '' != $d['comment'] ) {
				$text .= ' – <span class="comment">' . esc_attr( $d['comment'] ) . '</span>';
			}
			$text .= '</li>';
		}

		$text .= '</ul></section>';

		return $text;
	}

	/**
	 * Create HTML for quantity & units in US and metric including fractional display where appropriate
	 *
	 * Some foods are traditionally measured by volume in US recipes (dry goods i.e. flour, sugar)
	 * and by weight in metric based recipes.  Convert such ingredients from volume to weight
	 * using table of weight for each ingredient
	 *
	 * US => Metric
	 *   Volume Units:
	 *     If food should be displayed by weight:
	 *       Convert volume to a standard (cups) then convert to grams (grams/cup) - Lookup g/c in Food DB
	 *     else
	 *       Convert volume to ml
	 *   Weight Units:
	 *     Convert weight to grams (grams/oz, grams/lb)
	 * Metric => US
	 *   Volume Units:
	 *     Covert ml to cups/tbs/tsp
	 *   Weight Units:
	 *     If food should be displayed by volume:
	 *       Convert grams to volume cups/tbs/tsp
	 *     else
	 *       Convert grams to oz/lb
	 *
	 * @param associative-array $ingrd Ingredient object from Database lookup
	 *        $ingrd['food_id']
	 *        $ingrd['quantity']
	 *        $ingrd['unit']
	 *        $ingrd['ingrd']
	 *        $ingrd['comment']
	 *        $ingrd['NDB_No']
	 *        $ingrd['measure']
	 *        $ingrd['gpcup']
	 *
	 * @return string HTML text for units
	 *
	 * FIXME When displaying in US units, scale units to use teaspoons, tablespoons for amounts < 1/8 cup
	 **/
	function quantity_html( $ingrd ) {
		/**
		 * List of measures that will be considered metric and conversion to ounces or US fluid ounces
		 */
		static $metric_measures = array(
			'g'         => array( 'per' => 0.035274, 'us' => 'ounce' ),
			'gram'      => array( 'per' => 0.035274, 'us' => 'ounce' ),
			'grams'     => array( 'per' => 0.035274, 'us' => 'ounce' ),
			'kilogram'  => array( 'per' => 35.274, 'us' => 'ounce' ),
			'kilograms' => array( 'per' => 35.274, 'us' => 'ounce' ),
			'kg'        => array( 'per' => 35.274, 'us' => 'ounce' ),
			'kgs'       => array( 'per' => 35.274, 'us' => 'ounce' ),
			'l'         => array( 'per' => 4.22675, 'us' => 'cup' ),
			'litre'     => array( 'per' => 4.22675, 'us' => 'cup' ),
			'ml'        => array( 'per' => 0.00422675, 'us' => 'cup' ),
		);

		/**
		 * Volume conversion table to mL
		 * All cups are not created equal! http://en.wikipedia.org/wiki/Cup_(unit)
		 * http://en.wikipedia.org/wiki/Cooking_weights_and_measures
		 *
		 * tablespoons and teaspoons omitted from list so they don't get converted
		 */
		static $us_measures = array(
			'cup'         => array( 'per' => 236.59, 'metric' => 'ml' ),
			'cups'        => array( 'per' => 236.59, 'metric' => 'ml' ),
			'fl oz'       => array( 'per' => 29.57, 'metric' => 'ml' ),
			'fluid ounce' => array( 'per' => 29.57, 'metric' => 'ml' ),
			'gallon'      => array( 'per' => 3785.41, 'metric' => 'ml' ),
			'pint'        => array( 'per' => 473.18, 'metric' => 'ml' ),
			'quart'       => array( 'per' => 946.35, 'metric' => 'ml' ),
			'stick'       => array( 'per' => 118.29, 'metric' => 'ml' ),// TODO Maybe want to convert butter to weight?
			'oz'          => array( 'per' => 28.3495, 'metric' => 'g' ),
			'ounce'       => array( 'per' => 28.3495, 'metric' => 'g' ),
			'lb'          => array( 'per' => 453.592, 'metric' => 'g' ),
			'pound'       => array( 'per' => 453.592, 'metric' => 'g' ),
		);

		/*
		 * Conversion table to cups
		 * TODO Should tbs, tsp be converted to ml? No!! Don't convert
		 */
		static $per_cup = array(
			'cup'         => 1,
			'cups'        => 1,
			'fluid ounce' => 8,
			'tbsp'        => 16,
			'tbs'         => 16,
			'tablespoon'  => 16,
			'tsp'         => 48,
			'teaspoon'    => 48
		);

		/*
		 * gram conversions
		 */
		static $grams = array(
			'g'         => 1,
			'gram'      => 1,
			'grams'     => 1,
			'kg'        => 1000,
			'kgs'       => 1000,
			'kilogram'  => 1000,
			'kilograms' => 1000
		);

		/**
		 * @var string $unit
		 * @var string $quantity
		 * @var int $gpcup
		 * @var string $measure
		 */
		extract( $ingrd ); // Pull associative array into symbol table ($quantity = $ingrd['quantity'], ...)

		// If both quantity and unit are blank, bail out now
		if ( '' == $quantity && '' == $unit ) {
			return '';
		}

		/*
		 * Do unit conversions between metric and US formats
		 *
		 * $q - array of quantities for each measurement format
		 * $u - array of units for each measurement format
		 * $m_class - array of measure classes to include for each format
		 * $q_class - classes to include on quantity <span>
		 * $u_class - classes to include on unit <span>
		 */
		$q['original'] = $quantity;
		$u['original'] = $unit;
		$m_class       = array(
			'original' => array( 'selected-measure' ),
			'metric'   => array(),
			'us'       => array()
		);
		$q_class       = array(
			'original' => array( 'value' ),
			'metric'   => array(),
			'us'       => array(),
		);
		$u_class       = array(
			'original' => array( 'type' ),
			'metric'   => array(),
			'us'       => array()
		);

		if ( ! is_numeric( $quantity ) || '' == $unit ) {
			// No conversion possible
			$q['metric'] = $q['us'] = $quantity;
			$u['metric'] = $u['us'] = $unit;
		} elseif ( array_key_exists( $unit, $metric_measures ) ) {
			// Found Metric units
			$q['metric'] = $quantity;
			$u['metric'] = $unit;

			// Convert to US measure
			// TODO Display volume measurements for US in addition to the weight when possible
			if ( array_key_exists( $unit, $grams ) && $gpcup > 0 ) {
				$us_qty_volume  = $quantity * $grams[ $unit ] / $gpcup;
				$us_unit_volume = 'cup';
			}

			$m_class['us'][] = 'converted-measure';
			$q['us']         = $quantity * $metric_measures[ $unit ]['per'];
			$u['us']         = $metric_measures[ $unit ]['us'];
		} else {
			// Assume US measure
			$q['us'] = $quantity;
			$u['us'] = $unit;

			// For weight conversions, need to be able to convert quantity to cups
			if ( 'weight' == $measure && array_key_exists( $unit, $per_cup ) && $gpcup > 0 ) {
				// metric weight = quantity in cups * grams per cup
				$cups                = $quantity / $per_cup[ $unit ];
				$q['metric']         = round( $cups * $gpcup );
				$u['metric']         = 'g';
				$m_class['metric'][] = 'converted-measure';
			} elseif ( array_key_exists( $unit, $us_measures ) ) {
				/*
				 * Direct conversion to metric if starting unit is known 
				 * (us weight->metric weight, us volume=>metric volume)
				 */
				$q['metric']         = round( $quantity * $us_measures[ $unit ]['per'] );
				$u['metric']         = $us_measures[ $unit ]['metric'];
				$m_class['metric'][] = 'converted-measure';
			} else {
				// Unknown unit
				$q['metric'] = $quantity;
				$u['metric'] = $unit;
			}
		}

		/*
			Create list of measures: original, US and Metric formats
		
			The original value is marked as the "selected" value for default display
			Javascript code will be using the measure-* and selected-measure classes to identify and select
			the available measures.
		*/

		$text = '<div class="measure-equivalents">';
		foreach ( array( 'original', 'us', 'metric' ) as $type ) {
			array_push( $m_class[ $type ], "measure-equivalent", "measure-$type" );
			array_push( $q_class[ $type ], "quantity", "$type-value" );
			array_push( $u_class[ $type ], "unit", "$type-type" );

			$text .= '<div class="' . implode( ' ', $m_class[ $type ] ) . '">';
			if ( $q[ $type ] ) {
				$text .= '<span class="' . implode( ' ', $q_class[ $type ] ) . '">' .
				         $this->decimal_to_fraction( $q[ $type ], $u[ $type ] ) . '</span> ';
			}
			if ( $u[ $type ] ) {
				$text .= '<span class="' . implode( ' ', $u_class[ $type ] ) . '">' . esc_attr( $u[ $type ] ) . '</span>';
			}
			if ( in_array( 'converted-measure', $m_class[ $type ] ) ) {
				$text .= '<sup>*</sup>';
			}
			$text .= '</div>';
		}
		$text .= '</div>';

		return $text;
	}

	/**
	 * Convert decimal to common fractions based on unit type
	 *
	 * In US & imperial measurements:
	 *   cups are rarely displayed as 3/8 or 5/8.  1/8 cup == 2 Tbs
	 *   tsp, tbs never use 1/3 or 2/3 measurements.  spoons are generally graduated in 1/8 intervals.
	 * See:
	 *  http://allrecipes.com/HowTo/Commonly-Used-Measurements--Equivalents/Detail.aspx
	 *  http://allrecipes.com/HowTo/recipe-conversion-basics/detail.aspx
	 *  http://www.jsward.com/cooking/conversion.shtml
	 *
	 * Common fractions used in cooking:
	 *     1/8 = .125
	 *     1/4 = .25
	 *     1/3 = .333333...
	 *     3/8 = .375
	 *     1/2 = .5
	 *     5/8 = .625
	 *     2/3 = .666666...
	 *     3/4 = .75
	 *     7/8 = .875
	 *
	 * @param int $value value to convert
	 * @param string $unit Unit type being converted
	 *
	 * @return string Fractional string
	 */
	function decimal_to_fraction( $value, $unit ) {
		/**
		 * To mathematically differentiate between 5/8 and 2/3, use binary array to 1/64 accuracy
		 */
		static $sixtyfourths = array(
			'0',   // 0/64
			'0',
			'0',
			'0',
			'0',
			'1/8',
			'1/8',
			'1/8',
			'1/8', // 8/64
			'1/8',
			'1/8',
			'1/8',
			'1/8',
			'1/4',
			'1/4',
			'1/4',
			'1/4', // 16/64
			'1/4',
			'1/4',
			'1/4',
			'1/4',
			'1/3',
			'1/3',
			'3/8',
			'3/8', // 24/64
			'3/8',
			'3/8',
			'3/8',
			'3/8',
			'1/2',
			'1/2',
			'1/2',
			'1/2', // 32/64
			'1/2',
			'1/2',
			'1/2',
			'1/2',
			'5/8',
			'5/8',
			'5/8',
			'5/8', // 40/64
			'5/8',
			'2/3',
			'2/3',
			'3/4',
			'3/4',
			'3/4',
			'3/4',
			'3/4', // 48/64
			'3/4',
			'3/4',
			'3/4',
			'7/8',
			'7/8',
			'7/8',
			'7/8',
			'7/8', // 56/64
			'7/8',
			'7/8',
			'7/8',
			'1',
			'1',
			'1',
			'1',
			'1'    // 64/64
		);

		/**
		 * List of measures to display in fractional units
		 */
		static $fractional_measures = array(
			'cup',
			'cups',
			'fl oz',
			'fluid ounce',
			'gallon',
			'lb',
			'ounce',
			'oz',
			'pint',
			'pound',
			'quart',
			'stick',
			'tablespoon',
			'tbs',
			'tbsp',
			'teaspoon',
			'tsp',
		);

		// Only Convert numbers
		if ( ! is_numeric( $value ) ) {
			return esc_attr( $value );
		}

		// Only convert some unit types to fractions
		if ( ! in_array( $unit, $fractional_measures ) ) {
			return $value;
		}

		// $value is known to be a number so safe to assume decimal format
		$parts = explode( '.', $value );

		// Need integral and fractional parts to make fraction
		if ( count( $parts ) != 2 ) {
			return $value;
		}

		// turn fractional component back into fractional value
		$fractional = '.' . $parts[1];

		// Binary search to locate closest matching 64th
		$numerator = 32;
		$step      = 16;
		while ( $step >= 1 ) {
			if ( $fractional == $numerator / 64 ) {
				break;
			}
			if ( $fractional > $numerator / 64 ) {
				$numerator += $step;
			} else {
				$numerator -= $step;
			}
			$step /= 2;
		}

		/**
		 * Use array of 64ths to create fractional display
		 */
		if ( '1' == $sixtyfourths[ $numerator ] || '0' == $sixtyfourths[ $numerator ] ) {
			return $parts[0] . $sixtyfourths[ $numerator ];
		} elseif ( $parts[0] > 0 ) {
			return $parts[0] . " " . $this->pretty_fraction( $sixtyfourths[ $numerator ] );
		} else {
			return $this->pretty_fraction( $sixtyfourths[ $numerator ] );
		}
	}

	/**
	 * Change simple fraction value to super & subscript
	 *
	 * @param $fraction string value representing fraction
	 *
	 * @return string super and subscripted fraction
	 **/
	function pretty_fraction( $fraction ) {
		$parts = explode( '/', $fraction );

		return '<sup>' . $parts[0] . '</sup>&frasl;<sub>' . $parts[1] . '</sub>';
	}

	/**
	 * Process AJAX request to rate recipes, save user's rating in cookie
	 *
	 * TODO If cookies can't be saved, don't allow voting by the user - test for WP test cookie presence
	 * TODO Make cookie storage more efficient - store all votes in one cookie
	 *
	 * Note: This function terminates either by calling die() or exit()
	 *
	 * @internal Ballot box stuffing is possible since the presence of a vote is saved in the user's browser
	 **/
	function ajax_recipe_rating() {
		$post_id     = $_POST['postID'];
		$rating      = $_POST['rating'];
		$prev_rating = $_POST['prevRating'];
		$nonce       = $_POST['ratingNonce'];

		if ( ! wp_verify_nonce( $nonce, self::prefix . 'recipe-rating-nonce' ) ) {
			die( 'ajax_recipe_rating: Bad Nonce detected.' );
		}

		$ratings = get_post_meta( $post_id, self::prefix . 'ratings', true );
		if ( $ratings ) {
			$ratings = json_decode( $ratings, true );
		} else {
			$ratings = array( 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 );
		}

		// Adjust vote count for this user, remove old vote if previous value provided.
		if ( isset( $ratings[ $rating ] ) ) {
			$ratings[ $rating ] ++;
		}
		if ( $prev_rating && isset( $ratings[ $prev_rating ] ) && $ratings[ $prev_rating ] > 0 ) {
			$ratings[ $prev_rating ] --;
		}

		$avg = self::rating_avg( $ratings );

		// Save new ratings array for this post
		$json_ratings = json_encode( $ratings );
		add_post_meta( $post_id, self::prefix . 'ratings', $json_ratings, true ) or update_post_meta( $post_id, self::prefix . 'ratings', $json_ratings );

		// Save user rating for this recipe in a cookie (expires in 10 years)
		setcookie( 'recipe-rating-' . $post_id, $rating, time() + 60 * 60 * 24 * 365 * 10, COOKIEPATH, COOKIE_DOMAIN );

		// response output
		$response = json_encode( array(
			'postID'      => $post_id,
			'avg'         => $avg['avg'],
			'cnt'         => $avg['cnt'],
			'userRating'  => $rating,
			'ratingNonce' => wp_create_nonce( self::prefix . 'recipe-rating-nonce' ),
		) );
		header( "Content-Type: application/json" );
		echo $response;

		exit;
	}

	/**
	 * Handle AJAX request for auto-completion information for an ingredient name
	 *
	 * Note: This function terminates by calling exit()
	 *
	 * @uses $_REQUEST['name_contains'] string, substring for lookup
	 * @uses $_REQUEST['maxRows'] int, maximum rows to return
	 **/
	function ajax_ingrd_auto_complete() {
		// TODO Respond with an error on mal-formed request

		// Escape incoming name to prevent SQL attack
		$name_contains = esc_attr( $_REQUEST['name_contains'] );

		// Validate numeric
		// TODO Change hard coded default to configuration value
		$max_rows = is_numeric( $_REQUEST['maxRows'] ) ? intval( $_REQUEST['maxRows'] ) : 12;
		if ( $max_rows < 1 ) {
			$max_rows = 1;
		}

		// Retrieve ingredient names matching incoming string, use wildcard matching
		$rows = $this->ingrd_db->get_ingrds_by_name( $name_contains, $max_rows, false );

		// Encode Response Output for HTML return
		$response = json_encode( array(
			'list' => $rows,
		) );

		header( "Content-Type: application/json" );
		echo $response;
		exit;
	}

	/**
	 * Handle AJAX request for food definitions in USDA Nutrition DB
	 *
	 * Note: This function terminates by calling exit()
	 *
	 * @uses $_REQUEST['name_contains'] string, substring for lookup
	 * @uses $_REQUEST['maxRows'] int, maximum rows to return
	 * @uses $_REQUEST['pageNum'] int, page number for request - determines starting row for lookup
	 **/
	function ajax_NDB_search() {
		// TODO Respond with an error on mal-formed request

		// Escape incoming name to prevent SQL attack
		$name = esc_attr( $_REQUEST['name_contains'] );

		// Validate numerics
		// TODO Change hard coded default to configuration value
		$max_rows = is_numeric( $_REQUEST['maxRows'] ) ? intval( $_REQUEST['maxRows'] ) : 12;
		if ( $max_rows < 1 ) {
			$max_rows = 1;
		}

		$page = is_numeric( $_REQUEST['pageNum'] ) ? intval( $_REQUEST['pageNum'] ) : 1;
		if ( $page < 1 ) {
			$page = 1;
		}

		// Retrieve food names matching incoming string
		$names = $this->nutrient_db->get_name( $name, $max_rows, $page );

		// Encode Response Output for HTML return
		$response = json_encode( $names );

		header( "Content-Type: application/json" );
		echo $response;
		exit;
	}

	/**
	 * Handle AJAX request to retrieve measures for a food from USDA Nutrition DB
	 *
	 * Note: This function terminates by calling exit()
	 *
	 * @uses $_REQUEST['NDB_No'] string, Unique food ID from the Nutrition DB
	 **/
	function ajax_NDB_measures() {
		// TODO Respond with an error on mal-formed request

		// Escape incoming name to prevent SQL attack
		$NDB_No = esc_attr( $_REQUEST['NDB_No'] );

		// Retrieve measures from USDA Nutrition DB
		$measures = $this->nutrient_db->get_measures_by_NDB_No( $NDB_No );

		// Encode Response output for HTML return
		$response = json_encode( $measures );

		header( "Content-Type: application/json" );
		echo $response;
		exit;
	}

	/**
	 * Perform Plugin Activation handling
	 *  * Confirm that plugin environment requirements are met
	 *
	 * @return void, dies on error conditions
	 **/
	function plugin_activation() {
		// TODO On failure, activation is run 3 times!
		global $wp_version;

		// Enforce minimum PHP version requirements
		if ( version_compare( self::php_version_required, phpversion(), '>' ) ) {
			die( self::p . ' plugin requires minimum PHP v' . self::php_version_required . '.  You are running v' . phpversion() );
		}

		// Enforce minimum WP version
		if ( version_compare( self::wp_version_required, $wp_version, '>' ) ) {
			die( self::p . ' plugin requires minimum WordPress v' . self::wp_version_required . '.  You are running v' . $wp_version );
		}

		// Register the needed taxonomies so they can be populated
		$this->register_taxonomies();

		// Create the hrecipe post type so that rewrite rules can be flushed.
		$this->create_post_type();

		// Call the admin activation stuff
		if ( ! $this->on_activation() ) {
			// TODO Collect errors and display them here
			die( self::p . " plugin failed activation - Please check server log for details." );
		}
	}

	/**
	 * Perform Plugin Deactivation handling
	 *    * Remove the rewrite rules related to the plugin
	 *
	 * @return void
	 **/
	function plugin_deactivation() {
		// On deactivation, flush rewrite rules to cleanup from the plugin
		flush_rewrite_rules();  // TODO Need page_type removed for this to work
	}

	/**
	 * Register the custom taxonomies for recipes
	 *
	 * @return void
	 **/
	function register_taxonomies() {
		if ( ! isset( $this->taxonomies ) ) {
			$this->taxonomies = array();

			// Create a taxonomy for the Recipe Category
			$this->taxonomies[] = $this->recipe_category_taxonomy;
			register_taxonomy(
				$this->recipe_category_taxonomy,
				self::post_type,
				array(
					'hierarchical'          => true,
					'label'                 => __( 'Recipe Category', self::p ),
					'labels'                => array(
						'name'              => _x( 'Recipe Types', 'taxonomy general name', self::p ),
						'singular_name'     => _x( 'Recipe Type', 'taxonomy singular name', self::p ),
						'search_items'      => __( 'Search Recipe Types', self::p ),
						'popular_items'     => __( 'Popular Recipe Types', self::p ),
						'all_items'         => __( 'All Recipe Types', self::p ),
						'parent_item'       => __( 'Parent Recipe Type', self::p ),
						'parent_item_colon' => __( 'Parent Recipe Type:', self::p ),
						'edit_item'         => __( 'Edit Recipe Type', self::p ),
						'update_item'       => __( 'Update Recipe Type', self::p ),
						'add_new_item'      => __( 'Add New Recipe Type', self::p ),
						'new_item_name'     => __( 'New Recipe Type Name', self::p ),
					),
					'show_in_nav_menus'     => true,
					'show_tagcloud'         => true,
					'query_var'             => $this->recipe_category_taxonomy,
					'rewrite'               => array( 'slug' => 'hrecipe-type', 'hierarchical' => true ),
					'show_ui'               => true,
					'update_count_callback' => '_update_post_term_count'
				)
			);
		}
	}

	/**
	 * Create recipe post type and associated panels in the edit screen
	 *
	 * @return void
	 **/
	function create_post_type() {
		// Register the Recipe post type
		register_post_type( self::post_type,
			array(
				// 'description' => __('Post Type for publishing of Recipes', self::p),
				'labels'            => array(
					'name'               => _x( 'Recipes', 'post type general name', self::p ),
					'singular_name'      => _x( 'Recipe', 'post type singular name', self::p ),
					'add_new'            => _x( 'Add Recipe', 'recipe', self::p ),
					'add_new_item'       => __( 'Add New Recipe', self::p ),
					'edit_item'          => __( 'Edit Recipe', self::p ),
					'new_item'           => __( 'New Recipe', self::p ),
					'view_item'          => __( 'View Recipe', self::p ),
					'search_items'       => __( 'Search Recipes', self::p ),
					'not_found'          => __( 'No recipes found', self::p ),
					'not_found_in_trash' => __( 'No recipes found in Trash', self::p ),
					'menu_name'          => __( 'Recipes', self::p ),
				),
				'show_ui'           => true,
				'public'            => true,
				'show_in_nav_menus' => true,
				'show_in_menu'      => true,
				// TODO 'menu_icon' => ICON URL
				'has_archive'       => true,
				'rewrite'           => array( 'slug' => 'Recipes' ),
				'menu_position'     => 7,
				'supports'          => array(
					'title',
					'editor',
					'excerpt',
					'author',
					'thumbnail',
					'trackbacks',
					'comments',
					'revisions'
				),
				'taxonomies'        => array( 'post_tag' ), // TODO Setup Taxonomy to allow only a single selection
			)
		);
	}

	/**
	 * Sanitize the Plugin Options received from the user
	 *
	 * @return array Sanitized hash of plugin options
	 **/
	function sanitize_settings( $options ) {
		$valid_options = array();        // Accumulate sanitized options for return

		// Establish defaults used when sanitizing options
		$options_defaults = array(
			'display_in_home'      => false,
			'display_in_feed'      => false,
			'posts_per_page'       => 30,
			'include_metadata'     => false,
			'recipe_head_fields'   => 'yield,difficulty,rating,category,duration,preptime,cooktime',
			'recipe_footer_fields' => 'published,author,nutrition',
			'debug_log_enabled'    => false,
			'debug_log'            => array(),
		);

		// Option types for sanitizing data received from user. debug_log is not included as it has special handling below.
		$options_type = array(
			'display_in_home'      => 'bool',
			'display_in_feed'      => 'bool',
			'posts_per_page'       => 'int',
			'include_metadata'     => 'bool',
			'recipe_head_fields'   => 'text',
			'recipe_footer_fields' => 'text',
			'debug_log_enabled'    => 'bool',
		);

		// Fill in default values
		$options = (array) wp_parse_args( $options, $options_defaults );

		// Sanitize each value
		foreach ( $options_type as $key => $type ) {
			$valid_options[ $key ] = $this->sanitize_an_option( $options[ $key ], $type );
		}

		// Cleanup error log if it's disabled
		$valid_options['debug_log'] = $valid_options['debug_log_enabled'] ? $options['debug_log'] : array();

		return $valid_options;
	}

	/**
	 * Sanitize an option based on field type
	 *
	 * @param int $val Value of option to clean
	 * @param string $type Option type (text, bool, etc.)
	 *
	 * @return string sanitized option value
	 **/
	function sanitize_an_option( $val, $type ) {
		switch ( $type ) {
			case 'bool' :
				return $val ? true : false;

			case 'text' :
				return wp_filter_nohtml_kses( $val );  // HTML not allowed in options

			case 'int' :
				return intval( $val );
		}

		return $val;
	}
}
