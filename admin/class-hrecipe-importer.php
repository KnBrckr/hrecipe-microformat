<?php
/**
 * hrecipe_import class
 *
 * Manages recipe imports
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

// TODO Import MasterCook format
// TODO Import Text File format
// TODO Import hrecipe format

// Protect from direct execution
if (!defined('WP_PLUGIN_DIR')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

$required_libs = array('class-import-shopncook.php');
foreach ($required_libs as $lib) {
	if (!include_once($lib)) {
		return false;
	}
}

// The importer
class hrecipe_importer {
	public $id;
	public $name;
	public $desc;
	
	/**
	 * Translation domain
	 *
	 * @var string
	 */
	private $domain;
	
	/**
	 * Name of Recipe Category Taxonomy
	 *
	 * @access private
	 * @var string
	 **/
	private $category_taxonomy;
	
	/**
	 * Transient ID used in multi-step form handling to save recipe data
	 *
	 * @var string
	 **/
	private $transient_id;
	
	/**
	 * Class Constructor
	 *
	 */
	function __construct($domain, $category, $ingrd_db) {
		$this->id = $domain . '-importer';
		$this->name = __('Import Recipes', $domain);
		$this->desc = __('Import recipes from supported formats as a Recipe Post Type.', $domain);
		$this->domain = $domain;
		$this->category_taxonomy = $category;
		$this->transient_id = $domain . '-recipes_import'; // TODO Use a session var for concurrency?
		$this->ingrd_db = $ingrd_db;
	}
	
	/**
	 * import dispatch routine registered with Wordpress
	 *
	 * @access public
	 * @return void
	 **/
	public function dispatch() {
		global $hrecipe_microformat;
		
		echo '<div id="' . $this->id . '">';
		
		// Confirm that the hrecipe object is available
		if (empty($hrecipe_microformat)) {
			?>
			<h2>Recipe Import</h2>
			<p>There is an internal error with the hrecipe-microformat plugin.  Importing is not available.</p>	
			<?php
		} else {
			// TODO Provide form for import of multiple recipes to set difficulty etc.?

			// Retrieve which step is active in import process
			$step = empty ($_POST['step']) ? 0 : intval($_POST['step']);
			
			// What is requested post status
			$post_status = empty ( $_POST['post_status'] ) ? 'draft' : $_POST['post_status'];
			
			// 0 = Display upload form
			// 1 = Import entire file contents
			// 2 = Select recipes to upload from file
			// 3 = Import selected recipes
			switch ($step) {
				case 0 :
				  // Destroy Transient data if it exists
				  delete_transient($this->transient_id);
					// Display Upload form
					$this->upload_form();
					break;
				case 1 :
					// Import all recipes in the uploaded file
					check_admin_referer($this->id);
					$this->import_all($post_status);
					break;
				case 2:
					// Select recipes from file to be imported
					check_admin_referer($this->id);
					$this->select_import_recipes();
					break;
				case 3:
					// Import selected recipes
					check_admin_referer($this->id);
					echo 'Importing selected';
					// TODO Implement selective importing of recipes
					break;
				default:
					// Uh-Oh!  Shouldn't get here
					echo 'Invalid step value specified: ' . $step;
					echo 'Please report this error.';
					break;
			}
		} // End have valid hrecipe_microformat config
		
		echo '</div>';
	}
	
	/**
	 * Display upload form to user
	 *
	 * @access private
	 * @return void
	 **/
	function upload_form() {
		$bytes = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size = wp_convert_bytes_to_hr( $bytes );
		?>
		<h2><?php _e('Import Recipes', $this->domain)?></h2>
		<p><?php _e('The following recipe formats can be imported:', $this->domain)?></p>
		<ul>
			<li><?php _e('ShopNCook Export Format (.scx)', $this->domain)?></li>
		</ul>
		<form enctype="multipart/form-data" id="import-upload-form" method="post" 
			action="<?php echo esc_attr(wp_nonce_url('admin.php?import=' . $this->id, $this->id)); ?>">
		<p>
		<label for="upload"><h3><?php _e( 'Select a recipe file from your computer:', $this->domain ); ?></h3></label> (<?php printf( __('Maximum size: %s', $this->domain ), $size ); ?>)<br />
		<input type="file" id="upload" name="import" required />
		<input type="hidden" name="max_file_size" value="<?php echo $bytes; ?>" />
		<input type="hidden" name="step" value="1" />
		</p>
		<p>
			<label for="publish"><h4><?php _e( 'Imported Recipes should be ... ', $this->domain );?></h4></label>
			<ul>
				<li><input type="radio" name="post_status" value="draft" checked><?php _e ( 'Drafts', $this->domain ); ?></li>
				<li><input type="radio" name="post_status" value="publish"><?php _e ( 'Published', $this->domain ); ?></li>
				<li><input type="radio" name="post_status" value="private"><?php _e ( 'Private (only visible to Editors and Administrators)', $this->domain); ?></li>
			<?php
			if (WP_DEBUG) {
				?>
				<li><input type="radio" name="post_status" value="debug">Debug Mode - Trace VAR ONLY	</li>
				<?php
			}
			?>
			</ul>
		</p>
		<!-- TODO Implement ability to select recipes to import from larger file
		<p>
			<label for="import_all"><h3><?php _e( 'How should recipes be imported?', $this->domain); ?></h3></label>
			<input type="radio" name="step" value="1" checked><?php _e( 'Import All Recipes from source file', $this->domain ); ?><br />
			<input type="radio" name="step" value="2"><?php _e( 'Select Recipes from file to import', $this->domain); ?>
		</p>
		-->
		<?php submit_button( __('Upload file and import', $this->domain), 'button' ); ?>
		</form>
		<?php
	}
	
	/**
	 * Handle import request from dispatcher
	 *
	 * @param int $post_status  0 ==> Add Recipes as Drafts, 1 ==> Publish on Add, 2 ==> Private on Add
	 * @access private
	 * @return void
	 */
	private function import_all($post_status) {
		// Clean the input array up before using it
		$unknown_category = empty($_POST['unknown_category']) ? array() : array_map('intval', $_POST['unknown_category']);
		
		/**
		 * If transient data is available, use it.  File was recently uploaded
		 */
		if (false === ($recipes = get_transient($this->transient_id))) {
			// Normalize uploaded file into internal format for processing
			$recipes = $this->normalize_file();	

			if (isset($recipes['error'])) {
				echo $recipes['error'];
				return;
			}
			
			/**
			 * Validate incoming recipe categories - redirect if needed to map to existing categories
			 */
			if ( ! $this->validate_categories($post_status, $recipes) ) {
				return; // validate created a form to handle mappings
			}			
		}
		
		/**
		 * For any unknown categories in the import data, create any needed entries in the taxonomy 
		 */
		foreach ($unknown_category as $category => $target) {
			// Value of 0 indicates that the named category should be created in the taxonomy
			//          -1 ==> skip assigning this unknown category
			if ( 0 == $target ) {
				$term = wp_insert_term($category, $this->category_taxonomy);
				$unknown_category[$category] = $term['term_id'];
			}
		}
		
		/**
		 * For each recipe, create a new post
		 */		
		echo '<h3>' . sprintf(__('Importing %d Recipe(s):', $this->domain), count($recipes)) . '</h3>';
		echo '<ol>';
		foreach ($recipes as $index => $recipe) {
			if (!($post_id = $this->add_recipe_post($post_status, $recipe, $unknown_category))) {
				echo '<li>' . $this->error_msg . '</li>';
				$errmsg = sprintf(__('Error creating recipe %d.  Remainder of Import cancelled.', $this->domain), $index + 1);
				break;					
			}
			echo '<li><a href="' . get_edit_post_link($post_id) . '">' . esc_attr($recipe['fn']) . '</a></li>';
		}
		echo '</ol>';

		if (isset($errmsg)) {
			echo '<h3>' . $errmsg . '</h3>';
		} else {
			echo '<h3>' . sprintf(__('Recipe Import Complete.', $this->domain)) . '</h3>';			
		}
		
		// Destroy Transient data if it exists
	  delete_transient($this->transient_id);
	  
		return;
	}
	
	/**
	 * Identify any unknown categories, prompt for how to handle
	 *
	 * @param string $post_status Status to use when posting
	 * @param array $recipes Array of normalized recipe data
	 * @return false if handling form was needed
	 **/
	function validate_categories($post_status, $recipes)
	{
		/**
		 * Walk the recipes and find all categories being used
		 */
		$incoming_categories = array();
		foreach ($recipes as $recipe) {
			if (is_array($recipe['category'])) {
				foreach ($recipe['category'] as $category) {
					$incoming_categories[$category] = 1;
				}
			}
		}
		
		/**
		 * Look for any unknown categories
		 */
		$unknown = array();
		foreach ($incoming_categories as $category => $value) {
			if (!term_exists($category, $this->category_taxonomy)) {
				$unknown[] = $category;
			}
		}
		
		/**
		 * If there are unknown categories, ask user what to do with them
		 */
		if (count($unknown)) {
			$this->map_category_form($post_status, $recipes, $unknown);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Display form to map unknown categories to known ones
	 *
	 * @param string $post_status Post status to use on add
	 * @param array $recipes Array of recipes to import
	 * @param array $unknown array of category names not found in taxonomy
	 * @return void
	 **/
	function map_category_form($post_status,$recipes,$unknown)
	{
		/**
		 * Save the transient recipe data for 1 hour (60*60 seconds)
		 */
		set_transient($this->transient_id, $recipes, 3600);
		
		/**
		 * Setup for Taxonomy query and emit the form
		 */
		$dropdown_args = array (
			'hierarchical' => true,
			'taxonomy' => $this->category_taxonomy,
			'hide_if_empty' => false,
			'hide_empty' => false,
			'name' => 'use_category[]',
			'echo' => 1
			);
		?>
		<form enctype="multipart/form-data" id="import-upload-form" method="post" 
					action="<?php echo esc_attr(wp_nonce_url('admin.php?import=' . $this->id, $this->id)); ?>">
					
			<input type="hidden" name="step" value="1" />
			<input type="hidden" name="post_status" value="<?php echo $post_status ?>">
			<h3>Define recipe categories for import</h3>
			Unknown categories were found in the imported file.  Please indicate how each one should be created in the table below.
			<table>
				<caption>Define Mapping</caption>
				<thead>
					<tr>
						<th>Unknown Name</th><th>Category to Use</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($unknown as $index => $category) {
						?>
						<tr>
							<td><?php echo $category ?></td>
							<td><?php
							  /**
							   * Display dropdown form containing the available category names
							   */
								$dropdown_args['show_option_none'] = 'Ignore ' . $category;
								$dropdown_args['show_option_all'] = 'Create ' . $category;
								$dropdown_args['id'] = 'use-category-' . $index;
								$dropdown_args['name'] = 'unknown_category[' . $category. ']';
								wp_dropdown_categories($dropdown_args);
							?></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
			<?php submit_button( __('Finish Import', $this->domain), 'button' ); ?>
		</form>
	<?php
	}
	
	/**
	 * Display form to select which recipes in a data file will be imported
	 *
	 * @return void
	 **/
	private function select_import_recipes()
	{
		// Normalize uploaded file into internal format for processing
		$recipes = $this->normalize_file();
		
		// TODO Temporarily save normalized data for next step

		if (isset($recipes['error'])) {
			echo $recipes['error'];
			return;
		}
		?>
		<form enctype="multipart/form-data" id="import-upload-form" method="post" 
			action="<?php echo esc_attr(wp_nonce_url('admin.php?import=' . $this->id, $this->id)); ?>">
			<h3>Select Recipes to Import:</h3>
			<input type="hidden" name="step" value="3" />
			<?php
			foreach ($recipes as $index => $recipe) {
				echo '<input type="checkbox" name="import_indexes[]" value="'. $index . '">' . esc_attr( $recipe['fn'] ) . '<br />';
			}
		
			submit_button( __('Import selected recipes', $this->domain), 'button' );
			?>
		</form>
		<?php
	}
	
	/**
	 * Read uploaded file, normalizing recipe content into internal format for processing
	 * 
	 * @return array of normalized recipes
	 **/
	private function normalize_file()
	{
		if ( !isset($_FILES['import']) || 0 === $_FILES['import']['size'] ) {
			$recipes['error'] = __( 'Empty file received. This error could be caused by uploads being disabled or by post_max_size being defined as smaller than upload_max_filesize in your PHP configuration.', $this->domain );
			return $recipes;
		}

		/**
		 * Parse incoming data into a normalized format based on file type
		 */
		
		$path_parts = pathinfo($_FILES['import']['name']);	
		switch ($path_parts['extension']) {
			case 'scx': // Import ShopNCook data
				$import_type = __("ShopNCook", $this->domain);
				$import = new import_shopncook();
				break;
				
			default:
				$recipes['error'] = __('Unknown recipe format uploaded.');
				break;
		}
		
		if (! empty($import)) {
			$recipes = $import->normalize_all($_FILES['import']['tmp_name']);
		} else {
			$recipes['error'] = sprintf(__('Received error importing %s data.', $this->domain), $import_type) . ' ' . $import->error_msg;
		}
		return $recipes;		
	}
	
	/**
	 * From normalized recipe array, create a recipe post
	 *
	 * @param string $post_status Post Publication status
	 * @param array $recipe
	 *  $recipe['fn']            Recipe Title
	 *	$recipe['yield']         Recipe Yield
	 *	$recipe['duration']      Total time to execute recipe
	 *	$recipe['preptime']      Amount of prep time
	 *	$recipe['cooktime']      Cooking time for recipe
	 *	$recipe['author']        Recipe Author
	 *	$recipe['category']      Recipe Category
	 *	$recipe['content']       array of recipe content elements
	 *	$recipe['summary']       Summary or introduction text
	 *	$recipe['published']     Date published in 'Y-m-d H:i:s' format
	 *	$recipe['tag']           Comma separated list of tags
	 *	$recipe['difficulty']    Recipe difficulty rating  [0-5]
	 * @param array $unknown_category maps unknown categories to known elements
	 * @uses $this->error_msg string Error message on failure
	 * @return recipe id (post id) on success, NULL on failure
	 **/
	private function add_recipe_post($post_status, $recipe, $unknown_category)	{
		global $hrecipe_microformat;
		
		/**
		 * Sanitize incoming recipe data
		 */
		$recipe['yield'] = $recipe['yield'] != '0' ? $recipe['yield'] : '';
		$recipe['summary'] = wpautop($recipe['summary']);
		$recipe['published'] = $recipe['published'] ? $recipe['published'] : current_time('mysql');
		$recipe['difficulty'] = $recipe['difficulty'] ? $recipe['difficulty'] : '0';
		
		/**
		 * Add Recipe to the database
		 */
		$new_post = array(
			'post_title' => $recipe['fn'],
      'post_content' => $this->build_post_content($recipe['content']),
      'post_status' => $post_status, 
			'post_password' => '',
      'post_type' => $hrecipe_microformat::post_type,
      'post_date' => $recipe['published'],
      'post_excerpt' => $recipe['summary'],
			'tags_input' => $recipe['tag'], // string of Comma separated tags 
		);
		
		/**
		 * Map incoming categories into the recipe category taxonomy
		 */
		$tax_input = $this->set_recipe_categories($recipe['category'], $unknown_category);
		if ( $tax_input ) {
			$new_post['tax_input'] = $tax_input;
		}
		
		// IF in debug mode
		if (WP_DEBUG && 'debug' == $post_status ) {
			echo "<li>";
			print_r($new_post);
			echo "</li>";
			return -1;
		}
		
		// Insert post
		$post_id = wp_insert_post($new_post);
		if (! $post_id) {
			$this->error_msg = sprintf(__('Failed to import recipe "%s"', $this->domain), $new_post['post_title']);
			return NULL;
		}
		
		// Save Recipe meta-data
		$meta_fields = array('yield', 'duration', 'preptime', 'cooktime', 'author', 'difficulty');
		foreach ($meta_fields as $field) {
			if (isset($recipe[$field]) && $recipe[$field] != '' && ($post_meta_key = $hrecipe_microformat->post_meta_key($field))) {
				add_post_meta($post_id, $post_meta_key, $recipe[$field]);
			}
		}
		
		/**
		 * Save Recipe Ingredients
		 */
		$ingrd_list_id = 1; // Coorelates to short code ids added to content in build_post_content()
		$ingrd_list_title = array(); // array of ingrdient list titles, indexed by list id
		
		foreach ($recipe['content'] as $index => $section) {
			if ('ingrd-list' == $section['type']) {
				/**
				 * Add row to DB for each ingredient in list
				 */
				$ingrds = array(); // Start with empty list
				
				foreach ($section['data'] as $d) {
					if (array_key_exists('list-title', $d)) {
						$ingrd_list_title[$ingrd_list_id] = $d['list-title'];
					} else {
						/**
			   		 	  * TODO Standardize units of measurement during import
			   		 	  * teaspoon: t, ts, tsp, tspn
			   		 	  * tablespoon: T, tb, tbs, tbsp, tblsp, tblspn, tbls
						  */
						
						$row['quantity'] = $d['value'];
						$row['unit'] = $d['type'];
						$row['ingrd'] = $d['ingrd'];
						$row['comment'] = $d['comment'];
					
						$ingrds[] = $row; // Add row to the list
					}
				}
				
				// Add default list title if none found in content
				if (! array_key_exists($ingrd_list_id, $ingrd_list_title)) {
					$ingrd_list_title[$ingrd_list_id] = "Ingredients";
				}
				
				// Add list to the DB
				if ( count($ingrds) > 0 ) {
					// FIXME Handle insert errors
					
					// Add ingredients to the DB and move to next ingredient list id
					$this->ingrd_db->insert_ingrds_for_recipe($post_id, $ingrd_list_id++, $ingrds);
				}
			} // End if ('ingrd-list')
		} // End foreach ($content)
		
		/**
		 * Save ingredient list titles and list ids (array keys) to post meta data
		 */
		add_post_meta($post_id, $hrecipe_microformat::prefix . 'ingrd-list-title', $ingrd_list_title);
		
		return $post_id;
	}
	
	/**
	 * Build post content from array of ingredients and instruction text
	 *
	 * @access private
	 * @param array $content
	 *    Each index element is sub-array containing ['type'] and ['data']
	 * @return text
	 **/
	private function build_post_content($content)
	{
		$text = '';
		
		/*
			Each ingredient list encountred will be added in order to the ingredients database later.
			Add to the text flow using the short-code [ingrd-list #] to grab the ingredients from the
			DB during recipe display processing.
		*/
		$ingrd_list_id = 1; 
		
		foreach ($content as $index => $section) {
			switch ($section['type']) {
				case 'ingrd-list':
					// Can't save ingredient list to DB yet - Need the Post ID first.
					$text .= '[ingrd-list id="'. $ingrd_list_id++ . '"]' . "\n\n";
					break;
					
				case 'text':
					$text .= wpautop($section['data']);
					break;
				
				default:
					$text .= 'Unknown content type "' . $section['type'] . '" found in import data.';
					break;
			}
		}
		
		return $text;
	}
	
	/**
	 * Filter imported recipe categories into format ready to add to new recipe post.
	 * Because the category taxonomy is hierarchical, term_id's must be used in the tax_input array
	 *
	 * @param array $categories Array of recipe categories from imported data
	 * @param array $unknown_category Array mapping unknown category to term_id to use on add
	 * @return array of categories to be used as tax_input for post creation or false if category list is empty
	 **/
	function set_recipe_categories($categories, $unknown_category)
	{
		$new_categories = array();

		/**
		 * Build array of taxonmy id's to assign to the imported recipe.
		 * Filter out terms that don't exist that are not being remapped.
		 */
		foreach ($categories as $category) {
			if ($term_id = term_exists($category, $this->category_taxonomy)) {
				$new_categories[] = $term_id['term_id'];				
			} else {
				// If a mapping for the undefined category exists, use it.  Otherwise it's being ignored.
				if (!empty($unknown_category[$category])) {
					$new_categories[] = $unknown_category[$category];
				}
			}
		}			
		
		if (empty($new_categories)) {
			return false;
		}
		
		$tax_input = array();
		$tax_input[$this->category_taxonomy] = $new_categories;
		
		return $tax_input;
	}
} // End hrecipe_importer class

/**
 * Register the importer with Wordpress - must include the import module as it's not included by default
 */
include_once(ABSPATH . 'wp-admin/includes/import.php');
?>