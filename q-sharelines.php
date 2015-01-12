<?php

/**
 * Plugin Name:     Sharelines
 * Plugin URI:      https://qstudio.us
 * Description:     Suggest content for your users to share on social networks
 * Version:         0.1
 * Author:          Q Studio
 * Author URI:      https://qstudio.us/releases/sharelines
 * License:         GPL2
 * Class:           Q_Sharelines
 * Text Domain:     q-sharelines
 */

defined( 'ABSPATH' ) OR exit;

register_deactivation_hook( __FILE__, array( 'Q_Sharelines', 'on_deactivation' ) );

if ( ! class_exists( 'Q_Sharelines' ) ) {
    
    // instatiate plugin via WP plugins_loaded - init is too late for CPT ##
    #add_action( 'plugins_loaded', array ( 'Q_Sharelines', 'get_instance' ), 5 );
    
    // register widget ##
    add_action( 'widgets_init', 'register_q_sharelines', 0 );
    function register_q_sharelines() 
    {
    
        register_widget( 'Q_Sharelines' );
        
    }
    
    class Q_Sharelines extends WP_Widget {
                
        // Refers to a single instance of this class. ##
        private static $instance = null;
                       
        // Plugin Settings
        const version = '0.1';
        const text_domain = 'q-sharelines'; // for translation ##
        
        // settings ##
        public $settings = array();
        static $token = '**';
        static $facebook_app_id = '1381937288777556'; // default to qstudio
        
        /**
         * Creates or returns an instance of this class.
         *
         * @return  Foo     A single instance of this class.
         */
        public static function get_instance() 
        {

            if ( null == self::$instance ) {
                self::$instance = new self;
            }

            return self::$instance;

        }
        
        
        /**
         * Instatiate Class
         * 
         * @since       0.2
         * @return      void
         */
        public function __construct() 
        {
            
            // set text domain ##
            add_action( 'init', array( $this, 'load_plugin_textdomain' ), 1 );
            
            // register widget ##
            parent::__construct(
                'q_sharelines', // Base ID
                __( 'Sharelines', self::text_domain ), // Name
                array( 'description' => __( 'Suggested content sharing snippets', self::text_domain ), ) // Args
            );
            
            // shortcode ##
            add_shortcode( 'sharelines', array( $this, 'add_shortcode' ) );
            
            if ( is_admin() ) {
                
                // init process for registering TME button ##
                add_action( 'init', array( $this, 'shortcode_button_init' ) );
                
                // scan content on save - if we find text between the defined tokens - add a post meta "_q_sharelines" ##
                add_action( 'save_post', array( $this, 'save_post' ) );
                
            } else {
                
                // styles and scripts ##
                add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ), 1 );
                
            }
            
            
        }
        
        
        // -------------- PLUGIN FUNCTIONS ---------------------- //
        
        
        /**
         * Load Text Domain for translations
         * 
         * @since       1.7.0
         * 
         */
        public function load_plugin_textdomain() 
        {
            
            // set text-domain ##
            $domain = self::text_domain;
            
            // The "plugin_locale" filter is also used in load_plugin_textdomain()
            $locale = apply_filters('plugin_locale', get_locale(), $domain);

            // try from global WP location first ##
            load_textdomain( $domain, WP_LANG_DIR.'/plugins/'.$domain.'-'.$locale.'.mo' );
            
            // try from plugin last ##
            load_plugin_textdomain( $domain, FALSE, plugin_dir_path( __FILE__ ).'languages/' );
            
        }
        
        
        
        /**
         * Deactivation callback method
         * 
         * @since       0.1
         * @return      void
         */
        public static function on_deactivation()
        {
            
            if ( ! current_user_can( 'activate_plugins' ) ) {
                
                return;
                
            }
            
            $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
            check_admin_referer( "deactivate-plugin_{$plugin}" );

            // check if we have any posts tagged ##
            if ( $posts = self::get_posts_by_meta( array( 'meta_key' => '_q_sharelines', 'meta_value' => '1' ) ) ) {
                
                // loop over each post ##
                foreach( $posts as $post ) {
                    
                    $update_post = array(
                        'ID'           => $post->ID,
                        'post_content' => self::remove_shortcode( $post->post_content )
                    );

                    // Update the post into the database ##
                    wp_update_post( $update_post );
                    
                    // delete post meta marker ##
                    delete_post_meta( $post->ID, '_q_sharelines' );
                    
                }
                
                #pr( $posts );
                #exit( var_dump( $_GET ) );
                
            }
            
        }
        
        
        /**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) 
        {
            
            // get widget settings ##
            $title = $instance['title'] ? $instance['title'] : __( "Search", self::text_domain );
            $this->settings["title"] = apply_filters( 'widget_title', $title );
            $this->settings["facebook_app_id"] = $instance['facebook_app_id'] ? $instance['facebook_app_id'] : self::$facebook_app_id ;
            
            // check if widget settings ok ##
            if ( isset( $this->settings ) && array_filter( $this->settings ) && isset( $this->settings["facebook_app_id"] ) ) {
                
                // build widget ##
                $this->render();
                
            }
            
	}
        

        
	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) 
        {
        
            $title = isset( $instance[ 'title' ] ) ? $instance[ 'title' ] : __( 'Sharelines', self::text_domain ) ;
            $facebook_app_id = isset( $instance[ 'facebook_app_id' ] ) ? $instance[ 'facebook_app_id' ] : '';
            
?>
            <p>
                <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
                <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
            </p>
            <p>
                <label for="<?php echo $this->get_field_id( 'facebook_app_id' ); ?>"><?php _e( 'Facebook App ID:' ); ?></label> 
                <input class="widefat" id="<?php echo $this->get_field_id( 'facebook_app_id' ); ?>" name="<?php echo $this->get_field_name( 'facebook_app_id' ); ?>" type="text" value="<?php echo esc_attr( $facebook_app_id ); ?>">
            </p>
<?php 
	}

        
        
	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) 
        {
            
            $instance = array();
            $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '' ;
            $instance['facebook_app_id'] = ( ! empty( $new_instance['facebook_app_id'] ) ) ? strip_tags( $new_instance['facebook_app_id'] ) : self::$facebook_app_id ;

            return $instance;
            
	}
        
        
        
        /**
         * WP Enqueue Scripts - on the front-end of the site
         * 
         * @since       0.1
         * @return      void
         */
        public function wp_enqueue_scripts() 
        {
            
            // check if we should continue ##
            if ( ! self::check() ) { return false; }
            
            // Register the script ##
            #wp_register_script( 'q-sharelines-js', self::get_plugin_url( 'javascript/q-sharelines.js' ), array( 'jquery' ), self::version, true );

            // Now we can localize the script with our data.
            #$translation_array = array( 
            #        'ajax_nonce'    => wp_create_nonce( 'q_sharelines_nonce' )
            #    ,   'ajax_url'      => get_home_url( '', 'wp-admin/admin-ajax.php' )
                #,   'saved'         => __( "Student Saved", self::text_domain )
                #,   'error'         => __( "Error", self::text_domain )
            #);
            #wp_localize_script( 'q-sharelines-js', 'q_sharelines', $translation_array );

            // enqueue the script ##
            #wp_enqueue_script( 'q-sharelines-js' );
            
            wp_register_style( 'q-sharelines-css', self::get_plugin_url( 'css/q-sharelines.css' ) );
            wp_enqueue_style( 'q-sharelines-css' );
            
        }
        
        
        
        /**
         * Get Plugin URL
         * 
         * @since       0.1
         * @param       string      $path   Path to plugin directory
         * @return      string      Absoulte URL to plugin directory
         */
        public static function get_plugin_url( $path = '' ) 
        {

            return plugins_url( ltrim( $path, '/' ), __FILE__ );

        }
        
        
        
        /**
         * Find a string between two other strings
         * 
         * @param       string      $string
         * @param       type        $start
         * @param       type        $end
         * 
         * @link        http://stackoverflow.com/questions/5696412/get-substring-between-two-strings-php
         * @since       0.1
         * @return      string
         */
        public static function get_string_between( $string = null )
        {
            
            // kick back if nothing passed ##
            if ( is_null( $string ) ) { return false; }
            
            // empty array ##
            $matches = array();
            
            // search for the tokens in te string ##
            preg_match_all( "(".preg_quote( self::$token ).".*?".preg_quote( self::$token ).")s", $string, $matches );
                    
            if ( array_filter( $matches ) ) {
                
                // return the array ##
                #pr( $matches[0] );
                return $matches[0];
                
            }
            
            // fallback ##
            return false;
            
        }
        
        
        
        /**
         * Find all occurances of a single shortcode in a string
         * 
         * @param       string      $string
         * 
         * @since       0.1
         * @return      string
         */
        public static function get_shortcodes( $string = null )
        {
            
            // kick back if nothing passed ##
            if ( is_null( $string ) ) { return false; }
            
            // empty array ##
            $matches = array();
            
            // search for the tokens in te string ##
            preg_match_all( "(".preg_quote( '[sharelines]' ).".*?".preg_quote( '[/sharelines]' ).")s", $string, $matches );
            
            #pr( $matches );
            
            if ( array_filter( $matches ) ) {
                
                // return the array ##
                #pr( $matches[0] );
                return $matches[0];
                
            }
            
            // fallback ##
            return false;
            
        }
        
        
        
        /**
         * Remove plugin shortcode from string
         * 
         * @since       0.1
         * @return      String
         */
        public static function remove_shortcode( $string = null )
        {
            
            if ( is_null( $string ) ) { return false; }
            
            // Array of shortcodes to remove ##
            $array = array(
                    '[sharelines]'
                ,   '[/sharelines]'
            );
            
            // kick it back clean ##
            return str_replace( $array, '', $string );
            
        }
        
        
        
        /**
        * Get Post object by post_meta query
        *
        * @use         $post = get_post_by_meta( array( meta_key = 'page_name', 'meta_value = 'contact' ) )
        * @since       1.0.4
        * @return      Object      WP post object
        */
        public function get_posts_by_meta( $args = array() )
        {

            // Parse incoming $args into an array and merge it with $defaults - caste to object ##
            $args = ( object )wp_parse_args( $args );

            // grab page - polylang will take take or language selection ##
            $args = array(
                'meta_query'        => array(
                    array(
                        'key'       => $args->meta_key,
                        'value'     => $args->meta_value
                    )
                ),
                'post_type'         => 'page',
                'posts_per_page'    => -1
            );

            // run query ##
            $posts = get_posts( $args );

            // check results ##
            if ( ! $posts || is_wp_error( $posts ) ) return false;

            // test it ##
            #pr( $posts[0] );

            // kick back results ##
            return $posts;

        }
        
        
        
        /**
         * Chop a string to a defined length, if it exceeds that length
         * 
         * Note - Taken from Q Framework
         * 
         * @since       0.1
         * @return      String
         */
        public function q_chop( $content, $length = 0 ) {
            
            if ( $length > 0 ) { // trim required, perhaps ##
                
                if ( strlen( $content ) > $length ) { // long so chop ##
                    
                    return substr( $content , 0, $length ).'...';
                     
                } else { // no chop ##
                    
                    return $content;
                        
                }
                
            } else { // send as is ##
                
                return $content;
                
            }
            
        }
        
        
        
        /**
         * Register MCE shortcode button
         * 
         * @since       0.1
         */
        public function shortcode_button_init() 
        {
            
            // Abort early if the user will never see TinyMCE ##
            if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') && get_user_option('rich_editing') == 'true') {
                
                return;
                
            }
            
            if ( get_user_option('rich_editing') == 'true' ) {
            
                //Add a callback to regiser our tinymce plugin   
                add_filter( "mce_external_plugins", array( $this, "register_tinymce_plugin" ) ); 

                // Add a callback to add our button to the TinyMCE toolbar
                add_filter( 'mce_buttons', array( $this, 'add_tinymce_button' ) );

            }
            
        }

        

        /**
         * This callback registers our plug-in
         * 
         * @since       0.1
         */
        public function register_tinymce_plugin( $plugin_array ) 
        {
            
            $plugin_array['q_sharelines_button'] = self::get_plugin_url( 'javascript/q-sharelines.js' );
            return $plugin_array;
            
        }

        
        
        /**
         * This callback adds our button to the toolbar
         * 
         * @since       0.1
         */
        public function add_tinymce_button( $buttons ) 
        {
            
            // Add the button ID to the $button array ##
            $buttons[] = "q_sharelines_button";
            return $buttons;
              
        }
        
        
        
        // -------------- ADMIN FUNCTIONS ---------------------- //
        
        
        /**
 * When the post is saved, saves our custom data.
 *
 * @param int $post_id The ID of the post being saved.
 */
        public function save_post( $post_id ) {

            /*
             * We need to verify this came from our screen and with proper authorization,
             * because the save_post action can be triggered at other times.
             */

            // If this is an autosave, our form has not been submitted, so we don't want to do anything.
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                
                return;
                
            }

            // Check the user's permissions.
            if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

                if ( ! current_user_can( 'edit_page', $post_id ) ) {
                    
                    return;
                    
                }

            } else {

                if ( ! current_user_can( 'edit_post', $post_id ) ) {
                    
                    return;
                    
                }
                
            }

            // OK, it's safe for us to save the data now ##

            // Make sure that it is set.
            if ( ! isset( $_POST["content"] ) ) {
                
                return;
                
            }
            
            #if ( $shareline = self::get_string_between( $_POST["content"] ) ) {
            if ( has_shortcode ( $_POST["content"], 'sharelines' ) ) {
                
                #echo $shareline;
                update_post_meta( $post_id, '_q_sharelines', true );
                
            } else {
                
                delete_post_meta( $post_id, '_q_sharelines' );
                
            }
            
            #wp_die( pr( $_POST["content"] ) );
            
        }
        
        
        
        // -------------- TEMPLATE FUNCTIONS ---------------------- //
        
        
        /**
         * Shortcode dummy - returns post_content unchanged
         * 
         * @since       0.1
         * @return      String
         */
        public function add_shortcode( $atts, $content = "" ) 
        {
            
            return $content;
            
	}
        
        
        
        /***
         * Check if the current post has any sharelines
         * 
         * @since       0.1
         * @return      Boolean
         */
        public static function check()
        {
            
            global $post;
            #pr( $post );
            
            // no post ##
            if ( ! $post ) { return false; }
            
            // check for marker ##
            if ( ! $sharelines = get_post_meta( $post->ID, '_q_sharelines' )  ) {
                
                return false;
                
            }
            
            // check for actual sharelines ##
            #if ( ! $shareline = self::get_string_between( $post->post_content ) ) {
                
                #return false;
                
            #}
            
            // check if shortcode allowed ##
            if ( ! shortcode_exists ( 'sharelines' ) ) {
                
                #pr( "kicked 1" );
                return false;
                
            }
            
            // check for shortcode ##
            if ( ! has_shortcode( $post->post_content, 'sharelines' ) ) {
                
                #pr( "kicked 2" );
                return false;
                
            }
            
            // ok to continue ##
            return true;
            
        }
        
        
        
        /**
        * Render the widget
        * 
        * @since       0.1
        * @return      HTML
        */
        public function render()
        {
            
            global $post;
            #pr( $post );
            
            // no post ##
            if ( ! $post ) { return false; }
            
            // check if we should continue ##
            if ( ! self::check() ) { return false; }
            
            // check we can grab sharelines ##
            #$sharelines = self::get_string_between( $post->post_content );
            
            // get all shortcodes in use ##
            $sharelines = self::get_shortcodes( $post->post_content );
            
            // nothing cooking ##
            if ( ! $this->settings ) { return false; }
            
            // test settings ##
            #pr( $this->settings );
            
            // title ##
            $title = isset( $this->settings['title'] ) && ! empty( $this->settings['title'] ) ? $this->settings['title'] : __( "Sharelines", self::text_domain ) ;
            $facebook_app_id = isset( $this->settings['facebook_app_id'] ) ? $this->settings['facebook_app_id'] : self::$facebook_app_id ;
            
            // get details to share ##
            $fb_name = get_the_title( $post->ID );
            $fb_link = get_permalink( $post->ID );
            $fb_caption = get_post_meta( get_post_thumbnail_id( $post->ID ), '_wp_attachment_image_alt', true );
            $fb_pictures = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'square-small' ); // get post image ##
            $fb_picture = $fb_pictures[0];
            
