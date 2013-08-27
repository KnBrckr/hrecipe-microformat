<?php
/**
 * hrecipe_ingredient_table class
 *
 * WP_List_Table Class extension for managing ingredient lists in admin menu
 *
 * Uses concepts implemented in sample plugin: http://wordpress.org/plugins/custom-list-table-example/
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

// Protect from direct execution
if (!defined('WP_PLUGIN_DIR')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

class hrecipe_ingredients_Table extends WP_List_Table {
	
	/**
	 * Reference to ingredients database object
	 *
	 * @var object
	 **/
	var $ingrd_db;
	
	/**
	 * Post type to use in building links to other pages provided by plugin
	 *
	 * @var string
	 **/
	private $post_type;
	
	/**
	 * slug for page to edit an ingredient
	 *
	 * @var string
	 **/
	private $ingrd_page;

	/**
	 * Constructor, we override the parent to pass our own arguments
	 * We usually focus on three parameters: singular and plural labels, as well as whether the class supports AJAX.
	 */
	 function __construct($ingrd_db, $post_type, $page) {
		 parent::__construct( array(
		'singular'=> 'wp_list_ingredient', //Singular label
		'plural' => 'wp_list_ingredients', //plural label, also this well be one of the table css class
		'ajax'	=> false //We won't support Ajax for this table
		) );
		
		$this->ingrd_db = $ingrd_db;
		$this->post_type = $post_type;
		$this->ingrd_page = $page;
	 }
	 
 	/**
 	 * Provides extra navigation before and after ingredients table
 	 *
 	 * @return void
 	 **/
 	// function extra_tablenav( $which )
 	// {
 	// 	if ( 'top' == $which ) {
 	// 		echo "Before Table";
 	// 	} elseif ( 'bottom' == $which ) {
 	// 		echo "After Table";
 	// 	}
 	// }
	
	/**
	 * Define columns that are used in the table
	 *
	 * Array in form 'column_name' => 'column_title'
	 *
	 * @return array $columns, array of columns
	 **/
	function get_columns()
	{
		$columns = array(
            'cb'      => '<input type="checkbox" />', //Render a checkbox instead of text
			'food_id' => 'ID',
			'ingrd'   => 'Ingredient',
			'measure' => 'Measure By',
			'gpcup'   => 'Grams/Cup',
			'NDB_No'  => 'NDB_No'
	
	);
		
		return $columns;
	}
	
	/**
	 * Define columns that are sortable
	 *
	 * Array in form 'column_name' => 'database_field_name'
	 *
	 * @return array $sortable, array of columns that can be sorted
	 **/
	function get_sortable_columns()
	{
		$sortable = array(
			'food_id' => array('food_id', false),
			'ingrd' => array('ingrd', false)
		);
		
		return $sortable;
	}
	
	/**
	 * Define bulk actions that will work on table
	 *
	 * @return array Associative array of bulk actions in form 'slug' => 'visible title'
	 **/
	function get_bulk_actions()
	{
		$actions = array(
			'delete' => __('Delete')
		);
		return $actions;
	}
	
	/**
	 * Prepare table items for display
	 **/
	function prepare_items()
	{
		$screen = get_current_screen();
	
	    $orderby = !empty($_GET["orderby"]) ? mysql_real_escape_string($_GET["orderby"]) : '';
	    $order = !empty($_GET["order"]) ? mysql_real_escape_string($_GET["order"]) : '';

		/**
		 * Pagination of table elements
		 */
        //How many to display per page?
        $perpage = 10; // TODO Make ingredients per page configurable
        //Which page is this?
        $paged = !empty($_GET["paged"]) ? mysql_real_escape_string($_GET["paged"]) : '';
		
		/**
		 * Setup columns to display
		 */
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);

		/**
		 * Get the table items
		 */
		$result = $this->ingrd_db->get_ingrds($orderby, $order, $perpage, $paged);
		$this->items = $result['ingrds'];
		$totalitems = $result['totalitems'];
        //How many pages do we have in total?
        $totalpages = ceil($totalitems/$perpage);
	
		/**
		 * Setup pagination links
		 */
		$this->set_pagination_args( array(
			"total_items" => $totalitems,
			"total_pages" => $totalpages,
			"per_page" => $perpage,
		) );
	}
	
	/**
	 * Method to provide checkbox column in table
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML to be placed in table cell
	 **/
	function column_cb($item)
	{
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label
            /*$2%s*/ $item->food_id             //The value of the checkbox should be the record's id
        );
	}
		
	/**
	 * Method to provide HTML for food_id column
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML for food_id column
	 **/
	function column_food_id($item)
	{
		return esc_attr($item->food_id);
	}
	
	/**
	 * Method to provide HTML for food_id column
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML for food_id column
	 **/
	function column_NDB_No($item)
	{
		return esc_attr($item->NDB_No);
	}

	/**
	 * Method to provide HTML for food_id column
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML for food_id column
	 **/
	function column_ingrd($item)
	{
		$url = sprintf("<a href='?post_type=%s&page=%s&food_id=%d'>%s</a>", 
			$this->post_type, $this->ingrd_page, $item->food_id, esc_attr($item->ingrd));
		return $url ;
	}

	/**
	 * Method to provide HTML for food_id column
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML for food_id column
	 **/
	function column_measure($item)
	{
		return esc_attr($item->measure);
	}

	/**
	 * Method to provide HTML for food_id column
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML for food_id column
	 **/
	function column_gpcup($item)
	{
		return esc_attr($item->gpcup);
	}
}		

?>