<?php
/**
 * import_shopncook Class
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2015 Kenneth J. Brucker (email: ken@pumastudios.com)
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
	die( 'I don\'t think you should be here.' );
}

if (!include_once('class-hrecipe-parse-xml.php')) {
	return false;
}

class import_shopncook {
	/**
	 * Error description on method failure
	 *
	 * @var string
	 * @access public
	 */
	public $error_msg;
	
	/**
	 * imported recipe data
	 *
	 * @var array recipes
	 **/
	public $recipes;
	
	private $xml_parser;

	/**
	 * Parse a shopncook SCX file containing one or more exported recipes
	 *
	 * @param string $fname XML file
	 * @access public
	 * @return array Normalized array of imported recipe data
	 */
	public function __construct() {
		/**
		 * Setup parser that maps INGREDIENTTEXT to INGREDIENT so all elements in INGREDIENTLIST remain in order
		 * Modified tags will have extra '@orig_tag' hash in the returned array
		 */
		$this->xml_parser = new hrecipe_parse_xml();
		$this->xml_parser->set_tags(array('INGREDIENTTEXT' => 'INGREDIENT'));
	}
	
	/**
	 * import a ShopNCook SCX format file
	 *
	 * @param $fname string Filename to be parsed
	 * @return array of normalized recipes
	 **/
	function normalize_all($fname)
	{
		// import the XML data
		if (! ($recipe_import = $this->xml_parser->parse($fname))) {
			// If unable to parse the XML data file ...
			$this->error_msg = 'XML Parse Error returned: ' . $this->xml_parser->error_msg;
			return false;
		}
		
		if (! array_key_exists('SHOPNCOOK', $recipe_import)) {
			$this->error_msg = 'File does not appear to be exported from ShopNCook';
			return false;
		}
		
		// Version check the XML content
		if ('1.0' != $recipe_import['SHOPNCOOK']['@attrib']['VERSION']) {
			$this->error_msg = 'Unknown ShopNCook XML version ' . $recipe_import['SHOPNCOOK']['@attrib']['VERSION'];
			return false;
		}

		/*
		 * Imported list may contain one or more recipes, array structure will differ as a result
		 */
		if (array_key_exists(0, $recipe_import['SHOPNCOOK']['RECIPELIST']['RECIPE'])) {
			$recipes = array();
			foreach ($recipe_import['SHOPNCOOK']['RECIPELIST']['RECIPE'] as $scx_recipe) {
				array_push($recipes, $this->parse_recipe($scx_recipe));
			}
		} else {
			// Handle single recipe case:
			$recipes[0] = $this->parse_recipe($recipe_import['SHOPNCOOK']['RECIPELIST']['RECIPE']);
		}

		$this->recipes = $recipes;
		return $recipes;
	}
	
	/**
	 * Parse single recipe entry resulting from evaluation of ShopNCook XML file
	 *
	 * @access private
	 * @param string $scx Single recipe entry from evaluated ShopNCook XML data
	 * @return array Normalized recipe information
	 */
	private function parse_recipe($scx) {
		$recipe['fn'] = $scx['RECIPEHEADER']['RECIPETITLE'];
		// Use NBPERSONS as a backup to PORTIONYIELD
		$recipe['yield'] = is_string($scx['RECIPEHEADER']['PORTIONYIELD']) ? $scx['RECIPEHEADER']['PORTIONYIELD'] :  $this->scx_string($scx['RECIPEHEADER']['NBPERSONS']);
		if ('0' == $recipe['yield']) $recipe['yield'] = ''; // 0 Yield isn't helpful so filter it
		$recipe['duration'] = $this->scx_string($scx['RECIPEHEADER']['TOTALTIME']);
		$recipe['preptime'] = $this->scx_string($scx['RECIPEHEADER']['PREPTIME']);
		$recipe['cooktime'] = ''; // Not present in ShopNCook files
		$recipe['author'] = $this->scx_string($scx['RECIPEHEADER']['SOURCE']);
		$recipe['category'] = preg_split( '/\|/', $this->scx_string($scx['RECIPEHEADER']['CATEGORY']) );
		$recipe['summary'] = ''; // Not present in ShopNCook files
		$recipe['published'] = ''; // Not present in ShopNCook files
		$recipe['tag'] = ''; // Not present in ShopNCook files
		$recipe['difficulty'] = '0'; // Not present in ShopNCook files	

		/**
		 * Setup content array
		 */
		$recipe['content'] = array();
		
		/**
		 * Create normalized ingredient list array from the ingredient list in XML
		 *
		 * The Ingredient list XML may contain multiple sub-lists of Ingredients separated by IngredientText tokens.
		 * IngredientText tokens have been converted to Ingredient Tokens and marked with the original tag contents
		 * Each section should be treated as a separate list
		 */

		$ingrd_list = $scx['INGREDIENTLIST'];
		$ingrd_norm = array();
		
		// Add list of ingredients to array
		if (is_array($ingrd_list['INGREDIENT'])) {
			// When 'INGREDIENT' contains a single element need to push it down an array level
			if (! array_key_exists(0, $ingrd_list['INGREDIENT'])) {
				$array = array($ingrd_list['INGREDIENT']);
			} else {
				$array = $ingrd_list['INGREDIENT'];
			}
			foreach ($array as $ingrd) {
				// If starting a new sublist, save already accumlated contents as a list and start fresh
				if (array_key_exists('@orig_tag', $ingrd) 
				    && 'INGREDIENTTEXT' == $ingrd['@orig_tag']
				    && count($ingrd_norm) > 0 ) {
						array_push($recipe['content'], array('type' => 'ingrd-list', 'data' => $ingrd_norm));
						$ingrd_norm = array();
				}
				$ingrd_norm = array_merge($ingrd_norm, $this->scx_ingredient($ingrd));
			}
		} else {
			// Single recipe element
			$ingrd_norm = array_merge($ingrd_norm, $this->scx_ingredient($ingrd_list['INGREDIENT']));
		}
		
		// Save any accumulated ingredients to recipe content
		if (count($ingrd_norm) > 0) {
			array_push($recipe['content'], array('type' => 'ingrd-list', 'data' => $ingrd_norm));			
		}
		
		/**
		 * Add the actual recipe text to the content
		 */
		
		// Filter extraneous Nutrition info from text if present, format: "Nutr. Assoc. : 0 0 0 0 0 0 0 20176 0 0"
		$nutr_pattern = '/Nutr. Assoc. :( \d+){1,}/';
		array_push(
			$recipe['content'], 
			array('type' => 'text', 'data' => preg_replace($nutr_pattern, "", $scx['RECIPETEXT']))
		);

		return $recipe;
	}

	/**
	 * Return string value or empty string if the input was not a string
	 *
	 * @access private
	 * @param string Variable to test
	 * @return string
	 */
	private function scx_string($e) {
		return is_string($e) ? $e : '';
	}

	/**
	 * Translate ShopNCook ingredient to normalized form
	 *
	 * Original format:
	 *   <IngredientText>some text</IngredientText>
	 *  or
	 *	 <Ingredient id="313" quantity="1.0" unit="" comment="" defaultState="true" weightGram="50.0" included="true" cooked="false" isAutoId="true" isAutoWeight="true" isAutoUnit="true">
	 *	 <IngredientQuantity>1</IngredientQuantity>
	 *	 <IngredientItem>egg</IngredientItem>
	 *	 <IngredientComment></IngredientComment>
	 *	 </Ingredient>
	 *
	 * @access private
	 * @param array or string $ingrd Translation of XML formated ShopNCook ingredient
	 * @return array
	 **/
	private function scx_ingredient($ingrd) {
		$ingrd_norm = array();
		
		if (is_array($ingrd)) {
			// Treat INGREDIENTTEXT tag as a Recipe list title
			if (array_key_exists('@orig_tag', $ingrd) && 'INGREDIENTTEXT' == $ingrd['@orig_tag']) {
				// In event Ingredient Text is multi-line, explode and process each line
				$text = explode("\n", $ingrd['@value']);
				// Use first line as the title
				array_push($ingrd_norm, array('list-title' => array_shift($text)));
				while ($ingrd = array_shift($text)) {
					// Remaining lines entered as ingredients
					array_push(
						$ingrd_norm, 
						array('ingrd' => $ingrd, 'value' => NULL, 'type' => NULL, 'comment' => NULL)
					);							
				}
			} else {
				if ($ingrd['@attrib']['QUANTITY']) {
					$qty = $ingrd['@attrib']['QUANTITY'];
					
					// Remove trailing .0 if present
					$vals = explode('.', $qty);
					if (isset($vals[1]) && $vals[1] == 0) $qty = $vals[0];
					
					$unit = $ingrd['@attrib']['UNIT'];
				} else {
					$qty = $ingrd['INGREDIENTQUANTITY'];
					$unit = '';
				}
				// Filter '0's for qty out
				if (0 == $qty) $qty = '';
				
				array_push($ingrd_norm, array('value' => $qty, 'type' => $unit,
				                              'ingrd' => $this->scx_string($ingrd['INGREDIENTITEM']),
				                              'comment' => $this->scx_string($ingrd['INGREDIENTCOMMENT'])));
			}
		} else {
			foreach (explode("\n", $ingrd) as $item) {
				array_push($ingrd_norm, array('ingrd' => $item, 'value' => NULL, 'type' => NULL, 'comment' => NULL));
			}
		}

		return $ingrd_norm;
	}	
} // End class import_shopncook
?>