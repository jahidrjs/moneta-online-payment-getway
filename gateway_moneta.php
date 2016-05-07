<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Gateway_moneta extends CI_Controller {

    public function __Construct(){
        parent::__construct();	

/*        if(!$this->session->userdata('is_logged'))
        {
               redirect('', 'refresh');
        }*/
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
		
		if($from==3) //"3" means payment is coming from "payment_due"
		{
			$SCAP_ID = $this->session->userdata('SCAP_ID');
			
			//keep the next line for future purpose
		    //$amount = $this->session->userdata('due_amount');

			$details = $this->model_common->select_single_row("scap_payments_due","SCAP_ID",$SCAP_ID);
			$amount = $details["SCAP_AMOUNT"];
			$merchantOrderId = $SCAP_ID;
			$method_and_id = "moneta,".$SCAP_ID.",".$from.",".$amount;
		}
		
		
		if($from==2) //"2" means payment is coming from "renewal"
		{
			$SUBS_ID = $this->session->userdata('SUBS_ID');
			
			//$details = $this->model_common->select_single_row("subs_subscriptions","SUBS_ID",$SUBS_ID);
			$amount = $this->session->userdata('SUBS_AMOUNT_PAYED');;
			$merchantOrderId = $SUBS_ID;
			$method_and_id = "moneta,".$SUBS_ID.",".$from.",".$amount;
		}
		
		
		if($from==1) //"1" means payment is coming from "subscription"
		{
			$SUBS_ID = $this->session->userdata('SUBS_ID');
			
			//$details = $this->model_common->select_single_row("subs_subscriptions","SUBS_ID",$SUBS_ID);
			$amount = $this->session->userdata('SUBS_AMOUNT_PAYED');
			$merchantOrderId = $SUBS_ID;
			$method_and_id = "moneta,".$SUBS_ID.",".$from.",".$amount;
		}
		
		
		    //test id: 97702600, password: smF14001
			//production id: 97702600, password: Pippo2406
		
		$parameters = array(
			'id' => '97702600', // Terminal Id
			'password' => 'Pippo2406',
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
		$SUBS_ID = $id;
		$STUD_ID = $this->model_common->select_single_field("STUD_ID","subs_subscriptions","SUBS_ID",$SUBS_ID);
		$STUD_EMAIL = $this->model_common->select_single_field("STUD_MAIL_MAIL_1","stud_students","STUD_ID",$STUD_ID);
		
		//***************** just for checking
/*		$info = array();
		$info["method_and_id"] = $method_and_id;
		$info["sub_id"] = $SUBS_ID;
		$info["result"] = $result;
		$info["transaction_id"] = $paymentID;
		$info["from"] = $from;

		$log['call_time'] = date("Y-m-d h:i:s");
        $log['call_ip'] = $this->input->ip_address();
        $log['post'] = $info;
        $file = fopen("./moneta_log.txt","a+");
        if($file){
            fwrite($file, json_encode($log)."\r\n\r\n");
        }
        fclose($file);
*/
		//********************
		
		$sub = "Scuola di Musica di Fiesole"." | ".date("d-m-Y");
		$msg = "Caro Studente, <br/>
                        il tuo pagamento per l'iscrizione è stato ricevuto.<br/><br/>

                        Cordiali Saluti,<br/>
                        Segreteria Studenti<br/>
                        Scuola di Musica di Fiesole <br/><br/>

                        <hr/>

                        Dear Student,<br/>
                        Your payment for subscription was successful.<br/><br/>

                        Best Regards<br/>
                        Students' Office<br/>
                        Scuola di Musica di Fiesole. <br/>
                        ";
		
		$this->custom->send_email($STUD_EMAIL,$email_from="",$sub,$msg); //to, from, subject, msg
				//--- end: send email
		//$ResultURL = base_url_tr("gateway_moneta/result/".$result."/1/".$id);
		}
		elseif(($result!="APPROVED" or $result!="CAPTURED") and ($from=="1"))
		{
			$SUBS_ID = $id;
			$this->model_common->delete("subs_subscriptions","SUBS_ID",$SUBS_ID);
		}


		
		// for payments of "renewal"
		if(($method=="moneta") and (($result=="APPROVED") or ($result=="CAPTURED")) and ($from=="2"))
		{
		$data = array();
		$data["SUBS_TRANSACTION_ID"] = $paymentID;
		$data["SUBS_AMOUNT_PAYED"] = $SUBS_AMOUNT_PAYED;
		$this->model_common->update('subs_subscriptions',$data,"SUBS_ID",$id);
		
				//--- start: send email
		$SUBS_ID = $id;
		$STUD_ID = $this->model_common->select_single_field("STUD_ID","subs_subscriptions","SUBS_ID",$SUBS_ID);
		$STUD_EMAIL = $this->model_common->select_single_field("STUD_MAIL_MAIL_1","stud_students","STUD_ID",$STUD_ID);
		
		$sub = "Scuola di Musica di Fiesole"." | ".date("d-m-Y");
		$msg = "Caro Studente, <br/>
                        il tuo pagamento per l'iscrizione è stato ricevuto.<br/><br/>

                        Cordiali Saluti,<br/>
                        Segreteria Studenti<br/>
                        Scuola di Musica di Fiesole <br/><br/>

                        <hr/>

                        Dear Student,<br/>
                        Your payment for subscription was successful.<br/><br/>

                        Best Regards<br/>
                        Students' Office<br/>
                        Scuola di Musica di Fiesole. <br/>
                        ";
		
		$this->custom->send_email($STUD_EMAIL,$email_from="",$sub,$msg); //to, from, subject, msg
				//--- end: send email
		//$ResultURL = base_url_tr("gateway_moneta/result/".$result."/2/".$id);
		}
		elseif(($result!="APPROVED" or $result!="CAPTURED") and ($from=="2"))
		{
			$SUBS_ID = $id;
			$this->model_common->delete("subs_subscriptions","SUBS_ID",$SUBS_ID);
		}
		
		
		// for payments of "payment due"
		if(($method=="moneta") and (($result=="APPROVED") or ($result=="CAPTURED")) and ($from=="3"))
		{
		$data = array();
		$data["SCAP_TRANSACTION_ID"] = $paymentID;
		$data["PAYT_DAI_ID"] = $this->model_common->select_single_field("PAYT_DAI_ID","payt_payment_types","PAYT_ID","1");
		$data["SCAP_AMOUNT_PAYED"] = $this->model_common->select_single_field("SCAP_AMOUNT","scap_payments_due","SCAP_ID",$id);
		$data["CC_UPD_DATA"] = date("Y-m-d H:i:s");
                $this->model_common->update('scap_payments_due',$data,"SCAP_ID",$id);
		
						//--- start: send email
		$SCAP_ID = $id;
		$STUD_ID = $this->model_common->select_single_field("STUD_ID","scap_payments_due","SCAP_ID",$SCAP_ID);
		$STUD_EMAIL = $this->model_common->select_single_field("STUD_MAIL_MAIL_1","stud_students","STUD_ID",$STUD_ID);
		
		$sub = "Scuola di Musica di Fiesole"." | ".date("d-m-Y");
		$msg = "Caro Studente, <br/>
                        il tuo pagamento per l'iscrizione è stato ricevuto.<br/><br/>

                        Cordiali Saluti,<br/>
                        Segreteria Studenti<br/>
                        Scuola di Musica di Fiesole <br/><br/>

                        <hr/>

                        Dear Student,<br/>
                        Your payment for subscription was successful.<br/><br/>

                        Best Regards<br/>
                        Students' Office<br/>
                        Scuola di Musica di Fiesole. <br/>
                        ";
		
		$this->custom->send_email($STUD_EMAIL,$email_from="",$sub,$msg); //to, from, subject, msg
				//--- end: send email
		
		//$ResultURL = base_url_tr("gateway_moneta/result/".$result."/3/".$id);
		}
		
		$ResultURL = base_url_tr("gateway_moneta/result/" . $result);
		
		echo $ResultURL; // Send to MonetaWeb server 
    }
	
	
	public function result($result="")
	{
 		
/*	  if($from == 3)
	  {
						//--- start: send email
		$SCAP_ID = $id;
		$STUD_ID = $this->model_common->select_single_field("STUD_ID","scap_payments_due","SCAP_ID",$SCAP_ID);
		$STUD_EMAIL = $this->model_common->select_single_field("STUD_MAIL_MAIL_1","stud_students","STUD_ID",$STUD_ID);
		
		$sub = "Scuola di Musica di Fiesole"." | ".date("d-m-Y");
		$msg = "Caro Studente, <br/>
                        il tuo pagamento per l'iscrizione è stato ricevuto.<br/><br/>

                        Cordiali Saluti,<br/>
                        Segreteria Studenti<br/>
                        Scuola di Musica di Fiesole <br/><br/>

                        <hr/>

                        Dear Student,<br/>
                        Your payment for subscription was successful.<br/><br/>

                        Best Regards<br/>
                        Students' Office<br/>
                        Scuola di Musica di Fiesole. <br/>
                        ";
		
		$this->custom->send_email($STUD_EMAIL,$email_from="",$sub,$msg); //to, from, subject, msg
				//--- end: send email
	  }*/
		
	  $data["payment_status"] = $result;
	  $data['header']['title'] = "Payment Status";
	  $data['body']['page'] = "payment_made_status";
	  $this->load->view('template',$data);
	}
    
}