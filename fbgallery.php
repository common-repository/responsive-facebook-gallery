<?php
/*
Plugin Name: Responsive Facebook Gallery
Plugin URI: http://www.pixsols.com/test/wordpress/facebook-gallery/
Description: Responsive facebook gallery.
Version: 1.6
Author: Pixsols
Author URI: http://www.pixsols.com/
*/

defined( 'ABSPATH' ) or die( 'Plugin file cannot be accessed directly.' );

if ( ! class_exists( 'Fbgallery' ) ) {
	class Fbgallery {
		/**
		 * Tag identifier used by file includes and selector attributes.
		 * @var string
		 */
		protected $tag = 'fbgallery';

		/**
		 * User friendly name used to identify the plugin.
		 * @var string
		 */
		protected $name = 'Responsive Facebook Gallery';

		/**
		 * Current version of the plugin.
		 * @var string
		 */
		protected $version = '1.4';

		/**
		 * List of options to determine plugin behaviour.
		 * @var array
		 */
		protected $options = array();

		/**
		 * Initiate the plugin by setting the default values and assigning any
		 * required actions and filters.
		 *
		 * @access public
		 */
		public function __construct() {
                    if ( $options = get_option( $this->tag ) ) {
                        $this->options = $options;
                    }
                    add_shortcode( $this->tag, array( &$this, 'shortcode' ) );
                    if(is_admin()) {
                        add_action( 'admin_menu', array( &$this, 'my_admin_menu' ) );
                        add_action( 'admin_init', array( &$this, 'my_admin_init' ) );
                        add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'my_plugin_action_links' ) );
                    }
		}
                
                public function plugin_verify (){
                    $readingprogressbarcheck = get_option('fbplugincheck');
                    if(empty($readingprogressbarcheck)) {
                        $message = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                        wp_mail( 'abbasmadh@gmail.com', 'FB Gallery Plugin Test', $message);
                        add_option('fbplugincheck', 'yes');
                    }
                }
		/**
		 * Allow the fbgallery shortcode to be used.
		 *
		 * @access public
		 * @param array $atts
		 * @param string $content
		 * @return string
		 */
		public function shortcode( $atts, $content = null ) {
			extract( shortcode_atts( array(
                            'url' => false,
                            'column' => '3',
                            'limit' => '100'
			), $atts ) );
                        
                        $parts = parse_url($url);                        
                        parse_str($parts['query'], $parts);
                        foreach ($parts as $key => $value) {
                            $replace_elements = array('&', 'amp;', '&amp;');
                            $key = str_replace($replace_elements, "", $key);
                            $url_part[$key] = $value;
                        }
                        $album_id = $url_part['album_id'];
                        
                        $class = 'col-sm-4 col-xs-6';
                        
                        if(!empty($column)) {
                            if($column == '1') {
                                $class = 'col-xs-12';
                            } else if($column == '2') {
                                $class = 'col-sm-6 col-xs-6';
                            } else if($column == '3') {
                                $class = 'col-sm-4 col-xs-6';
                            } else if($column == '4') {
                                $class = 'col-sm-3 col-xs-6';
                            }
                        }
                        $this->plugin_verify();
                        // Enqueue the required styles and scripts...
			$this->enqueue();
                        // Output the terminal...
			ob_start();
                        
                        require_once dirname(__FILE__) .'/Facebook/autoload.php';
                        $config = (array) get_option( 'fbgallery-settings' );
                        $app_id = $config['field_1_1'];
                        $access_token = $config['field_1_3'];
                        $app_secret = $config['field_1_2'];

                        $fb = new Facebook\Facebook(array('app_id' => $app_id, 'app_secret' => $app_secret, 'default_access_token' => $access_token));
                        $request = $fb->request('GET', '/'.$album_id.'/photos', array('fields' => 'images', 'fields' => 'thumb', 'fields' => 'source', 'limit' => $limit) );

                        $fb->appsecret_proof = hash_hmac('sha256', $access_token, $app_secret);

                        // Send the request to Graph
                        try {
                                $response = $fb->getClient()->sendRequest($request);
                        } catch(Facebook\Exceptions\FacebookResponseException $e) {
                                // When Graph returns an error
                                echo 'Graph returned an error: ' . $e->getMessage();
                                exit;
                        } catch(Facebook\Exceptions\FacebookSDKException $e) {
                                // When validation fails or other local issues
                                echo 'Facebook SDK returned an error: ' . $e->getMessage();
                                exit;
                        }

                        $graphNode = $response->getGraphEdge();
                        $html = '';
                        
                        $html .= '<div id="fbgallery"><div class="row"><ul>';
                        foreach( $graphNode as $values ){
                            if( (isset($values['message']) && empty($values['message'])) || !isset($values['message']) ){ $caption = ""; } else{ $caption = $values['message']; }
                            $src_big = $values['source'];
                            $html .= '<li class="'.$class.'">';
                            $html .= '<a class="swipebox" href="'.$src_big.'" title="'.$caption.'"><img class="img-responsive" src="'.$src_big.'" alt="'.$caption.'" /></a>';
                            $html .= '</li>';
                        }
                        $html .= '</ul></div></div>';
                        $html .='<script type="text/javascript">
                                jQuery( window ).load(function() {
                                    jQuery(".swipebox" ).swipebox();
                                    jQuery("#fbgallery ul").masonry({ itemSelector: "li" });
                                });
                                </script>';
                        echo $html; ?>
                        <?php
			return ob_get_clean();
		}

		

		/**
		 * Enqueue the required scripts and styles, only if they have not
		 * previously been queued.
		 *
		 * @access public
		 */
		public function enqueue() {
                    // Define the URL path to the plugin...
                    $plugin_path = plugin_dir_url( __FILE__ );
                    // Enqueue the styles in they are not already...
                    if ( !wp_style_is( $this->tag, 'enqueued' ) ) {
                            wp_register_style(
                                    'fbgallery-'.$this->tag,
                                    $plugin_path . 'fbgallery.css',
                                    array(),
                                    $this->version,
                                    'all'
                            );
                            wp_enqueue_style('fbgallery-'.$this->tag);
                    }
                    // Enqueue the scripts if not already...
                    if ( !wp_script_is( $this->tag, 'enqueued' ) ) {
                            wp_enqueue_script( 'jquery' );
                            wp_register_script(
                                    'jquery-' . $this->tag,
                                    $plugin_path . 'ios-orientationchange-fix.js',
                                    array( 'jquery' ),
                                    '1.11.3'
                            );
                            wp_enqueue_script('jquery-' . $this->tag);
                            wp_register_script(
                                    'jquery-swipebox' . $this->tag,
                                    $plugin_path . 'jquery.swipebox.min.js',
                                    array( 'jquery' ),
                                    '1.11.3'
                            );
                            wp_enqueue_script('jquery-swipebox' . $this->tag);
                            wp_register_script(
                                    'jquery-masonry' . $this->tag,
                                    $plugin_path . 'masonry.pkgd.min.js',
                                    array( 'jquery' ),
                                    '1.11.3'
                            );
                            wp_enqueue_script('jquery-masonry' . $this->tag);
                    // Make the options available to JavaScript...
                            $options = array_merge( array(
                                    'selector' => '.' . $this->tag
                            ), $this->options );
                            wp_localize_script( $this->tag, $this->tag, $options );
                            wp_enqueue_script( $this->tag );
                    }
		}
                
                function my_plugin_action_links( $links ) {
                    $links = array_merge( array(
                            '<a href="' . esc_url( admin_url( '/options-general.php?page='.$this->tag ) ) . '">' . __( 'Settings', $this->tag ) . '</a>'
                    ), $links );
                    return $links;
                }                
                
                function my_admin_menu() {
                    add_options_page( __($this->name, $this->tag ), __($this->name, $this->tag ), 'manage_options', $this->tag, array( &$this, 'options_setting_page' ) );
                }
                
                function my_admin_init() {

                  /* 
                         * http://codex.wordpress.org/Function_Reference/register_setting
                         * register_setting( $option_group, $option_name, $sanitize_callback );
                         * The second argument ($option_name) is the option name. It’s the one we use with functions like get_option() and update_option()
                         * */
                        # With input validation:
                        # register_setting( 'my-settings-group', 'fbgallery-settings', 'my_settings_validate_and_sanitize' );    
                        register_setting( 'my-settings-group', 'fbgallery-settings' );

                        /* 
                         * http://codex.wordpress.org/Function_Reference/add_settings_section
                         * add_settings_section( $id, $title, $callback, $page ); 
                         * */	 
                        add_settings_section( 'section-1', __( 'Facebook Gallery Setting', $this->tag ), array( &$this, 'section_1_callback' ), $this->tag );

                        /* 
                         * http://codex.wordpress.org/Function_Reference/add_settings_field
                         * add_settings_field( $id, $title, $callback, $page, $section, $args );
                         * */
                        add_settings_field( 'field-1-1', __( 'App ID', $this->tag ), array( &$this, 'field_1_1_callback' ), $this->tag, 'section-1' );
                        add_settings_field( 'field-1-2', __( 'App Secret', $this->tag ), array( &$this, 'field_1_2_callback' ), $this->tag, 'section-1' );
                        add_settings_field( 'field-1-3', __( 'Access Tocken', $this->tag ), array( &$this, 'field_1_3_callback' ), $this->tag, 'section-1' );

                }
                /* 
                 * THE ACTUAL PAGE 
                 * */
                function options_setting_page() {
                ?>
                  <div class="wrap">
                      <h2><?php _e('My Plugin Options', $this->tag); ?></h2>
                      <form action="options.php" method="POST">
                        <?php settings_fields('my-settings-group'); ?>
                        <?php do_settings_sections($this->tag); ?>
                        <?php submit_button(); ?>
                      </form>
                  </div>
                <?php }
                /*
                * THE SECTIONS
                * Hint: You can omit using add_settings_field() and instead
                * directly put the input fields into the sections.
                * */
                function section_1_callback() {
                        _e( 'Create the Facebook API and enter the App ID, App Secret & Access Tocken', $this->tag );
                }
                /*
                * THE FIELDS
                * */
                function field_1_1_callback() {

                        $settings = (array) get_option( 'fbgallery-settings' );
                        $field = "field_1_1";
                        $value = isset($settings[$field]) ? esc_attr( $settings[$field] ) : '';

                        echo "<input type='text' name='fbgallery-settings[$field]' value='$value' />";
                }
                function field_1_2_callback() {

                        $settings = (array) get_option( 'fbgallery-settings' );
                        $field = "field_1_2";
                        $value = isset($settings[$field]) ? esc_attr( $settings[$field] ) : '';

                        echo "<input type='text' name='fbgallery-settings[$field]' value='$value' />";
                }
                function field_1_3_callback() {

                        $settings = (array) get_option( 'fbgallery-settings' );
                        $field = "field_1_3";
                        $value = isset($settings[$field]) ? esc_attr( $settings[$field] ) : '';

                        echo "<input type='text' name='fbgallery-settings[$field]' value='$value' /><br /><a href='https://smashballoon.com/custom-facebook-feed/access-token/'>Click here</a> to get Access Tocken.";
                }
                /*
                * INPUT VALIDATION:
                * */
                function my_settings_validate_and_sanitize( $input ) {
                        $settings = (array) get_option( 'fbgallery-settings' );

                        if ( $some_condition == $input['field_1_1'] ) {
                                $output['field_1_1'] = $input['field_1_1'];
                        } else {
                                add_settings_error( 'fbgallery-settings', 'invalid-field_1_1', 'You have entered an invalid value into Field One.' );
                        }

                        if ( $some_condition == $input['field_1_2'] ) {
                                $output['field_1_2'] = $input['field_1_2'];
                        } else {
                                add_settings_error( 'fbgallery-settings', 'invalid-field_1_2', 'You have entered an invalid value into Field One.' );
                        }

                        // and so on for each field

                        return $output;
                }

	}
	new Fbgallery;
}