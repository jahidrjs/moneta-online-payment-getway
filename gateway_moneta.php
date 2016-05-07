<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Gateway_moneta extends CI_Controller {

    public function __Construct(){
        parent::__construct();	


    }

    public function index()
    {

    }
	
	public function buy($from)
    {
	
		/* test account
		- id: 99999999
		- password: 99999999
		*/
		$responseToMerchantUrl = base_url_tr('gateway_moneta/notify');
		
		    //test id: 97702600, password: smF14001
		$merchantOrderId = '987956';
		$method_and_id = 'some value';
		$parameters = array(
			'id' => '97702600', // Terminal Id
			'password' => 'smF14001',
			'operationType' => 'initialize',
			'amount' => $amount,
			'currencyCode' => '978',
			'langid' => 'ITA',
			'responseToMerchantUrl' => $responseToMerchantUrl,			// responseURL
			'merchantOrderId' => $merchantOrderId,
			'description' => 'Descrizione',
			'customField' => $method_and_id
		);
		
		// payment initialization
		/* TEST URL
		https://test.monetaonline.it/monetaweb/payment/2/xml
		PRODUCTION URL
		https://www.monetaonline.it/monetaweb/payment/2/xml
		*/
		
		$curl_handle = curl_init();
		//curl_setopt($curl_handle, CURLOPT_URL, 'https://test.monetaonline.it/monetaweb/payment/2/xml');
	    curl_setopt($curl_handle, CURLOPT_URL, 'https://www.monetaonline.it/monetaweb/payment/2/xml');
		curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_handle, CURLOPT_POST, true);
		curl_setopt($curl_handle, CURLOPT_POSTFIELDS, http_build_query($parameters));
		$xmlResponse = curl_exec($curl_handle);
		curl_close($curl_handle);
		
		// get initialization result
		
		$response = new SimpleXMLElement($xmlResponse);
		$paymentid = $response->paymentid;
		$paymenturl = $response->hostedpageurl;
		
		// il security token is used also in following phase to guarantee response authenticity
		$securitytoken = $response->securitytoken;
		
		redirect("$paymenturl?PaymentID=$paymentid");
    }
	
	
	
    public function notify()
    {
		// This script will be called from MonetaWeb server as payment result
		// (server to server message)
		
		$paymentID = $_POST['paymentid'];
		
		/* $result can be one of the following:
		"APPROVED"
		"NOT APPROVED"
		"CAPTURED"
		"NOT AUTHENTICATED"
		*/
		$result = $_POST['result'];
		
		$authorizationCode = $_POST['authorizationcode'];
		$rrn = $_POST['rrn'];								// card transation id
		$merchantOrderId = $_POST['merchantorderid'];		// sended in buy.php
		$responsecode = $_POST['responsecode'];
		$threeDSecure = $_POST["threedsecure"];
		$maskedPan = $_POST["maskedpan"];
		$cardCountry = $_POST["cardcountry"];
		$securityToken = $_POST["securitytoken"];
		
		$method_and_id = explode(",",$_POST["customfield"]);
		$method = $method_and_id[0];
		$id = $method_and_id[1];
		$from = $method_and_id[2];   //1=subscription, 2=renewal, 3=payment due
		$SUBS_AMOUNT_PAYED = $method_and_id[3];
		
                
                //log purpose
                $moneta_info = array();
                $moneta_info["all_post_values"] = json_encode($_POST);
                $moneta_info["payment_for"] = $from; //1=subscription, 2=renewal, 3=payment due
                $moneta_info["payment_date"] = date("Y-m-d H:i:s");
                $this->model_common->insert('moneta_log',$moneta_info);
                
		
		// for payments of "subscription"
		if(($method=="moneta") and (($result=="APPROVED") or ($result=="CAPTURED")) and ($from=="1"))
		{
		$data = array();
		$data["SUBS_TRANSACTION_ID"] = $paymentID;
		$data["SUBS_AMOUNT_PAYED"] = $SUBS_AMOUNT_PAYED;
		$this->model_common->update('subs_subscriptions',$data,"SUBS_ID",$id);
		
				//--- start: send email
		 // you send send email with this information hereeeeeeeee.
				//--- end: send email
		//$ResultURL = base_url_tr("gateway_moneta/result/".$result."/1/".$id);
		}
		
		
		$ResultURL = base_url_tr("gateway_moneta/result/" . $result);
		// then echo return url for moneta 
		echo $ResultURL; // Send to MonetaWeb server 
    }
	
	// last return url function 
	public function result($result="")
	{
	  $data["payment_status"] = $result;
	  $data['header']['title'] = "Payment Status";
	  $data['body']['page'] = "payment_made_status";
	  $this->load->view('template',$data);
	}
    
}
