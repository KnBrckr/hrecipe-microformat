tinyMCEPopup.requireLangPack();

// Setup autocomplete for fields
var availableUnits=[ // TODO make list bigger!
	'cup',
	'cups',
	'ounce',
	'quart',
	'tbs',
	'tsp'
];

// After document is loaded, init elements
jQuery(document).ready( function($) {
	$('.type').autocomplete({source: availableUnits}); // FIXME auto-complete not working
	
	// Make table rows sortable
	$('tbody').sortable(
		{
			items: 'tr'
		}
	);
	
	// Insert a new row after the active row
	$('.insert').live('click', function(){
		var btn = $(this);
		var clonedRow = hrecipeCloneRow(btn.closest('tr'));
		
		// Put new row into the table after the current one
		btn.closest('tr').after(clonedRow);
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
		
		$(n).closest('.ingredients').find('.ingredient').each(function(){
			// For each ingredient in the document ...
			var sourceRow = $(this);
			var clonedRow = hrecipeCloneRow(emptyRow);
			$.each(['.value', '.type', '.ingrd', '.comment'], function(index,attr){
				var attrVal = sourceRow.find(attr).text();
				clonedRow.find(attr).val(attrVal);
			});
			emptyRow.before(clonedRow);
		});
	},

	insert : function() {
		// Insert the contents from the input into the document
		
		// For each row in the ingredients table, generate the target ingredient tags
		newList = '<div class="ingredients">';
		$('tbody tr').each(function() {
			var row = $(this);
			var atts = new Array;
			
			$.each(['value', 'type', 'ingrd', 'comment'],function(index,attr){
				if ('' !== (val = row.find('.' + attr).val())) {
					val = val.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
					atts.push('<span class="' + attr + '">' + val + '</span>');
				}				
			});
			if (atts.length > 0) { // If at least one field specified, add to the result
				newList += '<div class="ingredient">' + atts.join(' ') + "</div>\n";
			}
		});
		newList += '</div>'; // Close out the ingredient list
		
		// If insert point inside an existing list, replace the list, else insert at cursor

		var n = tinyMCEPopup.editor.selection.getNode();
		var ingredientList = $(n).closest('.ingredients');
		if (ingredientList.length > 0) {
			ingredientList.replaceWith(newList);
		} else {
			tinyMCEPopup.editor.execCommand('mceInsertContent', false, newList);
		}
		tinyMCEPopup.close();
	}
};

function hrecipeCloneRow(row) {
	var clonedRow = row.clone();

	// Clean out any values
	clonedRow.find('input').each(function() { this.value = '';});

	// Setup autocomplete for the new row elements
	clonedRow.find('.type').autocomplete({source: availableUnits});
	
	return clonedRow;
}

tinyMCEPopup.onInit.add(hrecipeIngredientListDialog.init, hrecipeIngredientListDialog);
