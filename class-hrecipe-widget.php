<?php
/**
 * Create Widget for Recipe data
 *
 * The widget is only of use when a single recipe page is being displayed
 *
 */

class hrecipe_widget extends WP_Widget {

	/**
	 * Register Widget with Wordpress
	 */
	public function __construct() {
		parent::__construct(
	 		'hrecipe_widget', // Base ID
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
		// Establish defaults if not already set in input 
		global $hrecipe_microformat;
		
		$defaults = array('title' => '');

		$fields = $hrecipe_microformat->get_recipe_fields();
		foreach ($fields as $key => $label) {
			$defaults[$key] = false;
		}
		
		$instance = wp_parse_args( (array) $instance, $defaults );
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>
		<p>
			<?php
			// For each Recipe field, setup a checkbox to include that item in the instance of the widget
			foreach ($fields as $key => $label) {
				?>
				<input class="checkbox" type="checkbox" <?php checked($instance[$key], true) ?> id="<?php echo $this->get_field_id($key); ?>" name="<?php echo $this->get_field_name($key); ?>" />
				<label for="<?php echo $this->get_field_id($key); ?>"><?php echo $label; ?></label><br />
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
		global $hrecipe_microformat;
		
		$new_instance = (array) $new_instance;
		
		$instance = array();
		$instance['title'] = strip_tags($new_instance['title']);
		
		$fields = $hrecipe_microformat->get_recipe_fields();
		foreach ($fields as $key => $label) {
			$instance[$key] = isset( $new_instance[$key] ) ? true : false;
		}
		
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
		global $hrecipe_microformat;
		
		// This widget only works when displaying a single recipe post
		if ( ! is_singular($hrecipe_microformat->get_post_type() ) ) {
			return;
		}
		
		extract( $args );
		
		$post_id = $hrecipe_microformat->get_post_id();
		
		$title = apply_filters('widget_title', empty( $instance['title'] ) ? 'Recipe Info' : $instance['title'], $instance, $this->id_base);
		
		$fields = $hrecipe_microformat->get_recipe_fields();
		$out = '';
		foreach ($fields as $key => $label) {
			if ( isset($instance[$key]) && $instance[$key] ) {
				$out .= $hrecipe_microformat->get_recipe_field_html($key, $post_id);
			}
		}
		
		if ( !empty( $out ) ) {
			echo $before_widget;
			if ( $title ) echo $before_title . $title . $after_title;
			echo $out;
			echo $after_widget;
		}
	}
}

add_action( 'widgets_init', create_function( '', 'register_widget( "hrecipe_widget" );' ) );
?>