<?php
/**
 * Class to manipulate the foods database tables
 *
 * Nutritional data from USDA National Nutrient Database for Standard Reference
 * (https://www.ars.usda.gov/Services/docs.htm?docid=8964)
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

if (is_admin()) {
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); // Need dbDelta to manipulate DB Tables
}

class hrecipe_food_db {
	/**
	 * Wordpress DB table prefix for Food DB tables
	 *
	 * @var string
	 **/
	protected $table_prefix;
	
	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
	 **/
	function __construct()
	{
		global $table_prefix;

		$this->table_prefix = $table_prefix . 'hrecipe_';		
	}
	
	/**
	 * Create the food DB using content from USDA National Nutrient Database for Standard Reference
	 *
	 * Table descriptions are taken from sr24_doc.pdf
	 *
	 * @return void
	 **/
	function create_food_schema()
	{
		global $charset_collate;

		$prefix = $this->table_prefix;
				
		/**
		 * Food Description Table
		 *
		 * Contains long and short descriptions and food group designators for 7,906 food items, along with common names, 
		 * manufacturer name, scientific name, percentage and description of refuse, and factors used for calculating 
		 * protein and kilocalories, if applicable. Items used in the FNDDS are also identified by value of “Y” in the Survey field.
		 *
		 * Field       Type  Blank  Description
		 * NDB_No      A 5*    N    5-digit Nutrient Databank number that uniquely identifies a food item. 
		 *                          If this field is defined as numeric, the leading zero will be lost.
		 * FdGrp_Cd    A 4     N    4-digit code indicating food group to which a food item belongs.
		 * Long_Desc   A 200   N    200-character description of food item.
		 * Shrt_Desc   A 60    N    60-character abbreviated description of food item. 
		 *                          Generated from the 200-character description using abbreviations in Appendix A.
		 *                          If short description is longer than 60 characters, additional abbreviations are made.
		 * ComName     A 100   Y    Other names commonly used to describe a food, including local or regional names 
		 *                          for various foods, for example, “soda” or “pop” for “carbonated beverages.”
		 * ManufacName A 65    Y    Indicates the company that manufactured the product, when appropriate.
		 * Survey      A 1     Y    Indicates if the food item is used in the USDA Food and Nutrient Database for 
		 *                          Dietary Studies (FNDDS) and thus has a complete nutrient profile for the 65 FNDDS nutrients.
		 * Ref_desc    A 135   Y    Description of inedible parts of a food item (refuse), such as seeds or bone.
		 * Refuse      N 2     Y    Percentage of refuse.
		 * SciName     A 65    Y    Scientific name of the food item. Given for the least processed form of the 
		 *                          food (usually raw), if applicable.
		 * N_Factor    N 4.2   Y    Factor for converting nitrogen to protein (see p. 10)
		 * Pro_Factor  N 4.2   Y    Factor for calculating calories from protein (see p. 11).
		 * Fat_Factor  N 4.2   Y    Factor for calculating calories from fat (see p. 11).
		 * CHO_Factor  N 4.2   Y    Factor for calculating calories from carbohydrate (see p. 11).
		 *
		 * * Marks Primary keys
		 *
		 * Some fields from Standard Reference are not imported
		 */
		$sql = "CREATE TABLE " . $prefix . 'food_des' . " (
			NDB_No char(5) NOT NULL,
			FdGrp_Cd char(4) NOT NULL,
			Long_Desc varchar(200) NOT NULL,
			Shrt_Desc varchar(60) NOT NULL,
			ComName varchar(100) DEFAULT '' NOT NULL,
			ManufacName varchar(65) DEFAULT '' NOT NULL,
			SciName varchar(65) DEFAULT '' NOT NULL,
			PRIMARY KEY  (NDB_No)
		) $charset_collate;";	
		
		/**
		 * Food Group Description Table
		 *
		 * support table to the Food Description table and contains a list of food groups used in SR24 and their descriptions.
		 *
		 * Field       Type  Blank  Description
		 * FdGrp_Cd    A 4*    N    4-digit code identifying a food group. Only the first 2 digits are currently assigned. 
		 *                          In the future, the last 2 digits may be used. Codes may not be consecutive.
		 * FdGrp_Desc  A 60    N    Name of food group.
		 *
		 * * Marks Primary keys
		 */
		$sql .= "CREATE TABLE " . $prefix . 'fd_group' . " (
			FdGrp_Cd char(4) NOT NULL,
			FdGrp_Desc varchar(60) NOT NULL,
			PRIMARY KEY  (FdGrp_Cd)
		) $charset_collate;";	
		
		/**
		 * Langual Factor Table
		 *
		 * support table to the Food Description table and contains the factors from the LanguaL 
		 * Thesaurus used to code a particular food.
		 *
		 * Field       Type  Blank  Description
		 * NDB_No      A 5*    N    5-digit Nutrient Databank number that uniquely identifies a food item. 
		 *                          If this field is defined as numeric, the leading zero will be lost.
		 * Factor_Code A 5     N    The LanguaL factor from the Thesaurus
		 *
		 * * Marks Primary keys		
		 */
		$sql .= "CREATE TABLE " . $prefix . 'langual' . " (
			NDB_No char(5) NOT NULL,
			Factor_Code char(5) NOT NULL,
			PRIMARY KEY  (NDB_No)
		) $charset_collate;";
		
		/**
		 * Langual Factor Descriptions Table
		 * 
		 * support table to the LanguaL Factor table and contains the descriptions for only those factors used 
		 * in coding the selected food items codes in this release of SR.
		 *
		 * Field       Type  Blank  Description
		 * Factor_Code A 5*    N    The The LanguaL factor from the Thesaurus. Only those codes used to factor the 
		 *                          foods contained in the LanguaL Factor file are included in this file
		 * Description A 140   N    The description of the LanguaL Factor Code from the thesaurus
		 *
		 * * Marks Primary keys		
		 */
		$sql .= "CREATE TABLE " . $prefix . 'langdesc' . " (
			Factor_Code char(5) NOT NULL,
			Description varchar(140) NOT NULL,
			PRIMARY KEY  (Factor_Code)
		) $charset_collate;";	
		
		/**
		 * Weight Table
		 *
		 * Contains the weight in grams of a number of common measures for each food item.
		 *
		 * Field         Type  Blank  Description
		 * NDB_No        A 5*    N    5-digit Nutrient Databank number that uniquely identifies a food item. 
		 *                          If this field is defined as numeric, the leading zero will be lost.
		 * Seq           A 2*    N    Sequence Number
		 * Amount        N 5.3   N    Unit modifier (for example, 1 in “1 cup”).
		 * Msre_Desc     A 80    N    Description (for example, cup, diced, and 1-inch pieces).
		 * Gm_Wgt        N 7.1   N    Gram weight.
		 * Num_Data_Pts  N 3     Y    Number of data points.
		 * Std_Dev       N 7.3   Y    Standard deviation.
		 *
		 * * Marks Primary keys		
		 */
		$sql .= "CREATE TABLE " . $prefix . 'weight' . " (
			NDB_No char(5) NOT NULL,
			Seq char(2) NOT NULL,
			Amount decimal(5,3) NOT NULL,
			Msre_Desc varchar(80) NOT NULL,
			Gm_Wgt decimal(7,1) NOT NULL,
			UNIQUE KEY NDB_No_Seq (NDB_No,Seq)
		) $charset_collate;";	

		/**
		 * Footnote
		 *
		 * Contains additional information about the food item, household weight, and nutrient value.
		 *
		 * Field       Type  Blank  Description
		 * NDB_No      A 5     N    5-digit Nutrient Databank number that uniquely identifies a food item. 
		 *                          If this field is defined as numeric, the leading zero will be lost.
		 * Footnt_No   A 4     N    Sequence number. If a given footnote applies to more than one nutrient number, 
		 *                          the same footnote number is used. As a result, this file cannot be indexed.
		 * Footnt_Typ  A 1     N    Type of Footnote
		 *                          D = footnote adding information to the food description;
		 *                          M = footnote adding information to measure description;
		 *                          N = footnote providing additional information on a nutrient value. 
		 *                          If the Footnt_typ = N, the Nutr_No will also be filled in.
		 * Nutr_No     A 3     Y    Unique 3-digit identifier code for a nutrient to which footnote applies.
		 * Footnt_Txt  A 200   N    Footnote text.
		 *
		 * * Marks Primary keys		
		 */

		/**
		 * Abbreviated Nutritional Data
		 *
		 * Field       Type    Description
		 * NDB_No      A 5*    5-digit Nutrient Databank number that uniquely identifies a food item. 
		 * Shrt_Desc   A 60    60-character abbreviated description of food item.†
		 * Water       N 10.2  Water (g/100g)
		 * Energ_Kcal  N 10    Food energy (kcal/100 g)
		 * Protein     N 10.2  Protein (g/100 g)
		 * Lipid_Tot   N 10.2  Total lipid (fat)(g/100 g)
		 * Ash         N 10.2  Ash (g/100 g)
		 * Carbohydrt  N 10.2  Carbohydrate, by difference (g/100 g)
		 * Fiber_TD    N 10.1  Total dietary fiber (g/100 g)
		 * Sugar_Tot   N 10.2  Total sugars (g/100 g)
		 * Calcium     N 10    Calcium (mg/100 g)
		 * Iron        N 10.2  Iron (mg/100 g)
		 * Magnesium   N 10    Magnesium (mg/100 g)
		 * Phosphorus  N 10    Phosphorus (mg/100 g)
		 * Potassium   N 10    Potassium (mg/100 g)
		 * Sodium      N 10    Sodium (mg/100 g)
		 * Zinc        N 10.2  Zinc (mg/100 g)
		 * Copper      N 10.3  Copper (mg/100 g)
		 * Manganese   N 10.3  Manganese (mg/100 g)
		 * Selenium    N 10.1  Selenium (μg/100 g)
		 * Vit_C       N 10.1  Vitamin C (mg/100 g)
		 * Thiamin     N 10.3  Thiamin (mg/100 g)
		 * Riboflavin  N 10.3  Riboflavin (mg/100 g)
		 * Niacin      N 10.3  Niacin (mg/100 g)
		 * Panto_acid  N 10.3  Pantothenic acid (mg/100 g)
		 * Vit_B6      N 10.3  Vitamin B6 (mg/100 g)
		 * Folate_Tot  N 10    Folate, total (μg/100 g)
		 * Folic_acid  N 10    Folic acid (μg/100 g)
		 * Food_Folate N 10    Food folate (μg/100 g)
		 * Folate_DFE  N 10    Folate (μg dietary folate equivalents/100 g)
		 * Choline_Tot N 10    Choline, total (mg/100 g)
		 * Vit_B12     N 10.2  Vitamin B12 (μg/100 g)
		 * Vit_A_IU    N 10    Vitamin A (IU/100 g)
		 * Vit_A_RAE   N 10    Vitamin A (μg retinol activity equivalents/100g)
		 * Retinol     N 10    Retinol (μg/100 g)
		 * Alpha_Carot N 10    Alpha-carotene (μg/100 g)
		 * Beta_Carot  N 10    Beta-carotene (μg/100 g)
		 * Beta_Crypt  N 10    Beta-cryptoxanthin (μg/100 g)
		 * Lycopene    N 10    Lycopene (μg/100 g)
		 * Lut+Zea     N 10    Lutein+zeazanthin (μg/100 g)
		 * Vit_E       N 10.2  Vitamin E (alpha-tocopherol) (mg/100 g)
		 * Vit_D_mcg   N 10.1  Vitamin D (μg/100 g)
		 * Vit_D_IU    N 10    Vitamin D (IU/100 g)
		 * Vit_K       N 10.1  Vitamin K (phylloquinone) (μg/100 g)
		 * FA_Sat      N 10.3  Saturated fatty acid (g/100 g)
		 * FA_Mono     N 10.3  Monounsaturated fatty acids (g/100 g)
		 * FA_Poly     N 10.3  Polyunsaturated fatty acids (g/100 g)
		 * Cholestrl   N 10.3  Cholesterol (mg/100 g)
		 * GmWt_1      N 9.2   First household weight for this item from the Weight file.
		 * GmWt_Desc1  A 120   Description of household weight number 1
		 * GmWt_2      N 9.2   Second household weight for this item from the Weight file.
		 * GmWt_Desc2  A 120   Description of household weight number 2.
		 * Refuse_Pct  N 2     Percent refuse.
		 *
		 * * Marks Primary keys		
		 */
		$sql .= "CREATE TABLE " . $prefix . 'abbrev' . " (
			NDB_No char(5) NOT NULL,
			Water decimal(10,2) NOT NULL,
			Energ_Kcal decimal(10,0) NOT NULL,
		  Protein decimal(10,0) NOT NULL,
		  Lipid_Tot decimal(10,2) NOT NULL,
		  Carbohydrt decimal(10,2) NOT NULL,
		  Fiber_TD decimal(10,1) NOT NULL,
		  Sugar_Tot decimal(10,2) NOT NULL,
		  Calcium decimal(10,2) NOT NULL,
		  Iron decimal(10,2) NOT NULL,
		  Magnesium decimal(10,0) NOT NULL,
		  Phosphorus decimal(10,0) NOT NULL,
		  Potassium decimal(10,0) NOT NULL,
		  Sodium decimal(10,0) NOT NULL,
		  Zinc decimal(10,2) NOT NULL,
		  Copper decimal(10,3) NOT NULL,
		  Manganese decimal(10,3) NOT NULL,
		  Selenium decimal(10,1) NOT NULL,
		  Vit_C decimal(10,1) NOT NULL,
		  Thiamin decimal (10,3) NOT NULL,
		  Riboflavin decimal(10,3) NOT NULL,
		  Niacin decimal(10,3) NOT NULL,
		  Panto_acid decimal(10,3) NOT NULL,
		  Vit_B6 decimal(10,3) NOT NULL,
		  Folate_Tot decimal(10,3) NOT NULL,
		  Folic_acid decimal(10,0) NOT NULL,
		  Food_Folate decimal(10,0) NOT NULL,
		  Folate_DFE decimal(10,0) NOT NULL,
		  Choline_Tot decimal(10,0) NOT NULL,
		  Vit_B12 decimal(10,2) NOT NULL,
		  Vit_A_IU decimal(10,0) NOT NULL,
		  Vit_A_RAE decimal(10,0) NOT NULL,
		  Retinol decimal(10,0) NOT NULL,
		  Alpha_Carot decimal(10,0) NOT NULL,
		  Beta_Carot decimal(10,0) NOT NULL,
		  Beta_Crypt decimal(10,0) NOT NULL,
		  Lycopene decimal(10,0) NOT NULL,
		  LutZea decimal(10,0) NOT NULL,
		  Vit_E decimal(10,2) NOT NULL,
		  Vit_D_mcg decimal(10,1) NOT NULL,
		  Vit_D_IU decimal(10,0) NOT NULL,
		  Vit_K decimal(10,1) NOT NULL,
		  FA_Sat decimal(10,3) NOT NULL,
		  FA_Mono decimal(10,3) NOT NULL,
		  FA_Poly decimal(10,3) NOT NULL,
		  Cholestrl decimal(10,3) NOT NULL,		 
			PRIMARY KEY  (NDB_No)
		) $charset_collate;";
		
		// Create the required databases
    dbDelta($sql);
	}
	
	/**
	 * Load USDA Standard Reference into the DB
	 *
	 * @return void
	 **/
	function load_sr()
	{
		// FIXME Load the tables
	}
} // End class hrecipe_food_db
?>