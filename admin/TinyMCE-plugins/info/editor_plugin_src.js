/**
 * editor_plugin_src.js
 *
 * Copyright 2009, Moxiecode Systems AB
 * Released under LGPL License.
 *
 * License: http://tinymce.moxiecode.com/license
 * Contributing: http://tinymce.moxiecode.com/contributing
 */

(function() {
	// Load plugin specific language pack	
	//	tinymce.PluginManager.requireLangPack('example');

	tinymce.create('tinymce.plugins.hrecipeTitlePlugin', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */
		init : function(ed, url) {
			// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');
			ed.addCommand('mceHrecipeTitle', function() {
				var n = ed.selection.getNode();

				if ('H1' == n.nodeName) {
					// Selector is inside an H1 so toggle Recipe Title class (fn)
					if (ed.dom.hasClass(n, 'fn')) {
						ed.dom.removeClass(n, 'fn');
					} else {
						ed.dom.addClass(n, 'fn');
					}
				} else {
					// Wrap the selection in Recipe Title microformat
					ed.execCommand('mceReplaceContent', false, '<H1 CLASS="fn">{$selection}</H1>');
				}
			});

			// Register example button
			ed.addButton('hrecipeTitle', {
				title : 'hrecipeTitle.desc',
				cmd : 'mceHrecipeTitle',
				image : url + '/img/hrecipeTitle.gif'
			});

			// Add a node change handler, selects the button in the UI when a recipe Title is selected
			ed.onNodeChange.add(function(ed, cm, n, co) {
				cm.setDisabled('hrecipeTitle', co && n.nodeName != 'H1');
				cm.setActive('hrecipeTitle', n.nodeName == 'H1' && !n.name); // FIXME - activate on class match
			});
		},

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
				longname : 'Example plugin',
				author : 'Kenneth J. Brucker',
				authorurl : 'http://action-a-day.com',
				infourl : 'http://action-a-day.com/hrecipe-microformat',
				version : "1.0"
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add('hrecipeTitle', tinymce.plugins.hrecipeTitlePlugin);
})();