<?Php
/*
Plugin Name: Boot-Modal
Plugin URI: https://wordpress.org/plugins/boot-modal/
Description: A simple plugin to open any page in a Bootstrap modal window.
Version: 1.9.1
Author: Julien Crego
Author URI: https://dev.juliencrego.com/boot-modal/
Text Domain: bootmodal
*/

/**
 Changes by Christer to :
 - take into account multi-lingual pages
 - process shortcodes in the page appearing in the modal
 - make it possible to send a parameter in the URL for the page being opened in the modal
   This makes it possible to customize the content of the page
   The parameter consists of a url_key (parameter name) and url_value
 Thanks to him !!
 */

/* Manage translations */
add_action('plugins_loaded', 'bootmodal_load_textdomain' );
function bootmodal_load_textdomain() {
    load_plugin_textdomain('bootmodal', false, plugin_basename( dirname( __FILE__ ) ) . '/lang' ); 
}

/* Fix the bug with WP 4.1.1. 
Warning: substr() expects parameter 1 to be string, array given in wp-includes/functions.php on line 1679
 */
set_error_handler("bootmodal_warning_handler", E_WARNING);
    function bootmodal_warning_handler($errno, $errstr) { 
}


if(!is_admin()){
    $boot = new BootModal(); 
} else {
    $boot = new BootModalAdmin();
}


class BootModal {
    private $plugin_folder = 'boot-modal';
    private $plugin_name = 'Boot-Modal';
    private $animation = false ;
    private $post = false ;
    private $postname = false;
    private $buttonclass = false ;
    private $buttontype = false ;
    private $buttontext = false ;
    private $buttoncloseclass = false ;
    private $buttonclosetext = false ;
    private $size = false ;
    private $urlkey = false ;
    private $urlvalue = false ;
    private $options = array();
    private $locale = false ;

    public function __construct(){
        global $wpdb;
        $this->wpdb = $wpdb ;
        $this->options = get_option('bootmodal_plugin_options');
        
        add_filter('widget_text', 'do_shortcode');
        add_shortcode('bootmodal', array($this,'shortcodeLauncher') );
    
        // Add Bootstrap if needed
        if($this->options['bootstrap_actif'] == 'yes'){
            add_action( 'wp_enqueue_scripts', array($this,'addBootstrap') );
        }

        add_action('wp_head', array($this,'custom_styles'), 100);
    }
    public function custom_styles() {
        echo "<style>";
        echo ".modal{max-width:100% !important;}";
        echo ".modal-dialog{overflow-y:initial !important;}";
        echo ".modal-body{overflow-y:auto;}";
        if(isset($this->options['css_custom'])) {
            echo $this->options['css_custom'];
        }
        echo '</style>';
    }

    public function shortcodeLauncher($atts){
        // Shortcode's params
        $params = shortcode_atts( array('post' => 'post',
                                        'buttonclass'=>'',
                                        'buttontext'=>'',
                                        'buttontype' =>'',
                                        'buttoncloseclass' => '',
                                        'buttonclosetext' => '',
                                        'size'=>'',
                                        'urlkey' => '', 
                                        'urlvalue' => '',
                                        'animation' => '',
                                        'dismiss' => 'yes'
                                ), $atts);
        extract($params);
        $this->animation = $params['animation'] ;
        $this->postname = $params['post'] ;
        $this->buttonclass = $params['buttonclass'];
        $this->buttontype = $params['buttontype'];
        $this->buttontext = $params['buttontext'];
        $this->buttoncloseclass = $params['buttoncloseclass'];
        $this->buttonclosetext = $params['buttonclosetext'];
        $this->size = $params['size'];
        $this->urlkey = $params['urlkey'];
        $this->urlvalue = $params['urlvalue'];
        $this->dismiss = $params['dismiss'];
        
        ob_start();
        // Other params
        $this->post = $this->getPostByPostName($this->postname);
                
        if($this->post != NULL){
            // Button or link text
            $this->locale = explode('_', get_locale())[0]; // Get first part of locale, like fr from fr_FR
            if(!$this->buttontext){ 
                $this->buttontext = apply_filters('translate_text', $this->post->post_title, $this->locale) ; 
            }

            // Construct link or button
            if($this->buttontype=="button" or ($this->buttontype=="" && $this->options['open_button_type']=="button")):
                $this->html_button();
            else:
                $this->html_link();
            endif;
        } else {
             _e( "Permalink unknow (Boot-modal)", 'bootmodal');
        }
        
        $this->html_modal();
        $content = ob_get_clean();
        return $content ;
    }
    
