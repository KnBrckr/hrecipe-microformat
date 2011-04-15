/**
 * hrecipe admin javascript
 **/

// Make recipe field sections sortable to configure head and footer contents.
jQuery(document).ready( function($) {
	$(recipe_field_sections.join(',')).sortable(
		{
			items: '.menu-item-handle',
			connectWith: '.recipe-fields', 
			update: function(event, ui) {
				jQuery.each(recipe_field_sections, function(index, value) {
					var new_list = jQuery(value + ' li').map(function(){return this.attributes['name'].value;}).get().join();
					jQuery(value + ' input').attr('value', new_list);
				});
			}
		}
		);
});
