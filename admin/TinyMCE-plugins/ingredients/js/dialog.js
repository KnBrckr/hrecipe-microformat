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
	$('.unit').autocomplete({source: availableUnits});
	
	// Make table rows sortable
	$('tbody').sortable(
		{
			items: 'tr'
		}
	);
	
	// Insert a new row after the active row
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
	
	// Delete active row
	$('.delete').live('click', function() {
		var $btn = $(this);
		
		if ($btn.closest('tbody').find('tr').length > 1) {
			$btn.closest('tr').remove();			
		}
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
		
		// For each row in the ingredients table, generate the target ingredient tags
		newList = '';
		$('tbody tr').each(function() {
			var row = $(this);
			var atts = new Array;
			
			$.each(['amount', 'unit', 'ingredient', 'comment'],function(index,attr){
				if ((val = row.find('.'+attr).val()) != '') atts.push(attr + '="' + val + '"');				
			});
			newList += '[ingredient ' + atts.join(' ') + ']';
		});

		tinyMCEPopup.editor.execCommand('mceInsertContent', false, newList);
		tinyMCEPopup.close();
	}
};

//tinyMCEPopup.onInit.add(hrecipeIngredientListDialog.init, hrecipeIngredientListDialog);
