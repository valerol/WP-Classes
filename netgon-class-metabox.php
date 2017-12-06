<?php
class Netgon_Metabox 
{  
    public $theme_name;
    public $label;
    public $name;
    public $nonce_name;
    public $fields = array();
    public $post_types = array();
    public $taxonomies = array();
    
    public function __construct( $args = array() ) 
    {
        $defaults = array(
            'theme_name' => '',
            'post_types' => array( 'post' ),
            'label' => '',
            'name' => 'custom_metabox',
            'fields' => array()
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        foreach ( $args as $key => $value ) {
            $this->$key = $value;
        }
        
        $this->name = $this->theme_name . '_' . $this->name;
        $this->nonce_name = $this->name . '_nonce';
        
        foreach ( $this->post_types as $post_type ) {
            add_action( 'add_meta_boxes_' . $post_type, array( $this, 'add_to_post' ) );
        }
        
        add_action( 'save_post', array( $this, 'update_post_meta' ) );
        
        foreach ( $this->taxonomies as $taxonomy ) {
            add_action( $taxonomy . '_add_form_fields', array( $this, 'add_to_term_add' ), 10 );
            add_action( $taxonomy . '_edit_form_fields', array( $this, 'add_to_term_edit' ), 10, 2 );
        }
        
        add_action( 'created_term', array( $this, 'update_term_meta' ), 10, 2 );
        add_action( 'edit_term', array( $this, 'update_term_meta' ), 10, 2 );
        add_action( 'delete_term', array( $this, 'delete_term_meta' ) );
    }
    
    public function add_to_post( $post )
    {
        if ( count( $this->fields ) == 1 ) {
            $this->fields[ 0 ]->label = '';
        }
		
		$nonce_name = $this->nonce_name;
		
		if ( $fields = $this->render_fields( array( 'post' => $post ) ) ) {
        
			$render_metabox = function () use ( $fields, $nonce_name ) {
				
				foreach ( $fields as $field ) {
					echo $field[ 'label' ] . $field[ 'field' ];
				}
				
				echo '<input type="hidden" name="' . $nonce_name . 
					'" value="' . wp_create_nonce( __FILE__ ) . '" />';
			};
		
			add_meta_box( 
				$this->name,
				$this->label,
				$render_metabox
			);
		}
    }
    
    public function add_to_term_add() 
	{        
        if ( $fields = $this->render_fields() ) {
            echo '<div class="form-field">';
        
            foreach ( $fields as $field ) {            
                echo $field[ 'label' ] . $field[ 'field' ];
            }
            
            echo '</div>';            
        }
    }
    
    public function add_to_term_edit( $term ) 
	{        
        if ( $fields = $this->render_fields( array( 'term' => $term ) ) ) {
        
            echo '<table class="form-table"><tbody>';
        
            foreach ( $fields as $field ) {
                
                if ( $field[ 'label' ] ) {
                    echo '<th scope="row">' . $field[ 'label' ] . '</th>';
                } 
                
                echo '<td>' . $field[ 'field' ] . '</td>';
            }
            
            echo '</tbody></table>';            
        }
    }
    
    public function render_fields( $args = array() ) 
	{        
		$fields = array();
		
		if ( $this->fields ) {
		
			$defaults = array(
				'post' => '',
				'term' => ''
			);
			
			$args = wp_parse_args( $args, $defaults );
			
			foreach ( $this->fields as $field ) {
				
				$rendered_field = array();
				
				if ( $post = $args[ 'post' ] ) {
					$this->get_field_value( $post->ID, $field, 'get_post_meta' );
				}
				
				if ( $term = $args[ 'term' ] ) {
					$this->get_field_value( $term->term_id, $field, 'get_term_meta' );
				}
				
				if ( count( $this->fields ) == 1 ) {
					$field->name = $this->name;
				} else {                  
					$field->name = $this->name . '[' . $field->name . ']';
				}

				$rendering_function = 'render_field_' . $field->type;
				
				if ( $field->label ) {
				
					if ( $args[ 'post' ] ) {
						$rendered_field[ 'label' ] = 
							'<p class="post-attributes-label-wrapper">' .
								'<label class="post-attributes-label" for="' . $field->name . '">' . $field->label . '</label>' .
							'</p>';                  
					} else {
						$rendered_field[ 'label' ] = '<label for="' . $field->name . '">' . $field->label . '</label>';
					}
				} else $rendered_field[ 'label' ] = '';
				
				$rendered_field[ 'field' ] = $this->$rendering_function( $field );
				
				if ( isset( $rendered_field[ 'field' ] ) ) {
				
					$fields[] = $rendered_field;
				}
			}
		}
        
		return $fields;
    }
    
    public function get_field_value( $object_id, $field, $function_name ) 
	{
                
        if ( count( $this->fields ) == 1 ) {        
            $field->value = $function_name( $object_id, '_' . $this->name, true );
            
        } else {            
            $meta_data = $function_name( $object_id, '_' . $this->name, true );
            $field->value = ( isset( $meta_data[ $field->name ] ) ? $meta_data[ $field->name ] : '' );
        }  
    }
    
    public function update_post_meta( $post_id )
    {
        $post_type = get_post_type( $post_id );
        
        if ( in_array( $post_type, $this->post_types ) && isset( $_POST[ $this->nonce_name ] ) ) {
            
            if ( ! wp_verify_nonce( $_POST[ $this->nonce_name ], __FILE__ ) ||
                defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ||
                ! current_user_can( 'edit_post', $post_id ) ||
                ! isset( $_POST[ $this->name ] ) ) {
                
                return false;
            }     
      
            if ( get_post_meta( $post_id, '_' . $this->name ) && empty( $_POST[ $this->name ] ) ) {
                delete_post_meta( $post_id, '_' . $this->name );
                
            } else {
                update_post_meta( $post_id, '_' . $this->name, $_POST[ $this->name ] );
            }
        }
        
        return $post_id;    
    }
    
    public function update_term_meta( $term_id ) 
    {
        $term = get_term( $term_id );
        
        if ( $term && in_array( $term->taxonomy, $this->taxonomies ) ) {
        
            if ( ! isset( $_POST[ $this->name ] ) ) return false;
            
            //$data_to_save = $this->prepare_for_save();

            if ( get_term_meta( $term_id, '_' . $this->name ) && empty( $_POST[ $this->name ] ) ) {
                delete_term_meta( $term_id, '_' . $this->name );
                
            } else {
                update_term_meta( $term_id, '_' . $this->name, $_POST[ $this->name ] );
            }
        }
    }
    
    public function delete_term_meta( $term_id ) 
    {
        $term = get_term( $term_id );
        
        if ( $term && in_array( $term->taxonomy, $this->taxonomies ) ) {
            global $wpdb;
            $table = ( $term->taxonomy == 'product_cat' ) ? 'woocommerce_termmeta' : 'termmeta';
            $col =  ( $term->taxonomy == 'product_cat' ) ? 'woocommerce_term_id' : 'term_id';

            if ( $term_id && get_option( 'db_version' ) < 34370 ) {
                $wpdb->delete( $wpdb->$table, array( $col => $term_id ), array( '%d' ) );
            }
        }
    }

    function render_field_text( $field ) 
    {
        return '<input type="text" name="' . $field->name . '" value="' . $field->value . '"' . ( $field->id ? ' id="' . $field->id . '"' : '' )  . '>';
    }
    
    function render_field_textarea( $field ) 
    {
        return '<textarea name="' . $field->name . '" class="widefat"' . ( $field->id ? ' id="' . $field->id . '"' : '' )  . '>' . $field->value . '</textarea>';
    }
    
    function render_field_select( $field ) 
    {
        $options = '';
        
        foreach ( $field->choices as $name => $label ) {
            $options .= '<option value="' . $name . '"' . selected( $field->value, $name, false ) . '>' . $label . '</option>';
        }
    
        return '<select name="' . $field->name . '">' . $options . '</select>';
    }
    
    function render_field_post_type_dropdown( $field ) 
    {
    	$args = array(
            'post_type' => $field->post_type,
            'name' => $field->name,
            'selected' => $field->value,
        );
    
        if ( function_exists( 'netgon_post_type_dropdown' ) ) {
			return netgon_post_type_dropdown( $args );
		}
    }
} 

class Netgon_Meta_Field 
{
    public $type = 'text';
    public $name = '';
    public $value = '';
    public $label = '';
    public $choices = array(); /* for select and radio type */
    public $post_type = ''; /* for "post type dropdown" field type */
    
    function __construct( $args = array() ) 
    {		
		$defaults = array(
            'type' => 'text',
            'name' => '',
            'value' => '',
            'label' => ''
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        foreach ( $args as $key => $value ) {
            $this->$key = $value;
        }
    }
}
