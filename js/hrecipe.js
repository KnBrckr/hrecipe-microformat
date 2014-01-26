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
	$('.recipe-user-rating').stars({
		cancelShow: false,
		inputType: 'select',
		callback: function(ui, type, value)
		{
			// Thank for the vote
			$('#recipe-stars-' + hrecipeVars.postID + ' .thank-you').fadeIn(200);
			
			$.post(
				hrecipeVars.ajaxurl, 
				{
					action: hrecipeVars.pluginPrefix + 'recipe_rating',
					postID: hrecipeVars.postID,
					rating: value, // New rating value
					prevRating: hrecipeVars.userRating, // Last saved rating value from user
					ratingNonce: hrecipeVars.ratingNonce
				}, 
				function(json)
				{
					// TODO Handle server errors, timeouts, etc.
					
					recipeRating = jQuery('#recipe-stars-' + json.postID);

					// After vote tally, blank out the message
					recipeRating.find('.thank-you').delay(3000).fadeOut(800);
					
					// Use new nonce that was generated for next AJAX request
					hrecipeVars.ratingNonce = json.nonce;
					
					// Save users updated rating
					hrecipeVars.userRating = json.userRating;
					
					// Setup new Nonce field so user can change their vote
					hrecipeVars.ratingNonce = json.ratingNonce;

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
	
	// Setup buttons for recipe unit conversion
	$('.ingredients-display-as button').
	button().click(function(e){
		var displayMeasure = 'measure-';
		// Value of button determines which measures to display
		displayMeasure += jQuery(this).val(); 
		// Hide measure currently displayed
		$('.ingredients .selected-measure').removeClass('selected-measure');
		// ... and show the desired target
		$('.ingredients .' + displayMeasure).addClass('selected-measure');

		e.preventDefault(); // Don't need default handler to fire
		return false;
	});
});