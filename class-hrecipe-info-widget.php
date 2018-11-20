<?php
/**
 * hrecipe_info_widget class
 *
 * WP_Widget Class extension to create Widget for Recipe infomation
 *
 * The widget is only of use when a single recipe page is being displayed
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
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die( 'I don\'t think you should be here.' );
}

class hrecipe_info_widget extends WP_Widget {

	/**
	 * Register Widget with Wordpress
	 */
	public function __construct() {
		parent::__construct(
			'hrecipe_info_widget', // Base ID
			'Recipe Info', // Name
			array( 'description' => 'Use this widget on Recipe Pages to display information about the recipe', ) // Args
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
		/**
		 * @var hrecipe_microformat $hrecipe_microformat
		 */
		global $hrecipe_microformat;

		// Establish defaults if not already set in input
		$defaults = array( 'title' => '' );

		$fields = $hrecipe_microformat->get_recipe_fields();
		foreach ( $fields as $key => $label ) {
			$defaults[ $key ] = false;
		}

		$instance = wp_parse_args( (array) $instance, $defaults );
		?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title:' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
                   name="<?php echo $this->get_field_name( 'title' ); ?>" type="text"
                   value="<?php echo esc_attr( $instance['title'] ); ?>"/>
        </p>
        <p>
			<?php
			// For each Recipe field, setup a checkbox to include that item in the instance of the widget
			foreach ( $fields as $key => $label ) {
				?>
                <input class="checkbox" type="checkbox" <?php checked( $instance[ $key ], true ) ?>
                       id="<?php echo $this->get_field_id( $key ); ?>"
                       name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>"/>
                <label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_attr( $label ); ?></label>
                <br/>
				<?php
			}
			?>
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
		/**
		 * @var hrecipe_microformat $hrecipe_microformat
		 */
		global $hrecipe_microformat;

		$new_instance = (array) $new_instance;

		$instance          = array();
		$instance['title'] = strip_tags( $new_instance['title'] );

		$fields = $hrecipe_microformat->get_recipe_fields();
		foreach ( $fields as $key => $label ) {
			$instance[ $key ] = isset( $new_instance[ $key ] ) ? true : false;
		}

		return $instance;
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		/**
		 * @var hrecipe_microformat $hrecipe_microformat
		 */
		global $hrecipe_microformat;

		// This widget only works when displaying a single recipe post
		if ( ! is_singular( $hrecipe_microformat->get_post_type() ) ) {
			return;
		}

		/**
		 * @var string $before_widget
		 * @var string $after_widget
		 * @var string $before_title
		 * @var string $after_title
		 */
		extract( $args );

		$post_id = $hrecipe_microformat->get_post_id();

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? $this->name : $instance['title'], $instance, $this->id_base );

		$fields = $hrecipe_microformat->get_recipe_fields();
		$out    = '';
		foreach ( $fields as $key => $label ) {
			if ( '' <> $out ) {
				$out .= '<br>';
			}
			if ( isset( $instance[ $key ] ) && $instance[ $key ] ) {
				$out .= $hrecipe_microformat->get_recipe_field_html( $key, $post_id );
			}
		}

		if ( ! empty( $out ) ) {
			echo $before_widget;
			if ( $title ) {
				echo $before_title . $title . $after_title;
			}
			echo $out;
			echo $after_widget;
		}
	}
}

// FIXME create_function() is deprecated
add_action( 'widgets_init', create_function( '', 'register_widget( "hrecipe_info_widget" );' ) );
