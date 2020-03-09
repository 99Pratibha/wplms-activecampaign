<?php
/**
 * Activecampaign Class
 *
 * @author 		VibeThemes
 * @category 	Admin
 * @package 	Wplms-Activecampaign/Includes
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* = DEVS If you're copying then please give Credits @Vibethemes @Ripul = */

class Wplms_Activecampaign{

	
	/*
	Activecampaign key
	 */
	private $apikey = '';

	/**
	* Constructor - if you're not using the class statically
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @param boolean $useSSL Enable SSL
	* @param string $endpoint Amazon URI
	* @return void
	*/
	public function __construct($api_key = null,$api_url = null)
	{
		$this->apikey = $api_key;
		$this->api_url = $api_url;
		$this->add_contact=[];
		$this->apiurl = $api_url.'/api/3/';
		$this->interest_ids = array();
		$this->args = array(
		 	'headers' => array(
				'Api-Token' => $api_key
			)
		);
	}


	function get_lists(){
		$response = wp_remote_get( $this->apiurl.'lists/?perPage=9999', $this->args );
		$body = json_decode( wp_remote_retrieve_body( $response ));
		return $body;
	}
	function get_all_contacts(){
		$response = wp_remote_get($this->apiurl.'contacts',$this->args);
		$body = json_decode(wp_remote_retrieve_body($response));
		foreach ($body->contacts as $key => $member) {
			$contacts[] = array('email'=>$member->email,'Id'=>$member->id);
		}
		return $contacts;
	}
	
	function create_list($list_args){
		$args = $this->args;
		$args['method'] = 'POST';
		/*{
			"list": {
				"name": "Name of List",
				"stringid": "Name-of-list",
				"sender_url": "http://activecampaign.com",
				"sender_reminder": "You are receiving this email as you are on our site."
			}
		}*/
		if(empty($list_args['list']['name']))
			return;

		$args['body'] = json_encode($list_args);
		$response = wp_remote_post(  $this->apiurl.'lists', $args );
		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if(!empty($body))
			return $body->list->id;
	}
	function get_all_emails_from_list($list_id){

		$response = wp_remote_get( $this->apiurl.'contacts/?listid='.$list_id, $this->args );
		$body = json_decode( wp_remote_retrieve_body( $response ),true );
		$emails = array();
		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			foreach ($body['contacts'] as $key => $member) {
				$emails[] = array('email'=>$member['email'],'Id'=>$member['id']);
			}
			return $emails;
		}else {
			echo '<b>' . wp_remote_retrieve_response_code( $response ) . wp_remote_retrieve_response_message( $response ) . ':</b> ' . $body->detail;
		}
	}

	function add_contact($contact_args){
		
		$args = $this->args;
		$args['method'] = 'POST';
		/*
		{
			"contact": {
				"email": "johndoe@example.com",
				"firstName": "John"
			}
		}*/
		$contacts = array();
		$args['body'] = json_encode($contact_args);
		$response = wp_remote_post(  $this->apiurl.'contacts',$args );
		$body = json_decode( wp_remote_retrieve_body( $response ) );
		return $body->contact->id;
	}

	function update_contact($contact_args){
		$args = $this->args;
		$args['method'] = 'POST';
		/*{
		    "contactList": {
		        "list": 2,
		        "contact": 1,
		        "status": 1
		    }
		}*/
		$args['body'] = json_encode($contact_args);
		$response = wp_remote_post($this->apiurl.'contactLists',$args);
		$body = json_decode(wp_remote_retrieve_body($response));
		print_r($body);
	}

	function remove_contact($contact_id){

		$args = $this->args;
		$args['method'] = 'DELETE';
		/*
		{
		  "id": "4"
		}*/
		$response = wp_remote_post(  $this->apiurl.'contacts/'.$contact_id, $args );
		
	}

	function debug($streamopt){
		$myFile = "activecampaign_debug.txt";
        if (file_exists($myFile)) {
          $fh = fopen($myFile, 'a');
          fwrite($fh, print_r($streamopt, true)."\n");
        } else {
          $fh = fopen($myFile, 'w');
          fwrite($fh, print_r($streamopt, true)."\n");
        }
        fwrite($fh, print_r(json_encode($data, JSON_PRETTY_PRINT), true)."\n");
        fclose($fh);  
	}
}