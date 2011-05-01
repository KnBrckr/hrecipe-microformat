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

	// Insert the contents from the input into the document
	insert : function() {
		var val, ingrdList;
		ed = tinyMCEPopup.editor;
		
		// Get a temp id for the new element; need to loop until it doesn't exist
		var tmpID = ed.dom.uniqueId('_hrecipe_');
		while (ed.getDoc().getElementById(tmpID)) {
			tmpID = ed.dom.uniqueId('_hrecipe_');
		}
		
		// Create container div for the ingredient list
		ingrdList = ed.dom.create('div', {'class': 'ingredients mceNonEditable', 'id': tmpID});

		// Add a Title if one provided
		if ('' !== (val = jQuery('#ingrd-list-name').val())) {
			val = val.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); // Sanitize user text
			ingrdList.appendChild(ed.dom.create('h4', {'class': 'ingredients-title'}, val));
		}
		
		// For each row in the ingredients table, generate the target ingredient tags
		$('tbody tr').each(function() {
			var row = $(this);
			var ingrdRow = ed.dom.create('div', {'class': 'ingredient'});
			$.each(['value', 'type', 'ingrd', 'comment'],function(index,attr){
				if ('' !== (val = row.find('.' + attr).val())) {
					val = val.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); // Sanitize user text
					ingrdRow.appendChild(ed.dom.create('span', {'class': attr}, val));
				}				
			});
			
			// If at least one field specified, add to the result
			if (ingrdRow.childElementCount > 0) {
				ingrdList.appendChild(ingrdRow);
			}
		}); // End processing ingredients table

		// If the new list has any content, insert at selection location
		if (ingrdList.childElementCount > 0) { 
			
			var n = ed.selection.getNode();
			var oldIngrdList = ed.dom.getParent(n, '.ingredients');
			if (oldIngrdList) {
				// Replace old with new - Change active selection to be the old div
				ed.selection.select(oldIngrdList);
			}

			// Insert the new list
			ed.selection.setNode(ingrdList);			
			
			// Put editing controls onto the new list
			n = ed.getDoc().getElementById(tmpID); // Find the new item using tmpID
			ed.execCommand('mceHrecipeSetupIngrdList', false, n);
			n.id = ''; // Clear the tmp id
		}
		tinyMCEPopup.close();
	},
	
	// Remove Ingredient List from content
	'remove' : function() {
		var n = tinyMCEPopup.editor.selection.getNode();
		$(n).closest('.ingredients').remove(); // TODO tinymce Undo button is not updating after remove.
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
