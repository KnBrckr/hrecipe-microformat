<?php
$lang_file = dirname(__FILE__) . '/' . $mce_locale . '_dlg.js';

if ( is_file($lang_file) && is_readable($lang_file) )
   // Found language file for defined locale, use it
   $strings = @file_get_contents($lang_file);
else {
	$lang_file = dirname(__FILE__) . '/en_dlg.js';
	if ( is_file($lang_file) && is_readable($lang_file) ) {
		// Use english locale file, treat it as the defined locale
		$strings = @file_get_contents(dirname(__FILE__) . '/en_dlg.js');
		$strings = preg_replace( '/([\'"])en\./', '$1'.$mce_locale.'.', $strings, 1 );		
	} else {
		$strings = '';
	}
}