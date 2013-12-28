<?php
/**
 * hrecipe microformat ingredient db class
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2013 Kenneth J. Brucker (email: ken@pumastudios.com)
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

// FIXME On post (auto)save, new ingredient list needs to be saved to go with that post revision
// FIXME Handle DB errors, including any cleanup needed on failed create

if (! class_exists('hrecipe_ingrd_db')) :
class hrecipe_ingrd_db {
	
	/**
	 * Table of defined foods for use in recipes
	 *
	 * @var string
	 * @access private
	 **/
	private $foods_table;
	
	/**
	 * Table of ingredients used in a recipe
	 *
	 * @var string
	 * @access private
	 **/
	private $recipe_ingrds_table;
	
	/**
	 * Construct new object
	 *
	 * @param $prefix string Prefix to use for database table
	 * @return void
	 **/
	function __construct($prefix) {
		$this->foods_table = $prefix . 'foods';
		$this->recipe_ingrds_table = $prefix . 'recipe_ingrds';
	}
	
	/**
	 * Create the database tables to hold ingredients
	 *
	 * Foods Table
	 * Col Field       Type  Blank  Description
	 * 0   food_id     N 20*  N     Unique key - Food ID number
	 * 1   NDB_No      A 5    Y     Associated food description from government food database
	 * 2   ingrd       A 200  N     Ingredient Name  -- Normalize names?
	 * 3   measure     Enum   N     'volume', 'weight'
	 * 4   gpcup       N 7.1  N     Grams/Cup
	 * * Marks Primary keys
	 * 
	 * Recipe Ingredients Table
	 * Col Field       Type  Blank  Description
	 * 0   id          N 20*  N     Unique key
	 * 1   post_id     N 20   N     Indexed: Associated post_id for ingredient
	 * 2   ingrd_list_id N 10 N     Recipe can have multiple lists; List ID within a post
	 * 3   list_order  N 10   N     sort order within ingrd_list_id list
	 * 4   food_id     N 20   Y     Associated food from foods table
	 * 5   quantity    A 100  Y     Amount of ingredient to use
	 * 6   unit        A 100  Y     Unit of measurement for quantity
	 * 7   ingrd       A 200  Y     Ingredient Name  -- Normalize names?
	 * 8   comment     longtext  Y     comments on use of ingredient in recipe
	 * * Marks Primary keys
	 *
	 * @return void
	 **/
	function create_schema() {
		global $charset_collate;
		global $wpdb;
		
		/**
		 * Create Food Table
		 */

		$sql = "CREATE TABLE IF NOT EXISTS " . $this->foods_table . " (
			food_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			NDB_No CHAR(5) DEFAULT '',
			ingrd VARCHAR(200) NOT NULL,
			measure ENUM('volume', 'weight') NOT NULL,
			gpcup DECIMAL(7,1) NOT NULL,
			PRIMARY KEY (food_id)
		) $charset_collate;";	

		// Run SQL to create the table
		// FIXME Handle error here
		dbDelta($sql);
		
		/**
		 * Create Recipe Ingredients Table
		 */
		
		$sql = "CREATE TABLE IF NOT EXISTS " . $this->recipe_ingrds_table . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			ingrd_list_id INT(10) UNSIGNED NOT NULL,
			list_order INT(10) NOT NULL,
			food_id BIGINT(20) DEFAULT NULL,
			quantity VARCHAR(100) DEFAULT '',
			unit VARCHAR(100) DEFAULT '',
			ingrd VARCHAR(200) DEFAULT '',
			comment LONGTEXT DEFAULT '',
			PRIMARY KEY (id),
			INDEX (post_id)
		) $charset_collate;";	

		// Run SQL to create the table
		// FIXME Handle error
		return dbDelta($sql);
	}
	
	/**
	 * Delete database table for ingredient lists (used for uninstall)
	 *
	 * @return void
	 **/
	function drop_schema()
	{
		global $wpdb;
		
		$wpdb->query("DROP TABLE IF EXISTS " . $this->foods_table);
		$wpdb->query("DROP TABLE IF EXISTS " . $this->recipe_ingrds_table);
	}
	
	/**
	 * Insert new ingredient into database
	 *
	 * @uses $wpdb
	 * @param $ingrd array of key/value pairs: ingrd, measure, gpcup
	 * @return result of $wpdb->replace(), >0 on success.
	 **/
	function insert_ingrd($ingrd)
	{
		global $wpdb;
		
		/**
		 * Ensure 'measure' value is valid, use volume as default
		 */
		if ('volume' != $ingrd['measure'] && 'weight' != $ingrd['measure']) {
			$ingrd['measure'] = 'volume';
		}
		
		// Use replace operation to add new row or replace existing, 
		$result = $wpdb->replace($this->foods_table, $ingrd);
		return $result;
	}
	
	/**
	 * Delete an ingredient from database
	 *
	 * @return result of $wpdb->query
	 **/
	function delete_ingrd($food_id)
	{
		global $wpdb;
		
		$result = $wpdb->query($wpdb->prepare("DELETE FROM " . $this->foods_table . " WHERE food_id = %d", $food_id));
		return $result;
	}
	
	/**
	 * Retrieve ingredients from database
	 *
	 * @param $orderby, string, field name to order by
	 * @param $order, string ASC or DESC
	 * @param $perpage, int, Number of rows per page
	 * @param $paged, int, page number (1-n)
	 * @return hash array: ['totalitems']=count of all matches, ['ingrds'] = object database query with food items
	 **/
	function get_ingrds($orderby, $order, $perpage, $paged)
	{
		global $wpdb;
		
		/**
		 * Prepare Query
		 */ 
		$query = "SELECT * FROM " . $this->foods_table;

		/**
		 * Add ordering parameters
		 */
	    $orderby = !empty($orderby) ? $orderby : 'food_id';
	    $order = !empty($order) ? $order : 'ASC';
	    if(!empty($orderby) & !empty($order)) { $query.=' ORDER BY '.$orderby.' '.$order; }
		
		/**
		 * Pagination of table elements
		 */
        // Number of elements in table?
        $totalitems = $wpdb->query($query); //return the total number of affected rows
        //Which page is this?
        $paged = !empty($paged) ? $paged : '';
        //Page Number
        if(empty($paged) || !is_numeric($paged) || $paged<=0 ){ $paged=1; }
        //adjust the query to take pagination into account
	    if(!empty($paged) && !empty($perpage)){
		    $offset=($paged-1)*$perpage;
    		$query.=' LIMIT '.(int)$offset.','.(int)$perpage;
	    }
		
		/**
		 * Get the table items
		 */
		$result = $wpdb->get_results($query);
		return array(
			'totalitems' => $totalitems,
			'ingrds' => $result
		);
	}
	
	/**
	 * Retrieve matching food names
	 *
	 * @uses $wpdb
	 * @param $name_contains string - String to match for food name
	 * @param $max_rows int - maximum number of rows to return
	 * @param $exact boolean - true if exact match should be returned
	 * @return array of names retrieved
	 **/
	function get_ingrds_by_name($name_contains, $max_rows, $exact)
	{
		global $wpdb;
		
		if ($exact) {
			$like = $name_contains;
		} else {
			$like = '%' . $name_contains . '%';			
		}
		$rows = $wpdb->get_results($wpdb->prepare("SELECT food_id,ingrd FROM " . $this->foods_table . " WHERE ingrd LIKE %s LIMIT 0,%d", $like, $max_rows));
		
		return $rows;
	}
	
	/**
	 * Retrieve info for a food
	 *
	 * @uses $wpdb
	 * @param $food_id, food id number for query
	 * @return array of ingredient properties
	 **/
	function get_ingrd_by_id($food_id)
	{
		global $wpdb;
		
		$row = $wpdb->get_row($wpdb->prepare("SELECT food_id,ingrd,measure,gpcup,NDB_No FROM " . $this->foods_table . " WHERE food_id = %d", $food_id), ARRAY_A);
		
		return $row;
	}
	
	/**
	 * Insert list of ingredients into table
	 * Sort order will match initial row order
	 *
	 * @uses wpdb
	 * @param $post_id
	 * @param $ingrd_list_id
	 * @param $ingrd_list array of rows of key/value pairs: NDB_No, quantity, unit, ingredient name and comment 
	 * @return void
	 **/
	function insert_ingrds_for_recipe($post_id, $ingrd_list_id, $ingrd_list)
	{
		global $wpdb;
		
		// Don't insert using invalid post ids!
		if ($post_id <= 0) {
			error_log("Tried to insert recipe ingredients using invalid post id: '$post_id'");
			return false;
		}
		
		/**
		 * Delete saved list if one already exists
		 */
		$this->delete_ingrds_for_recipe($post_id, $ingrd_list_id);
		
		/**
		 * Save new ingredient list contents
		 */
		$list_order = 0;  // Init the sort order counter; rows are added in the order received
		foreach ($ingrd_list as $row) {
			$row['post_id'] = $post_id;
			$row['ingrd_list_id'] = $ingrd_list_id;
			$row['list_order'] = $list_order++;
			$result = $wpdb->insert($this->recipe_ingrds_table, $row);
		}
	}
	
	/**
	 * Retrieve ingredients for a recipe post
	 *
	 * @uses $wpdb
	 * @param $post_id Retrieve ingredients for post_id
	 * @param $ingrd_list_id Which ingredient list in post to get
	 * @return array of associative arrays, representing ingredient rows
	 **/
	function get_ingrds_for_recipe($post_id,$ingrd_list_id)
	{
		global $wpdb;

		$result = $wpdb->get_results($wpdb->prepare("SELECT food_id,quantity,unit,ingrd,comment FROM " . $this->recipe_ingrds_table . " WHERE post_id LIKE %d AND ingrd_list_id LIKE %d ORDER BY list_order ASC", $post_id, $ingrd_list_id), ARRAY_A);
		return $result;
	}
	
	/**
	 * Delete an ingredient list from a post
	 *
	 * @return result of $wpdb->query()
	 **/
	function delete_ingrds_for_recipe($post_id, $ingrd_list_id)
	{
		global $wpdb;

		$result = $wpdb->query($wpdb->prepare("DELETE FROM " . $this->recipe_ingrds_table . " WHERE post_id LIKE %d AND ingrd_list_id LIKE %d", $post_id, $ingrd_list_id));
		return $result;
	}
	
	/**
	 * Delete all ingredient items associated with a post
	 *
	 * @uses $wpdb
	 * @param $post_id Delete all ingredients for indicated post
	 * @return result of $wpdb->query()
	 **/
	function delete_all_ingrds_for_recipe($post_id)
	{
		global $wpdb;

		$result = $wpdb->query($wpdb->prepare("DELETE FROM " . $this->recipe_ingrds_table . " WHERE post_id LIKE %d", $post_id));
		return $result;
	}
}
endif; // End class hrecipe_ingrd_db
?>