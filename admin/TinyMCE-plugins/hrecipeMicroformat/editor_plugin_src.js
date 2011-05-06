/**
 * editor_plugin_src.js
 *
 * Copyright 2011, Kenneth J. Brucker
 *
 * Copyright 2009, Moxiecode Systems AB
 * Released under LGPL License.
 *
 * License: http://tinymce.moxiecode.com/license
 * Contributing: http://tinymce.moxiecode.com/contributing
 */

// TODO Syntax check for balanced shortcodes and mark any unbalanced in visual editor.

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
			// ================
			// = Recipe Title =
			// ================

			// Register the commands so that they can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');
			ed.addCommand('mceHrecipeTitle', function() {
				if (jQuery(ed.dom.doc.documentElement).find('*:contains("[hrecipe_title]")').length === 0) {
					title = ed.dom.create('h3',{'class':'fn mceNonEditable'},'[hrecipe_title]');
					ed.selection.setNode(title);
				} else {
					alert(ed.getLang('hrecipeMicroformat.titlePresent'));
				}
			});

			// Register buttons
			ed.addButton('hrecipeTitle', {
				title : 'hrecipeMicroformat.titleDesc',
				cmd : 'mceHrecipeTitle',
				image : url + '/img/hrecipeTitle.gif' // FIXME Need GIF
			});
			
			// When switching to HTML editor, cleanup H3 content surrounding the title - only want to display the shortcode
			jQuery('body').bind('afterPreWpautop', function(e, o){
				o.data = o.data
					.replace(/<h3[\s\S]+?\[hrecipe_title\]<\/h3>/g, '[hrecipe_title]');
			}).bind('beforeWpautop', function(e, o){
				o.data = o.data
					.replace(/\[hrecipe_title\]/,'<h3 class="fn mceNonEditable">[hrecipe_title]</h3>');
			});
			
			//FIXME Need filter on new content to setup node with <h3> formatting for visual display
			
			// // On content change, 
			// ed.onChange.add(function(ed, l) {
			//               console.debug('Editor contents was modified. Contents: ' + l.content);
			//       });
						
			// ==============
			// = Ingredient =
			// ==============
			
			// TODO Implement Ingredient Button
			
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
						ed.execCommand('mceHrecipeIngredientList',true);
					}
				);
			});

			// Register Ingredient List button
			ed.addButton('hrecipeIngredientList', {
				title : 'hrecipeMicroformat.ingrdListDesc',
				cmd : 'mceHrecipeIngredientList',
				image : url + '/img/hrecipeIngredientList.gif' // FIXME Need GIF
			});
			
			// Setup dynamic handling for Ingredient lists
			// TODO - How to treat content as an opaque object in editor window to move cursor past?
			//				Protect content in the HTML tab - filter when switching between views?
			ed.onSetContent.add(function(ed, o) {
				var ingrds = ed.dom.select('.ingredients');
				ed.execCommand('mceHrecipeSetupIngrdList', false, ingrds);
			});
			
			// TODO Add & Remove the mceNonEditable class from ingredient list`
			// TODO Implement Ingredients as shortcodes and edit content on switching editor modes for display

			// When inside an ingredients list...
			// ed.onNodeChange.add(function(ed, cm, n, co) {
			// 	var ingrds = ed.dom.getParents(n, '.ingredients');
			// 	if (ingrds.length > 0) {
			// 		// Have an ingredients section - Is it already marked?
			// 		var marked = ed.dom.select('.h-dblClickText', n);
			// 		if (0 === marked.length) {
			// 			ed.dom.remove(ed.dom.select('.h-dblClickText')); // Remove from elsewhere		
			// 			ed.dom.add(ingrds, 'span', {'class': 'h-dblClickText'}, ed.getLang('hrecipeMicroformat.dblClick',0));
			// 		}
			// 	} else {
			// 		ed.dom.remove(ed.dom.select('.h-dblClickText')); // Remove
			// 	}
			// });
			
			// TODO - Drag, drop lists as a unit. - Use jQuery?
			// ed.onNodeChange.add(function(ed, cm, n, co) {
			// 	jQuery(n).closest('.ingredients').each(function() {
			// 		try {ed.selection.select(this);} catch(e) {}
			// 	});
			// });
			
			// ================
			// = Instructions =
			// ================
			
			// FIXME Implement Instructions
			
			// ========
			// = Hint =
			// ========
			
			// TODO Implement HINT as a shortcode
			// Register the commands so that they can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');
			ed.addCommand('mceHrecipeHint', function() {
				var n = ed.selection.getNode();
				hint = jQuery(n).closest('.hrecipe-hint');
				if (hint.length > 0) {
					// In a hint section... remove the aside tags
					hint.closest('div').replaceWith(hint.html()); // closest() to work around tinymce defect
				} else {
					// TODO Ideally want to use only an ASIDE tag here, but tinymce is adding "&nbsp;" text around it.
					//      When the DIV is removed, also remove the closest() portion from replaceWith above
					ed.execCommand('mceReplaceContent', false, '<DIV><ASIDE  CLASS="hrecipe-hint">{$selection}</ASIDE></DIV>');
				}				
			});

			// Register buttons
			ed.addButton('hrecipeHint', {
				title : 'hrecipeMicroformat.hintDesc',
				cmd : 'mceHrecipeHint',
				image : url + '/img/hrecipeHint.gif' //FIXME need gif
			});
			
			// Add a node change handler, selects the button in the UI when in an aside or text is selected
			ed.onNodeChange.add(function(ed, cm, n, co) {
				cm.setDisabled('hrecipeHint', co && n.nodeName != 'ASIDE');
				cm.setActive('hrecipeHint', n.nodeName == 'ASIDE' && jQuery(n).hasClass('hrecipe-hint'));
			});
			
			// ======================================
			// = Next/Prev Step for fullscreen mode =
			// ======================================
			
			// TODO Provide prev & next buttons for editing steps in full screen mode
		}, // End init
		
		/**
		 * Creates control instances based in the incomming name. This method is normally not
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
				infourl : 'http://action-a-day.com/hrecipe-microformat', // FIXME setup home for the plugin
				version : "0.5"
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add('hrecipeMicroformat', tinymce.plugins.hrecipeMicroformatPlugin);
})();