    public function addBootstrap() {
        if( !isset($this->options['bootstrap_version']) or $this->options['bootstrap_version'] == '3') {
            wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css');
            wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js', array('jquery'));
        } elseif($this->options['bootstrap_version'] == '4') {
            wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.1/css/bootstrap.min.css');
            wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.1/js/bootstrap.bundle.min.js', array('jquery'));
        }
    }
    
    public function getPostByPostName($postname){
        $pre = "SELECT * FROM {$this->wpdb->prefix}posts WHERE post_name = %s " ;
        $res = $this->wpdb->get_row($this->wpdb->prepare($pre,$postname));
        return $res ;
    }
    
    public function html_button() {
        ?>
        <button type="button" 
                class="<?php echo($this->buttonclass=="")?$this->options['open_button_class']:$this->buttonclass; ?>" 
                data-toggle="modal" 
                <?php if($this->dismiss == 'yes') : ?>
                    data-dismiss="modal"
                <?php endif; ?>
                data-target="#<?php echo $this->post->post_name; ?>">
            <?php echo $this->buttontext ; ?>
        </button>
        <?php
    }
    
    public function html_link() {
        $url = site_url();
        $url_param = ($this->urlkey && $this->urlvalue) ? '?'.$this->urlkey.'='.$this->urlvalue.'/#' : '/#'; 
        ?>
        <a href="<?php echo $url.'/index.php/'.$this->postname.$url_param; ?>" 
           class="<?php echo($this->buttonclass=="")?$this->options['open_button_class']:$this->buttonclass; ?>" 
           data-toggle="modal" 
           <?php if($this->dismiss == 'yes') : ?>
                data-dismiss="modal"
            <?php endif; ?>
           data-target="#<?php echo $this->post->post_name; ?>">
            <?php echo $this->buttontext ; ?>
        </a>
        <?php
    }
    
