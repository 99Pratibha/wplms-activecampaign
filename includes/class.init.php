<?php
/**
 * Admin functions and actions.
 *
 * @author 		VibeThemes
 * @category 	Admin
 * @package 	Wplms-Activecampaign/Includes
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wplms_Activecampaign_Init{

	/*
	Stores all lists
	 */
	public $lists = array();
	/*
	Stores emails from list id List ID => Member emails
	 */
	public $list_members = array();

	public static $instance;
    
    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new Wplms_Activecampaign_Init();
        return self::$instance;
    }

	private function __construct(){
		$this->loop_max = 99;
		
				// add_action( 'save_post', 'save_metadata', 100);


		add_action('bp_signup_validate', array($this,'add_subscribe_activecampaign'));
		add_action('bp_before_registration_submit_buttons', array($this,'display_subscribe_checkbox'),9,1);
			
			
		add_action('woocommerce_review_order_before_submit',array($this,'display_woo_subscribe_checkbox'),10);
		add_action('woocommerce_new_order',array($this,'check_if_enabled'),10,1);
		add_action( 'woocommerce_order_status_completed',array($this, 'add_student_to_subscribe_list' ),1,1);
			
			
		add_filter('wplms_course_metabox',array($this,'wplms_course_lists'));
			
				
		add_filter('wplms_course_subscribed',array($this,'add_to_list'),10,2);	
		add_filter('wplms_course_unsubscribe',array($this,'remove_from_list'),10,2);

		
		add_action('init',array($this,'auto_sync'));

		
		add_action('transition_post_status',array($this,'create_list_on_new_course_publish'),10,3);
		add_action('user_register',array($this,'subscribe_user_to_list'),10,1);
		
		
		/* AJAX FUNCTIONS */
		add_action('wp_ajax_sync_lists_get',array($this,'sync_lists_get'));
		add_action('wp_ajax_sync_lists_put',array($this,'sync_lists_put'));
		add_action('wp_ajax_get_create_course_lists',array($this,'get_create_course_lists'));
		add_action('wp_ajax_course_lists_put',array($this,'course_lists_put'));
		/* Subscribe Widget */
		add_action('wp_ajax_wplms_gr_subscribe_to_list',array($this,'wplms_gr_subscribe_to_list'));
		add_action('wp_ajax_nopriv_wplms_gr_subscribe_to_list',array($this,'wplms_gr_subscribe_to_list'));
		//Add activecampaign in custom registration form
		add_filter('wplms_registration_form_settings',array($this,'wplms_add_activecampaign_list_in_custom_registration_form'));
		add_action('wplms_before_registration_form',array($this,'wplms_add_activecampaign_checkbox_on_registration'));
		add_action('wplms_custom_registration_form_user_added',array($this,'wplms_add_user_to_activecampaign_list_on_registration'),10,3);
	}


	function get_settings(){
		if(empty($this->settings)){
			$this->settings = get_option(WPLMS_GETRESPONSE_OPTION);
		}
	}

	function show_admin_notices(){

	}

	function auto_sync(){
		$this->get_settings();
		if(!empty($this->settings['auto_sync_lists'])){

			if (! wp_next_scheduled ( 'wplms_activecampaign_sync_lists' )) {
				wp_schedule_event(time(), $this->settings['auto_sync_lists'], 'wplms_activecampaign_sync_lists');
			}

			add_action('wplms_activecampaign_sync_lists',array($this,'sync_lists'));
		}
	}

	function get_lists(){
		$lists = array();
		if(!empty($this->lists))
			return $this->lists;

		if(isset($this->settings) && isset($this->settings['activecampaign_api_key']) && isset($this->settings['activecampaign_api_url'])){
			$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
			$lists = $gr->get_lists(); 
			if(!empty($lists)){
				foreach($lists as $list){
					$this->lists[$list->campaignId]=$list->name;
				}
			}
		}
		return $this->lists;
	}

	function display_subscribe_checkbox(){
		$this->get_settings();

		if(empty($this->settings))
			return;

		if(!isset($this->settings['enable_registration']))
			return;

		echo '<div class="activecampaign_subscribe_checkbox" style="margin:15px 0;"><div class="checkbox">
        <input type="checkbox" name="subscribe_activecampaign" id="subscribe_activecampaign" value="1" checked> <label for="subscribe_activecampaign">'._x('Subscribe to our newsletter','Activecampaign subscribe checkbox in Buddypress registration form','wplms-activecampaign').'</label>  
        </div></div>';
	}

	function display_woo_subscribe_checkbox(){
		$this->get_settings();

		if(empty($this->settings))
			return;

		if(!isset($this->settings['enable_woo_subscription']))
			return;

		echo '<div class="activecampaign_subscribe_checkbox" style="margin:15px 0;"><div class="checkbox">
        <input type="checkbox" name="subscribe_activecampaign" id="subscribe_activecampaign" value="1" checked> <label for="subscribe_activecampaign">'._x('Subscribe to our newsletter','Activecampaign subscribe checkbox in Buddypress registration form','wplms-activecampaign').'</label>  
        </div></div>';
	}

	
	function add_subscribe_activecampaign(){
		global $bp;

		$this->get_settings();

		if(empty($this->settings))
			return;

		if(!isset($this->settings['enable_registration']))
			return;

		if(empty($_POST['subscribe_activecampaign']))
			return;

        if (!empty($_POST['subscribe_activecampaign']) && empty($bp->signup->errors)) {
            
            if(isset($this->settings) && isset($this->settings['activecampaign_api_key']) && isset($this->settings['activecampaign_api_url']) && !empty($_POST['signup_email']) && !empty($this->settings['enable_registration'])){

				$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);

				$args = apply_filters('wplms_activecampaign_list_filters',array(
					'name'=>$_POST['signup_username'],
					'campaign'=>array('campaignId'=>$this->settings['enable_registration']),
					'email'=>$_POST['signup_email']),
				$_POST);
				
				$return = $gr->add_contact($args);
				if(empty($return)){
					add_filter('the_content',function($content){ $content .='<div class="message success">'._x('You\'re subscribed to our newsletter','Success message on mail subscription','wplms-activecampaign').'</div>'; return $return;});
				}else{
					add_filter('the_content',function($content){ $content.='<div class="message">'.$return.'</div>';return $return;});
				}
			}
        }
        return;
	}
	
	function check_if_enabled($order_id){
		if(!isset($this->settings['enable_woo_subscription']))
			return;

		if(!empty($_POST['subscribe_activecampaign'])){
			update_post_meta( $order_id, '_subscribe_wplms_activecampaign', 'yes' );
		}
	}

	function add_student_to_subscribe_list($order_id){

		if(empty($this->settings['activecampaign_api_key'] && $this->settings['activecampaign_api_url']) || empty($this->settings['enable_woo_subscription']))
			return;

		$check = get_post_meta($order_id, '_subscribe_wplms_activecampaign',true);
		if(!empty($check)){
			$order = new WC_Order( $order_id );
			$user = $order->get_user();
		    if ( !empty($user)){ 
		        $gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		        $args = apply_filters('wplms_activecampaign_list_filters',array(
					'name'=>$_POST['signup_username'],
					'campaign'=>array('campaignId'=>$this->settings['enable_registration']),
					'email'=>$_POST['signup_email']),
				$_POST);
				$gr->add_contact($args);
		    }
		}
	}

	function sync_lists_get(){
		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'wplms_activecampaign_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator.','wplms-activecampaign').'</p></div>';
		}
		$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		$emails = $gr->get_all_emails_from_list($_POST['list']);
		print_r(json_encode($emails));
		die();
	}
	function sync_lists_put(){
		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'wplms_activecampaign_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator.','wplms-activecampaign').'</p></div>';
		     die();
		}
		$emails = json_decode(stripcslashes($_POST['emails']));

		$all_emails = array();
	
		if(!empty($emails)){
			
			foreach ($emails as $email) {
				$all_emails[$email->contactId]=$email->email;
			
			}
		}

		if($_POST['element'] == 'all_course_students' && !isset($_POST['paged'])){
			//Get all students;
			global $wpdb;
			$results = $wpdb->get_results("SELECT user_id FROM {$wpdb->usermeta} 
				WHERE meta_key LIKE 'course_status%' GROUP BY user_id");

			$total_count = count($results);
			if($total_count > $this->loop_max){

			//Run loop in batches of 100;
				for($i=0;$i<$total_count;$i=$i+$this->loop_max){
					$return_chained_ajax[]=array(
						'action'=> 'sync_lists_put', 
	                  	'security'=> $_POST['security'],
	                  	'emails'=> $_POST['emails'],
	                  	'element'=> $_POST['element'],
	                  	'list'=> $_POST['list'],
	                  	'paged'=> $i,
	                  	'course_group'=> $_POST['course_group']
					);
				}
				echo json_encode($return_chained_ajax);
				die();
			}
		}

		if(isset($_POST['paged'])){
			$paged = $_POST['paged'];
		}else{
			$paged = 0;
		}

		$this->sync_lists_put_check($all_emails,$_POST['element'],$_POST['list'],$paged);
		die();
	}

	function sync_lists_put_check($all_emails,$element,$list,$paged=0){
		
		$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		
		global $wpdb;
		switch($element){
			case 'enable_registration':
				$all_users = $wpdb->get_results("SELECT user_email,display_name 
					FROM {$wpdb->users} WHERE user_status = 0 ");
				$all_user_mails = array();
				$merge_fields = array();
				if(!empty($all_users)){
					foreach($all_users as $user){
						$all_user_mails[] = $user->user_email;
						$merge_fields[$user->user_email] = array(
							'name'=>$user->display_name,
							'campaign'=>array(
								'campaignId'=>$list),
							'email'=>$user->user_email
						);
					}
				}
				$tobe_rejected_mails =  array_diff($all_emails, $all_user_mails);
				$tobe_added_mails =  array_diff($all_user_mails,$all_emails);
				$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
				if(!empty($tobe_rejected_mails)){
					foreach($tobe_rejected_mails as $email){
						$contactID=array_search($email,$all_emails);
						$gr->remove_contact($contactID);
					}
				}
				if(!empty($tobe_added_mails)){
					foreach($tobe_added_mails as $email){
						$gr->add_contact($merge_fields[$email]);
					}
				}
			break;
			case 'enable_woo_subscription':
	            
				$all_users = $wpdb->get_results("SELECT  m.meta_value as email,p.ID as order_id FROM {$wpdb->postmeta} as m LEFT JOIN {$wpdb->posts} as p ON m.post_id = p.ID WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-completed' AND m.meta_key = '_billing_email' GROUP BY email LIMIT $paged,$this->loop_max");
				$all_user_mails = $merge_fields = array();
				if(!empty($all_users)){
					foreach($all_users as $user){
						$name   = get_post_meta( $user->order_id, '_billing_first_name',true);
	                	$user_email   = get_post_meta( $user->order_id, '_billing_email',true);
	                	$all_user_mails[] = $user_email;
						$merge_fields[$user_email] = array(
							'name'=>$name,
							'campaign'=>array('campaignId'=>$list),
							'email'=>$user_email
						);
					}
				}
				$tobe_rejected_mails =  array_diff($all_emails, $all_user_mails);
				$tobe_added_mails =  array_diff($all_user_mails,$all_emails);
				$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
				if(!empty($tobe_rejected_mails)){
					foreach($tobe_rejected_mails as $email){
						$contactID=array_search($email,$all_emails);
						$gr->remove_contact($contactID);
					}
				}

				if(!empty($tobe_added_mails)){
					foreach($tobe_added_mails as $email){
						$gr->add_contact($merge_fields[$email]);
					}
				}
				
			break;
			case 'all_course_students':
				
				$time = time();
				
				$all_users = $wpdb->get_results("SELECT u.user_email as email,
					u.display_name as name,
					p.post_title as course_name
					FROM {$wpdb->users} as u 
					LEFT JOIN {$wpdb->usermeta} as m 
					ON u.ID = m.user_id 
					LEFT JOIN {$wpdb->usermeta} as m2 
					ON u.ID = m2.user_id 
					LEFT JOIN {$wpdb->posts} as p 
					ON m.meta_key = p.ID 
					WHERE u.user_status = 0 
					AND m2.meta_key LIKE '%course_status%'
					AND p.post_type = 'course'
					AND p.post_status = 'publish'
					GROUP BY email, course_name
					LIMIT $paged,$this->loop_max");
				$all_user_mails = array();
				$merge_fields = array();
				if(!empty($all_users)){
					foreach($all_users as $user){
						$all_user_mails[] = $user->email;
						$merge_fields[$user->email] = array(
								'name'=>$user->name,
								'campaign'=>array('campaignId'=>$list),
								'email'=>$user->email
							);
					}
				}
				$tobe_rejected_mails =  array_diff($all_emails, $all_user_mails);
				$tobe_added_mails =  array_diff($all_user_mails,$all_emails);
				if(!empty($tobe_rejected_mails)){
					foreach($tobe_rejected_mails as $email){
						$contactID=array_search($email,$all_emails);
						$gr->remove_contact($contactID);
					}
				}

				if(!empty($tobe_added_mails)){
					foreach($tobe_added_mails as $email){
						$gr->add_contact($merge_fields[$email]);
					}
				}
			break;
			case 'all_instructors_list':
				$all_instructors = get_users( 'role=instructor' );
				$all_instructor_mails = array();
				$merge_fields = array();
				if(!empty($all_instructors)){
					foreach($all_instructors as $instructor){
						$all_instructor_mails[] = $instructor->user_email;
						$merge_fields[$instructor->user_email] = array(
							'name'=>$instructor->display_name,
							'campaign'=>array('campaignId'=>$list),
							'email'=>$instructor->user_email
						);
					}
				}
				$tobe_rejected_mails =  array_diff($all_emails, $all_instructor_mails);
				$tobe_added_mails =  array_diff($all_instructor_mails,$all_emails);
				if(!empty($tobe_rejected_mails)){
					foreach($tobe_rejected_mails as $email){
						$contactID=array_search($email,$all_emails);
						$gr->remove_contact($contactId);
					}
				}

				if(!empty($tobe_added_mails)){
					foreach($tobe_added_mails as $email){
					$gr->add_contact($merge_fields[$email]);
					}
					
				}
			break;
		}

	}
	
	function create_list_on_new_course_publish($new_status,$old_status,$post){

		if($post->post_type != 'course')
			return;

		if($new_status != 'publish')
			return;

		$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		$list_exists = get_post_meta($post->ID,'vibe_wplms_activecampaign_list',true);
		if($list_exists)
			return;
			global $wpdb;
		//create new list for course
		$courses = $wpdb->get_results("SELECT ID,post_title,post_name FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type ='course'");
		if(!empty($courses)){
			foreach($courses as $course){
				$list_args = array(
					"name"=>$course->post_name,
						"languageCode"=> $this->settings['language_code'],
						"optinTypes"=>array( "api"=> "single"),
					"profile"=>array(
						"description"=>$course->post_excerpt,
					    "title"=> $course->post_title
					)
				);
				$id = $gr->create_list($list_args);
				if($id){
						$this->lists[$id] = $course->post_name;
						$list_ids[]=array('list_id'=>$id,'list_name'=>$course->post_name);
						update_post_meta($course->ID,'vibe_wplms_activecampaign_list',$id);
				}
				else{
					$id = array_search($course->post_name,$this->lists);
					$list_ids[]=array('list_id'=>$id,'list_name'=>$course->post_name);
				}
			}
		}

	}

	function get_create_course_lists(){

		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'wplms_activecampaign_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator.','wplms-activecampaign').'</p></div>';
		}
		$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		if(empty($this->lists)){
			$this->get_lists();
		}

		global $wpdb;
		//Existing Course List ids
		$course_lists = $wpdb->get_results("SELECT meta_value,post_id FROM {$wpdb->postmeta} WHERE meta_key = 'vibe_wplms_activecampaign_list'");
		$course_list_ids = $exclude_courses = array();
		$ex_list_ids = array_keys($this->lists); 
		if(!empty($course_lists)){
			foreach($course_lists as $list){
				if($list->meta_value == 'disable'){
					$exclude_courses[] = $list->post_id;
				}
				else{
					if(in_array($list->meta_value,$ex_list_ids)){ // Check if list exists
						$course_list_ids[$list->meta_value] = $list->post_id;	
					}
				}
			}
		}

		$extra_q = '';
		if(!empty($exclude_courses)){ 
			$extra_q = ' AND ID NOT IN ('.implode(',',$exclude_courses).')';
		}
		$courses = $wpdb->get_results("SELECT ID,post_title,post_name FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type ='course'$extra_q");
		$list_ids = array();
		if(!empty($courses)){
			foreach($courses as $course){
			$course_name=sanitize_html_class(get_bloginfo('name')).'-'.$course->post_name;
				if(in_array($course->ID,$course_list_ids)){
					$id = array_search($course->ID,$course_list_ids);
					$list_ids[]=array('list_id'=>$id,'list_name'=>$course_name);
				}else{
					if(!in_array($course_name,$this->lists)){
						$list_args = array(
							"name"=>$course_name,
  							"languageCode"=> $this->settings['language_code'],
  							"optinTypes"=>array( "api"=> "single"),
							"profile"=>array(
								"description"=>$course->post_excerpt,
							    "title"=> $course->post_title
							)
						);
						$id = $gr->create_list($list_args);
						if($id){
							$this->lists[$id] = $course_name;
							$list_ids[]=array('list_id'=>$id,'list_name'=>$course_name);
							update_post_meta($course->ID,'vibe_wplms_activecampaign_list',$id);
						}
					}else{
						$id = array_search($course_name,$this->lists);
						$list_ids[]=array('list_id'=>$id,'list_name'=>$course_name);

					}
				}
			}
		}
		print_r(json_encode($list_ids));
		die();
	}

	function course_lists_put(){
		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'wplms_activecampaign_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator.','wplms-activecampaign').'</p></div>';
		}
		
		$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
	
		$data = json_decode(stripslashes($_POST['data']));	
		if(!empty($data)){
			$list_ids = array();
			foreach($data as $d){
				$all_lists[$d->list_id] = $d->list_name;
			}
			$this->course_specific_lists($all_lists);
		}

		die();	
	}

	function course_specific_lists($list_ids){
		
		$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		if(!empty($list_ids)){
			foreach($list_ids as $list_id => $list_name){
				if(!empty($list_id)){
					global $wpdb;
					$course_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'vibe_wplms_activecampaign_list' AND meta_value = %s",$list_id));
					if(!empty($course_id)){
						$synced_lists = get_transient('activecampaign_lists_synced');
						if(empty($synced_lists) || !in_array($list_ids,$synced_lists)){
							$synced_lists[]=$list_id;
							//get all emails from Course
							$all_course_users = $wpdb->get_results("
							SELECT 
								u.user_email as email,
								u.display_name as name
							FROM {$wpdb->users} as u 
							LEFT JOIN {$wpdb->usermeta} as m 
							ON u.ID = m.user_id 
							WHERE u.user_status = 0 
							AND m.meta_key = $course_id",true);
							//get all emails from list
							$all_list_emails = $gr->get_all_emails_from_list($list_id);
							$add_contacts = $remove_contacts = array();
							if(!empty($all_course_users)){
								foreach($all_course_users as $key=>$user){
									$all_course_users[$user->email] = $user;
									unset($all_course_users[$key]);
								}
							}

							if(!empty($all_list_emails)){
								/*
								new_list_members[$user->email][] = array(
									'name'=>$user->name,
									'course'=>$user->course_slug,
									'campaign'=>array(
										'campaignId'=>$list_id),
									'email'=>$user->email
								);*/
								$course_emails = array();
								if(!empty($all_course_users)){
									$course_emails = array_keys($all_course_users);
								}

								foreach($all_list_emails as $k=>$member){
									if(!in_array($member['email'],$course_emails)){
										$remove_contacts[]=$member['contactId'];
										
									}
								}
							}
							if(!empty($all_course_users)){
								$list_emails = array();
								if(!empty($all_list_emails)){
									foreach($all_list_emails as $member){
										$list_emails[]=$member['email'];
									}
								}
								
								foreach($all_course_users as $user){
									if(!in_array($user->email,$list_emails)){
										$add_contacts[$user->email] = array(
											'name'=>$user->name,
											'campaign'=>array(
												'campaignId'=>$list_id),
											'email'=>$user->email
										);
									}
								}
							}
							
							if(!empty($remove_contacts)){
								foreach($remove_contacts as $key=>$contact_id){
									$gr->remove_contact($contact_id);
								}
							}
							
							if(!empty($add_contacts)){
								print_r($add_contacts);
								foreach($add_contacts as $contact){
									$gr->add_contact($contact);
								}
							}
							set_transient('activecampaign_lists_synced',$synced_lists,DAY_IN_SECONDS);	
						}

						
					}			
				}
			}		 
		}
	}


		
	function wplms_gr_subscribe_to_list(){

		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],$_POST['list']) ){
		    echo __('Security check Failed. Contact Administrator.','wplms-gr');
		    die();
		}
		$this->get_settings();
		if(empty($this->settings['activecampaign_api_key'])){
			echo __('Activecampaign Key is missing in settings.','wplms-gr');
			die();
		}

		$dummy = new Wplms_ActiveCampaign_Subscribe_Widget();
 		if( isset($dummy->captcha_enabled) && $dummy->captcha_enabled == 1 ){
			if(empty($this->settings['recaptcha_secret'])){
				echo __('Missing Captcha field','wplms-gr');
				die();
	 		}else{
	 			include_once 'recaptchalib.php';
	 			$objRecaptcha = new ReCaptcha( $this->settings['recaptcha_secret']);
				$response = $objRecaptcha->verifyResponse($_SERVER['REMOTE_ADDR'], $_POST['captcha']);
				if(!isset($response->success) || 1 != $response->success){
					echo __('Invalid Captcha field','wplms-gr');
					die();
				}
	 		}
 		}
		//$list_id = get_post_meta($course_id,'vibe_wplms_activecampaign_list',true);
		$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key']);
		//$user = get_user_by('ID',$user_id);

		//valid email
		if(is_email($_POST['email']));{
			$args = apply_filters('wplms_activecampaign_list_filters',array(
					'name'=>$_POST['name'],
					'campaign'=>array(
						'campaignId'=>$_POST['list']),
					'email'=>$_POST['email']),
				$_POST);

			$response = $gr->add_contact($args);

			if(empty($response)){
				echo 1;
			}else{
				echo $response;
			}
			die();
		}
	}

	function wplms_course_lists($fields){

		if(!isset($this->settings['course_list']))
			return;

		$lists = $this->get_lists();
		$options = array(
                array('label'=>__('None','wplms-activecampaign'),'value'=>''),
                array('label'=>__('Disable','wplms-activecampaign'),'value'=>'disable'),
	        );
		if(!empty($lists)){
			foreach($lists as $key=>$list){
				$options[]=array('label'=>$list,'value'=>$key);
			}	
		}
		
		$fields['vibe_wplms_activecampaign_list']=array(
			'label'	=> __('Select a Activecampaign List','wplms-activecampaign'), // <label>
			'desc'	=> __('Select a list in which users are enrolled into.','wplms-activecampaign'), // description
			'id'	=> 'vibe_wplms_activecampaign_list', // field id and name
			'type'	=> 'select', // type of field
	        'options' => $options
		);
		return $fields;
	}

	function add_to_list($course_id,$user_id){
		if(empty($this->settings['activecampaign_api_key']) && isset($this->settings['activecampaign_api_url']))
			return;

		if(!isset($this->settings['auto_course_list_subscribe']))
			return;
		
		$list_id = get_post_meta($course_id,'vibe_wplms_activecampaign_list',true);
		if(empty($list_id) && $list_id == 'disable')
			return;

		$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		$user = get_user_by('ID',$user_id);

		$return = $gr->add_contact(array(
			'name'=>$user->display_name,
			'campaign'=>array(
				'campaignId'=>$list_id),
			'email'=>$user->user_email));

		return;		
	}

	function remove_from_list($course_id,$user_id){

		if(empty($this->settings['activecampaign_api_key']) && isset($this->settings['activecampaign_api_url']))
			return;

		if(!isset($this->settings['auto_course_list_subscribe']))
			return;

		$list_id = get_post_meta($course_id,'vibe_wplms_activecampaign_list',true);
		if(empty($list_id))
			return;

		$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		$contact_ids = $gr->get_all_emails_from_list($list_id);

		$contacts=[];
		if(!empty($contact_ids)){
			foreach($contact_ids as $id=>$value){
				$contacts[$value['contactId']]=$value['email'];
			}
		}
		$user = get_user_by('ID',$user_id);
		$contact_id = array_search($user->data->user_email,$contacts);
		if(!empty($contact_id)){
			$return = $gr->remove_contact($contact_id);
		}
		return;
	}

	function change_status($course_id,$marks,$user_id){
		if(empty($this->settings['activecampaign_api_key'] && $this->settings['activecampaign_api_url']))
			return;

		$list_id = get_post_meta($course_id,'vibe_wplms_activecampaign_list',true);
		if(empty($list_id))
			return;
		
		$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		$user = get_user_by('ID',$user_id);
		$return = $gr->add_contact(array(
			'name'=>$user->display_name,
			'campaign'=>array(
				'campaignId'=>$list_id),
			'email'=>$user->user_email));
	}

	function sync_lists(){
		
		$this->get_settings();

		if(empty($this->settings['auto_sync_lists']))
			return;

		if(empty($this->lists))
			$this->get_lists();

		$list_types = array(
			'enable_registration',
			'enable_woo_subscription',
			'all_course_students',
			'all_instructors_list'
		);
		
		$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		foreach($list_types as $setting){
			if(!empty($this->settings[$setting])){ 

				$emails = $gr->get_all_emails_from_list($this->settings[$setting]);

				$all_emails = array();
				if(!empty($emails)){
					foreach($emails as $email){
						$all_emails[]=$email['email'];
					}	
				}
				$this->sync_lists_put_check($all_emails,$setting,$this->settings[$setting]);
			}
		}
		/*
		Sync Course specific lists
		 */
		global $wpdb;
		$course_lists = $wpdb->get_results("SELECT meta_value,post_id FROM {$wpdb->postmeta} WHERE meta_key = 'vibe_wplms_activecampaign_list'");


		$course_list_ids = $exclude_courses = array();
		$es_list_ids = array_keys($this->lists);
		
		if(!empty($course_lists)){
			foreach($course_lists as $list){
				if($list->meta_value == 'disable'){
					$exclude_courses[] = $list->post_id;
				}else{
					if(in_array($list->meta_value,$es_list_ids)){ // Check if list exists
						$course_list_ids[$list->meta_value] = get_the_title($list->post_id);	
					}
				}
			}
		}

		$extra_q = '';
		if(!empty($exclude_courses)){ 
			$extra_q = ' AND ID NOT IN ('.implode(',',$exclude_courses).')';
		}
		$courses = $wpdb->get_results("SELECT ID,post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type ='course'$extra_q");

		
		$list_ids = array();
		if(!empty($courses)){
			foreach($courses as $course){
				if(!in_array($course->ID,$course_list_ids)){ // Does not have connected list
					if(!in_array($course->post_title,$this->lists)){
						$list_args = array(
							"name"=>$course->post_name,
  							"languageCode"=> $this->settings['language_code'],
  							"optinTypes"=>array( "api"=> "single"),
							"profile"=>array(
								"description"=>$course->post_excerpt,
							    "title"=> $course->post_title
							)
						);
						$id = $gr->create_list($list_args);
						$this->lists[$id] = $course->post_title;
						$course_list_ids[$id] = $course->post_title;	
						update_post_meta($course->ID,'vibe_wplms_activecampaign_list',$id);
					}
				}
			}	
		}
		
		if(!empty($course_list_ids)){
			$this->course_specific_lists($course_list_ids);
		}
        return;
	}

	function wplms_add_activecampaign_list_in_custom_registration_form( $settings ){

		if( $_POST['name'] ){
			$settings['activecampaign_list'] = '';
			return $settings;
		}
		$lists = $this->get_lists();
		if( empty($lists) )
			return $settings;

		$settings['activecampaign_list'] = array(
			'label' => __('Assign Activecampaign List','wplms-activecampaign'),
			'default_option' => __('None','wplms-activecampaign'),
			'options' => $lists
		);
		return $settings;
	}

	function wplms_add_activecampaign_checkbox_on_registration($name){
		$forms = get_option( 'wplms_registration_forms' );
		if( !empty( $forms[$name] ) ){
			$settings = $forms[$name]['settings'];

			if( isset($settings['activecampaign_list']) && !empty($settings['activecampaign_list']) ){
				echo '<div class="activecampaign_subscribe_checkbox" style="margin:15px 0;"><div class="checkbox">
			        <input type="checkbox" name="subscribe_activecampaign_list" id="subscribe_activecampaign_list" value="'.$settings['activecampaign_list'].'" checked /> <label for="subscribe_activecampaign_list">'._x('Subscribe to our newsletter','Activecampaign subscribe checkbox in custom registration form','wplms-activecampaign').'</label>  
			        </div></div>';
			}
		}
	}

	function wplms_add_user_to_activecampaign_list_on_registration( $user_id,$user_args,$settings ){
		if( empty($settings) )
			return;

		foreach ($settings as $setting) {
			if( $setting->id == 'subscribe_activecampaign_list' ){
				$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
				$list_id = get_post_meta($course_id,'vibe_wplms_activecampaign_list',true);
				$user = get_user_by('ID',$user_id);
				$args = $gr->add_contact(array(
					'name'=>$user->display_name,
					'campaign'=>array(
						'campaignId'=>$this->settings['enable_registration']),
					'email'=>$user->user_email));
				$gr->activecampaign_list_function($args,$setting->value);
			}
		}
	}

	function subscribe_user_to_list($user_id){

		//get wplms get response settings

		//check if there is a list connected with all Users
		// if yes, then add contact to this list
	

		$this->get_settings();

		if(empty($this->settings))
			return;

		if(!isset($this->settings['enable_registration']))
			return;

            if(isset($this->settings) && isset($this->settings['activecampaign_api_key']) && isset($this->settings['activecampaign_api_url'])&& !empty($this->settings['enable_registration'])){
				$gr = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
				$user_meta=get_userdata($user_id);
				$user_role=$user_meta->roles;
				if($user_role != 'instructor'){
					$user = get_user_by('ID',$user_id);

					$args = apply_filters('wplms_activecampaign_list_filters',array(
						'name'=>$user->display_name,
						'campaign'=>array('campaignId'=>$this->settings['enable_registration']),
						'email'=>$user->user_email),
					$_POST);
					
					$return = $gr->add_contact($args);
				}
				//check if the user role is instructor
				else{
					// check if there is list conencted with all isntructors
					if(isset($_POST['all_instructors_list'])){
						//if yes add contact to this list
						$args = apply_filters('wplms_activecampaign_list_filters',array(
						'name'=>$user->display_name,
						'campaign'=>array('campaignId'=>$this->settings['all_instructors_list']),
						'email'=>$user->user_email),
						$_POST);

						$return = $gr->add_contact($args);
					}
				}
			}
		return;
	}

}

Wplms_Activecampaign_Init::init();