/**
 * Primary Javascript for hrecipe microformat plugin
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2011 Kenneth J. Brucker (email: ken@pumastudios.com)
 * 
 * This file is part of hRecipe Microformat, a plugin for Wordpress.
 *
 * Copyright 2011  Kenneth J. Brucker  (email : ken@pumastudios.com)
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
	var metric = ['g', 'kg', 'l', 'ml'];
	
	//Display non-metric values in fractions
	jQuery('.ingredient').each(function(i,e){
		var value = jQuery(this).find('.value');
		var type = jQuery(this).find('.type');
		if (jQuery.inArray(type.text().toLowerCase(), metric) >= 0) { return; } // If type is in metric array, skip processing
		value.text(value.text().replace(/(\d*)\.(\d*)/g, decimal2fraction));
	});
	
	// Fixup display of fractions in ingredients
	jQuery('.ingredient .value').html(function(i,value){
		return fancyFraction(value);
	});
	
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

// Improved formating of fractions
function fancyFraction(value) {
	return value.replace(/(\d*)\/(\d*)/g,'<sup>$1</sup>&frasl;<sub>$2</sub>');
}

/**
 * Convert decimal to common fractions based on unit type
 *
 * In imperial measurements:
 *   cups are rarely displayed as 3/8 or 5/8.  1/8 cup == 2 Tbs
 *   tsp, tbs never use 1/3 or 2/3 measurements.  spoons are generally graduated in 1/8 intervals.
 * See:
 *  http://allrecipes.com/HowTo/Commonly-Used-Measurements--Equivalents/Detail.aspx
 *  http://allrecipes.com/HowTo/recipe-conversion-basics/detail.aspx
 *  http://www.jsward.com/cooking/conversion.shtml
 * 
 * Common fractions used in cooking:  
 *     1/8 = .125
 *     1/4 = .25
 *     1/3 = .333333...
 *     3/8 = .375
 *     1/2 = .5
 *     5/8 = .625
 *     2/3 = .666666...
 *     3/4 = .75
 *     7/8 = .875
 *
 * 
 */
//To mathmatically differentiate between 5/8 and 2/3, use binary sort to 1/64 accuracy
var sixtyfourths = [
	'0',   // 0/64
	'0','0','0','0','1/8','1/8','1/8',
	'1/8', // 8/64
	'1/8','1/8','1/8','1/8','1/4','1/4','1/4',
	'1/4', // 16/64
	'1/4','1/4','1/4','1/4','1/3','1/3','3/8',
	'3/8', // 24/64
	'3/8','3/8','3/8','3/8','1/2','1/2','1/2',
	'1/2', // 32/64
	'1/2','1/2','1/2','1/2','5/8','5/8','5/8',
	'5/8', // 40/64
	'5/8','2/3','2/3','3/4','3/4','3/4','3/4',
	'3/4', // 48/64
	'3/4','3/4','3/4','7/8','7/8','7/8','7/8',
	'7/8', // 56/64
	'7/8','7/8','7/8','1','1','1','1',
	'1'    // 64/64
	];
function decimal2fraction(str, i, f, s) {
	f = '.' + f; // Put the leading decimal back on
	var numerator = 32;
	var step = 16;
	while (step >= 1) {
		if (f > numerator/64) {
			numerator += step;
		} else {
			numerator -= step;
		}
		step /= 2;
	}
	if ('1' == sixtyfourths[numerator] || '0' == sixtyfourths[numerator]) {
		return i + sixtyfourths[numerator];
	} else if (i > 0) {
		return i + " " + sixtyfourths[numerator];
	} else {
		return sixtyfourths[numerator];
	}
}


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