    public function html_modal_bootstrap3() {
        ?>
        <div class="modal <?php echo $this->anim_value ; ?>" 
             id="<?php echo $this->post->post_name; ?>" tabindex="-1" role="dialog" aria-labelledby="modalLabel_<?php echo $this->post->post_name; ?>">
            <div class="modal-dialog <?php echo $size; ?>" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="<?php if($this->options['button_text']!=""){ echo $this->options['button_text'];} else { _e( "Close", 'bootmodal'); } ?>"><span aria-hidden="true">&times;</span></button>                        
                        <h4 class="modal-title" id="modalLabel_<?php echo $this->post->post_name; ?>"><?php echo apply_filters('translate_text', $this->post->post_title, $this->locale) ; ?></h4>
                    </div>
                    <div class="modal-body">
                        <?php echo apply_filters('the_content', apply_filters('translate_text', $this->post->post_content, $this->locale)) ; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="<?php echo ($this->buttoncloseclass) ? $this->buttoncloseclass : $this->options['button_class']; ?>" data-dismiss="modal">
                            <?php if($this->buttonclosetext){echo $this->buttonclosetext ;}elseif($this->options['button_text']!=""){ echo $this->options['button_text'];} else { _e( "Close", 'bootmodal'); } ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    public function html_modal_bootstrap4() {
        ?>
        <div class="modal <?php echo $this->anim_value ; ?>" 
             id="<?php echo $this->post->post_name; ?>" tabindex="-1" aria-labelledby="modalLabel_<?php echo $this->post->post_name; ?>" aria-hidden="true">
            <div class="modal-dialog <?php echo $size; ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalLabel_<?php echo $this->post->post_name; ?>"><?php echo apply_filters('translate_text', $this->post->post_title, $this->locale) ; ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="<?php if($this->options['button_text']!=""){ echo $this->options['button_text'];} else { _e( "Close", 'bootmodal'); } ?>">
                            <span aria-hidden="true">&times;</span>
                        </button>                        
                    </div>
                    <div class="modal-body">
                        <?php echo apply_filters('the_content', apply_filters('translate_text', $this->post->post_content, $this->locale)) ; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="<?php echo ($this->buttoncloseclass) ? $this->buttoncloseclass : $this->options['button_class']; ?>" data-dismiss="modal">
                            <?php if($this->buttonclosetext){echo $this->buttonclosetext ;}elseif($this->options['button_text']!=""){ echo $this->options['button_text'];} else { _e( "Close", 'bootmodal'); } ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function html_modal() {
        //$bootmodal_options = get_option('bootmodal_plugin_options');
        ($this->size == '')? $size = $this->options['size'] : $size = $this->size ;
        if($this->animation=='yes') {
            $this->anim_value = 'fade';
        } elseif($this->animation=='no') {
            $this->anim_value = '';
        } elseif($this->options['animation']=='yes') {
            $this->anim_value = 'fade';
        } else {
            $this->anim_value = '';
        }
        
        if( !isset($this->options['bootstrap_version']) or $this->options['bootstrap_version'] == '3') {
            $this->html_modal_bootstrap3();
        } elseif($this->options['bootstrap_version'] == '4') {
            $this->html_modal_bootstrap4();
        }

        ?>
        <script>
            // Hack to enable multiple modals by making sure the .modal-open class
            // is set to the <body> when there is at least one modal open left
            jQuery(document).on('hidden.bs.modal','.modal', function () {
                if (jQuery('.modal:visible').length) { 
                    jQuery('body').addClass('modal-open');
                }
            }) ;   
        </script>
        
        <?php
    }
}
    




















class BootModalAdmin {
    private $plugin_folder = 'boot-modal';
    private $plugin_name = 'Boot-Modal';
    private $wpdb = false ;
    private $options = array();
    private $options_default = array(
        'animation' => 'yes',
        'button_text' => 'Fermer',
        'button_class' => 'btn btn-primary',
        'bootstrap_actif' => 'no',
        'bootstrap_version' => '4',
        'open_button_type' => 'button',
        'open_button_class' => 'btn btn-primary',
        'size' => '',
        'editor_button' => 'yes',
        'css_custom' => '.modal {
    top: 5vh;
}
.modal-content {
    max-height: 90vh; 
}'
    );
    
    

    public function __construct(){
        global $wpdb;
        $this->wpdb = $wpdb ;
        $this->options = get_option('bootmodal_plugin_options');
        add_action('admin_enqueue_scripts', array($this,'load_admin_css'));
        add_action('admin_init', array( $this,'register_settings_and_fields'));
        add_action('admin_menu', array( $this,'options_init'));
        register_activation_hook( __FILE__, array( $this,'plugin_activate'));
        
        if(!isset($this->options['editor_button']) or $this->options['editor_button']!='no'){
            $this->tinymce();
        }
    }
    
    /**
     * HTML page for plugin options
     */
    function html_options_page() {
    ?>
        <div class="wrap bootmodal-admin">
            <header id="bootmodal-admin-header">
                <h1>
                    <img id="bootmodal_logo" src="<?php echo plugins_url().'/'.$this->plugin_folder ; ?>/img/logo_big.png" width="35px" />
                    <?php echo $this->plugin_name; ?>
                </h1>
                <h2><?php _e( "Load a page in a Boostrap's modal", 'bootmodal');?></h2>
                <span id="logo"></span>
            </header>
            <nav>
                <ul id="tabs">      
                    <li><a class="tab_link tab_active" id="use_it" href="#"><?php echo __('How to use this plugin ?', 'bootmodal');?></a></li>
                    <li><a class="tab_link" id="params" href="#"><?php _e( "Plugin settings", 'bootmodal'); ?></a></li>
                    <li><a class="tab_link" id="short" href="#"><?php _e( "Shortcode options", 'bootmodal'); ?></a></li>
                    <li><a class="tab_link" id="credits" href="#"><?php _e( "To donate", 'bootmodal'); ?></a></li>
                </ul>
            </nav>
            <div>
                <div id="use_it_c"  class="content_tab" >
                    <div class="step">
                        <span>1</span> <?php _e( "Set up Bootstrap", 'bootmodal'); ?>
                    </div>
                    <p><?php _e( "If your template does not use Boostrap, activate a light version within the parameters of the plugin.", 'bootmodal'); ?></p>
                    <div class="step">
                        <span>2</span> <?php _e( "Use a shortcode", 'bootmodal'); ?>
                    </div>
                    <p><?php _e('Insert the shortcode [bootmodal post="page"] anywhere to place a link for opening the modal window or use the shortcode generator in the editor.', 'bootmodal');?></p>
                    <p><?php _e('Post parameter is the final section of the permalink of the article you want to display in the modal window.', 'bootmodal');?></p>
                    
                    <div class="step">
                        <span>3</span> <?php _e( "Indulge yourself", 'bootmodal'); ?>
                        <p><?php _e( "Normally it works ...", 'bootmodal'); ?></p>
                        <p><?php _e( "You are happy now ! So smile ! And look at the options available and make me a donation if I changed your life ! Or not...", 'bootmodal'); ?></p>
                    </div>
                </div>
                
                <div id="params_c" class="content_tab">
                    <form method="post" action="options.php" enctype="multipart/form-data">
                        <?php 
                            settings_fields( 'bootmodal_plugin_options' );
                            do_settings_sections( __FILE__ );
                        ?>   
                        <p class="submit">
                            <input type="submit" class="button-primary" name="submit" value="Enregistrer">
                        </p>
                    </form>
                </div>
                
                <div id="short_c" class="content_tab" style="display:none;">
                    <h3>post (<?php _e( "required", 'bootmodal'); ?>)</h3>
                    <p>
                        <?php _e( "This parameter determines which page or which item should be displayed in the modal window.", 'bootmodal'); ?>
                        <br/>
                        <?php _e( "This corresponds to the last part of the permalink articles.", 'bootmodal'); ?>
                    </p>
                    <p class="bootmodal-admin-exemples">
                        [bootmodal post="hello-world"]
                    </p>
                    
                    <h3>animation</h3>
                    <p><?php _e( "Choose whether to activate the animation when displaying the modal window.", 'bootmodal'); ?></p>
                    <p class="bootmodal-admin-exemples">
                        [bootmodal post="hello-world" animation="no"]
                    </p>
                    <p><strong><?php _e( "Possible values", 'bootmodal'); ?></strong> : <?php _e( "yes or no", 'bootmodal'); ?></p>
                    
                    <h3>buttonclass</h3>
                    <p><?php _e( "Customizes the link/button CSS class.", 'bootmodal'); ?></p>
                    <p class="bootmodal-admin-exemples">
                        [bootmodal post="hello-world" buttonclass="my-class-css"]
                    </p>
                    <p><strong><?php _e( "Possible values", 'bootmodal'); ?></strong> : <?php _e( "All you want as long as it is a CSS class.", 'bootmodal'); ?></p>
                    
                    <h3>buttontext</h3>
                    <p><?php _e( "Choose the text for the link or the HTML button. If you don't choose your own text, the text will be the title of the post to open.", 'bootmodal'); ?></p>
                    <p class="bootmodal-admin-exemples">
                        [bootmodal post="hello-world" buttontext="Open It"]
                    </p>
                                        
                    <h3>buttontype</h3>
                    <p><?php _e( "Choose whether to insert a link or an HTML button.", 'bootmodal'); ?></p>
                    <p class="bootmodal-admin-exemples">
                        [bootmodal post="hello-world" buttontype="link"]
                    </p>
                    <p><strong><?php _e( "Possible values", 'bootmodal'); ?></strong> : <?php _e( "Link", 'bootmodal'); ?> : link, <?php _e( "Button", 'bootmodal'); ?> : button</p>
                    
                    <h3>buttoncloseclass</h3>
                    <p><?php _e( "Customize the CSS class associated with the modal window close button.", 'bootmodal'); ?></p>
                    <p class="bootmodal-admin-exemples">
                        [bootmodal post="hello-world" buttoncloseclass="my-class-css"]
                    </p>
                    <p><strong><?php _e( "Possible values", 'bootmodal'); ?></strong> : <?php _e( "All you want as long as it is a CSS class.", 'bootmodal'); ?></p>
                    
                    <h3>buttonclosetext</h3>
                    <p><?php _e( "Text to display in the close button of the modal window.", 'bootmodal'); ?></p>
                    <p class="bootmodal-admin-exemples">
                        [bootmodal post="hello-world" buttonclosetext="Close this modal"]
                    </p>
                    
                    <h3>dismiss</h3>
                    <p><?php _e("Do not close other modal windows open when you open a new one.", 'bootmodal'); ?></p>
                    <p class="bootmodal-admin-exemples">
                        [bootmodal post="hello-world" dismiss="no"]
                    </p>
                    
                    <h3>size</h3>
                    <p><?php _e( "This parameter defines the size of the modal window.", 'bootmodal'); ?></p>
                    <p class="bootmodal-admin-exemples">
                        [bootmodal post="hello-world" size="modal-lg"]
                    </p>
                    <p><strong><?php _e( "Possible values", 'bootmodal'); ?></strong> : <?php _e( "Standard", 'bootmodal'); ?> : <?php _e( "Leave empty", 'bootmodal'); ?>, <?php _e( "Large", 'bootmodal'); ?> : modal-lg, <?php _e( "Small", 'bootmodal'); ?> : modal-sm</p>
                    
                    <h3>urlkey / urlvalue</h3>
                    <p><?php _e( "This parameter make possible to send a parameter in the URL for the page being opened in the modal. This makes it possible to customize the content of the page. The parameter consists of a url_key (parameter name) and url_value (Warning : work only with link and not with button).", 'bootmodal'); ?></p>
                    <p class="bootmodal-admin-exemples">
                        [bootmodal post="hello-world" buttontype="link" urlkey="param" urlvalue="paramvalue"]
                    </p>   
                </div>
                
                <div id="credits_c" class="content_tab">
                    <p><?php _e( "If you want to encourage me to create other plugins or improve this one, you can donate something with Paypal ! If you have no money your are free to use this plugin and enjoy !", 'bootmodal'); ?></p>
                    <div>
                        <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                            <input type="hidden" name="cmd" value="_s-xclick">
                            <input type="hidden" name="hosted_button_id" value="NW6WN5MJ37N9U">
                            <input type="image" src="https://www.paypalobjects.com/fr_FR/FR/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal, le réflexe sécurité pour payer en ligne">
                            <img alt="" border="0" src="https://www.paypalobjects.com/fr_FR/i/scr/pixel.gif" width="1" height="1">
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function(){ 
                $j = jQuery; 
                $j(".content_tab").hide();
                $j("#use_it_c").fadeIn('slow');
                $j(".tab_link" ).click(function() {
                    $j(".tab_active").removeClass('tab_active');
                    $j(".content_tab").hide();
                    $ref = $j(this).attr('id');                            
                    $j("#"+$ref+'_c').show();
                    $j("#"+$ref).addClass('tab_active');
                });
            });
        </script>
    <?php
    }
    
