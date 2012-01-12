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
	 * Class Constructor
	 *
	 */
	function __construct($domain) {
		$this->id = $domain . '-importer';
		$this->name = __('Import Recipes', $domain);
		$this->desc = __('Import recipes from supported formats as a Recipe Post Type.', $domain);
		$this->domain = $domain;
	}
	
	/**
	 * import dispatch routine registered with Wordpress
	 *
	 * @access public
	 * @return void
	 **/
	public function dispatch() {
		global $hrecipe_microformat;
		
		// Confirm that the hrecipe object is available
		if (empty($hrecipe_microformat)) {
			?>
			<h2>Recipe Import</h2>
			<p>There is an internal error with the hrecipe-microformat plugin.  Importing is not available.</p>	
			<?php
			return;
		}
		
		// TODO Provide form for import of multiple recipes to set difficulty etc.?
		
		// Retrieve which step is active in import process
		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];
 
		switch ($step) {
			case 0 :
				$this->upload_form('admin.php?import='.$this->id.'&amp;step=1');
				break;
			case 1 :
				check_admin_referer($this->id);
				$recipes = $this->import();
				if (isset($recipes['error'])) echo $recipes['error'];
				break;
		}
	}
	
	/**
	 * Display upload form
	 *
	 * TODO Allow multiple files on upload
	 *
	 * @access private
	 * @return void
	 **/
	function upload_form($action) {
		$bytes = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size = wp_convert_bytes_to_hr( $bytes );
		?>
		<h2><?php _e('Import Recipes', $this->domain)?></h2>
		<p><?php _e('The following recipe formats can be imported:', $this->domain)?></p>
		<ul>
			<li><?php _e('ShopNCook Export Format (.scx)', $this->domain)?></li>
		</ul>
		<form enctype="multipart/form-data" id="import-upload-form" method="post" action="<?php echo esc_attr(wp_nonce_url($action, $this->id)); ?>">
		<p>
		<label for="upload"><?php _e( 'Select a recipe file from your computer:', $this->domain ); ?></label> (<?php printf( __('Maximum size: %s', $this->domain ), $size ); ?>)
		<input type="file" id="upload" name="import" required />
		<input type="hidden" name="action" value="save" />
		<input type="hidden" name="max_file_size" value="<?php echo $bytes; ?>" />
		</p>
		<?php submit_button( __('Upload file and import', $this->domain), 'button' ); ?>
		</form>
		<?php
	}
	
	/**
	 * Handle import request from dispatcher
	 *
	 * @access private
	 * @return void
	 */
	private function import() {
		if ( !isset($_FILES['import']) || 0 === $_FILES['import']['size'] ) {
			$recipes['error'] = __( 'Empty file received. This error could be caused by uploads being disabled in your <ph></ph>p.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.', $this->domain );
			return $recipes;
		}

		// TODO Change import to work a single recipe at a time to minimize memory impact off large imports
		
		/**
		 * Parse incoming data into a normalized format
		 */
		$import = new import_shopncook();
		$recipes = $import->import_scx($_FILES['import']['tmp_name']);
		if (! $recipes) {
			$recipes['error'] = __('Received error importing ShopNCook data.', $this->domain) . ' ' . $import->error_msg;
			return $recipes;
		}
		
		/**
		 * For each recipe, create a new post
		 */
		
		echo '<h3>' . sprintf(__('Importing %d Recipe(s):', $this->domain), count($recipes)) . '</h3>';
		echo '<ol>';
		foreach ($recipes as $index => $recipe) {
			if ($errmsg = $this->add_recipe_post($recipe)) {
				echo '<li>' . $errmsg . '</li>';
				$recipes['error'] = sprintf(__('Error creating recipe %d.  Remainder of Import cancelled.', $this->domain), $index + 1);
				break;
			}
			echo '<li>' . esc_attr($recipe['fn']) . '</li>';
		}
		echo '</ol>';

		return $recipes;
	}
	
	/**
	 * From normalized recipe array, create a recipe post
	 *
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
	 * @return false on success, error message on failure
	 **/
	private function add_recipe_post($recipe)	{
		global $hrecipe_microformat;
		
		$post_status = 'draft';  // TODO Setup option for default post status
		
		/**
		 * Sanitize incoming recipe data
		 */
		$recipe['yield'] = $recipe['yield'] != '0' ? $recipe['yield'] : '';
		$recipe['summary'] = wpautop(wp_kses_data($recipe['summary']));
		$recipe['published'] = $recipe['published'] ? $recipe['published'] : date('Y-m-d H:i:s'); // TODO Validate published date format
		$recipe['difficulty'] = $recipe['difficulty'] ? $recipe['difficulty'] : '0';
		
		/**
		 * Add Recipe to the database
		 */
		$new_post = array(
			'post_title' => $recipe['fn'],
      'post_content' => $this->build_post_content($recipe['content']),
      'post_status' => $post_status, 
      'post_type' => $hrecipe_microformat::post_type,
      'post_date' => $recipe['published'],
      'post_excerpt' => $recipe['summary'],
			'tags_input' => $recipe['tag'], // string of Comma separated tags 
		);
		
		// Insert post
		$post_id = wp_insert_post($new_post);
		if (! $post_id) {
			return sprintf(__('Failed to import recipe "%s"', $this->domain), $new_post['post_title']);
		}
		
		// Save Recipe meta-data
		$meta_fields = array('fn', 'yield', 'duration', 'preptime', 'cooktime', 'author', 'difficulty');
		foreach ($meta_fields as $field) {
			if (isset($recipe[$field]) && $recipe[$field] != '' && ($post_meta_key = $hrecipe_microformat->post_meta_key($field))) {
				add_post_meta($post_id, $post_meta_key, $recipe[$field]);
			}
		}
		
		return false;
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
		
		foreach ($content as $index => $section) {
			switch ($section['type']) {
				case 'ingrd-list':
					// Open Ingredients table and add header
					$text .= '<table class="ingredients">';
					$text .= '<thead><tr><th colspan="2"><span class="ingredients-title">Ingredients</span></th></tr></thead>';

					// Add row for each ingredient
					foreach ($section['data'] as $d) {
						foreach (array('value', 'type', 'ingrd', 'comment') as $i) {
							$$i = isset($d[$i]) && $d[$i] ? '<span class="' . $i . '">' . $d[$i] . '</span>' : '';
						}
						$text .= '<tr class="ingredient"><td>' . $value . $type . '</td><td>' . $ingrd . $comment . '</td></tr>';
					}
					
					$text .= '</table>';
					break;
					
				case 'text':
					$text .= wpautop(wp_kses_post($section['data']));
					break;
				
				default:
					$text .= 'Unknown content type "' . $section['type'] . '" found in import data.';
					break;
			}
		}
		
		return $text;
	}
} // End hrecipe_importer class

/**
 * Register the importer with Wordpress - must include the import module as it's not included by default
 */
// TODO Confirm importer not registered if the plugin is not active
include_once(ABSPATH . 'wp-admin/includes/import.php');
if(function_exists('register_importer')) {
	$hrecipe_import = new hrecipe_importer(hrecipe_admin::p);
	register_importer($hrecipe_import->id, $hrecipe_import->name, $hrecipe_import->desc, array ($hrecipe_import, 'dispatch'));
}
?>