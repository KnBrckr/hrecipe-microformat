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
TODO Create shortcodes to place recipe content into a post
TODO Provide mechanism to import recipes from external sources
*/

// Protect from direct execution
if (!defined('WP_PLUGIN_DIR')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

global $hrecipe_microformat;

// If the class already exists, all setup is complete
if ( ! class_exists('hrecipe_microformat')) :
/**
 * Load the required libraries - use a sub-scope to protect global variables
 *		meta-box class used to create new post type
 *		Plugin options class
 **/		
{
	$required_libs = array('lib/meta-box-3.1/meta-box.php', 'admin/options-class.php');
	foreach ($required_libs as $lib) {
		if (!include_once($lib)) {
			hrecipe_microformat_error_log('Unable to load required library:  "' . $lib . '"');
			return;  // A required module is not available
		}
	}
}

class hrecipe_microformat extends hrecipe_microformat_options {
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
		parent::setup();

		// Put recipes into the stream if requested in configuration
		add_filter('pre_get_posts', array(&$this, 'pre_get_posts_filter'));
		
		// Update the post class as required
		add_filter('post_class', array(&$this, 'post_class'));
		
		// Catch any posts that have a plugin supplied default template
		add_action('template_redirect', array(&$this, 'template_redirect'));
		
		// Register CSS and enqueue
		wp_register_style(self::prefix . '-style', self::$url . 'hrecipe.css');
		wp_enqueue_style(self::prefix . '-style');
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
		if ((!array_key_exists('suppress_filters', $query->query_vars) || !$query->query_vars['suppress_filters']) 
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
	 * Update the classes assigned to a post if required
	 *
	 * @return array list of post classes
	 **/
	function post_class($classes)
	{
		if ($this->add_post_class) {
			$classes[] = "post";
		}
		return $classes;
	}
	
	/**
	 * Use template from the plugin if one isn't available in the theme
	 *
	 * @return void | Does not return if a matching template is located
	 **/
	function template_redirect()
	{
		global $post;
		
		if (is_singular(self::post_type)) {
			$template_name = 'single-';
		} elseif (is_post_type_archive(self::post_type)) {
			$template_name = 'archive-';
		} else {
			return;
		}
		$template_name .= get_post_type($post) . '.php';
			
		// Look for available template
		$template = locate_template(array($template_name), true);
		if (empty($template)) {
			include(self::$dir . 'lib/template/' . $template_name);
		}
		exit();
	}
	
	/**
	 * Prints HTML with meta information for the current post recipe (date/time and author.)
	 *
	 * @return void
	 */
	function posted_on()
	{ // TODO Confirm microformat of Author field
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
	 * @return void
	 */
	function posted_in() {
		global $post;
		
		// Retrieves tag list of current post, separated by commas.
		$tag_list = get_the_tag_list( '', ', ' );
		$recipe_category = get_the_term_list( $post->ID, self::prefix . 'category', '', ', ', '' );
		if ( $tag_list ) {
			$posted_in = __( 'This recipe is posted in %1$s and tagged %2$s. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.', self::p );
		} elseif (!empty($recipe_category)) {
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
	function get_author_recipes_url($author_id, $author_nicename = '') {
		global $wp_rewrite;
		$auth_ID = (int) $author_id;
		$link = $wp_rewrite->get_author_permastruct();

		if ( empty($link) ) {
			$file = home_url( '/' );
			$link = $file . '?author=' . $auth_ID;
			$token = '&';
		} else {
			if ( '' == $author_nicename ) {
				$user = get_userdata($author_id);
				if ( !empty($user->user_nicename) )
					$author_nicename = $user->user_nicename;
			}
			$link = str_replace('%author%', $author_nicename, $link);
			$link = home_url( user_trailingslashit( $link ) );
			$token = '?';
		}
		
		// Add post_type to the query
		$link .= $token . 'post_type=' . self::post_type;

		$link = apply_filters('author_recipes_link', $link, $author_id, $author_nicename);

		return $link;
	}
	
	/**
	 * Emit Recipe Header HTML
	 *
	 * @return void
	 **/
	function recipe_head()
	{
		// Determine fields to place in header
		// Allow filtering of list ? Or use a config panel to select placement
		// Emit list
		echo "Recipe Header";
	}
	
	/**
	 * Emit Recipe Footer HTML
	 *
	 * @return void
	 **/
	function recipe_footer()
	{
		// Determine fields to place in footer
		// Allow filtering of list ? or use config panel to select placement
		// Emit list
		echo "Recipe Footer";
	}

	/**
	 * Perform Plugin Activation handling
	 *	* Start fresh with re-write rules 
	 *	* Populate the plugin taxonomies with defaults
	 *
	 * @return void
	 **/
	public static function plugin_activation()
	{
		parent::register_taxonomies();  // Register the needed taxonomies so they can be populated
		parent::create_post_type();			// Create the hrecipe post type so that rewrite rules can be flushed.
		
		// On activation, flush rewrite rules to make sure plugin is setup correctly. 
		flush_rewrite_rules();
		
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
	
	/**
	 * Perform Plugin Deactivation handling
	 *	* Remove the rewrite rules related to the plugin
	 *
	 * @return void
	 **/
	function plugin_deactivation()
	{
		// On deactivation, flush rewrite rules to cleanup from the plugin
		flush_rewrite_rules();  // FIXME Need page_type removed for this to work
	}
}

$hrecipe_microformat = new hrecipe_microformat();

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

// Setup plugin activation function to populate the taxonomies
register_activation_hook( __FILE__, array('hrecipe_microformat', 'plugin_activation'));

// Setup plugin de-activation function to cleanup rewrite rules
register_activation_hook(__FILE__, array('hrecipe_microformat', 'plugin_deactivation'));

?>