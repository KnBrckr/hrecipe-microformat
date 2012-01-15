<?php
/**
 * Class to parse text files from the USDA National Nutrient Database for Standard Reference
 *
 * Nutritional data from USDA National Nutrient Database for Standard Reference
 * (https://www.ars.usda.gov/Services/docs.htm?docid=8964)
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2012 Kenneth J. Brucker (email: ken@pumastudios.com)
 * 
 * This file is part of hRecipe Microformat, a plugin for Wordpress.
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

// Protect from direct execution
if (!defined('WP_PLUGIN_DIR')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

class hrecipe_usda_sr_txt {
	/**
	 * File Handle to SR Text File
	 *
	 * @var object file handle
	 **/
	private $fh;
	
	/**
	 * Constructor Function: open target SR file for processing
	 *
	 * @return void, throws exception on open failure
	 **/
	function __construct($sr_txt)
	{
		if (! is_readable($sr_txt)) {
			throw new Exception ("Required SR file is not readable: $sr_txt");
		}
		
		if (! ($this->fh = fopen($sr_txt, "r"))) {
			throw new Exception ("Unable to open $sr_txt");
		}
	}
	
	/**
	 * Destructor function
	 *
	 * @return void
	 **/
	function __destruct()
	{
		fclose($this->fh);
	}
	
	/**
	 * Retrieve next record from SR file
	 *
	 * @return array of elements from SR file record
	 **/
	function next()
	{
		if (! ($line = fgets($this->fh, 4096)) ) {
			return NULL;
		}
		
		// Split records on '^', strings are quoted by '~', last column might contain '\r' and/or '\n'
		$cols = preg_replace('/^~(.*)~[\r\n]*$/','$1', split('\^', $line));
		return $cols;
	}
}
?>