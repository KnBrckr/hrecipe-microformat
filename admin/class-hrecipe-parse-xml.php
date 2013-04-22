<?php
/**
 * parse_xml Class
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2011-2013 Kenneth J. Brucker (email: ken@pumastudios.com)
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
	public  $error_msg;
	private $tags;

	function __construct() {
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
		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;

		if (!$doc->load($path)) {
			$this->error_msg = "Cannot open XML data file: '$path'";
			return NULL;
		}

		return $this->xml_to_array($doc->documentElement);
	}

	/**
	 * Recursively parse XML data to build array representation
	 *
	 * @param $node_xml DOMDocument element
	 * @return array representing XML data
	 **/
	private function xml_to_array($node_xml) {
		$node_array = array();
		$hits_array = array();
		
		/**
		 * For each sibling node get associated contents and attributes
		 */
		while ($node_xml != NULL) {
			/**
			 * Map tag names to alternates provided
			 */
			$tag = strtoupper($node_xml->nodeName);
			if (isset($this->tags) && array_key_exists($tag, $this->tags)) $tag = $this->tags[$tag];
			
			/**
			 * Text nodes are end of chain, just get their value
			 */
			if (XML_TEXT_NODE == $node_xml->nodeType) {
				$node_val = $node_xml->nodeValue;
			} else {
				/**
				 * Assume this is XML_ELEMENT_NODE type
				 * Recurse on children of this node, result will be an array as long as child is not only a single text node
				 */
				$node_val = $this->xml_to_array($node_xml->firstChild);				

				/**
				 * Collect node attributes
				 */
				if ($node_xml->hasAttributes()) {
					/**
					 * If we don't have an array, there is a problem
					 */
					if (! is_array($node_val)) {
						$this->error_msg = "XML format error: Found tag <$tag>, a text node masquerading as an element node";
						return NULL;
					}
					
					$attr_array = array();
				
					foreach ($node_xml->attributes as $attrib) {
						$attr_array[strtoupper($attrib->nodeName)] = $attrib->nodeValue;
					}
					$node_val['@attrib'] = $attr_array;
				}
			}
			
			/**
			 * If hitting tag again, value is saved in an array
			 */
			if (array_key_exists($tag, $node_array)) {
				/**
				 * If seeing the key for the 2nd time, convert to an array
				 */
				if (1 == $hits_array[$tag]) {
					$curr_val = $node_array[$tag];
					$node_array[$tag] = array($curr_val);
					$hits_array[$tag]++;
				}
				$node_array[$tag][] = $node_val;
			} else {
				$node_array[$tag] = $node_val;
				$hits_array[$tag] = 1;
			}
			
			/**
			 * Go to next sibling
			 */
			$node_xml = $node_xml->nextSibling;
		}
		
		/**
		 * If result is an empty array, return NULL
		 * If result is an array with a single text node, collapse the array and just return the text
		 */
		if (count($node_array) == 0) {
			return NULL;
		} elseif (count($node_array) == 1 && array_key_exists('#TEXT', $node_array)) {
			return $node_array['#TEXT'];
		} else {
			return $node_array;
		}
	}
} // end of class parse_xml
?>