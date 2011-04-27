/**
 * hrecipe admin javascript
 **/

// counter to create unique ids based on 'hrecipe-tinymce' prefix
var hrecipeTinymceId = 1;
var hrecipeStepId = 1;  // FIXME need to start out equal to largest seen on page

jQuery(document).ready( function($) {
	// Make the recipe field sections sortable to configure head and footer contents
	if (typeof recipe_field_sections === 'array') {
		$(recipe_field_sections.join(',')).sortable(
			{
				items: '.menu-item-handle',
				connectWith: '.recipe-fields', 
				update: function(event, ui) {
					// On update, fixup the hidden input tracking contents of head and footer
					jQuery.each(recipe_field_sections, function(index, value) {
						var new_list = jQuery(value + ' li').map(function(){return this.attributes['name'].value;}).get().join();
						jQuery(value + ' input').attr('value', new_list);
					});
				}
			});		
	}

	/**
	 * Manipulate the ingredients metabox a bit
	 */
	
	var metabox = $('#hrecipe_ingredients');
	// Make the ingredients metabox contents sortable
	metabox.sortable(
		{
			items: 'tbody tr'
		});
		
	// Action to create new row
	metabox.find('.insert').live('click', function(){
		var row = $(this).closest('tr');
		var clonedRow = hrecipeCloneStep(row, true);
		
		// Put new row into the table after the current one
		row.after(clonedRow);
		// FIXME Update step order
	});
	
	// Action to delete active row
	$('.delete').live('click', function() {
		var btn = $(this);
		
		if (btn.closest('tbody').find('tr').length > 1) {
			btn.closest('tr').remove();			
			// FIXME Update step order
		}
	});
	
		
  // Use TinyMCE controls on metaboxes that request it.		
	// if ( typeof( tinyMCE ) == "object" && typeof( tinyMCE.execCommand ) == "function" ) {
	// 	$('.add-tinymce').each(function() {
	// 		id = $(this).attr('id');
	// 		if (!id) {
	// 			// element has no id, put one on it
	// 			id = 'hrecipe-tinymce-' . hrecipeTinymceId++;
	// 			$(this).attr('id', id);
	// 		}
	// 		tinyMCE.execCommand("mceAddControl", false, id);			
	//	});
	//}
});

//
// Clone an step row
//
// row (DOM element) step row to clone
//
// return cloned Row, cleaned of input values
function hrecipeCloneStep(row) {
	var clonedRow = row.clone();
	var newId = ++hrecipeStepId;

	// Prep the new row for insert
	clonedRow.find('textarea').each(function() { 
		this.value = '';
		this.id += '_' + newId;  // Make the row ID unique
		
		// Make row name unique.  Format is hrecipe_step-#
		a = this.name.split('-');
		a.splice(-1,1,newId); // Tear off the trailing number and replace with unique value
		this.name = a.join('-');
	});

	return clonedRow;
}