?>
            <li>
                <ul class="q-sharelines">
                    <li>
                        <h2><?php echo $title; ?></h2>
                    </li>
<?php

                    // loop over each item ##
                    foreach ( $sharelines as $shareline ) {
                        
                        // grab content ##
                        //$fb_description = self::q_chop( $shareline, 140 );
                        
?>
                    <li class="q_shareline_item" data-sharelines="<?php echo self::remove_shortcode( $shareline ); ?>">
                        <?php echo q_chop( self::remove_shortcode( $shareline ), 40 ); ?>
                    </li>
<?php
                        
                    }

?>
                </ul>
            </li>
            <div id="fb-root"></div>
<script>
jQuery(document).ready(function($) {

    // FB share ##
    jQuery(".q_shareline_item").click(function( e ) {

        e.preventDefault();
        
        if ( typeof FB !== "undefined" ) {
            
            // save "this" ##
            $t = jQuery(this);
            
            // grab current text ##
            $text = $t.data("sharelines");
            //console.log( $text );
            
            FB.ui (
                {
                    method: 'feed',
                    name: '<?php echo esc_js( $fb_name ); ?>',
                    link: '<?php echo esc_js( $fb_link ); ?>',
                    picture: '<?php echo esc_js( $fb_picture ); ?>',
                    caption: '<?php echo esc_js( $fb_caption ); ?>',
                    description: $text // get content from clicked item ##
                },
                function(response) {
                    if ( response && response.post_id ) {
                        $t.text( '<?php _e( 'Shared :)', self::text_domain ); ?>' );
                    } else {
                        $t.text( '<?php _e( 'Failed :(', self::text_domain ); ?>' );
                    }
                }
            );

        } else {
            
            // debug ##
            $t.text( '<?php _e( 'Facebook Error :(', self::text_domain ); ?>' );
            fb_restore = setTimeout(function(){
                $t.text( $text );
            }, 3000);
        
        }

    });
    
    // late load fb sharing library ##
    $sharelines = jQuery('.q-sharelines');
    if ( $sharelines.length != 0 ) { // load options, if '.q-sharelines' selector found ##
        
        jQuery.ajaxSetup({ cache: true });
        jQuery.getScript('//connect.facebook.net/en_UK/all.js', function(){
          FB.init({
            appId: '<?php echo $facebook_app_id; ?>'
          });     
          jQuery( '#loginbutton,#feedbutton' ).removeAttr('disabled');
          //FB.getLoginStatus(updateStatusCallback);
        });
    }
   
});
</script>
<?php
            
        }
        
    }

}
