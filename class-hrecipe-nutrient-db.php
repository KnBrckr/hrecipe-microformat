<?php
/**
 * hrecipe_nutrient_db class
 *
 * Class to manipulate the foods database tables
 *
 * Nutritional data from USDA National Nutrient Database for Standard Reference
 * (https://www.ars.usda.gov/Services/docs.htm?docid=8964)
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2015 Kenneth J. Brucker (ken@pumastudios.com)
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

// TODO Remove tables that are not needed

// Protect from direct execution
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die( 'I don\'t think you should be here.' );
}

if ( is_admin() ) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); // Need dbDelta to manipulate DB Tables

	// Load plugin libs that are needed
	$required_libs = array( 'admin/class-hrecipe-usda-sr-txt.php' );
	foreach ( $required_libs as $lib ) {
		if ( ! include_once( $lib ) ) {
			return false;
		}
	}
}

class hrecipe_nutrient_db {
	/**
	 * @var number DB_RELEASE Version of Nutrient Database Standard Reference loaded with plugin
	 * @access public
	 **/
	const DB_RELEASE = 24;

	/**
	 * @var string $options_name Name of options used in WP table
	 * @access protected
	 **/
	protected $options_name;

	/**
	 * Saved options for nutrient DB
	 *  'db_version' => USDA Nutrition DB version loaded in DB
	 *  'table_prefix' => WP WB table prefix for Food DB tables
	 *
	 * @var array $options Saved options for nutrient DB
	 * @access protected
	 **/
	protected $options;

	/**
	 * Class Constructor
	 * - sets up prefix to use for tables in DB
	 * - Load WP options for the class including DB release level installed
	 *
	 * @param string $prefix Prefix to use for name of DB tables
	 *
	 * @return void
	 **/
	function __construct( $prefix ) {
		$this->options_name = get_class( $this ) . "_class_option";

		/*
		 * Retrieve Options for this DB class
		 */
		$options_defaults = array(
			'db_version'   => 0, // Default to 0 - No version loaded
			'table_prefix' => $prefix . "sr_",
		);

		$this->options = (array) wp_parse_args( get_option( $this->options_name ), $options_defaults );
	}

	/**
	 * Checks if DB release is up to date
	 *
	 * @return FALSE if loaded database is current
	 */
	function update_needed() {
		return ! $this->options['db_version'] == self::DB_RELEASE;
	}

	/**
	 * Create the food DB using content from USDA National Nutrient Database for Standard Reference
	 *
	 * Table descriptions are taken from sr<version>_doc.pdf (contained in the documents downloaded from USDA)
	 *
	 * @access private
	 * @return void
	 **/
	private function create_nutrient_schema() {
		global $charset_collate;

		// Prefix for table creation below
		$prefix = $this->options['table_prefix'];

		/**
		 * Food Description Table
		 *
		 * Contains long and short descriptions and food group designators for 7,906 food items, along with common names,
		 * manufacturer name, scientific name, percentage and description of refuse, and factors used for calculating
		 * protein and kilocalories, if applicable. Items used in the FNDDS are also identified by value of “Y” in the Survey field.
		 *
		 * Col Field       Type  Blank  Description
		 * 0   NDB_No      A 5*    N    5-digit Nutrient Databank number that uniquely identifies a food item.
		 *                          If this field is defined as numeric, the leading zero will be lost.
		 * 1   FdGrp_Cd    A 4     N    4-digit code indicating food group to which a food item belongs.
		 * 2   Long_Desc   A 200   N    200-character description of food item.
		 * 3   Shrt_Desc   A 60    N    60-character abbreviated description of food item.
		 *                          Generated from the 200-character description using abbreviations in Appendix A.
		 *                          If short description is longer than 60 characters, additional abbreviations are made.
		 * 4   ComName     A 100   Y    Other names commonly used to describe a food, including local or regional names
		 *                          for various foods, for example, “soda” or “pop” for “carbonated beverages.”
		 * 5   ManufacName A 65    Y    Indicates the company that manufactured the product, when appropriate.
		 * 6   Survey      A 1     Y    Indicates if the food item is used in the USDA Food and Nutrient Database for
		 *                          Dietary Studies (FNDDS) and thus has a complete nutrient profile for the 65 FNDDS nutrients.
		 * 7   Ref_desc    A 135   Y    Description of inedible parts of a food item (refuse), such as seeds or bone.
		 * 8   Refuse      N 2     Y    Percentage of refuse.
		 * 9   SciName     A 65    Y    Scientific name of the food item. Given for the least processed form of the
		 *                          food (usually raw), if applicable.
		 * 10  N_Factor    N 4.2   Y    Factor for converting nitrogen to protein (see p. 10)
		 * 11  Pro_Factor  N 4.2   Y    Factor for calculating calories from protein (see p. 11).
		 * 12  Fat_Factor  N 4.2   Y    Factor for calculating calories from fat (see p. 11).
		 * 13  CHO_Factor  N 4.2   Y    Factor for calculating calories from carbohydrate (see p. 11).
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
		 * Col Field       Type  Blank  Description
		 * 0   FdGrp_Cd    A 4*    N    4-digit code identifying a food group. Only the first 2 digits are currently assigned.
		 *                          In the future, the last 2 digits may be used. Codes may not be consecutive.
		 * 1   FdGrp_Desc  A 60    N    Name of food group.
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
		 * Col Field       Type  Blank  Description
		 * 0   NDB_No      A 5*    N    5-digit Nutrient Databank number that uniquely identifies a food item.
		 *                          If this field is defined as numeric, the leading zero will be lost.
		 * 1   Factor_Code A 5     N    The LanguaL factor from the Thesaurus
		 *
		 * * Marks Primary keys
		 */
		$sql .= "CREATE TABLE " . $prefix . 'langual' . " (
			NDB_No char(5) NOT NULL,
			Factor_Code char(5) NOT NULL,
			KEY NDB_No (NDB_No)
		) $charset_collate;";

		/**
		 * Langual Factor Descriptions Table
		 *
		 * support table to the LanguaL Factor table and contains the descriptions for only those factors used
		 * in coding the selected food items codes in this release of SR.
		 *
		 * Col Field       Type  Blank  Description
		 * 0   Factor_Code A 5*    N    The The LanguaL factor from the Thesaurus. Only those codes used to factor the
		 *                          foods contained in the LanguaL Factor file are included in this file
		 * 1   Description A 140   N    The description of the LanguaL Factor Code from the thesaurus
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
		 * Col Field         Type  Blank  Description
		 * 0   NDB_No        A 5*    N    5-digit Nutrient Databank number that uniquely identifies a food item.
		 *                          If this field is defined as numeric, the leading zero will be lost.
		 * 1   Seq           A 2*    N    Sequence Number
		 * 2   Amount        N 5.3   N    Unit modifier (for example, 1 in “1 cup”).
		 * 3   Msre_Desc     A 80    N    Description (for example, cup, diced, and 1-inch pieces).
		 * 4   Gm_Wgt        N 7.1   N    Gram weight.
		 * 5   Num_Data_Pts  N 3     Y    Number of data points.
		 * 6   Std_Dev       N 7.3   Y    Standard deviation.
		 *
		 * * Marks Primary keys
		 */
		$sql .= "CREATE TABLE " . $prefix . 'weight' . " (
			NDB_No char(5) NOT NULL,
			Seq char(2) NOT NULL,
			Amount decimal(5,3) NOT NULL,
			Msre_Desc varchar(80) NOT NULL,
			Gm_Wgt decimal(7,1) NOT NULL,
			UNIQUE KEY NDB_No_Seq (NDB_No,Seq),
			KEY NDB_No (NDB_No)
		) $charset_collate;";

		/**
		 * Footnote
		 *
		 * Contains additional information about the food item, household weight, and nutrient value.
		 *
		 * Col Field       Type  Blank  Description
		 * 0   NDB_No      A 5     N    5-digit Nutrient Databank number that uniquely identifies a food item.
		 *                          If this field is defined as numeric, the leading zero will be lost.
		 * 1   Footnt_No   A 4     N    Sequence number. If a given footnote applies to more than one nutrient number,
		 *                          the same footnote number is used. As a result, this file cannot be indexed.
		 * 2   Footnt_Typ  A 1     N    Type of Footnote
		 *                          D = footnote adding information to the food description;
		 *                          M = footnote adding information to measure description;
		 *                          N = footnote providing additional information on a nutrient value.
		 *                          If the Footnt_typ = N, the Nutr_No will also be filled in.
		 * 3   Nutr_No     A 3     Y    Unique 3-digit identifier code for a nutrient to which footnote applies.
		 * 4   Footnt_Txt  A 200   N    Footnote text.
		 *
		 * * Marks Primary keys
		 */

		/**
		 * Abbreviated Nutritional Data
		 *
		 * Col Field       Type    Description
		 * 0   NDB_No      A 5*    5-digit Nutrient Databank number that uniquely identifies a food item.
		 * 1   Shrt_Desc   A 60    60-character abbreviated description of food item.†
		 * 2   Water       N 10.2  Water (g/100g)
		 * 3   Energ_Kcal  N 10    Food energy (kcal/100 g)
		 * 4   Protein     N 10.2  Protein (g/100 g)
		 * 5   Lipid_Tot   N 10.2  Total lipid (fat)(g/100 g)
		 * 6   Ash         N 10.2  Ash (g/100 g)
		 * 7   Carbohydrt  N 10.2  Carbohydrate, by difference (g/100 g)
		 * 8   Fiber_TD    N 10.1  Total dietary fiber (g/100 g)
		 * 9   Sugar_Tot   N 10.2  Total sugars (g/100 g)
		 * 10  Calcium     N 10    Calcium (mg/100 g)
		 * 11  Iron        N 10.2  Iron (mg/100 g)
		 * 12  Magnesium   N 10    Magnesium (mg/100 g)
		 * 13  Phosphorus  N 10    Phosphorus (mg/100 g)
		 * 14  Potassium   N 10    Potassium (mg/100 g)
		 * 15  Sodium      N 10    Sodium (mg/100 g)
		 * 16  Zinc        N 10.2  Zinc (mg/100 g)
		 * 17  Copper      N 10.3  Copper (mg/100 g)
		 * 18  Manganese   N 10.3  Manganese (mg/100 g)
		 * 19  Selenium    N 10.1  Selenium (μg/100 g)
		 * 20  Vit_C       N 10.1  Vitamin C (mg/100 g)
		 * 21  Thiamin     N 10.3  Thiamin (mg/100 g)
		 * 22  Riboflavin  N 10.3  Riboflavin (mg/100 g)
		 * 23  Niacin      N 10.3  Niacin (mg/100 g)
		 * 24  Panto_acid  N 10.3  Pantothenic acid (mg/100 g)
		 * 25  Vit_B6      N 10.3  Vitamin B6 (mg/100 g)
		 * 26  Folate_Tot  N 10    Folate, total (μg/100 g)
		 * 27  Folic_acid  N 10    Folic acid (μg/100 g)
		 * 28  Food_Folate N 10    Food folate (μg/100 g)
		 * 29  Folate_DFE  N 10    Folate (μg dietary folate equivalents/100 g)
		 * 30  Choline_Tot N 10    Choline, total (mg/100 g)
		 * 31  Vit_B12     N 10.2  Vitamin B12 (μg/100 g)
		 * 32  Vit_A_IU    N 10    Vitamin A (IU/100 g)
		 * 33  Vit_A_RAE   N 10    Vitamin A (μg retinol activity equivalents/100g)
		 * 34  Retinol     N 10    Retinol (μg/100 g)
		 * 35  Alpha_Carot N 10    Alpha-carotene (μg/100 g)
		 * 36  Beta_Carot  N 10    Beta-carotene (μg/100 g)
		 * 37  Beta_Crypt  N 10    Beta-cryptoxanthin (μg/100 g)
		 * 38  Lycopene    N 10    Lycopene (μg/100 g)
		 * 39  Lut+Zea     N 10    Lutein+zeazanthin (μg/100 g)
		 * 40  Vit_E       N 10.2  Vitamin E (alpha-tocopherol) (mg/100 g)
		 * 41  Vit_D_mcg   N 10.1  Vitamin D (μg/100 g)
		 * 42  Vit_D_IU    N 10    Vitamin D (IU/100 g)
		 * 43  Vit_K       N 10.1  Vitamin K (phylloquinone) (μg/100 g)
		 * 44  FA_Sat      N 10.3  Saturated fatty acid (g/100 g)
		 * 45  FA_Mono     N 10.3  Monounsaturated fatty acids (g/100 g)
		 * 46  FA_Poly     N 10.3  Polyunsaturated fatty acids (g/100 g)
		 * 47  Cholestrl   N 10.3  Cholesterol (mg/100 g)
		 * 48  GmWt_1      N 9.2   First household weight for this item from the Weight file.
		 * 49  GmWt_Desc1  A 120   Description of household weight number 1
		 * 50  GmWt_2      N 9.2   Second household weight for this item from the Weight file.
		 * 51  GmWt_Desc2  A 120   Description of household weight number 2.
		 * 52  Refuse_Pct  N 2     Percent refuse.
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
		dbDelta( $sql );
	}

	/**
	 * Drop USDA SR tables if they exist in DB
	 *
	 * @return void
	 **/
	function drop_nutrient_schema() {
		global $wpdb;

		$tables = array( 'abbrev', 'fd_group', 'food_des', 'langdesc', 'langual', 'weight' );
		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS " . $this->options['table_prefix'] . $table );
		}

		// Delete class options from WP database
		delete_option( $this->options_name );
	}

	/**
	 * Setup USDA Standard Reference into the DB
	 *
	 * @param string $db_path Path to Standard Reference files
	 *
	 * @return number|boolean version of the DB table loaded
	 **/
	function setup_nutrient_db( $db_path ) {
		global $wpdb;

		// If the loaded version matches what's delivered with this version of the plugin, no need to update tables
		if ( $this->options['db_version'] == self::DB_RELEASE ) {
			return self::DB_RELEASE;
		}

		// This can take some time so increase the execution time limit
		set_time_limit( 300 );

		// Drop tables that already exist - New versions will be loaded
		$this->drop_nutrient_schema();

		// Setup DB schema
		$this->create_nutrient_schema();

		/*
			From this point forward, if returning an error then drop the schema
		*/

		/**
		 * Table FOOD_DES
		 */
		$sr = new hrecipe_usda_sr_txt( $db_path . 'FOOD_DES.txt' );

		// Food Groups that aren't needed in the ingredients table:
		//  ~0300~^~Baby Foods~
		//  ~2100~^~Fast Foods~
		//  ~2200~^~Meals, Entrees, and Sidedishes~
		//  ~3500~^~Ethnic Foods~
		//  ~3600~^~Restaurant Foods~
		$skip_food_groups = array( '0300', '2100', '2200', '3500', '3600' );

		$rows_affected = 0;
		while ( $row = $sr->next() ) {
			if ( count( $row ) < 10 ) {
				continue;
			}

			// Skip rows for some food groups
			if ( in_array( $row[1], $skip_food_groups ) ) {
				continue;
			}

			// Insert $row into the table
			$rows_affected += $wpdb->insert( $this->options['table_prefix'] . 'food_des',
				array(
					'NDB_No'      => $row[0],
					'FdGrp_Cd'    => $row[1],
					'Long_Desc'   => $row[2],
					'Shrt_Desc'   => $row[3],
					'ComName'     => $row[4],
					'ManufacName' => $row[5],
					'SciName'     => $row[9]
				) );
		}
		unset( $sr ); // Trigger __destructor() for class

		if ( $rows_affected == 0 ) {
			error_log( "No contents added for FOOD_DES.txt!  Setup Failed." );

			return false;
		}

		/**
		 * Table fd_group
		 */
		$sr = new hrecipe_usda_sr_txt( $db_path . 'FD_GROUP.txt' );

		$rows_affected = 0;
		while ( $row = $sr->next() ) {
			if ( count( $row ) < 2 ) {
				continue;
			}

			// Insert $row into the table
			$rows_affected += $wpdb->insert( $this->options['table_prefix'] . 'fd_group', array(
				'FdGrp_Cd'   => $row[0],
				'FdGrp_Desc' => $row[1]
			) );
		}
		unset( $sr ); // Trigger __destructor() for class

		if ( $rows_affected == 0 ) {
			error_log( "No contents added for FD_GROUP.txt!  Setup Failed." );

			return false;
		}

		/**
		 *  Table langual
		 */
		$sr = new hrecipe_usda_sr_txt( $db_path . 'LANGUAL.txt' );

		$rows_affected = 0;
		while ( $row = $sr->next() ) {
			if ( count( $row ) < 2 ) {
				continue;
			}

			// Skip any where NDB_No is not in food_des table (added above)
			if ( ! $this->ndb_no_defined( $row[0] ) ) {
				continue;
			}

			// Insert $row into the table
			$rows_affected += $wpdb->insert( $this->options['table_prefix'] . 'langual', array(
				'NDB_No'      => $row[0],
				'Factor_Code' => $row[1]
			) );
		}
		unset( $sr ); // Trigger __destructor() for class

		if ( $rows_affected == 0 ) {
			error_log( "No contents added for LANGUAL.txt!  Setup Failed." );

			return false;
		}

		/**
		 *  Table langdesc
		 */
		$sr = new hrecipe_usda_sr_txt( $db_path . 'LANGDESC.txt' );

		$rows_affected = 0;
		while ( $row = $sr->next() ) {
			if ( count( $row ) < 2 ) {
				continue;
			}

			// Insert $row into the table
			$rows_affected += $wpdb->insert( $this->options['table_prefix'] . 'langdesc', array(
				'Factor_Code' => $row[0],
				'Description' => $row[1]
			) );
		}
		unset( $sr ); // Trigger __destructor() for class

		if ( $rows_affected == 0 ) {
			error_log( "No contents added for LANGDESC.txt!  Setup Failed." );

			return false;
		}

		/**
		 *  Table weight
		 */
		$sr = new hrecipe_usda_sr_txt( $db_path . 'WEIGHT.txt' );

		$rows_affected = 0;
		while ( $row = $sr->next() ) {
			if ( count( $row ) < 5 ) {
				continue;
			}
			// Skip any where NDB_No is not in food_des table (added above)
			if ( ! $this->ndb_no_defined( $row[0] ) ) {
				continue;
			}

			// Insert $row into the table
			$rows_affected += $wpdb->insert( $this->options['table_prefix'] . 'weight',
				array(
					'NDB_No'    => $row[0],
					'Seq'       => $row[1],
					'Amount'    => $row[2],
					'Msre_Desc' => $row[3],
					'Gm_Wgt'    => $row[4]
				) );
		}
		unset( $sr ); // Trigger __destructor() for class

		if ( $rows_affected == 0 ) {
			error_log( "No contents added for WEIGHT.txt!  Setup Failed." );

			return false;
		}

		/**
		 *  Table abbrev
		 */
		$sr = new hrecipe_usda_sr_txt( $db_path . 'ABBREV.txt' );

		$rows_affected = 0;
		while ( $row = $sr->next() ) {
			if ( count( $row ) < 48 ) {
				continue;
			}

			// Skip any where NDB_No is not in food_des table (added above)
			if ( ! $this->ndb_no_defined( $row[0] ) ) {
				continue;
			}

			// Insert $row into the table
			$rows_affected += $wpdb->insert( $this->options['table_prefix'] . 'abbrev',
				array(
					'NDB_No'      => $row[0],
					'Water'       => $row[2],
					'Energ_Kcal'  => $row[3],
					'Protein'     => $row[4],
					'Lipid_Tot'   => $row[5],
					'Carbohydrt'  => $row[7],
					'Fiber_TD'    => $row[8],
					'Sugar_Tot'   => $row[9],
					'Calcium'     => $row[10],
					'Iron'        => $row[11],
					'Magnesium'   => $row[12],
					'Phosphorus'  => $row[13],
					'Potassium'   => $row[14],
					'Sodium'      => $row[15],
					'Zinc'        => $row[16],
					'Copper'      => $row[17],
					'Manganese'   => $row[18],
					'Selenium'    => $row[19],
					'Vit_C'       => $row[20],
					'Thiamin'     => $row[21],
					'Riboflavin'  => $row[22],
					'Niacin'      => $row[23],
					'Panto_acid'  => $row[24],
					'Vit_B6'      => $row[25],
					'Folate_Tot'  => $row[26],
					'Folic_acid'  => $row[27],
					'Food_Folate' => $row[28],
					'Folate_DFE'  => $row[29],
					'Choline_Tot' => $row[30],
					'Vit_B12'     => $row[31],
					'Vit_A_IU'    => $row[32],
					'Vit_A_RAE'   => $row[33],
					'Retinol'     => $row[34],
					'Alpha_Carot' => $row[35],
					'Beta_Carot'  => $row[36],
					'Beta_Crypt'  => $row[37],
					'Lycopene'    => $row[38],
					'LutZea'      => $row[39],
					'Vit_E'       => $row[40],
					'Vit_D_mcg'   => $row[41],
					'Vit_D_IU'    => $row[42],
					'Vit_K'       => $row[43],
					'FA_Sat'      => $row[44],
					'FA_Mono'     => $row[45],
					'FA_Poly'     => $row[46],
					'Cholestrl'   => $row[47]
				)
			);
		}
		unset( $sr ); // Trigger __destructor() for class

		if ( $rows_affected == 0 ) {
			error_log( "No contents added for ABBREV.txt!  Setup Failed." );

			return false;
		}

		/**
		 *  Record Version number in options
		 */
		$this->options['db_version'] = self::DB_RELEASE;
		update_option( $this->options_name, $this->options );

		// Return DB version loaded
		return self::DB_RELEASE;
	}

	/**
	 * Perform lookup in food_des table for given NDB_No
	 *
	 * @param $ndb_no string
	 *
	 * @return integer count of matching records (0 or 1)
	 * @access private
	 **/
	private function ndb_no_defined( $ndb_no ) {
		global $wpdb;

		$db_name = $this->options['table_prefix'] . 'food_des';

		return $wpdb->get_var( "SELECT COUNT(*) FROM ${db_name} WHERE NDB_No LIKE '${ndb_no}'" );
	}

	/**
	 * Retrieve matching food names, split input names on spaces to search for presence of each anywhere
	 *
	 * @uses $wpdb
	 *
	 * @param $name_contains string - String to match for food name
	 * @param $per_page int - maximum number of rows to return
	 * @param $paged , int - page number for group of rows to return
	 *
	 * @return array: ['paged']=this page number, ['pages']=total # pages, ['rows'] = DB rows of names retrieved
	 **/
	function get_name( $name_contains, $per_page, $paged ) {
		global $wpdb;

		$db_name = $this->options['table_prefix'] . 'food_des';
		$terms   = array();
		foreach ( explode( ' ', $name_contains ) as $word ) {
			$word = trim( $word );
			if ( ! empty( $word ) ) {
				// TODO Allow use of '-' prefix to add NOT LIKE to search
				$terms[] = $wpdb->prepare( "Long_Desc LIKE %s", '%' . $word . '%' );
			}
		}
		$query = "SELECT NDB_No,Long_Desc FROM ${db_name} WHERE " . implode( ' AND ', $terms );

		/**
		 * Pagination of table elements
		 */
		$per_page  = max( 5, (int) $per_page );  // Do at least 5 per page
		$totalrows = $wpdb->query( $query ); // Number of total rows matching name
		$pages     = max( 1, ceil( $totalrows / $per_page ) );  // make sure pages is at least 1

		// 1 <= paged <= pages
		if ( $paged < 1 ) {
			$paged = 1;
		} elseif ( $paged > $pages ) {
			$paged = $pages;
		}

		// Adjust the query to take pagination into account
		$offset = ( $paged - 1 ) * $per_page;
		$query  .= ' LIMIT ' . (int) $offset . ',' . (int) $per_page;

		/**
		 * Get results
		 */
		$rows = $wpdb->get_results( $query );

		return array(
			'page'      => $paged,
			'pages'     => $pages,
			'totalrows' => $totalrows,
			'rows'      => $rows
		);
	}

	/**
	 * Retrieve food name for given NDB_No
	 *
	 * @uses wpdb
	 *
	 * @param $NDB_No string NDB Database Index
	 *
	 * @return string
	 **/
	function get_name_by_NDB_No( $NDB_No ) {
		global $wpdb;

		$db_name = $this->options['table_prefix'] . 'food_des';

		return $wpdb->get_var( "SELECT Long_Desc FROM ${db_name} WHERE `NDB_No` = '${NDB_No}'" );
	}

	/**
	 * Retrieve measures information for given NDB_No
	 *
	 * @return array of rows
	 **/
	function get_measures_by_NDB_No( $NDB_No ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT t1.`NDB_No`, t1.`Long_Desc`, t2.`Amount`, t2.`Msre_Desc`, t2.`Gm_Wgt`, t2.`Seq` FROM `ps_hrecipe_sr_food_des` as t1 natural join `ps_hrecipe_sr_weight` AS t2 WHERE `NDB_No` = %s", $NDB_No );
		$rows  = $wpdb->get_results( $query );

		return $rows;
	}
}