    /**
     * HTML functions for options plugin form
     */
    public function getOptionDefaultValue($option) {
        if(isset($this->options_default[$option])) {
            return $this->options_default[$option] ;
        }
        return $option;
    }

    function html_section_callback() {
        echo "<hr/>";
    }
    function html_generic_text_callback($option) {
        $name = $option['name'];
        $value = "{$this->options[$name]}";
        ?>
        <input type="text" name="bootmodal_plugin_options[<?php echo $name; ?>]" value="<?php echo $value; ?>"/>
        <?php if(isset($option['description'])) : ?>
            <p class="description"><?php echo $option['description'] ; ?></p>
        <?php	endif; 
    }
    function html_generic_longtext_callback($option) {
        $name = $option['name'];
        if(!isset($this->options[$name])) {
            $value = $this->getOptionDefaultValue($name);
        } else {
            $value = "{$this->options[$name]}";
        }
        ?>
        <textarea style="width: 100%; min-height:250px;max-width:700px;" name="bootmodal_plugin_options[<?php echo $name; ?>]"><?php echo $value; ?></textarea>
        <?php if(isset($option['description'])) : ?>
            <p class="description"><?php echo $option['description'] ; ?></p>
        <?php	endif; 
    }
    function html_generic_yesno_callback($option) {
        $name = $option['name'];
        $value = "{$this->options[$name]}";
        ?>
        <input type="radio" name="bootmodal_plugin_options[<?php echo $name; ?>]" value="yes" checked="checked"/> <?php _e( "yes", 'bootmodal'); ?>
        <input type="radio" name="bootmodal_plugin_options[<?php echo $name; ?>]" value="no" <?php if($value=='no'){ echo ' checked="checked"';}?> /> <?php _e( "no", 'bootmodal'); ?>
        <?php if(isset($option['description'])) : ?>
            <p class="description"><?php echo $option['description'] ; ?></p>
        <?php	endif;	
    }
    function html_generic_select_callback($option) {
        $name = $option['name'];
        $value = "{$this->options[$name]}";
        ?>
        <select name="bootmodal_plugin_options[<?php echo $name; ?>]">
            <?php foreach($option['options'] as $option_txt => $option_value) :?>
                <option value="<?php echo $option_value ;?>"<?php if($value==$option_value){ echo ' selected="selected"';}?>>
					<?php echo $option_txt; ?>
				</option>
            <?php endforeach; ?>    
        </select>
		<?php if(isset($option['description'])) : ?>
            <p class="description"><?php echo $option['description'] ; ?></p>
        <?php	endif;	
		
    }
    function html_open_button_type_callback() {
        $value = "{$this->options['open_button_type']}";
        ?>
        <input type="radio" name="bootmodal_plugin_options[open_button_type]" value="button" checked="checked"/> <?php _e( "button", 'bootmodal'); ?>
        <input type="radio" name="bootmodal_plugin_options[open_button_type]" value="link" <?php if($value=='link'){ echo ' checked="checked"';}?> /> <?php _e( "link", 'bootmodal'); ?>
        <?php
    }
    function html_size_callback() {
        $value = "{$this->options['size']}";
        ?>
        <input type="radio" name="bootmodal_plugin_options[size]" value="" checked="checked"/> <?php _e( "standard", 'bootmodal'); ?>
        <input type="radio" name="bootmodal_plugin_options[size]" value="modal-lg" <?php if($value=='modal-lg'){ echo ' checked="checked"';}?> /> <?php _e( "large", 'bootmodal'); ?>
        <input type="radio" name="bootmodal_plugin_options[size]" value="modal-sm" <?php if($value=='modal-sm'){ echo ' checked="checked"';}?> /> <?php _e( "small", 'bootmodal'); ?>
        <?php
    }
    
    
    /**
     * Load the CSS style sheet for backoffice
     */
    function load_admin_css() {
	wp_enqueue_style( 'bootmodal_admin', plugins_url( $this->plugin_folder.'/css/boot_modal_admin.css'));
    }

