<?php
if ( ! class_exists('hrecipe_microformat')) :

if (!include_once('admin/options-class.php')) {
	return false;
}

class hrecipe_microformat extends hrecipe_microformat_options {
	/**
	 * undocumented function
	 *
	 * @author Kenneth J. Brucker <ken@pumastudios.com>
	 */
	
	function __construct() {
		parent::setup();

		add_action('init', array(&$this, 'wp_init'));
	}
	
	function wp_init() {
		// Catch any posts that have a plugin supplied default template
		// FIXME - use this? -> add_action('template_redirect', array(&$this, 'template_redirect'));
		
		// Add recipe meta data to the post content
		add_filter('the_content', array(&$this, 'the_content'));

		// Put recipes into the stream if requested in configuration
		add_filter('pre_get_posts', array(&$this, 'pre_get_posts_filter'));
		
		// Update the post class as required
		add_filter('post_class', array(&$this, 'post_class'));
				
		// Register Plugin CSS
		wp_register_style(self::prefix . 'style', self::$url . 'hrecipe.css');

		// Include the plugin styling
		wp_enqueue_style(self::prefix . 'style'); // TODO Move so that CSS only included to format recipes
		
		/*
		 * Register shortcodes
		 */
		add_shortcode(self::prefix . 'title', array(&$this, 'sc_title'));
	}
	
	/**
	 * Add recipe head and footer to post content
	 *
	 * @param $content string post content
	 * @return string Updated post content
	 **/
	function the_content($content)
	{
		$head = $this->recipe_meta_html('head', $this->options['recipe_head_fields']);
		$footer = $this->recipe_meta_html('footer', $this->options['recipe_footer_fields']);
		
		return $head . $content . $footer;
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
		&& ((is_home() && $this->options['display_in_home']) || (is_feed() && $this->options['display_in_feed']))) {
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
		if ($this->options['add_post_class']) {
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
	 * Called during The Loop
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
	 * Called during The Loop
	 *
	 * @uses $post Uses Post ID to locate recipe category assisgned to the post
	 * @return void
	 */
	// Hook into category display
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
	// FIXME Hook into author generation
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
	 * Generate recipe meta data section
	 *
	 * @return string HTML
	 **/
	function recipe_meta_html($section, $list)
	{
		$content = '<div class="' . self::post_type . '-' . $section . '">';
		foreach (explode(',', $list) as $field) {
			$content .= $this->recipe_field_html($field);
		}
		$content .= '</div>';
		
		return $content;
	}

	/**
	 * Provide HTML for a named recipe field
	 *
	 * @uses $post When called within The Loop, $post will contain the data for the active post
	 * @return string HTML
	 **/
	function recipe_field_html($field)
	{
		global $post;
		if (empty($this->recipe_field_map[$field]) || ! isset($this->recipe_field_map[$field]['format'])) return;
		
		// Produce the field value based on format of the meta data
		switch ($this->recipe_field_map[$field]['format']) {
			case 'text':  // default Post Meta Data
				$value = get_post_meta($post->ID, self::prefix . $field, true);
				break;
				
			case 'tax': // Taxonomy data
				$terms = get_the_terms($post->ID, self::prefix . $field);
				if (is_array($terms)) {
					foreach ($terms as $term) {
						$names[] = $term->name;
					}
					$value = implode(', ', $names);	
				} else {
					$value = '';
				}
				break;
				
			case 'difficulty': // Recipe difficulty
				$value = get_post_meta($post->ID, self::prefix . $field, true);	  // FIXME
				break;
			
			case 'rating': // Recipe rating based on reader response
				$value = '';  // FIXME Add rating module
				break;
				
			case 'nutrition': // Recipe nutrition as calculated from ingredients
				$value = ''; // TODO Add nutrition calculation on save
				break;
				
			default:
				$value = '';
		}

		$content =  '<div class="' . self::prefix . $field . '">';
		if (isset($value) && '' != $value)
			$content .= $this->recipe_field_map[$field]['label'] . ': <span class="' . $field . '">' . $value . '</span>';
		$content .= '</div>';
		
		return $content;
	}
	
	/**
	 * Generate HTML for the recipe title shortcode
	 *
	 * @return string HTML formatted title string
	 **/
	function sc_title($atts, $content = '')
	{
		global $post;
		return '<div class="fn">' . get_post_meta($post->ID, self::prefix . 'fn', true). '</div>';
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
		parent::on_activation();
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
		flush_rewrite_rules();  // TODO Need page_type removed for this to work
	}
}

endif; // End Class Exists
?>