<?php

/**
 * WPLMS Activecampaign Widget
 */


 if ( ! defined( 'ABSPATH' ) ) exit;

/*
WIDGETS
 */

add_action( 'widgets_init', function() {
	register_widget('Wplms_Activecampaign_Subscribe_Widget');
});

class Wplms_Activecampaign_Subscribe_Widget extends WP_Widget {


	function __construct() {

	  $widget_ops = array( 
	  	'classname' => 'wplms_activecampaign_subscribe', 
	  	'description' => __('Subscribe to a Activecampaign list','Activecampaign Widget description','wplms-activecampaign') 
	  );

	  $control_ops = array( 'width' => 250, 'height' => 350,'id_base' => 'wplms_activecampaign_subscribe_widget');

	  	parent::__construct( 'Wplms_Activecampaign_Subscribe_Widget',  __('WPLMS Activecampaign Subscribe','wplms-activecampaign'), $widget_ops, $control_ops);
  	}

	function widget( $args, $instance ) {

		global $bp,$wpdb;

		extract( $args );

		extract( $instance, EXTR_SKIP );
		echo $before_widget;
		if(isset($title) && $title !='')

		if(!empty($course_list)){
			if(is_singular('course')){
				$list_id = get_post_meta(get_the_ID(),'vibe_wplms_activecampaign_list',true);
				if(!empty($list_id)){
					$list = $list_id;
				}
			}
		}

     	$recaptcha_html = '';
	    if(!empty($captcha)){
	    	$this->captcha_enabled = 1;
	    	if(!empty(vibe_get_option('google_captcha_public_key')) && !empty(vibe_get_option('google_captcha_private_key'))){
	    		echo "<script src='https://www.google.com/recaptcha/api.js'></script>";
	    		$recaptcha_html = '<div class="g-recaptcha" data-theme="clean" data-sitekey="'.vibe_get_option('google_captcha_public_key').'"></div>';
	    	}
	    } 
	    switch($style){
	    	case 'standard':
	    		echo '<div class="activecampaign_subscription_form">';
	    		echo $before_title . $title . $after_title; 
	    		if(!is_user_logged_in()){
	    			echo '<input type="email" name="" class="form_field wplms_activecampaign_name_field" placeholder="'.$name_placeholder.'" />';
	    		}else{
	    			echo '<input type="hidden" name="" class="form_field wplms_activecampaign_name_field" value="'.bp_core_get_user_displayname(get_current_user_id()).'" />';
	    		}
				echo '<input type="email" name="" class="form_field wplms_activecampaign_email_field" placeholder="'.$placeholder.'" />
				'.$recaptcha_html.'<a class="wplms_subscribe_ac full button fa fa-envelope-o" data-list-id="'.$list.'"> &nbsp; '.$subscribe_button.'</a>';
				wp_nonce_field($list,'wplms_activecampaign_list_security');
				echo '</div>'.(empty($recaptcha_html)?'':'<style>.g-recaptcha{transform:scale(0.77);-webkit-transform:scale(0.77);transform-origin:0 0;-webkit-transform-origin:0 0;}.g-recaptcha+.wplms_subscribe_ac{margin-top:-10px}</style>');
	    	break;
	    	case 'elegant':
	    		echo '<div style="background:rgba(0,0,0,0.05);padding:30px 30px 0;"><div class="activecampaign_subscription_form" style="overflow:hidden;">';
	    		echo $title; 
	    		if(!is_user_logged_in()){
	    			echo '<input type="email" name="" class="form_field wplms_activecampaign_name_field" placeholder="'.$name_placeholder.'" />';
	    		}else{
	    			echo '<input type="hidden" name="" class="form_field wplms_activecampaign_name_field" value="'.bp_core_get_user_displayname(get_current_user_id()).'" />';
	    		}
				echo '<input type="email" name="" class="form_field wplms_activecampaign_email_field" placeholder="'.$placeholder.'" />
				'.$recaptcha_html.'<a class="wplms_subscribe_ac button full fa fa-envelope-o" data-list-id="'.$list.'"> &nbsp; '.$subscribe_button.'</a>';
				wp_nonce_field($list,'wplms_activecampaign_list_security');
				echo '</div></div>'.(empty($recaptcha_html)?'':'<style>.g-recaptcha{transform:scale(0.67);-webkit-transform:scale(0.67);transform-origin:0 0;-webkit-transform-origin:0 0;}.g-recaptcha+.wplms_subscribe_ac{margin-top:-10px}</style>');
	    	break;
	    	default:
	    		echo $before_title . $title . $after_title; 
	    		echo '<div class="activecampaign_subscription_form">';
		    		if(!is_user_logged_in()){
		    			echo '<input type="email" name="" class="form_field wplms_activecampaign_name_field" placeholder="'.$name_placeholder.'" />';
		    		}else{
		    			echo '<input type="hidden" name="" class="form_field wplms_activecampaign_name_field" value="'.bp_core_get_user_displayname(get_current_user_id()).'" />';
		    		}
				echo '<input type="email" name="" class="form_field wplms_activecampaign_email_field" placeholder="'.$placeholder.'" />
				<a class="wplms_subscribe_ac ac_button button fa fa-envelope-o" data-list-id="'.$list.'"></a>';
				wp_nonce_field($list,'wplms_activecampaign_list_security');
				echo $recaptcha_html.'</div>'.(empty($recaptcha_html)?'':'<style>.g-recaptcha{transform:scale(0.77);-webkit-transform:scale(0.77);transform-origin:0 0;-webkit-transform-origin:0 0;}.g-recaptcha+.wplms_subscribe_ac{margin-top:-10px}</style>');
	    	break;
	    }
		
		?>
		<style>.activecampaign_subscription_form{position:relative;}input.activecampaign_form{padding-right:36px;}.ac_button.button{position: absolute;margin:0;border-radius:0;font-size: 16px;padding:7px 10px 8px;top: 0;right: 0;border-width:1px !important;}.wplms_subscribe_ac.button.fa-check{background: #7ABA7A !important;border-color: #7ABA7A !important;color: #fff !important;}.wplms_subscribe_ac.button.fa-times{background: #D13F31 !important;border-color: #D13F31 !important;color: #fff !important;}</style><script>
		jQuery(document).ready(function($){
			$('.wplms_subscribe_ac').on('click',function(){
				var $this = $(this);
				var list_id = $this.attr('data-list-id');
				var field = $this.parent().find('.wplms_activecampaign_email_field');
				var value = field.val();
				if(!value){
					field.css('border-color','#D13F31');
					return;
				}
				if(!$this.parent().find('.wplms_activecampaign_email_field').val().length){
					$this.parent().find('.wplms_activecampaign_email_field').css('border-color','#D13F31');
					return;
				}
				var regex = /^([a-z0-9._-]+@[a-z0-9._-]+\.[a-z]{2,4}$)/i;
				if(!value.match(regex)){
					console.log('failed');
		            field.css('border-color','#D13F31');
		            return;
		        }

		        var response='';
				if(typeof grecaptcha != 'undefined'){
					response = grecaptcha.getResponse();
					if(response.length == 0){
						field.css('border-color','#D13F31');
						return;
					}
				}
				$this.addClass('fa-spinner');
				$.ajax({
                      	type: 	"POST",
                      	url: 	ajaxurl,
                      	data: { action: 'wplms_activecampaign_subscribe_to_list', 
                              	security: $('#wplms_activecampaign_list_security').val(),
                              	email:value,
                              	name:$this.parent().find('.wplms_activecampaign_name_field').val(),
                              	list:list_id,
                              	captcha:response,
                            },
                        cache: false,
                      	success: function (html) {
                      		$this.removeClass('fa-spinner');
                  		 	if($.isNumeric(html)) {
                              field.css('border-color','#7ABA7A');
                              $this.addClass('fa-check');
                            }else{
                            	field.css('border-color','#D13F31');
                            	$this.addClass('fa-times');
                            	if($this.hasClass('ac_button')){
                            		$this.attr('title',html);
                            	}else{
                            		$this.after('<span class="ac_error" style="color:#D13F31;">'+html+'</span>');
                            	}
                            }  
                      		setTimeout(function(){
                      			$this.removeClass('fa-check');
                      			$this.removeClass('fa-times');
                      			$this.removeAttr('title');
                      			$this.next('.ac_error').remove();
                      			field.removeAttr('style');
                      			field.val('');
                      		},5000);
                      	}
                  	});
			});
			$('.wplms_activecampaign_email_field').on('click',function(){$(this).removeAttr('style');});
		});
		</script>
		<?php
	 	echo $after_widget; ?>
	<?php
	}


	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['placeholder'] = strip_tags( $new_instance['placeholder'] );
		$instance['name_placeholder'] = strip_tags( $new_instance['name_placeholder'] );
		$instance['subscribe_button'] = strip_tags( $new_instance['subscribe_button'] );
		$instance['course_list'] = strip_tags( $new_instance['course_list'] );
		$instance['list'] = strip_tags( $new_instance['list'] );
		$instance['style'] = strip_tags( $new_instance['style'] );
		$instance['captcha'] = strip_tags( $new_instance['captcha'] );
		
		return $instance;
	}

