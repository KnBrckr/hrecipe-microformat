<?php
/**
 * hrecipe_import class
 *
 * Manages recipe imports
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
		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];
			error_log("In dispatch step $step");
 
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
	 * @access private
	 * @return void
	 **/
	function upload_form($action) {
		$bytes = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size = wp_convert_bytes_to_hr( $bytes );
		?>
		<form enctype="multipart/form-data" id="import-upload-form" method="post" action="<?php echo esc_attr(wp_nonce_url($action, $this->id)); ?>">
		<p>
		<label for="upload"><?php _e( 'Choose a recipe file from your computer:' ); ?></label> (<?php printf( __('Maximum size: %s' ), $size ); ?>)
		<input type="file" id="upload" name="import" size="25" />
		<input type="hidden" name="action" value="save" />
		<input type="hidden" name="max_file_size" value="<?php echo $bytes; ?>" />
		</p>
		<?php submit_button( __('Upload file and import'), 'button' ); ?>
		</form>
		<?php
	}
	
	/**
	 * Handle import request from dispatcher
	 *
	 * @access private
	 * @return void
	 */
	function import() {
		if ( !isset($_FILES['import']) || 0 === $_FILES['import']['size'] ) {
			$recipes['error'] = __( 'Empty file received. This error could be caused by uploads being disabled in your <ph></ph>p.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.', $this->domain );
			return $recipes;
		}
		
		$import = new import_shopncook();
		$recipes = $import->import_scx($_FILES['import']['tmp_name']);
		if (! $recipes) {
			$recipes['error'] = __('Received error importing ShopNCook data.', $this->domain) . ' ' . $import->error_msg;
			return $recipes;
		}

		var_dump($recipes);

		// $file = wp_import_handle_upload();
		// if ( isset($file['error']) ) {
		// 	echo '< p>Sorry, there has been an error.< /p>';
		// 	echo '< p><strong>' . $file['error'] . '</strong>< /p>';
		// 	return;
		// }
		// $this->file = $file['file'];
		// $this->id = (int) $file['id'];
 
		// TODO: Write import code
	}
 
} // End hrecipe_importer class

/**
 * Register the importer with Wordpress - must include the import module as it's not included by default
 */
include_once(ABSPATH . 'wp-admin/includes/import.php');
if(function_exists('register_importer')) {
	$hrecipe_import = new hrecipe_importer(hrecipe_microformat_admin::p);
	register_importer($hrecipe_import->id, $hrecipe_import->name, $hrecipe_import->desc, array ($hrecipe_import, 'dispatch'));
}
?>