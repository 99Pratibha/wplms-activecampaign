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
	public function __construct($api_key = null)
	{
		$this->apikey = $api_key;
		$this->add_contact=[];
		$this->apiurl = 'https://api.activecampaign.com/v3/';
		$this->interest_ids = array();
		$this->args = array(
		 	'headers' => array(
				'X-Auth-Token' => 'api-key '.$api_key,
				'Content-Type' => 'application/json'
			)
		);
	}


	function get_lists(){
		$response = wp_remote_get( $this->apiurl.'campaigns/?perPage=9999', $this->args );
		$body = json_decode( wp_remote_retrieve_body( $response ));
		return $body;
	}

	function activecampaign_list_function($user_args,$list_id){	
		//ob_start();
		$args = $this->args; 

		$args['body'] = json_encode($user_args);

		$response = wp_remote_post(  $this->apiurl . 'campaigns/' . $list_id . '/contacts/' . md5(strtolower($email)), $args );	

		$body = json_decode( $response['body'] );
		if ( $response['response']['code'] == 200) {
			return 0;
		} else {
			return $response['response']['code'] . $body->title .' : '. $body->detail;
		}
	}
	
	function get_all_emails_from_list($list_id){

		$response = wp_remote_get( $this->apiurl.'campaigns/'.$list_id.'/contacts/', $this->args );
		$body = json_decode( wp_remote_retrieve_body( $response ),true );
		$emails = array();
		// print_r('body start'); 
		// print_r($body);
		// print_r('body ends');
		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			foreach ($body as $key => $member) {
				$emails[] = array('email'=>$member['email'],'contactId'=>$member['contactId'],'info'=>array('email'=>$member['email'],'name'=>$member['name'],'campaign'=>array('campaignId'=>$list_id)));
			}
			return $emails;
		}else {
			echo '<b>' . wp_remote_retrieve_response_code( $response ) . wp_remote_retrieve_response_message( $response ) . ':</b> ' . $body->detail;
		}
	}

	
	function create_list($list_args){
		$args = $this->args;
		$args['method'] = 'POST';
		$list_args['name']=sanitize_html_class(get_bloginfo('name')).'-'.$list_args['name'];
		$args['body'] = json_encode($list_args);
		$response = wp_remote_post(  $this->apiurl.'campaigns', $args );
		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if(!empty($body)){
			return $body->campaignId;
		}
	}

	function add_contact($contact_args){

		if(!in_array($contact_args['email'],array_keys($this->add_contact)) || !in_array($contact_args['campaign']['campaignId'], $this->add_contact[$contact_args['email']])){
			$this->add_contact[$contact_args['email']][]=$contact_args['campaign']['campaignId'];
		
			$args = $this->args;
			$args['method'] = 'POST';
			/*
			{
			  "name": "John Doe",
			  "campaign": {
			    "campaignId": "yhLAG"
			  },
			  "email": "ripul@99fusion.com"
			}*/
			$args['body'] = json_encode($contact_args);
			$response = wp_remote_post(  $this->apiurl.'contacts',$args );
			$body = json_decode( wp_remote_retrieve_body( $response ) );
			if(empty($body)){
				return true;
			}
		}
		return true;
	}

	function remove_contact($contact_id){

		$args = $this->args;
		$args['method'] = 'DELETE';
		/*
		{
		  "contactId": "B3Fjk"
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