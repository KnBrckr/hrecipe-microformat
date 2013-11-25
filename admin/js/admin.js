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
	'pint',
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
		Add tools to Recipe Ingredient list section
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
	
	/*
		Tools for Add Ingredient page
	*/
	
	// On Enter, submit search for ingredient in NDB database
	$('#NDB_search_ingrd').keypress(function(e){
		if (e.keyCode === 13) { 
			hrecipeNDBSearch(1); 
		}
	});
	
	// Handle submit event for NDB ingredient search
	$('#NDB_search_form').submit(function(e){
		// On a cancel action, close the ThickBox
		if ( 'cancel' === NDBSearchAction ) {
			self.parent.tb_remove(); // Close the thickbox modal
			return false;
		}
		
		// Record selected food from Nutrition DB with new ingredient
		if ( 'selectIngrd' === NDBSearchAction && typeof this.elements.NDB_No != "undefined" && "" != this.elements.NDB_No.value ) {
			// Fill in the NDB_No for the food
			jQuery('#NDB_No').val(this.elements.NDB_No.value);
			
			// FIXME Determine grams/cup
			self.parent.tb_remove(); // Close the thickbox modal
			return false;
		}

		return false;
	});
	$('#NDB_search_form :submit').click(function(){ NDBSearchAction = this.name; });
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
	jQuery(target).find('.ingrd').autocomplete({
		source: function( request, response ) {
			jQuery.ajax({
				url: HrecipeMicroformat.ajaxurl,
				dataType: "json",
				data: {
					action: HrecipeMicroformat.pluginPrefix + 'ingrd_auto_complete',
					maxRows: HrecipeMicroformat.maxRows,
					name_contains: request.term
				},
				// When Ajax returns successfully, process the retrieved data
				success: function( data ) {
					if (0 == data) return null;
	
					// return array of mapping items for jQuery autocomplete tool to use
					return(jQuery.map( data.list, function(item){
						return {
							label: item.ingrd,
							food_id: item.food_id
						} ;
					}));
				}
			});
		},
		minLength: 2,
		// change triggered when field is blurred if the value has changed
		change: function( event, ui ) {
			if (ui.item) {
				// If an item was selected, record the food database record number
				jQuery(this).addClass('food_linked').parent().siblings().find('.food_id').val(ui.item.food_id);				
			} else {
				// No matching item, clear food database record number
				jQuery(this).removeClass('food_linked').parent().siblings().find('.food_id').val('');
			}
		}
	});
	
	return;
}

//
// Perform and AJAX search of NDB database for matching ingredients
//
var hrecipeNDBSearchResult = {
	'page' : 1,
	'pages' : 1,
	'totalrows' : 0
}

function hrecipeNDBSearch(page) {
	if (searchString = jQuery('#NDB_search_ingrd').val()) {
		jQuery('#NDB_search_form .waiting').show();  // Show busy icon
		jQuery.ajax({
			'url': HrecipeMicroformat.ajaxurl,
			'dataType': 'json',
			'data': {
				'action': HrecipeMicroformat.pluginPrefix + 'NDB_search',
				'maxRows': HrecipeMicroformat.maxRows,
				'pageNum': page,
				'name_contains': searchString
			},
			// When Ajax returns successfully, process the retrieved data
			success: function( data, textStatus, jqXHR ) {
				jQuery('#NDB_search_form .waiting').hide(); // Hide busy icon
	
				if (data) {
					hrecipeNDBSearchResult = data;
				} else {
					hrecipeNDBSearchResult = {
						'page' : 1,
						'pages' : 1,
						'totalrows' : 0,
						'rows' : {}
					}					
				}
				
				// Cleanup old table contents, but leave the prototype row
				jQuery('.tr_ingrd:not(.prototype)').remove();
	
				// Add results to table for display
				for (var i = 0; i < data.rows.length; i++) {
					// Clone the prototype row and make it visible
					var newRow = jQuery('.prototype.tr_ingrd').clone().removeClass('prototype');
					newRow.attr('NDB_No', data.rows[i].NDB_No);
					newRow.find('.ingrd').text(data.rows[i].Long_Desc);
					newRow.find('.NDB_No').val(data.rows[i].NDB_No).click(function(e){
						hrecipeNDBMeasures(e);
					});
					jQuery('.NDB_ingredients>tbody').append(newRow);
				}
				jQuery('#NDB_search_results').show();
				jQuery('.total-pages').text(data.pages);
				jQuery('.displaying-num').text(data.totalrows + ' items');
				jQuery('.current-page').val(data.page);
			},
			error: function ( jqXHR, textStatus, errorThrown) {
				jQuery('#NDB_search_form .waiting').hide();  // Hide busy icon
				// TODO Anything else to do on error here?
			}
		});
		event.returnValue = false;
	} else {
		// FIXME Flash Input box
	};
}

function hrecipeNDBMeasures(e) {
	// Rehide measures tables
	// TODO Don't hide table that is about to be displayed
	jQuery('.measures').hide();
	
	// If data already collected for this NDB_No, just show it.
	if (jQuery('.tr_ingrd[ndb_no='+e.currentTarget.value+'] .measures .tr_measure').size() > 1) {
		jQuery('.tr_ingrd[ndb_no='+e.currentTarget.value+'] .measures').show();
		return;
	}
	
	// Submit AJAX to get measures from DB
	jQuery.ajax({
		'url': HrecipeMicroformat.ajaxurl,
		'dataType': 'json',
		'data': {
			'action': HrecipeMicroformat.pluginPrefix + 'NDB_measures',
			'NDB_No': e.currentTarget.value
		},
		success: function (data, textStatus, jqXHR) {
			if (data) {
				// FIXME Scan available measures for one that can be used to calculate grams/cup
				
				var measuresTbl = jQuery('.tr_ingrd[ndb_no=' + data[0].NDB_No + '] .measures').show();
				
				if (0 != measuresTbl) {
					for (var i = 0; i < data.length; i++) {
						var newRow = measuresTbl.find('.prototype').clone().removeClass('prototype');
						newRow.find('.Seq').val(data[i].Seq);
						newRow.find('.Amount').text(data[i].Amount);
						newRow.find('.Msre_Desc').text(data[i].Msre_Desc);
						newRow.find('.Gm_Wgt').text(data[i].Gm_Wgt);
						measuresTbl.append(newRow);
					}				
				}
			}
		},
		error: function (jqXHR, textStatus, errorThrown) {
			// TODO Anything else needed here?
		}
	});
}