<?php
/**
 * hrecipe_microformat Class
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2011 Kenneth J. Brucker (email: ken@pumastudios.com)
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


if ( ! class_exists('hrecipe_microformat')) :

if (!include_once('admin/class-hrecipe-admin.php')) {
	return false;
	
	// TODO Change self::prefix to use '-' as seperator?  shortcodes OK
}

class hrecipe_microformat extends hrecipe_admin {
	/**
	 * Minimum version of WordPress required by plugin
	 **/
	const wp_version_required = '3.2';
	
	/**
	 * Minimum version of PHP required by the plugin
	 **/
	const php_version_required = '5.2.7';
	
	/**
	 * Class contructor
	 *
	 * Call the parent class setup and init the module during wp_init phase
	 */
	
	function __construct() {
		parent::setup();

		add_action('init', array(&$this, 'wp_init'));
	}
	
	/**
	 * Executes during WP init phase
	 *
	 * @return void
	 */
	function wp_init() {
		// Put recipes into the stream if requested in configuration
		add_filter('pre_get_posts', array(&$this, 'pre_get_posts_filter'));
		
		// Do template setup after posts have been loaded
		add_action('wp', array(&$this, 'wp'));
		
		// Register Plugin CSS
		wp_register_style(self::prefix . 'style', self::$url . 'hrecipe.css');
		
		// Register Plugin javascript
		wp_register_script(self::prefix . 'js', self::$url . 'js/hrecipe.js', array('jquery'));
		
		// Register jQuery UI plugin for Ratings
		wp_register_script('jquery.ui.stars', self::$url . 'lib/jquery.ui.stars-3.0/jquery.ui.stars.min.js', array('jquery-ui-core', 'jquery-ui-widget'), '3.0.1');
		wp_register_style('jquery.ui.stars', self::$url . 'lib/jquery.ui.stars-3.0/jquery.ui.stars.min.css', array(), '3.0.1');
		
		// Setup AJAX handling for recipe ratings
		add_action('wp_ajax_'. self::prefix .  'recipe_rating', array(&$this, 'ajax_recipe_rating'));
		add_action('wp_ajax_nopriv_' . self::prefix . 'recipe_rating', array(&$this, 'ajax_recipe_rating'));
	}
	
	/**
	 * Setup actions, filters, etc. needed during template processing if recipes will be handled
	 *
	 * Executes after query has been parsed and posts are loaded and before template actions
	 *
	 * @uses $post Post data
	 * @return void
	 **/
	function wp()
	{
		global $post;
		
		// When query does not include recipes, not necessary to do the related processing
		if ( ! ( is_singular(self::post_type) 
						|| is_post_type_archive(self::post_type) 
						|| (is_home() && $this->options['display_in_home']) 
						|| (is_feed() && $this->options['display_in_feed'])) ) {
			return;
		}
		
		// Include Ratings JS module
		wp_enqueue_script('jquery.ui.stars');
		wp_enqueue_style('jquery.ui.stars');
		
		// Include JSON processing javascript module
		wp_enqueue_script('json2');
				
		// Include the plugin styling
		wp_enqueue_style(self::prefix . 'style');
		
		// Load plugin javascript
		wp_enqueue_script(self::prefix . 'js');

		// During handling of the header ...
		add_action('wp_head', array(&$this, 'wp_head'));
		
		// Update classes applied to <body> element
		add_filter('body_class', array (&$this, 'body_class'),10,2);
		
		// Hook template redirect to provide default page templates within the plugin
		add_action('template_redirect', array(&$this, 'template_redirect'));

		// During handling of footer in the body ...
		add_action('wp_footer', array(&$this, 'wp_footer'));
		
		// Update the post class as required
		if ($this->options['add_post_class']) {
			add_filter('post_class', array(&$this, 'post_class'));			
		}

		// When displaying a single recipe, add the recipe header and footer content
		if (is_single()) {
			// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
			wp_localize_script( 
				self::prefix . 'js', 
				'HrecipeMicroformat', 
				array( 
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'ratingAction' => self::prefix . 'recipe_rating',
					'postID' => $post->ID,
					'userRating' => self::user_rating($post->ID),
					'ratingNonce' => wp_create_nonce(self::prefix . 'recipe-rating-nonce')
				) 
			);

			// Add recipe meta data to the post content
			add_filter('the_content', array(&$this, 'the_content'));			
		}
		
		/*
		 * Register plugin supported shortcodes
		 */
		add_shortcode(self::prefix . 'title', array(&$this, 'sc_title'));
		add_shortcode('instructions', array(&$this, 'sc_instructions'));
		add_shortcode('step', array(&$this, 'sc_step'));
		add_shortcode('instruction', array(&$this, 'sc_step'));  // Allow instruction shortcode as an alternate
	}
	
	/**
	 * Add bits to the header portion of the page
	 *
	 * @return void
	 **/
	function wp_head()
	{
		// Setup google font used in recipe ingredient lists
		echo "<link href='http://fonts.googleapis.com/css?family=Annie+Use+Your+Telescope' rel='stylesheet' type='text/css'>";
		return;
	}
	
	/**
	 * Add plugin defined classes to body
	 *
	 * Update body class to flag as no-js.  If javascript is available, the class will be cleared.
	 * The filter is called after WP has added contents of $class to the $classes array.
	 *
	 * @param array $classes Array of classes defined for <body>
	 * @param string|array $class String or array of classes to be added to class array
	 * @return array Updated class Array
	 */
	function body_class($classes, $class) {
		if (! in_array('no-js', $classes)) {
			// Add no-js to class list if not already there
			$classes[] = "no-js";	
		}
		return $classes;
	}
	
	/**
	 * Use template provided page templates as defaults
	 *
	 * @return void
	 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
	 **/
	function template_redirect()
	{
		global $post;
		
		if (!$this->options['add_post_class'] && is_singular(self::post_type)) {
			// Don't format as a post TODO Provide a template to format single recipes
			$template_name = 'single-';
		} elseif (is_post_type_archive(self::post_type)) {
			$template_name = 'archive-';
		} else {
			return;
		}
		
		$template_name .= get_post_type($post) . '.php';
		// Look for available templates
		$template = locate_template(array($template_name), true);
		if (empty($template)) {
			include(self::$dir . 'lib/template/' . $template_name);
		}
		exit();
	}
	
	/**
	 * Plugin processing in the footer section of the body element.
	 * - Add javascript to remove 'no-js' class when javascript is available
	 *
	 * @return void
	 **/
	function wp_footer()
	{
		?>
		<script>
			// If javascript can run, remove the no-js class from the body
			var el = document.getElementsByTagName('body')[0];  
			el.className = el.className.replace('no-js','');
		</script>
		<?php
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
	// TODO Hook into author generation
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
			$content .= $this->recipe_field_html($field, $section);
		}
		$content .= '</div>';
		
		return $content;
	}

	/**
	 * Provide HTML for a named recipe field
	 *
	 * @uses $post When called within The Loop, $post will contain the data for the active post
	 * @param $field recipe meta data field name
	 * @param $section 'head' or 'footer'
	 * @return string HTML
	 **/
	function recipe_field_html($field, $section)
	{
		global $post;
		if (empty($this->recipe_field_map[$field]) || ! isset($this->recipe_field_map[$field]['format'])) return;
		
		// Produce the field value based on format of the meta data
		switch ($this->recipe_field_map[$field]['format']) {
			case 'text':  // default Post Meta Data
				$value = get_post_meta($post->ID, self::prefix . $field, true);
				$value = esc_html($value);
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
				$value = $this->recipe_difficulty($post->ID);
				break;
			
			case 'rating': // Recipe rating based on reader response
				$value = $this->recipe_rating_html($post->ID);
				break;
				
			case 'nutrition': // Recipe nutrition as calculated from ingredients
				$value = $this->nutrition_html($post->ID);
				break;
				
			default:
				$value = '';
		}

		$content =  '<div class="' . self::post_type . '-' . $section . '-field ' . self::prefix . $field . '">';
		$content .= $this->recipe_field_map[$field]['label'] . ': <span class="' . $field . '">' . $value . '</span>';
		$content .= '</div>';
		
		return $content;
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Kenneth J. Brucker <ken@pumastudios.com>
	 **/
	function recipe_difficulty($post_id)
	{
		$difficulty = get_post_meta($post_id, self::prefix . 'difficulty', true) | 0;
		$description = $this->recipe_field_map['difficulty']['option_descriptions'][$difficulty] | '';
		
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
	 * @param $post_id
	 * @return string HTML
	 **/
	function recipe_rating_html($post_id)
	{
		// Get rating votes from meta data if available
		$ratings = get_post_meta($post_id, self::prefix . 'ratings', true);
		if ($ratings) {
			$ratings = json_decode($ratings);
			$avg = self::rating_avg($ratings);
			$unrated = '';
		} else {
			$avg['avg'] = 0;
			$avg['cnt'] = 0;
			$unrated = ' unrated';
		}
		
		// Microformat encoding of the recipe rating (x out of 5)
		$content = '<span class="value-title" title="' . $avg['avg'] . '/5"></span>';

		$content .= '<div id="recipe-stars-' . $post_id . '" class="recipe-stars' . $unrated . '">';
		$content .= '<div class="recipe-avg-rating">';
		
		// Display average # of stars
		$stars_on_width = round($avg['avg']*16); // Stars are 16px wide, how many are turned on?
		$content .= '<div class="recipe-stars-off"><div class="recipe-stars-on" style="width:' . $stars_on_width . 'px"></div></div>';
		
		// Text based display of average rating and vote count
		$content .= sprintf('<span class="recipe-stars-avg">%.2f</span> (<span class="recipe-stars-cnt">%d</span> %s)', 
			$avg['avg'], $avg['cnt'],  _n('vote','votes', $avg['cnt']));
		$content .= '</div>';
		
		// In the event the recipe is unrated...
		$content .= sprintf('<div class="recipe-unrated">%s</div>', __("Unrated", self::p));
		
		// Give user a way to rate the recipe		
		$content .= '<form class="recipe-user-rating"><div>';
		$content .= __('Your Rating: ', self::p) . '<select name="recipe-rating">';
		$user_rating = self::user_rating($post_id);
		for ($i=1; $i <= 5; $i++) {
			$selected = ($user_rating == $i) ? 'selected' : ''; // Show user rating
			$content .= '<option value="' . $i .'"'. $selected . '>' . sprintf(_n('%d star', '%d stars', $i, self::p), $i) . '</option>';
		}
		$content .= '</select><div class="thank-you">' . __('Thank you for your vote!', self::p) . '</div></div></form>';
		$content .= '</div>'; // Close entire ratings div
		return $content;
	}
	
	/**
	 * Retrieve the user rating for a recipe from cookies
	 *
	 * @return int rating value 0-5
	 **/
	function user_rating($post_id)
	{
		$index = 'recipe-rating-' . $post_id;
		return isset($_COOKIE[$index]) ? $_COOKIE[$index] : 0;
	}
	
	/**
	 * Calculate a rating average from array of rating counts
	 *
	 * @return real Rating Average
	 **/
	function rating_avg($ratings)
	{
		$total = $sum_count = 0;
		
		foreach($ratings as $key => $count) {
			$total += $key * $count;
			$sum_count += $count;
		}
		
		return array( 'avg' => $total / $sum_count, 'cnt' => $sum_count );
	}
	
	/**
	 * Generate HTML for recipe nutrition block
	 *
	 * @return string HTML
	 **/
	function nutrition_html($post_id)
	{
		// TODO Add nutrition calculation on save		
		return '';
	}
	
	/**
	 * Generate HTML for the recipe title shortcode
	 *
	 * @param array $atts shortcode attributes
	 * @param string $content shortcode contents
	 * @return string HTML formatted title string
	 **/
	function sc_title($atts, $content = '')
	{
		global $post;
		return '<div class="fn">' . get_post_meta($post->ID, self::prefix . 'fn', true). '</div>';
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
	 * @return string HTML
	 **/
	function sc_instructions($atts, $content = '')
	{
		$content = '<div class="instructions">' . do_shortcode($content) . '</div>';
		return $content;
	}
	
	/**
	 * Generate HTML for the step shortcode
	 * Example usage: [step]An instruction step[/step]
	 *
	 * @param array $atts shortcode attributes
	 * @param string $content shortcode contents
	 * @return string HTML
	 **/
	function sc_step($atts, $content = '')
	{
		return '<div class="step">' . do_shortcode($content) . '</div>';
	}
	
	/**
	 * Process AJAX request to rate recipes, save user's rating in cookie
	 *
	 * TODO If cookies can't be saved, don't allow voting by the user - test for WP test cookie presence
	 * TODO Make cookie storage more efficient - store all votes in one cookie
	 *
	 * @internal	Ballot box stuffing is possible since the presence of a vote is saved in the user's browser
	 * @return does not return
	 **/
	function ajax_recipe_rating()
	{
		$post_id = $_POST['postID'];
		$rating = $_POST['rating'];
		$prev_rating = $_POST['prevRating'];
		$nonce = $_POST['ratingNonce'];
		
		if (! wp_verify_nonce($nonce, self::prefix . 'recipe-rating-nonce')) {
			die( 'ajax_recipe_rating: Bad Nonce detected.');
		}
		
		$ratings = get_post_meta($post_id, self::prefix . 'ratings', true);
		if ($ratings) {
			$ratings = json_decode($ratings, true);
		} else {
			$ratings = array( 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 );
		}

		// Adjust vote count for this user, remove old vote if previous value provided.
		if (isset($ratings[$rating])) { $ratings[$rating]++; }
		if ($prev_rating && isset($ratings[$prev_rating]) && $ratings[$prev_rating] > 0) {
			$ratings[$prev_rating]--;
		}
		
		$avg = self::rating_avg($ratings);
		
		// Save new ratings array for this post
		$json_ratings = json_encode($ratings);
		add_post_meta($post_id, self::prefix . 'ratings', $json_ratings, true) or update_post_meta($post_id, self::prefix . 'ratings', $json_ratings);
		
		// Save user rating for this recipe in a cookie (expires in 10 years)
		setcookie('recipe-rating-' . $post_id, $rating, time()+60*60*24*365*10, COOKIEPATH, COOKIE_DOMAIN);

		// response output
		$response = json_encode(array(
			'postID' => $post_id,
			'avg' => $avg['avg'],
			'cnt' => $avg['cnt'],
			'userRating' => $rating,
			'ratingNonce' => wp_create_nonce(self::prefix . 'recipe-rating-nonce'),
		));
		header( "Content-Type: application/json" );
		echo $response;
		
		exit;
	}
	
	/**
	 * Perform Plugin Activation handling
	 *  * Confirm that plugin environment requirements are met
	 *	* Run parent class activation
	 *
	 * @return void
	 **/
	public static function plugin_activation()
	{
		global $wp_version;
		
		// Enforce minimum PHP version requirements
		if (version_compare(self::php_version_required, phpversion(), '>')) {
			die(self::p . ' plugin requires minimum PHP v' . self::php_version_required . '.  You are running v' . phpversion());
		}
		
		// Enforce minimum WP version
		if (version_compare(self::wp_version_required, $wp_version, '>')) {
			die(self::p . ' plugin requires minimum WordPress v' . self::wp_version_required . '.  You are running v' . $wp_version);
		}
		
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