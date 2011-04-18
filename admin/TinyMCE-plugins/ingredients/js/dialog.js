//tinyMCEPopup.requireLangPack();

// Setup autocomplete for fields
var availableUnits=[ // TODO make list bigger!
	'cup',
	'cups',
	'ounce',
	'quart',
	'tbs',
	'tsp'
];

// Make the ingredient rows sortable
jQuery(document).ready( function($) {
	$('.unit').autocomplete({source: availableUnits});
	
	$('tbody').sortable(
		{
			items: 'tr'
		}
		);
		
	$('.insert').live('click', function(){
		var $btn = $(this);
		var $clonedRow = $btn.closest('tr').clone();
		
		// Clean out any values
		$clonedRow.find('input').each(function() { this.value = '';});
		
		// Setup autocomplete for the new row elements
		$clonedRow.find('.unit').autocomplete({source: availableUnits});
		
		// Put new row into the table after the current one
		$btn.closest('tr').after($clonedRow);
	});
});


var hrecipeIngredientListDialog = {
	init : function() {
		var f = document.forms[0];

		// Get the selected contents as text and place it in the input
		f.someval.value = tinyMCEPopup.editor.selection.getContent({format : 'text'});
		f.somearg.value = tinyMCEPopup.getWindowArg('some_custom_arg');
	},

	insert : function() {
		// Insert the contents from the input into the document
		tinyMCEPopup.editor.execCommand('mceInsertContent', false, document.forms[0].someval.value);
		tinyMCEPopup.close();
	}
};

//tinyMCEPopup.onInit.add(hrecipeIngredientListDialog.init, hrecipeIngredientListDialog);
