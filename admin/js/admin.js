/**
 * hrecipe admin javascript
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

jQuery(document).ready( function($) {
	// Make the recipe field sections sortable to configure head and footer contents
	$('.recipe-fields').sortable(
		{
			items: '.menu-item-handle',
			connectWith: '.recipe-fields', 
			update: function(event, ui) {
				// On update, fixup the hidden input tracking contents of head and footer
				jQuery('.recipe-fields').each(function() {
					var n = jQuery(this);
					var new_list = n.find('li').map(function(){return this.attributes['name'].value;}).get().join();
					n.find('input').attr('value', new_list);
				});
			}
		});		

	/**
	 * Manipulate the instructions metabox a bit
	 */
	
	var metabox = $('#hrecipe_instructions');
	// Make the ingredients metabox contents sortable
	metabox.sortable(
		{
			items: '.step'
		});
		
	// Action to create new row
	metabox.find('.insert').live('click', function(){
		var row = $(this).closest('div');
		var clonedRow = hrecipeCloneStep(row, true);
		
		// Put new row into the table after the current one
		row.after(clonedRow);
	});
	
	// Action to delete active row
	$('.delete').live('click', function() {
		var step = $(this).closest('.step');
		
		if (step.siblings().length > 0) {
			step.remove();			
		}
	});
});

//
// Clone an step row
//
// row (DOM element) step row to clone
//
// return cloned Row, cleaned of input values
function hrecipeCloneStep(row) {
	var clonedRow = row.clone();

	// Prep the new row for insert
	clonedRow.find('textarea').each(function() { 
		this.value = '';
	});

	return clonedRow;
}