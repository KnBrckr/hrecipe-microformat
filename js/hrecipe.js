/**
 * hrecipe-microformat
 */

jQuery(document).ready( function($) {
	// Format recipe ratings with jQuery UI stars plugin
	$('.recipe-user-rating').stars({
		cancelShow: false,
		inputType: 'select',
		callback: function(ui, type, value)
		{
			// Thank for the vote
			jQuery('#recipe-stars-' + HrecipeMicroformat.postID + ' .thank-you').fadeIn(200);
			
			$.post(
				HrecipeMicroformat.ajaxurl, 
				{
					action: HrecipeMicroformat.ratingAction,
					postID: HrecipeMicroformat.postID,
					rating: value, // New rating value
					prevRating: HrecipeMicroformat.userRating, // Last saved rating value from user
					ratingNonce: HrecipeMicroformat.ratingNonce
				}, 
				function(json)
				{
					// TODO Handle server errors, timeouts, etc.
					
					recipeRating = jQuery('#recipe-stars-' + json.postID);

					// After vote tally, blank out the message
					recipeRating.find('.thank-you').delay(3000).fadeOut(800);
					
					// Use new nonce that was generated for next AJAX request
					HrecipeMicroformat.ratingNonce = json.nonce;
					
					// Save users updated rating
					HrecipeMicroformat.userRating = json.userRating;
					
					// Setup new Nonce field so user can change their vote
					HrecipeMicroformat.ratingNonce = json.ratingNonce;

					// Update user display with new averages
					recipeRating.removeClass('unrated');
					recipeRating.find('.recipe-stars-avg').text(json.avg.toFixed(2));
					recipeRating.find('.recipe-stars-cnt').text(json.cnt);
					
					// Update Star Average display
					recipeRating.find('.recipe-stars-on').width(Math.round(json.avg*16));
				}
			)/*  TODO - needs jQuery 1.5 .error(function(json) {
				console.log('error-side');
			})*/;
		}
	});
});

// $("#stars-wrapper1").stars({
//     cancelShow: false,
//     captionEl: $("#stars-cap"),
//     callback: function(ui, type, value)
//     {
//         $.getJSON("ratings.php", {rate: value}, function(json)
//         {
//             $("#fake-stars-on").width(Math.round( $("#fake-stars-off").width() / ui.options.items * parseFloat(json.avg) ));
//             $("#fake-stars-cap").text(json.avg + " (" + json.votes + ")");
//         });
//     }
// });