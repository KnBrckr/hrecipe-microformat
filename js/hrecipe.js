/**
 * Primary Javascript for hrecipe microformat plugin
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2011-2013 Kenneth J. Brucker (email: ken@pumastudios.com)
 * 
 * This file is part of hRecipe Microformat, a plugin for Wordpress.
 *
 * Copyright 2011-2013  Kenneth J. Brucker  (email : ken@pumastudios.com)
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

jQuery(document).ready( function($) {
	// Format recipe ratings with jQuery UI stars plugin
	jQuery('.recipe-user-rating').stars({
		cancelShow: false,
		inputType: 'select',
		callback: function(ui, type, value)
		{
			// Thank for the vote
			jQuery('#recipe-stars-' + HrecipeMicroformat.postID + ' .thank-you').fadeIn(200);
			
			jQuery.post(
				HrecipeMicroformat.ajaxurl, 
				{
					action: HrecipeMicroformat.pluginPrefix + 'recipe_rating',
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

// jQuery("#stars-wrapper1").stars({
//     cancelShow: false,
//     captionEl: jQuery("#stars-cap"),
//     callback: function(ui, type, value)
//     {
//         jQuery.getJSON("ratings.php", {rate: value}, function(json)
//         {
//             jQuery("#fake-stars-on").width(Math.round( jQuery("#fake-stars-off").width() / ui.options.items * parseFloat(json.avg) ));
//             jQuery("#fake-stars-cap").text(json.avg + " (" + json.votes + ")");
//         });
//     }
// });