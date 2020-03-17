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
		//Add activecampaign in custom registration form
		add_filter('wplms_registration_form_settings',array($this,'wplms_add_activecampaign_list_in_custom_registration_form'));
		add_action('wplms_before_registration_form',array($this,'wplms_add_activecampaign_checkbox_on_registration'));
		add_action('wplms_custom_registration_form_user_added',array($this,'wplms_add_user_to_activecampaign_list_on_registration'),10,3);

		/* Subscribe Widget */
		add_action('wp_ajax_wplms_activecampaign_subscribe_to_list',array($this,'wplms_activecampaign_subscribe_to_list'));
		add_action('wp_ajax_nopriv_wplms_activecampaign_subscribe_to_list',array($this,
			'wplms_activecampaign_subscribe_to_list'));
	}


	function get_settings(){
		if(empty($this->settings)){
			$this->settings = get_option(WPLMS_ACTIVECAMPAIGN_OPTION);
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
			$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
			$lists = $ac->get_lists();
			if(!empty($lists->lists)){
				foreach($lists->lists as $list){
					$this->lists[$list->id]=$list->stringid;
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

				$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);

				$args = apply_filters('wplms_activecampaign_list_filters',array(
					'contact'=>array(
						'email'=>$user->user_email,
						'firstName'=>$user->display_name
					)),
				$_POST);
				if(is_numeric($args)){
					update_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',$args);
				}
				$return = $ac->add_contact($args);
				$update_contact = $ac->update_contact(array(
					'contactList'=>array(
						'list'=>$list,
						'contact'=>$return,
						'status'=>1
					)
				));
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
		        $ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		        $args = apply_filters('wplms_activecampaign_list_filters',array(
					'contact'=>array(
						'email'=>$user->user_email,
						'firstName'=>$user->display_name
					)),
				$_POST);
				if(is_numeric($args)){
					update_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',$args);
				}
				$id = $ac->add_contact($args);
				$update_contact = $ac->update_contact(array(
					'contactList'=>array(
						'list'=>$list,
						'contact'=>$id,
						'status'=>1
					)
				));
		    }
		}
	}

	function sync_lists_get(){
		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'wplms_activecampaign_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator.','wplms-activecampaign').'</p></div>';
		}
		$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		$emails = $ac->get_all_emails_from_list($_POST['list']);
		print_r(json_encode($emails));
		die();
	}
	function sync_lists_put(){
		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'wplms_activecampaign_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator.','wplms-activecampaign').'</p></div>';
		     die();
		}
		$emails = json_decode(stripcslashes($_POST['emails']));
		
		$all_ac_emails = array();
	
		if(!empty($emails)){
			
			foreach ($emails as $email) {
				$all_ac_emails[$email->Id]=$email->email;
			
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

		$this->sync_lists_put_check($all_ac_emails,$_POST['element'],$_POST['list'],$paged);
		die();
	}

	function sync_lists_put_check($all_ac_emails,$element,$list,$paged=0){
		
		$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		
		global $wpdb;
		switch($element){
			case 'enable_registration':
				$all_users = $wpdb->get_results("SELECT ID,user_email,display_name 
					FROM {$wpdb->users} WHERE user_status = 0 ");
				$all_ac_ids = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'vibe_wplms_activecampaign_contact_id'");
				
				$all_ac_accounts = array();
				if(!empty($all_ac_ids)){
					foreach($all_ac_ids as $ac_id){
						$all_ac_accounts[$ac_id->user_id]=$ac_id->meta_value;
					}
				}
				$all_user_mails = array();
				if(!empty($all_users)){
					foreach($all_users as $user){
						$all_user_mails[] = $user->user_email;
						if(!in_array($user->ID,array_keys($all_ac_accounts))){
							$id = $ac->add_contact(array(
								'contact'=>array(
									'email'=>$user->user_email,
									'firstName'=>$user->display_name
								)
							));
							if(is_numeric($id)){
								update_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',$id);
								$all_ac_accounts[$user->ID]=$id;
							}
						}

						$update_contact = $ac->update_contact(array(
							'contactList'=>array(
								'list'=>$list,
								'contact'=>$all_ac_accounts[$user->ID],
								'status'=>1
							)
						));
						
						}
						$tobe_rejected_mails =  array_diff($all_ac_emails, $all_user_mails);
						
						if(!empty($tobe_rejected_mails)){
						foreach($tobe_rejected_mails as $id => $email){
							$update_contact = $ac->update_contact(array(
								'contactList'=>array(
									'list'=>$list,
									'contact'=>$id,
									'status'=>0
								)
							));
						}
					 }
				}
			break;
			case 'enable_woo_subscription':
	            
				// $all_users = $wpdb->get_results("SELECT m.meta_value as email,p.ID as order_id FROM {$wpdb->postmeta} as m LEFT JOIN {$wpdb->posts} as p ON m.post_id = p.ID WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-completed' AND m.meta_key = '_billing_email' GROUP BY email LIMIT $paged,$this->loop_max");

				$all_users = $wpdb->get_results("SELECT DISTINCT u.ID as ID, um.user_id as User_id,u.display_name as name, p.meta_value as email FROM {$wpdb->users} as u JOIN {$wpdb->postmeta} as p ON p.meta_value = u.user_email INNER JOIN {$wpdb->usermeta} as um ON u.ID = um.user_id ");

				$all_ac_ids = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'vibe_wplms_activecampaign_contact_id'");

				$all_ac_accounts = array();
				if(!empty($all_ac_ids)){
					foreach($all_ac_ids as $ac_id){
						$all_ac_accounts[$ac_id->user_id]=$ac_id->meta_value;
					}
				}
				$all_user_mails = array();

				$add_to_list_ac_emails = array();

				if(!empty($all_users)){
					foreach($all_users as $user){
						if(!in_array($user->ID,array_keys($all_ac_accounts))){
							$id = $ac->add_contact(array(
								'contact'=>array(
									'email'=>$user->email,
									'firstName'=>$user->name
								)
							));
							if(is_numeric($id)){
								update_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',$id);
								$all_ac_accounts[$user->ID]=$id;
							}
						}

						$update_contact = $ac->update_contact(array(
							'contactList'=>array(
								'list'=>$list,
								'contact'=>$all_ac_accounts[$user->ID],
								'status'=>1
							)
						));
					}
					$tobe_rejected_mails =  array_diff($all_ac_emails, $all_user_mails);
						
					if(!empty($tobe_rejected_mails)){
						foreach($tobe_rejected_mails as $id => $email){
							$update_contact = $ac->update_contact(array(
								'contactList'=>array(
									'list'=>$list,
									'contact'=>$id,
									'status'=>0
								)
							));
						}
					}
				}

			break;
			case 'all_course_students':
				
				$time = time();
				
				$all_users = $wpdb->get_results("SELECT u.user_email as email,
					u.ID as ID,
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
					GROUP BY email,course_name
					LIMIT $paged,$this->loop_max");
				$all_ac_ids = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'vibe_wplms_activecampaign_contact_id'");
				$all_ac_accounts = array();
				if(!empty($all_ac_ids)){
					foreach($all_ac_ids as $ac_id){
						$all_ac_accounts[$ac_id->user_id]=$ac_id->meta_value;
					}
				}
				$all_user_mails = array();

				$add_to_list_ac_emails = array();

				if(!empty($all_users)){
					foreach($all_users as $user){
						$all_user_mails[] = $user->user_email;
						if(!in_array($user->ID,array_keys($all_ac_accounts))){
							$id = $ac->add_contact(array(
								'contact'=>array(
									'email'=>$user->email,
									'firstName'=>$user->name
								)
							));
							if(is_numeric($id)){
								update_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',$id);
								$all_ac_accounts[$user->ID]=$id;
							}
						}

						$update_contact = $ac->update_contact(array(
							'contactList'=>array(
								'list'=>$list,
								'contact'=>$all_ac_accounts[$user->ID],
								'status'=>1
							)
						));
					}
				}
				$tobe_rejected_mails =  array_diff($all_ac_emails, $all_user_mails);
				if(!empty($tobe_rejected_mails)){
					foreach($tobe_rejected_mails as $id => $email){
						$update_contact = $ac->update_contact(array(
							'contactList'=>array(
								'list'=>$list,
								'contact'=>$id,
								'status'=>0
							)
						));
					}
				}
			break;
			case 'all_instructors_list':
				$all_instructors = get_users( 'role=instructor' );

				$all_ac_ids = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'vibe_wplms_activecampaign_contact_id'");
				
				$all_ac_accounts = array();
				if(!empty($all_ac_ids)){
					foreach($all_ac_ids as $ac_id){
						$all_ac_accounts[$ac_id->user_id]=$ac_id->meta_value;
					}
				}
				$all_instructor_mails = array();

				$add_to_list_ac_emails = array();

				if(!empty($all_instructors)){
					foreach($all_instructors as $user){
						$all_instructor_mails[] = $user->user_email;
						if(!in_array($user->ID,array_keys($all_ac_accounts))){
							$id = $ac->add_contact(array(
								'contact'=>array(
									'email'=>$user->user_email,
									'firstName'=>$user->display_name
								)
							));
							if(is_numeric($id)){
								update_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',$id);
								$all_ac_accounts[$user->ID]=$id;
							}
						}

						$update_contact = $ac->update_contact(array(
							'contactList'=>array(
								'list'=>$list,
								'contact'=>$all_ac_accounts[$user->ID],
								'status'=>1
							)
						));
					}
					$tobe_rejected_mails =  array_diff($all_ac_emails, $all_instructor_mails);
					if(!empty($tobe_rejected_mails)){
						foreach($tobe_rejected_mails as $id => $email){
							$update_contact = $ac->update_contact(array(
								'contactList'=>array(
									'list'=>$list,
									'contact'=>$id,
									'status'=>0
								)
							));
						}
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
			
			$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
			$list_exists = get_post_meta($post->ID,'vibe_wplms_activecampaign_list',true);
			if($list_exists)
				return;
				global $wpdb;
			//create new list for course
			//$courses = $wpdb->get_results("SELECT ID,post_title,  FROM {$wpdb->posts} as p INNER JOIN {$wpdb->postmeta} as m ON p.ID = m.post_id WHERE p.post_status = 'publish' AND p.post_type ='course' and m");
			
					$list_args = array(
						"list" => array(
							"name"=>$post->post_name,
							"stringid"=>$post->post_name,
							"sender_reminder"=> $this->settings['sender_reminder'],
							"sender_url"=>$this->settings['sender_url']
						)
					);
					$id = $ac->create_list($list_args);
					if($id){
						$this->lists[$id] = $post->post_name;
						$list_ids[]=array('list_id'=>$id,'list_name'=>$post->post_name);
						update_post_meta($post->ID,'vibe_wplms_activecampaign_list',$id);
					}
					else{
						$id = array_search($post->post_name,$this->lists);
						$list_ids[]=array('list_id'=>$id,'list_name'=>$post->post_name);
					}
				
			
		
	}

	function get_create_course_lists(){

		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'wplms_activecampaign_settings') ){
		     echo '<div class="error notice is-dismissible"><p>'.__('Security check Failed. Contact Administrator.','wplms-activecampaign').'</p></div>';
		}
		$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
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
				if(in_array($course->ID,$course_list_ids)){
					$id = array_search($course->ID,$course_list_ids);
					$list_ids[]=array('list_id'=>$id,'list_name'=>$course->post_name);
				}else{
					if(!in_array($course->post_name,$this->lists)){
						$list_args = array(
							"list" => array(
								"name"=>$course->post_name,
								"stringid"=>$course->post_name,
	  							"sender_reminder"=> $this->settings['sender_reminder'],
	  							"sender_url"=>$this->settings['sender_url']
	  						)
						);
						$id = $ac->create_list($list_args);
						if($id){
							$this->lists[$id] = $course->post_name;
							$list_ids[]=array('list_id'=>$id,'list_name'=>$course->post_name);
							update_post_meta($course->ID,'vibe_wplms_activecampaign_list',$id);
						}
					}else{
						$id = array_search($course->post_name,$this->lists);
						$list_ids[]=array('list_id'=>$id,'list_name'=>$course->post_name);
						// update_post_meta($course->ID,'vibe_wplms_activecampaign_list',$id);
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
		
		$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
	
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
		
		$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
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
								u.ID as ID,
								u.user_email as email,
								u.display_name as name
							FROM {$wpdb->users} as u 
							LEFT JOIN {$wpdb->usermeta} as m 
							ON u.ID = m.user_id 
							WHERE u.user_status = 0 
							AND m.meta_key = $course_id",true);

							$all_ac_ids = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'vibe_wplms_activecampaign_contact_id'");
				
							$all_ac_accounts = array();
							if(!empty($all_ac_ids)){
								foreach($all_ac_ids as $ac_id){
									$all_ac_accounts[$ac_id->user_id]=$ac_id->meta_value;
								}
							}
							$all_user_mails = array();

							$add_to_list_ac_emails = array();

							if(!empty($all_course_users)){
								foreach($all_course_users as $user){
									if(!in_array($user->ID,array_keys($all_ac_accounts))){
										$id = $ac->add_contact(array(
											'contact'=>array(
												'email'=>$user->email,
												'firstName'=>$user->name
											)
										));
										if(is_numeric($id)){
											update_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',$id);
											$all_ac_accounts[$user->ID]=$id;
										}
									}

									$update_contact = $ac->update_contact(array(
										'contactList'=>array(
											'list'=>$list_id,
											'contact'=>$all_ac_accounts[$user->ID],
											'status'=>1
										)
									));
								}
							}
							set_transient('activecampaign_lists_synced',$synced_lists,DAY_IN_SECONDS);	
						}
					}			
				}
			}		 
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

		$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		$user = get_user_by('ID',$user_id);
		global $wpdb;
		$all_ac_ids = $wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'vibe_wplms_activecampaign_contact_id'");
		
		$all_ac_accounts = array();
		if(!empty($all_ac_ids)){
			foreach($all_ac_ids as $ac_id){
				$all_ac_accounts[$ac_id->user_id]=$ac_id->meta_value;
			}
		}
		if(!in_array($user->ID, array_keys($all_ac_accounts)))
		{
			$id = $ac->add_contact(array(
				'contact'=>array(
					'email'=>$user->user_email,
					'firstName'=>$user->display_name
				)
			));
			if(is_numeric($id)){
				update_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',$id);
				$all_ac_accounts[$user->ID]=$id;
			}
		}

		$update_contact = $ac->update_contact(array(
			'contactList'=>array(
				'list'=>$list_id,
				'contact'=>$all_ac_accounts[$user->ID],
				'status'=>1
			)
		));

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

		$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		
		$user = get_user_by('ID',$user_id);
		$ac_contact_id = get_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',true);
		$update_contact = $ac->update_contact(array(
			'contactList'=>array(
				'list'=>$list_id,
				'contact'=>$ac_contact_id,
				'status'=>0
			)
		));

		return;
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
		
		$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		foreach($list_types as $setting){
			if(!empty($this->settings[$setting])){ 

				$emails = $ac->get_all_emails_from_list($this->settings[$setting]);

				$all_ac_emails = array();
				if(!empty($emails)){
					foreach($emails as $email){
						$all_ac_emails[]=$email['email'];
					}	
				}
				$this->sync_lists_put_check($all_ac_emails,$setting,$this->settings[$setting]);
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
					if(!in_array($course->post_name,$this->lists)){
						$list_args = array(
							"list" => array(
								"name"=>$course->post_name,
								"stringid"=>$course->post_name,
	  							"sender_reminder"=> $this->settings['sender_reminder'],
	  							"sender_url"=>$this->settings['sender_url']
	  						)
						);
						$id = $ac->create_list($list_args);
						$this->lists[$id] = $course->post_name;
						$course_list_ids[$id] = $course->post_name;	
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
				$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
				$list_id = get_post_meta($course_id,'vibe_wplms_activecampaign_list',true);
				$user = get_user_by('ID',$user_id);
				$id = $ac->add_contact(array(
					'contact'=>array(
						'email'=>$user->user_email,
						'firstName'=>$user->display_name
					)
				));
				if(is_numeric($id)){
					update_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',$id);
				}
			
				$ac->activecampaign_list_function($args,$setting->value);
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
				$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
				$user_meta=get_userdata($user_id);
				$user_role=$user_meta->roles;
				if($user_role != 'instructor'){
					$user = get_user_by('ID',$user_id);

					$args = apply_filters('wplms_activecampaign_list_filters',array(
					'contact'=>array(
						'email'=>$user->user_email,
						'firstName'=>$user->display_name
					)),
					$_POST);
					if(is_numeric($args)){
						update_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',$args);
					}
					$id = $ac->add_contact($args);
					$update_contact = $ac->update_contact(array(
						'contactList'=>array(
							'list'=>$_POST['list'],
							'contact'=>$id,
							'status'=>1
						)
					));
				}
				//check if the user role is instructor
				else{
					// check if there is list conencted with all isntructors
					if(isset($_POST['all_instructors_list'])){
						//if yes add contact to this list
					$args = apply_filters('wplms_activecampaign_list_filters',array(
					'contact'=>array(
						'email'=>$user->user_email,
						'firstName'=>$user->display_name
					)),
					$_POST);
					if(is_numeric($args)){
						update_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',$args);
					}
						$id = $ac->add_contact($args);
						$update_contact = $ac->update_contact(array(
						'contactList'=>array(
							'list'=>$_POST['list'],
							'contact'=>$id,
							'status'=>1
						)
					));
					}
				}
			}
		return;
	}

	function wplms_activecampaign_subscribe_to_list(){
		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],$_POST['list']) ){
		    echo __('Security check Failed. Contact Administrator.','wplms_activecampaign');
		    die();
		}
		$this->get_settings();
		if(empty($this->settings['activecampaign_api_key'])){
			echo __('Activecampaign Key is missing in settings.','wplms_activecampaign');
			die();
		}

		$dummy = new Wplms_Activecampaign_Subscribe_Widget();
 		if( isset($dummy->captcha_enabled) && $dummy->captcha_enabled == 1 ){
			if(empty($this->settings['recaptcha_secret'])){
				echo __('Missing Captcha field','wplms_activecampaign');
				die();
	 		}else{
	 			include_once 'recaptchalib.php';
	 			$objRecaptcha = new ReCaptcha( $this->settings['recaptcha_secret']);
				$response = $objRecaptcha->verifyResponse($_SERVER['REMOTE_ADDR'], $_POST['captcha']);
				if(!isset($response->success) || 1 != $response->success){
					echo __('Invalid Captcha field','wplms_activecampaign');
					die();
				}
	 		}
 		}
		//$list_id = get_post_meta($course_id,'vibe_wplms_getresponse_list',true);
		$ac = new Wplms_Activecampaign($this->settings['activecampaign_api_key'],$this->settings['activecampaign_api_url']);
		$user = get_user_by('ID',$user_id);

		//valid email
		if(is_email($_POST['email']));{
			$args = apply_filters('wplms_activecampaign_list_filters',array(
					'contact'=>array(
						'email'=>$_POST['email'],
						'firstName'=>$_POST['name']
					)),
			$_POST);
			if(is_numeric($args)){
				update_user_meta($user->ID,'vibe_wplms_activecampaign_contact_id',$args);
			}
			$id = $ac->add_contact($args);
			$update_contact = $ac->update_contact(array(
				'contactList'=>array(
					'list'=>$_POST['list'],
					'contact'=>$id,
					'status'=>1
				)
			));

			if(empty($id)){
				echo 1;
			}else{
				echo $response;
			}
			die();
		}
	}

}

Wplms_Activecampaign_Init::init();