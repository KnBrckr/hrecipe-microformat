<?php
$lang_file = dirname(__FILE__) . '/' . $mce_locale . '_dlg.js';

if ( is_file($lang_file) && is_readable($lang_file) )
	// Found language file for defined locale, use it
	$strings = hrecipe_microformat::get_file($lang_file);
else {
	// Use english locale file, treat it as the defined locale
	$strings = $hrecipe_microformat::get_file(dirname(__FILE__) . '/en_dlg.js');
	$strings = preg_replace( '/([\'"])en\./', '$1'.$mce_locale.'.', $strings, 1 );
}
