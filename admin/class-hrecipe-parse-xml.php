<?php
/**
 * parse_xml Class
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2011 Kenneth J. Brucker (email: ken@pumastudios.com)
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

class hrecipe_parse_xml {
	/**
	 * The array created by the parser which can be assigned to a variable with: $varArr = $domObj->array.
	 *
	 * @var Array
	 */
	public  $array;
	public  $error_msg;
	private $parser;
	private $pointer;
	private $tags;

	function __construct() {
		$this->pointer =& $this->array;
		$this->parser = xml_parser_create("UTF-8");

		xml_set_object( $this->parser, $this );
		xml_set_element_handler( $this->parser, "tag_open", "tag_close" );
		xml_set_character_data_handler( $this->parser, "cdata" );
	}
	
	/*
	 * Establish set of alternate names for tags
	 */
	function set_tags( $tags ) {
		$this->tags = $tags;
	}

	/*
	 * Parse an XML file
	 */
	function parse( $path ) {
		if ( !( $fp = fopen( $path, "r" ) ) ) {
			$this->error_msg = "Cannot open XML data file: '$path'";
			return false;
		}

		while ( $data = fread( $fp, 4096 ) ) {
			if ( !xml_parse( $this->parser, $data, feof( $fp ) ) ) {
				$this->error_msg = sprintf( "XML error: %s at line %d",
						xml_error_string( xml_get_error_code( $this->parser ) ),
						xml_get_current_line_number( $this->parser ) );
				xml_parser_free( $this->parser );
				return false;
			}
		}

		xml_parser_free( $this->parser );
		fclose($fp);
		return $this->array;
	}

  /*
   * Parse XML from a string
   */
	function parseString( $data ) {
		xml_parse( $this->parser, $data );
		return $this->array;
	}

	private function tag_open( $parser, $tag, $attributes ) {
		// Map tag names to alternates provided
		if (isset($this->tags) && array_key_exists($tag, $this->tags)) $tag = $this->tags[$tag];
		
		$this->convert_to_array( $tag, '_' );
		$idx=$this->convert_to_array( $tag, 'cdata' );
		if ( isset( $idx ) ) {
			$this->pointer[$tag][$idx] = array( '@idx' => $idx, '@parent' => &$this->pointer );
			$this->pointer =& $this->pointer[$tag][$idx];
		}else {
			$this->pointer[$tag] = array( '@parent' => &$this->pointer );
			$this->pointer =& $this->pointer[$tag];
		}
		if ( !empty( $attributes ) ) { $this->pointer['_'] = $attributes; }
	}

	/**
	 * Adds the current elements content to the current pointer[cdata] array.
	 */
	private function cdata( $parser, $cdata ) {
		if ("\n" == $cdata) $cdata = ''; // Ignore empty elements
		if ( isset( $this->pointer['cdata'] ) ) { $this->pointer['cdata'] .= $cdata;}
		else { $this->pointer['cdata'] = $cdata;}
	}

	private function tag_close( $parser, $tag ) {
		$current = & $this->pointer;
		if ( isset( $this->pointer['@idx'] ) ) {unset( $current['@idx'] );}
		$this->pointer = & $this->pointer['@parent'];
		unset( $current['@parent'] );
		if ( isset( $current['cdata'] ) && count( $current ) == 1 ) { $current = $current['cdata'];}
		else if ( empty( $current['cdata'] ) ) { unset( $current['cdata'] ); }
	}

	/**
	 * Converts a single element item into array(element[0]) if a second element of the same name is encountered.
	 */
	private function convert_to_array( $tag, $item ) {
		if ( isset( $this->pointer[$tag][$item] ) ) {
			$content = $this->pointer[$tag];
			$this->pointer[$tag] = array( ( 0 ) => $content );
			$idx = 1;
		}else if ( isset( $this->pointer[$tag] ) ) {
				$idx = count( $this->pointer[$tag] );
				if ( !isset( $this->pointer[$tag][0] ) ) {
					foreach ( $this->pointer[$tag] as $key => $value ) {
						unset( $this->pointer[$tag][$key] );
						$this->pointer[$tag][0][$key] = $value;
					}}}else $idx = null;
		return $idx;
	}

} // end of class parse_xml
?>