<?php
/**
 * Admin functions and actions.
 *
 * @author 		VibeThemes
 * @category 	Admin
 * @package 	wplms-activecampaign/Includes
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wplms_Activecampaign_Admin{

	public static $instance;
    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new Wplms_Activecampaign_Admin();
        return self::$instance;
    }

	private function __construct(){
		$this->init = Wplms_Activecampaign_Init::init();
		$this->settings = $this->init->settings;
		add_action('admin_notices', array( $this, 'show_admin_notices' ), 10);
		add_filter('wplms_lms_settings_tabs',array($this,'setting_tab'));
		add_filter('lms_settings_tab',array($this,'tab'));

	}

	function show_admin_notices(){

	}

	function setting_tab($tabs){
		$tabs['wplms-activecampaign'] = __('Activecampaign','wplms-activecampaign');
		return $tabs;
	}

	function tab($name){
		if($name == 'wplms-activecampaign')
			return 'wplms_activecampaign_settings';
		return $name;
	}

	function get_settings(){

		if(!empty($this->settings)){
			if(empty($this->settings['language_code'])){
				$list_creation_button = __('Enter List creation details','wplms-activecampaign');
			}else{
				$list_creation_button = __('Click to Create/Sync Lists','wplms-activecampaign');
			}
		}else{
			$list_creation_button = __('Enter List creation details','wplms-activecampaign');
		}
		
		return apply_filters('wplms_activecampaign_settings',array(
			array(
				'label' => __( 'ActiveCampaign API Key', 'wplms-activecampaign' ),
				'name' => 'activecampaign_api_key',
				'type' => 'text',
				'desc' => sprintf(__( 'How to get Activecampaign API Key %s Tutorial %s', 'wplms-activecampaign' ),'<a href="http://kb.activecampaign.com/integrations/api-integrations/about-api-keys" target="_blank">','</a>'),
			),
			array(
				'label' => __( 'ActiveCampaign URL', 'wplms-activecampaign' ),
				'name' => 'activecampaign_api_url',
				'type' => 'text',
				'desc' => __( 'Enter the ActiveCampaign URL here.', 'wplms-activecampaign' ),
			),
			array(
				'label' => __( 'All Users List, also adds enable Subscribe option in Registration ', 'wplms-activecampaign' ),
				'name' => 'enable_registration',
				'type' => 'activecampaign_lists',
				'desc' => __( 'All users signing up via Registration see an option to signup for the newsletter.', 'wplms-activecampaign' ),
			),
			array(
				'label' => __( 'Enable Subscribe option in WooCommerce Checkout', 'wplms-activecampaign' ),
				'name' => 'enable_woo_subscription',
				'type' => 'activecampaign_lists',
				'desc' => __( 'All users purchasing any course or signing up via WooCommerce see an option to signup for the newsletter.', 'wplms-activecampaign' ),
			),
			array(
				'label' => __( 'All Courses Students List', 'wplms-activecampaign' ),
				'name' => 'all_course_students',
				'type' => 'activecampaign_lists',
				'desc' => __( 'All users signing up via WooCommerce see an option to signup for the newsletter.', 'wplms-activecampaign' ),
			),
			array(
				'label' => __( 'Create Course specific lists', 'wplms-activecampaign' ),
				'name' => 'course_list',
				'type' => 'checkbox',
				'desc' => sprintf(__( '%s %s %s , To create a list for every course, list creation details must be saved. reload the page after saving details. ', 'wplms-activecampaign' ),'<a id="sync_course_lists_now" class="button"><span></span>',$list_creation_button,'</a>'),
			),
			array(
				'label' => __( 'Language Code (Required for list creation)', 'wplms-activecampaign' ),
				'name' => 'language_code',
				'type' => 'text',
				'desc' => __( 'Enter langauge code', 'wplms-activecampaign' ),
			),

			array(
				'label' => __( 'Auto-Subscribe/unsubscribe user on Course subscribe/unsubscribe', 'wplms-activecampaign' ),
				'name' => 'auto_course_list_subscribe',
				'type' => 'checkbox',
				'desc' => _x( 'Auto-Subscribe user to course list on course subscription. Auto-remove user from Course when user is removed from the course.','WPLMS Activecampaign setting', 'wplms-activecampaign'),
			),
			array(
				'label' => __( 'All Instructors list', 'wplms-activecampaign' ),
				'name' => 'all_instructors_list',
				'type' => 'activecampaign_lists',
				'desc' => __( 'All users signing up via WooCommerce see an option to signup for the newsletter.', 'wplms-activecampaign' ),
			),
			/*
			array(
				'label' => __( 'Auto Sync Lists', 'wplms-activecampaign' ),
				'name' => 'auto_sync_lists',
				'type' => 'select',
				'options'=>array(
					''=>__('Manual Sync','wplms-cc'),
					'daily'=>__('Every Day','wplms-cc'),
				),
				'desc' => __( 'Sync all Lists,', 'wplms-activecampaign' ),
			),*/
		));	
	}
	function settings(){

		echo '<form method="post">';
		wp_nonce_field('wplms_activecampaign_settings');   
		echo '<table class="form-table">
				<tbody>';

		$settings = $this->get_settings();

		$this->generate_form($settings);
		if(isset($_GET['batch'])){
			$gr = new Wplms_Activecampaign($this->init->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
			
		}
		
		?>

		<?php
		echo '<tr valign="top"><th colspan="2"><input type="submit" name="save_wplms_activecampaign_settings" class="button button-primary" value="'.__('Save Settings','wplms-activecampaign').'" /></th>';
		echo '</tbody></table></form>'; ?><style>#sync_course_lists_now span,.sync_lists span{padding:0;} .sync_lists,#sync_course_lists_now{position:relative;}.sync_lists.active,#sync_course_lists_now.active{color: rgba(255,255,255,0.2);} #sync_course_lists_now.active span,.sync_lists.active span{position:absolute;left:0;top:0;width:0;transition: width 1s;height:100%;background:#009dd8;text-align: center;color: #fff;}.company,.company_address,.company_country,.company_zip,.company_state,.company_city,.permission_reminder,.from_name,.from_email,.subject,.language_code{display:none;}</style><script>

			function isJson(str) {

				if(str == null)
					return false;

			    try {
			        JSON.parse(str);
			    } catch (e) {
			        return false;
			    }
			    if(Object.keys(str).length === 0 && str.constructor === Object){
			    	return false;
			    }


			    return true;
			}
			jQuery(document).ready(function($){

				$('.sync_lists').each(function(list,i){
					let val = $(this).parent().find('.sync_list_selection').val();
					if(!val || !val.length){
						$(this).hide();
					}
				});
				$('.sync_list_selection').on('change',function(){
					if($(this).val().length){
						$(this).parent().find('.sync_lists').show();
					}else{
						$(this).parent().find('.sync_lists').hide();
					}
				});
				$("body").on('click','.sync_lists',function(event){
					event.preventDefault();
					var $this = $(this);

					if($this.hasClass('active')){return;}
					$this.addClass('active');
					$this.find('span').css('width','10%');
					$.ajax({
                      	type: 	"POST",
                      	url: 	ajaxurl,
                      	data: { action: 'sync_lists_get', //Fetches from 
                              	security: $('#_wpnonce').val(),
                              	list:$('select[name="'+$this.attr('id')+'"]').val(),
                            },
                      	cache: false,
                      	success:function(json){ 

                      		$this.find('span').css('width','40%'); 
                      		
                      		$.ajax({
		                      	type: 	"POST",
		                      	url: 	ajaxurl,
		                      	data: { action: 'sync_lists_put', 
		                              	security: $('#_wpnonce').val(),
		                              	emails:json,
		                              	element:$this.attr('id'),
		                              	list:$('select[name="'+$this.attr('id')+'"]').val(),
		                            },
		                        cache: false,
		                      	success: function (html) {
		                      		if(isJson(html)){ 

		                      			ajaxcalls = $.parseJSON(html);
		                      			if(ajaxcalls.length){
											var json = ajaxcalls[0];
											var data = getData(json,0);
											for (var i = 1; i < ajaxcalls.length; i++) {
											    // Or only the last "i" will be used
											    (function (i) {
											        data = data.then(function() {
											            return getData(ajaxcalls[i],i);
											        });
											    }(i));
											}

											// Also, see how better the getData can be.
											function getData(json,key) {
												console.log(key);
												console.log(ajaxcalls.length);
												console.log((key+1/ajaxcalls.length));
											    return 	$.ajax({
									                      	type: "POST",
									                      	url: ajaxurl,
									                      	data: json,
									                      	success:function(j){

									                      		var width = 40+(((key+1)/ajaxcalls.length)*60);
									                      		console.log(width);

									                      		if(width >=100){
									                      			width = 99;
									                      		}
									                      		$this.find('span').css('width',width+'%');
									                      		if((key+1) == ajaxcalls.length ){
									                      			$this.find('span').css('width','100%');
									                      			$this.find('span').text('Sync Complete');
										                      		setTimeout(function(){
										                      			$this.removeClass('active');$this.find('span').text('');
										                      			$this.find('span').css('width','0%');
										                      		},2000);
									                      		}
									                      	}
								                      	}).done(function(d) {
														        var response = d;
														    }).fail(function() {
														        alert('ERROR');
														    });
											}
		                      			}
		                      		}else{
		                      			$this.find('span').css('width','100%');
			                      		$this.find('span').text('Sync Complete');
			                      		setTimeout(function(){
			                      			$this.removeClass('active');$this.find('span').text('');
			                      			$this.find('span').css('width','0%');
			                      		},2000);
		                      		}
		                      	}
	                      	}); 
                		}
					});
				});
				$('#sync_course_lists_now').on('click',function(event){
					if(!$(this).hasClass('filled')){
						event.preventDefault();
						$('.language_code').toggle(200);
						if(!$(this).hasClass('filled')){
							$(this).addClass('filled button-primary');

						}
					}else{
				
					var $this = $(this);
					if($this.hasClass('active')){return;}
					var language_code =$('input[name="language_code"]').val();
				    if (!language_code){
				      alert("Please fill all the required fields for List creation in Activecampaign");
				      return false;
				    }

				    $this.addClass('active');
					$this.find('span').css('width','10%');
					$.ajax({
                      	type: 	"POST",
                      	url: 	ajaxurl,
                      	dataType: "json",
                      	data: { action: 'get_create_course_lists', //Fetches from 
                              	security: $('#_wpnonce').val(),
                            },
                      	cache: false,
                      	success:function(json){
                      		$this.find('span').css('width','40%'); 
	                      		$.ajax({
			                      	type: 	"POST",
			                      	url: 	ajaxurl,
			                      	data: { action: 'course_lists_put', 
			                              	security: $('#_wpnonce').val(),
			                              	data:JSON.stringify(json),
			                            },
			                        cache: false,
			                      	complete: function (html) {
			                      		$this.find('span').css('width','100%');
			                      		$this.find('span').text('Sync Complete');
			                      		setTimeout(function(){
			                      			$this.removeClass('active');$this.find('span').text('');
			                      			$this.find('span').css('width','0%');
			                      		},2000);
			                      	}
		                      	}); 
                		}
					});
				}
				});
			});
			</script>
			<?php
	}

	function generate_form($settings){
		
		foreach($settings as $setting ){
			echo '<tr valign="top" class="'.$setting['name'].'">';
			switch($setting['type']){
				case 'textarea':
					echo '<th scope="row" class="titledesc"><label>'.$setting['label'].'</label></th>';
					echo '<td class="forminp"><textarea name="'.$setting['name'].'" style="max-width: 560px; height: 240px;border:1px solid #DDD;">'.(isset($this->settings[$setting['name']])?$this->settings[$setting['name']]:'').'</textarea>';
					echo '<span>'.$setting['desc'].'</span></td>';
				break;
				case 'select':
					echo '<th scope="row" class="titledesc"><label>'.$setting['label'].'</label></th>';
					echo '<td class="forminp"><select name="'.$setting['name'].'">';
					foreach($setting['options'] as $key=>$option){
						echo '<option value="'.$key.'" '.(isset($this->settings[$setting['name']])?selected($key,$this->settings[$setting['name']]):'').'>'.$option.'</option>';
					}
					echo '</select>';
					echo '<span>'.$setting['desc'].'</span></td>';
				break;
				case 'checkbox':
					echo '<th scope="row" class="titledesc"><label>'.$setting['label'].'</label></th>';
					echo '<td class="forminp"><input type="checkbox" name="'.$setting['name'].'" '.(isset($this->settings[$setting['name']])?'CHECKED':'').' />';
					echo '<span>'.$setting['desc'].'</span>';
				break;
				case 'number':
					echo '<th scope="row" class="titledesc"><label>'.$setting['label'].'</label></th>';
					echo '<td class="forminp"><input type="number" name="'.$setting['name'].'" value="'.(isset($this->settings[$setting['name']])?$this->settings[$setting['name']]:$setting['std']).'" />';
					echo '<span>'.$setting['desc'].'</span></td>';
				break;
				case 'text':
					echo '<th scope="row" class="titledesc"><label>'.$setting['label'].'</label></th>';
					echo '<td class="forminp"><input type="text" name="'.$setting['name'].'" value="'.(isset($this->settings[$setting['name']])?$this->settings[$setting['name']]:$setting['std']).'" />';
					echo '<span>'.$setting['desc'].'</span></td>';
				break;
				case 'activecampaign_lists':

					echo '<th scope="row" class="titledesc"><label>'.$setting['label'].'</label></th>';
					echo '<td class="forminp"><select class="sync_list_selection" name="'.$setting['name'].'">
					<option value="">'._x('Disable','disable switch in WPLMS Activecampaign settings','wplms-activecampaign').'</option>';
					$gr_lists = $this->init->get_lists();

					foreach($gr_lists as $key=>$option){
						echo '<option value="'.$key.'" '.(isset($this->settings[$setting['name']])?selected($key,$this->settings[$setting['name']]):'').'>'.$option.'</option>';
					}
					echo '</select><a id="'.$setting['name'].'" class="button sync_lists"><span></span>'.__('Sync all Users','wplms-cc').'</a>';
					echo '<span>'.$setting['desc'].'</span></td>';
				break;
			}
		}	
	}
	/*a#enable_registration:before { position: absolute; content: ''; width: 10%; background: #009dd8; left: 0; top: 0; height: 100%; z-index: 2; border-radius: 2px; } a#enable_registration:after { position: absolute; content: ''; width: 100%; left: 0; top: 0; height: 100%; background: rgba(255,255,255,0.8); border-radius: 2px; z-index: 1; }*/
	function save(){
		

		if(!isset($_POST['save_wplms_activecampaign_settings']))
			return;

		if ( !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'wplms_activecampaign_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator.','wplms-activecampaign').'</p></div>';
		}

		$settings = $this->get_settings();
		foreach($settings as $setting){
			if(isset($_POST[$setting['name']])){
				$this->settings[$setting['name']] = $_POST[$setting['name']];
			}else if($setting['type'] == 'checkbox' && isset($this->settings[$setting['name']])){
				unset($this->settings[$setting['name']]);
			}
		}

		update_option(WPLMS_ACTIVECAMPAIGN_OPTION,$this->settings);
		echo '<div class="updated notice is-dismissible"><p>'.__('Settings Saved.','wplms-activecampaign').'</p></div>';
	}

}

add_action('admin_init','wplms_activecampaign_admin_initialise');
function wplms_activecampaign_admin_initialise(){
	Wplms_Activecampaign_Admin::init();	
}

function wplms_activecampaign_settings(){
	$init = Wplms_Activecampaign_Admin::init();
	$init->save();
	$init->settings();
}