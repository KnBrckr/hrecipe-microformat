/**
 * Plugin to manage hrecipe format in TinyMCE
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2012 Kenneth J. Brucker (email: ken@pumastudios.com)
 * 
 * This file is part of hRecipe Microformat, a plugin for Wordpress.
 *
 * Copyright 2009, Moxiecode Systems AB
 * Released under LGPL License.
 *
 * License: http://tinymce.moxiecode.com/license
 * Contributing: http://tinymce.moxiecode.com/contributing
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

// TODO Allow editor buttons to be manipulated by TinyMCE configuration panel(s)

(function() {	
	// Load plugin specific language pack	
	tinymce.PluginManager.requireLangPack('hrecipeMicroformat');
	
	tinymce.create('tinymce.plugins.hrecipeMicroformatPlugin', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */
		init : function(ed, url) {
			// =============================
			// = Shortcode editor handling =
			// =============================
			// When cursor is in a shortcode, select the entire node
			shortcodeClasses = ['fn'];
			ed.onNodeChange.add(function(ed, cm, n, co) {
				tinymce.each(shortcodeClasses, function(v,i) {
					if (ed.dom.hasClass(n, v)) {
						ed.selection.select(n);
						return;
					}
				});
			});
			
			// ================
			// = Recipe Title =
			// ================

			// Register the commands so that they can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');
			ed.addCommand('mceHrecipeTitle', function() {
				if (jQuery(ed.dom.doc.documentElement).find('*:contains("[hrecipe_title]")').length === 0) {
					title = ed.dom.create('h3',{'class':'fn'},'[hrecipe_title]');
					ed.selection.setNode(title);
				} else {
					alert(ed.getLang('hrecipeMicroformat.titlePresent'));
				}
			});

			// Register buttons
			ed.addButton('hrecipeTitle', {
				title : 'hrecipeMicroformat.buttonTitle',
				cmd : 'mceHrecipeTitle',
				image : url + '/img/hrecipeTitle.gif'
			});
			
			// When switching to HTML editor, cleanup H3 content surrounding the title - only want to display the shortcode
			jQuery('body').bind('afterPreWpautop', function(e, o){
				o.data = o.data
					.replace(/<h3[\s\S]+?\[hrecipe_title\]<\/h3>/g, '[hrecipe_title]');
			});
			
			// When content is inserted, wrap [hrecipe_title] shortcode with <h3>
			ed.onBeforeSetContent.add(function(ed, o) {
				o.content = o.content.replace(/(\[hrecipe_title\])/,'<h3 class="fn">$1</h3>');
      });
						
			// ===================
			// = Ingredient List =
			// ===================

			// Register command to open dialog so that it can be invoked by using tinyMCE.activeEditor.execCommand();
			ed.addCommand('mceHrecipeIngredientList', function() {
				ed.windowManager.open({
					file : url + '/ingredient_list.html',
					width : 700 + parseInt(ed.getLang('hrecipeIngredientList.delta_width', 0),10),
					height : 450 + parseInt(ed.getLang('hrecipeIngredientList.delta_height', 0),10),
					inline : 1
				}, {
					plugin_url : url // Plugin absolute URL
				});
			});
			
			// Register command to init dynamic content handling on a dom element or elements
			ed.addCommand('mceHrecipeSetupIngrdList', function(ui,n) {
				// Highlight ingredients sections when cursor hovers
				jQuery(n).hover(
					function() {
						jQuery(this).addClass('ui-state-hover');
					},
					function() {
						jQuery(this).removeClass('ui-state-hover');
					}
				);
				
				// On click inside ingredients
				jQuery(n).click(
					function() {
						ed.selection.select(this);
						ed.execCommand('mceHrecipeIngredientList',true);
					}
				);
			});

			// Register Ingredient List button
			ed.addButton('hrecipeIngredientList', {
				title : 'hrecipeMicroformat.buttonIngrdList',
				cmd : 'mceHrecipeIngredientList',
				image : url + '/img/hrecipeIngredientList.gif'
			});
			
			// Setup dynamic handling for Ingredient lists
			// TODO - How to treat content as an opaque object in editor window to move cursor past?
			//				Protect content in the HTML tab - filter when switching between views?
			ed.onSetContent.add(function(ed, o) {
				var ingrds = ed.dom.select('.ingredients');
				ed.execCommand('mceHrecipeSetupIngrdList', false, ingrds);
			});
						
		}, // End init
		
		/**
		 * Creates control instances based in the incoming name. This method is normally not
		 * needed since the addButton method of the tinymce.Editor class is a more easy way of adding buttons
		 * but you sometimes need to create more complex controls like listboxes, split buttons etc then this
		 * method can be used to create those.
		 *
		 * @param {String} n Name of the control to create.
		 * @param {tinymce.ControlManager} cm Control manager to use inorder to create new control.
		 * @return {tinymce.ui.Control} New control instance or null if no control was created.
		 */
		createControl : function(n, cm) {
			return null;
		},

		/**
		 * Returns information about the plugin as a name/value array.
		 * The current keys are longname, author, authorurl, infourl and version.
		 *
		 * @return {Object} Name/value array containing information about the plugin.
		 */
		getInfo : function() {
			return {
				longname : 'hrecipe-microformat plugin',
				author : 'Kenneth J. Brucker',
				authorurl : 'http://action-a-day.com',
				infourl : 'http://action-a-day.com/hrecipe-microformat',
				version : "0.1"
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add('hrecipeMicroformat', tinymce.plugins.hrecipeMicroformatPlugin);
})();