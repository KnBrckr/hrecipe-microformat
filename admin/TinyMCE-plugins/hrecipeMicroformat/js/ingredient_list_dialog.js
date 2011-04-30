tinyMCEPopup.requireLangPack();

// Setup autocomplete for fields
var availableUnits=[ // TODO Setup for i18n
	'cup',
	'cups',
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
	'oz.',
	'pinch',
	'pound',
	'quart',
	'tbs',
	'tsp'
];

// TODO - Autocomplete for ingredient name

// After document is loaded, init elements
jQuery(document).ready( function($) {
	// Make table rows sortable
	$('tbody').sortable(
		{
			items: 'tr'
		}
	);
	
	// Insert a new row after the active row
	$('.insert').live('click', function(){
		var row = $(this).closest('tr');
		var clonedRow = hrecipeCloneRow(row, true);
		
		// Put new row into the table after the current one
		row.after(clonedRow);
	});
	
	// Delete active row
	$('.delete').live('click', function() {
		var btn = $(this);
		
		if (btn.closest('tbody').find('tr').length > 1) {
			btn.closest('tr').remove();			
		}
	});
});


var hrecipeIngredientListDialog = {
	init : function() {
		// If editing an existing list, populate the dialog with the content
		var n = tinyMCEPopup.editor.selection.getNode();
		//var f = document.forms[0];
		var emptyRow = $('.ingrd-list tr:first');
		var ingredientList = $(n).closest('.ingredients');
		
		$('#ingrd-list-name').val(ingredientList.find('.ingredients-title').text()); // Grab title for this list
		ingredientList.find('.ingredient').each(function(){
			// For each ingredient in the document ...
			var sourceRow = $(this);
			var clonedRow = hrecipeCloneRow(emptyRow, false); // Don't init autocomplete on clone
			$.each(['.value', '.type', '.ingrd', '.comment'], function(index,attr){
				var attrVal = sourceRow.find(attr).text();
				clonedRow.find(attr).val(attrVal);
			});
			emptyRow.before(clonedRow);
		});
		
		// Setup autocomplete for fields in the table
		$('.type').autocomplete({source: availableUnits});
	},

	insert : function() {
		// Insert the contents from the input into the document
		
		// For each row in the ingredients table, generate the target ingredient tags
		newList = '<div class="ingredients">';
		newList += '<h4 class="ingredients-title">' + $('#ingrd-list-name').val() + '</h4>';
		$('tbody tr').each(function() {
			var row = $(this);
			var atts = new Array;
			
			$.each(['value', 'type', 'ingrd', 'comment'],function(index,attr){
				if ('' !== (val = row.find('.' + attr).val())) {
					val = val.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); // Sanitize user text
					atts.push('<span class="' + attr + '">' + val + '</span>');
				}				
			});
			if (atts.length > 0) { // If at least one field specified, add to the result
				newList += '<div class="ingredient mceNonEditable">' + atts.join(' ') + "</div>\n";
			}
		});
		newList += '</div>'; // Close out the ingredient list
		
		// If insert point inside an existing list, replace the list, else insert at cursor

		var n = tinyMCEPopup.editor.selection.getNode();
		var ingredientList = $(n).closest('.ingredients');
		if (ingredientList.length > 0) {
			ingredientList.replaceWith(newList);
		} else {
			newList += "\n\n";
			tinyMCEPopup.editor.execCommand('mceInsertContent', false, newList);
		}
		tinyMCEPopup.close();
	}
};

//
// Clone an ingredient row
//
// row (DOM element) ingredient row to clone
// autoinit (boolean) true if autocomplete should be setup on the row
//
// return cloned Row, cleaned of input values
function hrecipeCloneRow(row, autoinit) {
	var clonedRow = row.clone();

	// Clean out any values
	clonedRow.find('input').each(function() { this.value = '';});

	if (autoinit) {
		// Setup autocomplete for the new row elements
		clonedRow.find('.type').autocomplete({source: availableUnits});		
	}
	
	return clonedRow;
}

tinyMCEPopup.onInit.add(hrecipeIngredientListDialog.init, hrecipeIngredientListDialog);
