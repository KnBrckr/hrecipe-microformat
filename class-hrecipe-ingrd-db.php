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
// FIXME Ingredients are deleted only when Post is removed from DB

if (! class_exists('hrecipe_ingrd_db')) :
class hrecipe_ingrd_db {
	
	/**
	 * Ingredients database table name
	 *
	 * @var string
	 * @access private
	 **/
	private $ingrds_table;
	
	/**
	 * Construct new object
	 *
	 * @param $prefix string Prefix to use for database table
	 * @return void
	 **/
	function __construct($prefix) {
		global $table_prefix;

		$this->ingrds_table = $table_prefix . $prefix . 'ingrds';
	}
	
	/**
	 * Create the database table to hold ingredients
	 *
	 * Col Field       Type  Blank  Description
	 * 0   ingrd_id    N 20*  N     Unique key
	 * 1   post_id     N 20   N     Indexed: Associated post_id for ingredient
	 * 2   ingrd_list_id N 10 N     Recipe can have multiple lists; List ID within a post
	 * 3   list_order  N 10   N     sort order within ingrd_list_id list
	 * 4   NDB_No      A 5    Y     Associated food description from government food database
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
		
		$sql = "CREATE TABLE IF NOT EXISTS " . $this->ingrds_table . " (
			ingrd_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id bigint(20) UNSIGNED NOT NULL,
			ingrd_list_id int(10) UNSIGNED NOT NULL,
			list_order int(10) NOT NULL,
			NDB_No char(5) DEFAULT '',
			quantity varchar(100) DEFAULT '',
			unit varchar(100) DEFAULT '',
			ingrd varchar(200) DEFAULT '',
			comment longtext DEFAULT '',
			PRIMARY KEY (ingrd_id),
			INDEX (post_id)
		) $charset_collate;";	

		// Run SQL to create the table
		dbDelta($sql);
	}
	
	/**
	 * Delete database table for ingredient lists (used for uninstall)
	 * FIXME Call uninstall
	 *
	 * @return void
	 **/
	function drop_schema()
	{
		global $wpdb;
		
		$wpdb->query("DROP TABLE IF EXISTS " . $this->ingrds_table);
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
	function insert_ingrds($post_id, $ingrd_list_id, $ingrd_list)
	{
		global $wpdb;
		
		$list_order = 0;  // Init the sort order counter; rows are added in the order received
		foreach ($ingrd_list as $row) {
			$row['post_id'] = $post_id;
			$row['ingrd_list_id'] = $ingrd_list_id;
			$row['list_order'] = $list_order++;
			$result = $wpdb->insert($this->ingrds_table, $row);
		}
	}
	
	/**
	 * Retrieve ingredients for a recipe post
	 *
	 * @uses $wpdb
	 * @param $post_id Retrieve ingredients for post_id
	 * @param $ingrd_list_id Which ingredient list in post to get
	 * @return array of ingredient rows
	 **/
	function get_ingrds($post_id,$ingrd_list_id)
	{
		global $wpdb;
		
		$result = $wpdb->get_results($wpdb->prepare("SELECT NDB_No,quantity,unit,ingrd,comment FROM " . $this->ingrds_table . " WHERE post_id LIKE %d AND ingrd_list_id LIKE %d ORDER BY list_order ASC", $post_id, $ingrd_list_id));

		return $result;
	}
	
	/**
	 * Delete an ingredient list from a post
	 *
	 * @return null
	 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
	 **/
	function delete_ingrd_list($post_id, $ingrd_list_id)
	{
		global $wpdb;
		
		$result = $wpdb->query($wpdb->prepare("DELETE FROM " . $this->ingrds_table . " WHERE post_id LIKE %d AND ingrd_list_id LIKE %d", $post_id, $ingrd_list_id));
	}
	
	/**
	 * Delete all ingredient items associated with a post
	 * FIXME Call delete when posts are removed
	 *
	 * @uses $wpdb
	 * @param $post_id Delete all ingredients for indicated post
	 * @return void
	 **/
	function delete_ingrds_for_post($post_id)
	{
		global $wpdb;
		$result = $wpdb->query($wpdb->prepare("DELETE FROM " . $this->ingrds_table . " WHERE post_id LIKE %d", $this->ingrds_table, $post_id));
	}
}
endif; // End class hrecipe_ingrd_db
?>