    /**
     * Enregistrement des parametres du plugin
     */
    function register_settings_and_fields() {
        // $option_group, $option_name, $sanitize_callback
        register_setting('bootmodal_plugin_options','bootmodal_plugin_options');
        
        // Bootstrap
        add_settings_section('bootmodal_plugin_main_section', __( "Bootstrap activation", 'bootmodal'), array($this,'html_section_callback'), __FILE__);
        add_settings_field('bootstrap_actif', 
                            __( "Activate Bootstrap", 'bootmodal'), 
                            array( $this,'html_generic_yesno_callback'), 
                            __FILE__, 
                            'bootmodal_plugin_main_section',
                            array('name'=>'bootstrap_actif',
                                  'description' => __( 'Choose "no" if Bootstrap is already used by your theme or another plugin.', 'bootmodal') ));
        add_settings_field('bootstrap_version', 
                            __( "Bootstrap version", 'automatichxmenu'), 
                            array( $this,'html_generic_select_callback'), 
                            __FILE__, 
                            'bootmodal_plugin_main_section',
                            array('name'=>'bootstrap_version',
                                'description' => __( "Version of bootstrap to use.", 'bootmodal'),
                                'options'=>array( '3' => '3',
                                                  '4' => '4')));

        // Links params
        add_settings_section('bootmodal_plugin_param_link', __( "Link params", 'bootmodal'), array($this,'html_section_callback'), __FILE__);
        add_settings_field('open_button_type', 
                            __( "Link type", 'bootmodal'), 
                            array( $this,'html_open_button_type_callback'), 
                            __FILE__, 
                            'bootmodal_plugin_param_link');
        
        add_settings_field('open_button_class', 
                            __( "CSS class", 'bootmodal'), 
                            array( $this,'html_generic_text_callback'), 
                            __FILE__, 
                            'bootmodal_plugin_param_link',
                            array('name'=>'open_button_class',
                                  'description' => __( 'CSS class of the button to open the modal (default: btn btn-primary).', 'bootmodal') ));
        
        // Modal window params
        add_settings_section('bootmodal_plugin_param_modal', __( "Modal window params", 'bootmodal'), array($this,'html_section_callback'), __FILE__);    
        add_settings_field('button_text', 
                            __( "Close button text", 'bootmodal'), 
                            array( $this,'html_generic_text_callback'), 
                            __FILE__, 
                            'bootmodal_plugin_param_modal',
                            array('name'=>'button_text',
                                  'description' => __( 'Texte of the button inside the modal to close it.', 'bootmodal') ));
        
        add_settings_field('button_class', 
                            __( "Close button CSS class", 'bootmodal'), 
                            array( $this,'html_generic_text_callback'), 
                            __FILE__, 
                            'bootmodal_plugin_param_modal',
                            array('name'=>'button_class',
                                  'description' => __( 'CSS class of the button inside the modal to close it (default: btn btn-primary).', 'bootmodal') ));
        
        add_settings_field('animation', 
                            __( "Animation", 'bootmodal'), 
                            array( $this,'html_generic_yesno_callback'), 
                            __FILE__, 
                            'bootmodal_plugin_param_modal',
                            array('name'=>'animation',
                                  'description' => __( 'Choose "no" if you do not want to animate the modal.', 'bootmodal') ));
        
        add_settings_field('size', 
                            __( "Modal window size", 'bootmodal'), 
                            array( $this,'html_size_callback'), 
                            __FILE__, 
                            'bootmodal_plugin_param_modal');   
        
        add_settings_field('css_custom', 
                            __( "Custom CSS", 'bootmodal'), 
                            array( $this,'html_generic_longtext_callback'), 
                            __FILE__, 
                            'bootmodal_plugin_param_modal',
                            array('name'=>'css_custom',
                                  'description' => __( 'Custom css to override theme styles.', 'bootmodal') ));
        
        // Editor params
        add_settings_section('bootmodal_plugin_param_editor', __( "Editor params", 'bootmodal'), array($this,'html_section_callback'), __FILE__); 
        add_settings_field('editor_button', 
                            __( "Button in editor", 'bootmodal'), 
                            array( $this,'html_generic_yesno_callback'), 
                            __FILE__, 
                            'bootmodal_plugin_param_editor',
                            array('name'=>'editor_button',
                                  'description' => __( 'Add a button in editor to add the shortcode', 'bootmodal') ));
    }
 
