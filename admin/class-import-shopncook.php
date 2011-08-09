<?php
/**
 * import_shopncook Class
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

if (!include_once('class-parse-xml.php')) {
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
		$this->xml_parser = new parse_xml();
		$this->xml_parser->set_tags(array('INGREDIENTTEXT' => 'INGREDIENT'));
	}
	
	/**
	 * import a ShopNCook SCX format file
	 *
	 * @return void
	 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
	 **/
	function import_scx($fname)
	{
		// import the XML data
		if (! ($recipe_import = $this->xml_parser->parse($fname))) {
			// If unable to parse the XML data file ...
			$this->error_msg = 'XML Parse Error returned: ' . $this->xml_parser->error_msg;
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
		$recipe['yield'] = $this->scx_string($scx['RECIPEHEADER']['NBPERSONS']); // NBPERSONS vs. PORTIONYIELD?
		$recipe['duration'] = $this->scx_string($scx['RECIPEHEADER']['TOTALTIME']);
		$recipe['preptime'] = $this->scx_string($scx['RECIPEHEADER']['PREPTIME']);
		$recipe['cooktime'] = ''; // Not present in ShopNCook files
		$recipe['author'] = $this->scx_string($scx['RECIPEHEADER']['SOURCE']);
		$recipe['category'] = $this->scx_string($scx['RECIPEHEADER']['CATEGORY']);
		$recipe['instructions'] = $this->scx_instructions($scx['INGREDIENTLIST'], $scx['RECIPETEXT']); // FIXME
		//$recipe['photo']?
		$recipe['summary'] = ''; // Not present in ShopNCook files
		$recipe['published'] = ''; // Not present in ShopNCook files
		$recipe['tag'] = ''; // Not present in ShopNCook files
		$recipe['difficulty'] = '0'; // Not present in ShopNCook files	

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
	 * Create recipe instruction text from the ingredient list and recipe text
	 *
	 * @access private
	 * @param string $ingrd_list list of ingredients from ShopNCook recipe
	 * @param string $instructions Recipe text from ShopNCook recipe
	 * @return string HTML text for recipe
	 */
	private function scx_instructions($ingrd_list, $instructions) {
		$text = '<table class="ingredients">';
		if (is_array($ingrd_list['INGREDIENT'])) {
			foreach ($ingrd_list['INGREDIENT'] as $ingrd) {
				$text .= $this->scx_ingredient($ingrd);
			}
		} else {
			$text .= $this->scx_ingredient($ingrd_list['INGREDIENT']);
		}
		$text .= '</table>';

		return $text . "\n\n" . $instructions;
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
	 * @return string
	 **/
	private function scx_ingredient($ingrd) {
		if (is_array($ingrd)) {
			if ($ingrd['_']['QUANTITY']) {
				$qty = rtrim($ingrd['_']['QUANTITY'], '.0'); // Remove trailing 0 and decimal point
				$unit = $ingrd['_']['UNIT'];
			} else {
				$qty = $ingrd['INGREDIENTQUANTITY'];
				$unit = '';
			}
			$qty = $qty ? '<span class="value">' . $qty . '</span>' : '';
			$unit = $unit ? '<span class="type">' . $unit . '</span>' : '';
			$item = ($tmp = $this->scx_string($ingrd['INGREDIENTITEM'])) ? '<span class="ingrd">' . $tmp . '</span>' : ''; 
			$comment = ($tmp = $this->scx_string($ingrd['INGREDIENTCOMMENT'])) ? '<span class="comment">' . $tmp . '</span>' : '';
			$text = '<tr class="ingredient"><td>' . $qty . $unit . '</td><td>' . $item . $comment . '</td></tr>';
		} else {
			$text = '';
			foreach (explode("\n", $ingrd) as $item) {
				$text .= '<tr class="ingredient"><td></td><td><span class="ingrd">' . $item . '</span></td></tr>';
			}
		}

		return $text;
	}	
} // End class import_shopncook
?>