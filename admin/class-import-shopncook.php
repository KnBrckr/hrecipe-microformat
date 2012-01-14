<?php
/**
 * import_shopncook Class
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
		// Setup parser that maps INGREDIENTTEXT to INGREDIENT so all elements in INGREDIENTLIST remain in order
		$this->xml_parser = new hrecipe_parse_xml();
		$this->xml_parser->set_tags(array('INGREDIENTTEXT' => 'INGREDIENT'));
	}
	
	/**
	 * import a ShopNCook SCX format file
	 *
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
		if ('1.0' != $recipe_import['SHOPNCOOK']['_']['VERSION']) {
			$this->error_msg = 'Unknown ShopNCook XML version ' . $recipe_import['SHOPNCOOK']['_']['VERSION'];
			return false;
		}

		/*
		 * Imported list may contain one or more recipes, array structure will differ as a result
		 */
		if (isset($recipe_import['SHOPNCOOK']['RECIPELIST']['RECIPE']['_'])) {
			// Handle single recipe case:
			$recipes[0] = $this->parse_recipe($recipe_import['SHOPNCOOK']['RECIPELIST']['RECIPE']);
		} else {
			$recipes = array();
			foreach ($recipe_import['SHOPNCOOK']['RECIPELIST']['RECIPE'] as $scx_recipe) {
				array_push($recipes, $this->parse_recipe($scx_recipe));
			}
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
		// Yield is consistently provided by NBPERSONS vs. PORTIONYIELD
		$recipe['yield'] = $this->scx_string($scx['RECIPEHEADER']['NBPERSONS']);
		$recipe['duration'] = $this->scx_string($scx['RECIPEHEADER']['TOTALTIME']);
		$recipe['preptime'] = $this->scx_string($scx['RECIPEHEADER']['PREPTIME']);
		$recipe['cooktime'] = ''; // Not present in ShopNCook files
		$recipe['author'] = $this->scx_string($scx['RECIPEHEADER']['SOURCE']);
		$recipe['category'] = $this->scx_string($scx['RECIPEHEADER']['CATEGORY']);
		$recipe['summary'] = ''; // Not present in ShopNCook files
		$recipe['published'] = ''; // Not present in ShopNCook files
		$recipe['tag'] = ''; // Not present in ShopNCook files
		$recipe['difficulty'] = '0'; // Not present in ShopNCook files	

		/**
		 * Setup content array
		 */
		$recipe['content'] = array();
		array_push($recipe['content'], array('type' => 'ingrd-list', 'data' => $this->scx_ingrd_list($scx['INGREDIENTLIST'])));
		array_push($recipe['content'], array('type' => 'text', 'data' => $scx['RECIPETEXT']));

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
	 * Create normalized recipe array from the ingredient list
	 *
	 * @access private
	 * @param string $ingrd_list list of ingredients from ShopNCook recipe
	 * @return string HTML text for recipe
	 */
	private function scx_ingrd_list($ingrd_list) {
		$ingrd_norm = array();

		// Add list of ingredients to array
		if (is_array($ingrd_list['INGREDIENT'])) {
			foreach ($ingrd_list['INGREDIENT'] as $ingrd) {
				$ingrd_norm = array_merge($ingrd_norm, $this->scx_ingredient($ingrd));
			}
		} else {
			$ingrd_norm = array_merge($ingrd_norm, $this->scx_ingredient($ingrd_list['INGREDIENT']));
		}

		return $ingrd_norm; 
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
			if ($ingrd['_']['QUANTITY']) {
				$qty = rtrim($ingrd['_']['QUANTITY'], '.0'); // Remove trailing 0 and decimal point
				$unit = $ingrd['_']['UNIT'];
			} else {
				$qty = $ingrd['INGREDIENTQUANTITY'];
				$unit = '';
			}
			array_push($ingrd_norm, array('value' => $qty, 'type' => $unit,
			                              'ingrd' => is_string($ingrd['INGREDIENTITEM']) ? $ingrd['INGREDIENTITEM'] : '',
			                              'comment' => is_string($ingrd['INGREDIENTCOMMENT']) ?  $ingrd['INGREDIENTCOMMENT'] : ''));
		} else {
			foreach (explode("\n", $ingrd) as $item) {
				array_push($ingrd_norm, array('ingrd' => $item));
			}
		}

		return $ingrd_norm;
	}	
} // End class import_shopncook
?>