	function form( $instance ) {

		$defaults = array( 
			'title'=> 'Subscribe to our News Letter',
			'placeholder'=>'Enter your email address',
			'name_placeholder'=>'Enter your name',
			'subscribe_button'=>'Subscribe now',
			'course_list'=>'',
			'list' => '',
			'style'=>'',
			'captcha'=>'',
		);

		$instance = wp_parse_args( (array) $instance, $defaults );

		$title = esc_attr($instance['title']);
		$placeholder = esc_attr($instance['placeholder']);
		$name_placeholder = esc_attr($instance['name_placeholder']);
		$subscribe_button = esc_attr($instance['subscribe_button']);
		$course_list = esc_attr($instance['course_list']);
		$list = esc_attr($instance['list']);
		$style = esc_attr($instance['style']);
		$captcha = esc_attr($instance['captcha']);
		?>
		<p><label for="wplms-activecampaign-widget-title"><?php _e( 'Widget Title', 'wplms-activecampaign' ); ?> <input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" style="width: 100%" /></label></p>
		<p><label for="wplms-activecampaign-widget-placeholder"><?php _e( 'Name placeholder label', 'wplms-activecampaign' ); ?> <input id="<?php echo $this->get_field_id( 'name_placeholder' ); ?>" name="<?php echo $this->get_field_name( 'name_placeholder' ); ?>" type="text" value="<?php echo esc_attr( $name_placeholder ); ?>" style="width: 100%" /></label></p>
		<p><label for="wplms-activecampaign-widget-placeholder"><?php _e( 'Email placeholder label', 'wplms-activecampaign' ); ?> <input id="<?php echo $this->get_field_id( 'placeholder' ); ?>" name="<?php echo $this->get_field_name( 'placeholder' ); ?>" type="text" value="<?php echo esc_attr( $placeholder ); ?>" style="width: 100%" /></label></p>
		<p><label for="wplms-activecampaign-widget-button-text"><?php _e( 'Subscribe Button label', 'wplms-activecampaign' ); ?> <input id="<?php echo $this->get_field_id( 'subscribe_button' ); ?>" name="<?php echo $this->get_field_name( 'subscribe_button' ); ?>" type="text" value="<?php echo esc_attr( $subscribe_button ); ?>" style="width: 100%" /></label></p>
		<p><label for="wplms-activecampaign-widget-course-list"><input id="<?php echo $this->get_field_id( 'course_list' ); ?>" name="<?php echo $this->get_field_name( 'course_list' ); ?>" type="checkbox" value="1" <?php if(!empty($course_list)){echo "CHECKED";} ?> /><?php _e( 'Auto Pick Course List on course page', 'wplms-activecampaign' ); ?> </label></p>
		<p><label for="wplms-activecampaign-lists"><?php _e( 'Select List (Default)', 'wplms-activecampaign' ); ?> 
			<select id="<?php echo $this->get_field_id( 'list' ); ?>" name="<?php echo $this->get_field_name( 'list' ); ?>">
				<?php
					$ac_init = Wplms_Activecampaign_Init::init();
					$lists = $ac_init->get_lists();
					foreach($lists as $list_id => $list_name){
						echo '<option value="'.$list_id.'" '.(($list == $list_id)?'SELECTED':'').'>'.$list_name.'</option>';
					}
				?>
			</select>
			</label>
		</p>
		<p><label for="wplms-activecampaign-widget-style"><?php _e( 'Select Widget Style', 'wplms-activecampaign' ); ?></label>
		<select id="<?php echo $this->get_field_id( 'style' ); ?>" name="<?php echo $this->get_field_name( 'style' ); ?>">
			<option value='' <?php if(empty($style)){echo "SELECTED";} ?>><?php _ex('Minimal','activecampaign widget style option','wplms-activecampaign'); ?></option>
			<option value='standard' <?php if($style == 'standard'){echo "SELECTED";} ?>><?php _ex('Standard','activecampaign widget style option','wplms-activecampaign'); ?></option>
			<option value='elegant' <?php if($style == 'elegant'){echo "SELECTED";} ?>><?php _ex('Elegant','activecampaign widget style option','wplms-activecampaign'); ?></option>
		</select>
		</p>
		<p><label for="wplms-activecampaign-widget-captcha"><input id="<?php echo $this->get_field_id( 'captcha' ); ?>" name="<?php echo $this->get_field_name( 'captcha' ); ?>" type="checkbox" value="1" <?php if(!empty($captcha)){echo "CHECKED";} ?> /><?php _e( 'Add Google Captcha (requires Google recaptcha key in WP admin - WPLMS - Misc.)', 'wplms-activecampaign' ); ?> </label></p>
		<?php
	}
}