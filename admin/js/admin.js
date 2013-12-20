/**
 * hrecipe admin javascript
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

var hrecipe = {
	pagination : {
		'page' : 1,
		'pages' : 1,
		'totalrows' : 0
	},
	
	// Run at document load
	init: function($){
		/*
			Tools for Admin Settings Section
		*/
		
		// Make the recipe field sections sortable to configure head and footer contents
		$('.recipe-fields').sortable(
			{
				items: '.menu-item-handle',
				connectWith: '.recipe-fields', 
				update: function(event, ui) {
					// On update, fixup the hidden input tracking contents of head and footer
					$('.recipe-fields').each(function() {
						var n = jQuery(this);
						var new_list = n.find('li').map(function(){return this.attributes['name'].value;}).get().join();
						n.find('input').attr('value', new_list);
					});
				}
			});
		
		/*
			Add tools to Recipe Ingredient list section
		*/

		// Enable manipulation of rows in ingredients table
		var ingrdsTable = $('.ingredients');
		if (ingrdsTable.length > 0) {
			// Make ingredients list sortable and setup UI elements for each row
			ingrdsTable.sortable({ items: 'tbody tr' }).each(function(index,item){
				hrecipe.recipePage.setupIngrdRow(item);
			});
				
			// Setup Insert and Delete Row functions at table level
			ingrdsTable.on('click', '.insert', hrecipe.recipePage.insertIngrdRow);
			ingrdsTable.on('click', '.delete', hrecipe.recipePage.deleteIngrdRow);
		}
	
		/*
			Tools for Add Ingredient page
		*/
	
		// On Enter, submit search for ingredient in NDB database
		$('#NDB_search_ingrd').keypress(function(e){
			if (e.keyCode === 13) { 
				hrecipe.addIngrdPage.NDBSearch(1); 
			}
		});
	
		// Handler for Submit and INPUT form field click actions for NDB ingredient search
		$('#NDB_search_form').submit(hrecipe.addIngrdPage.NDBSearchSubmit).click(function(e) {
			// Special click actions for some of the INPUT form fields
			if ('INPUT' != e.target.nodeName) return;
			var targetType = jQuery(e.target).prop('type');
			
			// For submit buttons, save ID of button clicked for submit handling
			if ('submit' == targetType) {
	  		  $(this).data('clicked',$(e.target).prop('id'));				
			}
			
			// For radio buttons, collect measures for the related ingredient
			if ('radio' == targetType) {
				hrecipe.addIngrdPage.getNDBMeasures(e);
			}
		});
	},
	
	/*
		Methods and properties for Admin Recipe Page
	*/
	recipePage : {
		// Autocomplete list for Units
		availableUnits : [ // TODO Dynamically pull from WEIGHT database?
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
		],

		// Insert an ingredient row
		insertIngrdRow : function() {
			// Travel up DOM to find the containing TR and clone it
			var row = jQuery(this).closest('tr');
			var newRow = jQuery('.recipe_ingrd_row.prototype').clone().removeClass('prototype');

			// Setup UI elements for the new row elements
			hrecipe.recipePage.setupIngrdRow(newRow);
			
			// Put new row into the table after the current one
			row.after(newRow);
			jQuery('.ingredients').sortable('refresh');
		},
	
		// Delete an ingredient row
		deleteIngrdRow : function() {
			var btn = jQuery(this);

			// Only remove if there will still be at least one row remaining
			if (btn.closest('tbody').find('tr').length > 1) {
				btn.closest('tr').remove();			
			}
		},
		
		//
		// Setup UI elements for Ingredient rows in recipe edit screen
		//
		// target (DOM or jQuery Object referencing row of ingredient table)
		setupIngrdRow : function(target) {
			jTarget = jQuery(target);
			
			// Add some pizzaz to the buttons
			jTarget.find( "li.ui-state-default" ).hover(
				function() {
					jQuery( this ).addClass( "ui-state-hover" );
				},
				function() {
					jQuery( this ).removeClass( "ui-state-hover" );
				}
			);
			
			// Autocomplete for the type (unit) column of ingredient list
			jTarget.find('.type').autocomplete({source: hrecipe.recipePage.availableUnits});
	
			// Autocomplete for the ingredient column of ingredient list
			jTarget.find('.ingrd').autocomplete({
				source: function( request, response ) {
					jQuery.ajax({
						url: hrecipeAdminVars.ajaxurl,
						dataType: "json",
						data: {
							action: hrecipeAdminVars.pluginPrefix + 'ingrd_auto_complete',
							maxRows: hrecipeAdminVars.maxRows,
							name_contains: request.term
						},
						// When Ajax returns successfully, process the retrieved data
						success: function( data ) {
							if (0 == data) return null;
	
							// return array of mapping items for jQuery autocomplete tool to use
							response(jQuery.map( data.list, function(item){
								return {
									label: item.ingrd,
									food_id: item.food_id
								} ;
							}));
						}
					});
				},
				minLength: 3,
				// change triggered when field is blurred if the value has changed
				// FIXME Some timing issues in having this reliably work
				change: function( event, ui ) {
					if (ui.item) {
						// If an item was selected, record the food database record number
						jQuery(this).addClass('food_linked').closest('tr').find('.food_id').val(ui.item.food_id);				
					} else {
						// No matching item, clear food database record number
						jQuery(this).removeClass('food_linked').closest('tr').find('.food_id').val('');
					}
				}
			});
		}		
	},
	
	addIngrdPage : {
		// Array of measures for indregrients
		NDBMeasures : Object(),

		// Used to convert units of measure to cups for calculation of grams/cup
		convert2Cup : {
			'cup' : 1,
			'cups' : 1,
			'fluid ounce' : 8,
			'tbsp' : 16,
			'tbs' : 16,
			'tablespoon' : 16,
			'tsp' : 48,
			'teaspoon' : 48
		},
	
		//
		// Perform an AJAX search of NDB database for matching ingredients
		//
		NDBSearch : function(page) {
			var searchString = jQuery('#NDB_search_ingrd').val();
			if (searchString) {
				jQuery('#NDB_search_form .waiting').show();  // Show busy icon
				jQuery.ajax({
					'url': hrecipeAdminVars.ajaxurl,
					'dataType': 'json',
					'data': {
						'action': hrecipeAdminVars.pluginPrefix + 'NDB_search',
						'maxRows': hrecipeAdminVars.maxRows,
						'pageNum': page,
						'name_contains': searchString
					},
					// When Ajax returns successfully, process the retrieved data
					success: function( data, textStatus, jqXHR ) {
						jQuery('#NDB_search_form .waiting').hide(); // Hide busy icon
	
						if (data) {
							hrecipe.pagination = {
								'page' : data.page,
								'pages' : data.pages,
								'totalrows' : data.totalrows,
							}
						} else {
							hrecipe.pagination = {
								'page' : 1,
								'pages' : 1,
								'totalrows' : 0,
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
							newRow.find('.NDB_No').val(data.rows[i].NDB_No);
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
				// FIXME Display Error - Input box needs a value to continue
			};
		},
	
		getNDBMeasures : function(e) {
			var measures = hrecipe.addIngrdPage.NDBMeasures;
			
			// If data already collected for this NDB_No, return
			if (e.target.value in measures) {
				return;
			}
	
			// Submit AJAX to get measures from DB
			jQuery.ajax({
				'url': hrecipeAdminVars.ajaxurl,
				'dataType': 'json',
				'data': {
					'action': hrecipeAdminVars.pluginPrefix + 'NDB_measures',
					'NDB_No': e.target.value
				},
				success: function (data, textStatus, jqXHR) {
					// FIXME Deal with empty data return
					if (data) {
						// Save measures in local table
						for (var i = 0; i < data.length; i++) {
							if (! jQuery.isArray(measures[data[i].NDB_No])) {
								measures[data[i].NDB_No] = Array();
							}
							measures[data[i].NDB_No].push(data[i]);
						}
					}
				},
				error: function (jqXHR, textStatus, errorThrown) {
					// TODO Display a search error
				}
			});
		},
	
		NDBSearchSubmit : function(e){
			// Action name was set from onclick function established on the form
			var action = jQuery(this).data('clicked');
		
			// On a cancel action, close the ThickBox
			if ( 'cancel' === action ) {
				self.parent.tb_remove(); // Close the thickbox modal
				return false;
			}
	
			// Record selected food from Nutrition DB with new ingredient
			if ( 'selectIngrd' === action && typeof this.elements.NDB_No != "undefined" && "" != this.elements.NDB_No.value ) {
				var NDB_No = this.elements.NDB_No.value;
		
				// TODO Only update if this is a new NDB number being assigned
		
				// Fill in the NDB_No for the food
				jQuery('#NDB_No').val(NDB_No);
		
				// Determine grams/cup if an appropriate measure is available
				var gpcup = '';
				var measures;

				// If Measures for this NDB Number are available, try to determine grams/cup
				if (jQuery.isArray(hrecipe.addIngrdPage.NDBMeasures[NDB_No])) {
					measures = hrecipe.addIngrdPage.NDBMeasures[NDB_No];
					for (var i = 0; i < measures.length; i++) {
						if (measures[i].Msre_Desc in hrecipe.addIngrdPage.convert2Cup) {
							// Grams / Cup = (Gram Weight) / (Amount of Measures) * (Measure per cups)
							// eg. g/c = (100g / 2 Tbs) * (16 Tbs / 1 cup)
							gpcup = measures[i].Gm_Wgt / measures[i].Amount *
								hrecipe.addIngrdPage.convert2Cup[measures[i].Msre_Desc];
							break;
						}
					}
				}
		
				// Update form with Grams per cup
				jQuery('#gpcup').val(gpcup);
		
				// Remove any rows present from previously linked food in the Measures Table
				var measuresTbl = jQuery('.NDB_linked');
				measuresTbl.find('tbody>tr:not(.prototype)').remove();
		
				// Setup Table Caption using ingredient name found in the search table for selected NDB
				var ingrd = jQuery('.tr_ingrd[NDB_No=' + NDB_No + '] .ingrd').text();
				jQuery('#NDB_ingrd').text(ingrd);
		
				// Add Measures for the newly linked food if they are defined
				if (measures) {
					for (var i = 0; i < measures.length; i++) {
						var newRow = measuresTbl.find('.prototype').clone().removeClass('prototype');
						newRow.find('.Amount').text(measures[i].Amount);
						newRow.find('.Msre_Desc').text(measures[i].Msre_Desc);
						newRow.find('.Gm_Wgt').text(measures[i].Gm_Wgt);
						measuresTbl.append(newRow);
					}
				}
		
				// Close the thickbox modal
				self.parent.tb_remove(); 
				return false;
			}

			return false;
		}		
	}
};

jQuery(document).ready(function($){hrecipe.init($)});
