<?php
/**
 * hrecipe_recipes_widget class
 *
 * WP_Widget Class extension to generate recipe lists
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2015 Kenneth J. Brucker (email: ken@pumastudios.com)
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
	die( 'I don\'t think you should be here.' );
}

class hrecipe_recipes_widget extends WP_Widget {
	/**
	 * Default number of recipes to display
	 *
	 * @var int
	 */
	const DEFAULT_NUM_RECIPES = 5;
	
	/**
	 * Array of supported list types
	 *
	 * @var string
	 */
	var $list_types = array('published', 'edited'); // First item is the default

	/**
	 * Register Widget with Wordpress
	 */
	public function __construct() {
		parent::__construct(
	 		'hrecipe_recipes_widget', // Base ID
			'Recent Recipes', // Name
			array( 'description' => 'Display list of recent recipes', ) // Args
		);
	}

	/**
	 * Back-end Widget Form
	 *
	 * Outputs content of the Widget
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database
	 */
 	public function form( $instance ) {
		// Establish defaults if not already set in input 
		$defaults = array(
			'title' => '', 
			'num_recipes' => self::DEFAULT_NUM_RECIPES,
			'list_type' => 'published'
		);
		
		$instance = wp_parse_args( (array) $instance, $defaults );
		$list_type = $instance['list_type'];
		
		?>
		<p>
			<label for="<?php echo esc_attr($this->get_field_id( 'title' )); ?>"><?php _e( 'Title:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>
        <p><label for="<?php echo esc_attr($this->get_field_id('list_type')); ?>">List Type</label>
            <select id="<?php echo esc_attr($this->get_field_id('list_type')); ?>" name="<?php echo esc_attr($this->get_field_name('list_type')); ?>">
                <option value="published" <?php if ($list_type == "published"): ?>selected<?php endif; ?>>Recently Published</option>
                <option value="edited" <?php if ($list_type == "edited"): ?>selected<?php endif; ?>>Recently Edited</option>
            </select>
        </p>
        <p>
			<label for="<?php echo esc_attr($this->get_field_id('num_recipes')); ?>">Number of Recipes</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('num_recipes')); ?>" 
				   type="number" min="1" max="10" step="1" 
                   value="<?php echo $instance['num_recipes']; ?>" name="<?php echo esc_attr($this->get_field_name('num_recipes')); ?>"/>
		</p>
		<?php
	}

	/**
	 * Sanitize Widget form values as they are saved
	 *
	 * @see WP_Widget::update()
	 * 
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$new_instance = (array) $new_instance;
		
		$instance = array();
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['num_recipes'] = intval($new_instance['num_recipes']) ? intval($new_instance['num_recipes']) :  self::DEFAULT_NUM_RECIPES ;

		/**
		 * Only allow the defined list types
		 */
		$instance['list_type'] = in_array($new_instance['list_type'], $this->list_types) ? 
			$new_instance['list_type'] : $this->list_types[0];
		
		return $instance;
	}
	
	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		/**
		 * @var string $before_widget HTML to display before widget
         * @var string $after_widget HTML to display after widget
         * @var string $before_title HTML to display before title
         * @var string $after_title HTMN to display after title
		 */
		extract( $args );

		$title = apply_filters('widget_title', empty( $instance['title'] ) ? $this->name : $instance['title'], $instance, $this->id_base);
		
		$num_recipes = empty($instance['num_recipes']) ? self::DEFAULT_NUM_RECIPES : $instance['num_recipes'];
		$list_type = empty($instance['list_type']) ? $this->list_types[0] : $instance['list_type'];
		
		$recipes = $this->get_post_list($num_recipes, $list_type);

		if (count($recipes) > 0) {
			// FIXME Add classes to list elements for recipe lists
			$out = '<ul>';
			foreach ($recipes as $recipe) {
				$out .= sprintf('<li><a href="%s">%s</a></li>', esc_url($recipe['url']), esc_attr($recipe['title']));
			}
			$out .= '</ul>';
		
			echo $before_widget;
			if ( $title ) echo $before_title . $title . $after_title;
			echo $out;
			echo $after_widget;
		}
	}
	
	/**
	 * Retrieve list of recent recipes
	 *
	 * @param $count, int Number of recipes to return
	 * @param $list_type, string List type to return (recently published, edited)
	 * @return array, 
	 */
	private function get_post_list($count, $list_type)
	{
		global $hrecipe_microformat;
		
		$items = array();
		
		switch($list_type) {
		case 'edited':
			$order_by = 'modified';
			break;
			
		case 'published':
		default:
			$order_by = 'date';
			break;
		}
		
        $query = new WP_Query(array(
            'orderby' => $order_by,
            'posts_per_page' => $count,
            'ignore_sticky_posts' => 1,
			'post_type' => $hrecipe_microformat::post_type
        ));
		
        $recent_recipes = $query->get_posts();
        foreach ($recent_recipes as $p) {
            $temp_item = array(
				'id' => $p->ID,
				'title' => $p->post_title, 
				'url' => get_permalink($p->ID), 
				'date' => get_the_time('Y-m-d', $p->ID),
			);
            // if ($display_thumb == 1) {
            //     $temp_item['image'] = $this->_get_featured_image($p->ID);
            // }
            array_push($items, $temp_item);
        }
        $items = array_slice($items, 0, $count);
        return $items;
	}
}

add_action( 'widgets_init', create_function( '', 'register_widget( "hrecipe_recipes_widget" );' ) );
?>