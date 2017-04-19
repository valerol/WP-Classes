<?php 

class Netgon_Customize_Section
{
	public $name;
	public $title;
	public $order = 10;
	public $options;
	
	function __construct( $args ) {
		
		foreach ( $args as $key => $value ) {
			
			if ( ! empty( $value ) ) {
			
				$this->$key = $value;
			}
		}	
	}
	
	function add_section( $panel ) {
	
		global $wp_customize;
		
		$wp_customize->add_section( $this->name, array(
			'title'          => $this->title,
			'priority'       => $this->order,
			'panel' => $panel,
		) );
		
		foreach ( $this->options as $option ) {
		
			$wp_customize->add_setting( $option->setting, array(
				'type' => 'option',
				'sanitize_callback' => $option->callback
			) );
			
			if ( in_array( $option->type, array( 'checkbox', 'radio', 'select' ) ) ) {
			
				$wp_customize->add_control( $option->setting, array(
					'label'    => $option->label,
					'description' => $option->description,
					'type' => $option->type,
					'section'  => $this->name,
					'settings' => $option->setting,
					'choices' => $option->choices
				) );			
			
			} elseif ( $option->type == 'image' ) {
				
				$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $option->setting, array(
					'label'    => $option->label,
					'section'  => $this->name,
					'settings' => $option->setting
				) ) );
				
			} else {			
				
				$wp_customize->add_control( $option->setting, array(
					'label'    => $option->label,
					'description' => $option->description,
					'type' => $option->type,
					'section'  => $this->name,
					'settings' => $option->setting,
				) );
			}	
		}
	}
}

class Netgon_Option
{	
	public $setting;
	public $label;
	public $description = '';
	public $callback = 'esc_html';
	public $type = 'text';
	public $choices;
	public $default;
	
	function __construct( $args ) {
	
		foreach ( $args as $key => $value ) {
			
			if ( ! empty( $value ) ) {
			
				$this->$key = $value;
			}
		}
		
		switch ( $this->type ) {
		
			case 'url' :
			case 'image' :
				$this->callback = 'esc_url';
				break;
			
			case 'number' :
				$this->callback = 'absint';
				break;
			
			case 'email' :
				$this->callback = 'sanitize_email';
				break;
				
			case 'textarea' :
				$this->callback = 'esc_textarea';
				break;
		}
	}
}