    /**
     * Link to show plugin options
     */
    function options_init() {
        add_menu_page( $this->plugin_name, $this->plugin_name, 'administrator', 'bootmodal-options', array( $this,'html_options_page'), plugin_dir_url( __FILE__ ).'/img/logo_small.png' );
        //add_options_page($this->plugin_name, $this->plugin_name, 'administrator', __FILE__, array( $this,'html_options_page'));
    }

    /**
     * Activation function
     */
    function plugin_activate() {
        $defaults = $this->options_default ; 

        if(get_option('bootmodal_plugin_options')) return;
        add_option('bootmodal_plugin_options', $defaults);
    }
    
    /***************************************************************************
     * TINYMCE EDITOR
     **************************************************************************/
    public function tinymce(){
        // Declaring a new TinyMCE button
        add_action('admin_head', array($this ,'tinymce_add_editor_button'));
        
        // Adding CSS
        add_action('admin_enqueue_scripts', array($this ,'tinymce_css'));
        
        // Multilingual support
        add_filter( 'mce_external_languages', array($this ,'tinymce_multilingual_support'));
    }

    public function tinymce_add_editor_button() {
        global $typenow;
        // check user permissions
        if ( !current_user_can('edit_posts') && !current_user_can('edit_pages') ) {
            return;
        }
        // verify the post type
        if( ! in_array( $typenow, array( 'post', 'page' ) ) )
            return;
        // check if WYSIWYG is enabled
        if ( get_user_option('rich_editing') == 'true') {
            // Specify the path to the script with the plugin for TinyMCE 
            add_filter("mce_external_plugins", array($this ,"tinymce_add_plugin"));
            // Add buttons in the editor 
            add_filter('mce_buttons', array($this ,'tinymce_register_button'));
        }
    }
    
    public function tinymce_add_plugin($plugin_array) {
        $plugin_array['bootmodal_editor_button'] = plugins_url( 'js/tinymce.js', __FILE__ );
        return $plugin_array;
    }

    public function tinymce_register_button($buttons) {
       array_push($buttons, "bootmodal_editor_button");
       return $buttons;
    }
    
    public function tinymce_css() {
        wp_enqueue_style( $this->plugin_folder.'-tinymce', plugins_url( $this->plugin_folder.'/css/tinymce.css'));
    }
    
    public function tinymce_multilingual_support($locales) {
        $locales['bootmodal_editor_button2'] = plugin_dir_path ( __FILE__ ) . 'tinymce-translations.php';
        return $locales;
    }
}