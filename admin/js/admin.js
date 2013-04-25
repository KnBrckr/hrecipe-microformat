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

// Autocomplete list for Units
var availableUnits=[ // TODO Dynamically pull from WEIGHT database?
	'cup',
	'cups',
	'fl oz',
	'fluid ounce',
	'g',
	'gallon',
	'gram',
	'grams',
	'kg',
	'kilogram',
	'kilograms',
	'l',
	'lb',
	'litre',
	'ml',
	'ounce',
	'oz',
	'pinch',
	'pound',
	'quart',
	'stick',
	'tablespoon',
	'tbs',
	'tbsp',
	'teaspoon',
	'tsp'
];

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
		
	/*
		Add tools to Ingredient list section
	*/
	
	// Make ingredients list sortable
	$('.ingredients').sortable({ items: 'tbody tr' });
			
	// Setup autocomplete for fields in the table
	hrecipeInitAutocomplete($('.ingredients'));
	
	// Setup Insert and Delete Row functions
	$('.ingredients').ready( function($) {
		// Insert a new row after the active row
		jQuery('.insert').live('click', function(){
			var row = jQuery(this).closest('tr');
			var newRow = hrecipeNewIngredient(row);
		
			// Put new row into the table after the current one
			row.after(newRow);
			jQuery('.ingredients').sortable('refresh');
		});
	
		// Delete active row
		jQuery('.delete').live('click', function() {
			var btn = jQuery(this);
		
			if (btn.closest('tbody').find('tr').length > 1) {
				btn.closest('tr').remove();			
			}
		});
	});
});

//
// Create a blank Ingredient row
//
// row (DOM element) ingredient row to clone
//
// return cloned Row, cleaned of input values
function hrecipeNewIngredient(row) {
	var clonedRow = row.clone();

	// Clean out any values
	clonedRow.find('input').each(function() { this.value = '';});

	// Setup autocomplete for the new row elements
	hrecipeInitAutocomplete(clonedRow);
	
	return clonedRow;
}

//
// Init Autocomplete on a jQuery object
//
// target (jQuery Object)
//
// return void
function hrecipeInitAutocomplete(target) {
	// Autocomplete for the type (unit) column of ingredient list
	jQuery(target).find('.type').autocomplete({source: availableUnits});
	
	// Autocomplete for the ingredient column of ingredient list
	// TODO the URL below assumes a standard WP install.  If wp-admin is in a different location, this won't work
	jQuery(target).find('.ingrd').autocomplete({
		source: function( request, response ) {
			jQuery.ajax({
				url: "../../../../../../wp-admin/admin-ajax.php",
				dataType: "json",
				data: {
					action: 'hrecipe-microformat_ingrd_auto_complete',
					maxRows: 12,
					name_contains: request.term
				},
				// When Ajax returns successfully, process the retrieved data
				success: function( data ) {
					response(hrecipeAjaxAutocompleteSuccess(data));
				}
			});
		},
		minLength: 2,
		// change triggered when field is blurred if the value has changed
		change: function( event, ui ) {
			if (ui.item) {
				// If an item was selected, record the food database record number
				jQuery(this).addClass('sr_linked').siblings('.NDB_No').val(ui.item.NDB_No);				
			} else {
				// No matching item, clear food database record number
				jQuery(this).removeClass('sr_linked').siblings('.NDB_No').val('');
			}
		}
	});
	
	return;
}

//
// AJAX Completion for ingredient column
//
// return array of mapping items for jQuery autocomplete tool
//
function hrecipeAjaxAutocompleteSuccess(data) {
	if (0 == data) return null;
	
	return(jQuery.map( data.list, function(item){
		return {
			label: item.Long_Desc,
			NDB_No: item.NDB_No
		} ;
	}));
}
