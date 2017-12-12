<?php
/**
	Plugin Name: sn for EDD
	Version: 1.7
	Description: این افزونه درگاه <a href="https://sn/" target="_blank">sn</a> را به افزونه Easy Digital Downloads اضافه می&zwnj;کند. این افزونه با نسخه 1.7.1  سازگار است.


	Author: sn
	Author URI: http://sn
**/
@session_start();
 if(!class_exists('nusoap_client'))
     include_once(WP_PLUGIN_DIR.'/edd-sn/nu/nusoap.php');
 function eddp_rial ($formatted, $currency, $price) {
	return $price . ' ریال';
}
add_filter( 'eddp_rial_currency_filter_after', 'eddp_rial', 10, 3 );

function pp_add_gateway ($gateways) {
	$gateways['sn'] = array('admin_label' => 's&zwnj;n', 'checkout_label' => 'پرداخت آنلاين sn');;
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'pp_add_gateway' );

function pp_cc_form () {
	do_action( 'pp_cc_form_action' );
}
add_filter( 'edd_sn_cc_form', 'pp_cc_form' );

function pp_process_payment ($purchase_data) {
	global $edd_options;

	$payment_data = array(
		'price' => $purchase_data['price'],
		'date' => $purchase_data['date'],
		'user_email' => $purchase_data['post_data']['edd_email'],
		'purchase_key' => $purchase_data['purchase_key'],
        'sn-ref' => '',
		'currency' => $edd_options['currency'],
		'downloads' => $purchase_data['downloads'],
		'cart_details' => $purchase_data['cart_details'],
		'user_info' => $purchase_data['user_info'],
		'status' => 'pending'
	);
	$payment = edd_insert_payment($payment_data);

	if ($payment) {

		$_SESSION['sn_payment'] = $payment;
		$return = urlencode(add_query_arg('order', 'sn', get_permalink($edd_options['success_page'])));
        
 $parameters =$edd_options['sn_webservice'];





     

        $price = $purchase_data['price'];
        $email = $purchase_data['post_data']['edd_email'];
        $fname =$purchase_data['post_data']['edd_first'];
        $lname =$purchase_data['post_data']['edd_last'];
        $name=$fname." ".$lname;
         //$date = $purchase_data['date'];
         
        if($edd_options['currency'] == 'RIAL'){
              $price = $price / 10;}
              
                                            // Security
                                            $sec = uniqid();
                                            $md = md5($sec.'vm');
                                            // Security

                $listener_url = trailingslashit( home_url() ).'?edd-listener=IPN2';



                            if($parameters==1)
                            {
                            $data_string = json_encode(array(
                            'pin'=>  $edd_options['sn_Merchant'],
                            'price'=> ceil(($price)),
                            'callback'=>$listener_url.'&md='.$md .'&sec='.$sec ,
                            'order_id'=> $payment,
                            'email'=>$email,
                            'name'=> $name,
                            'ip'=> $_SERVER['REMOTE_ADDR'],
                            'callback_type'=>2
                            ));
                             }
                             else
                             {
                               $data_string = json_encode(array(
                            'pin'=>  $edd_options['sn_Merchant'],
                            'price'=> ceil(($price)),
                            'callback'=>$listener_url.'&md='.$md .'&sec='.$sec ,
                            'order_id'=> $payment,
                            'email'=>0,
                            'name'=> 0,
                            'ip'=> $_SERVER['REMOTE_ADDR'],
                            'callback_type'=>2
                            ));  
                                 
                             }


		$desc='پرداخت سفارش شاره'.$payment ;
       	
    
            
            $ch = curl_init('https://developerapi.net/api/v1/request');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 20);
            $result = curl_exec($ch);
            curl_close($ch);
            $json = json_decode($result,true);
            
            $PayPath = $res['RequestPaymentResult']['PaymentPath'];
            $Status = $res['RequestPaymentResult']['ResultStatus'];

    
        	if(!empty($json['result']) AND $json['result'] == 1)
        {
            
                        // Set Session
                        $_SESSION[$sec] = [
                        	'price'=>$price ,
                        	'order_id'=>$payment ,
                        ];
                        $_SESSION[$sec]['au'] = $json['au']; //SET AU Session
                        
				
				EDD()->session->set( 'sn_Direct_au', $json['au'] );
				edd_update_payment_meta( $payment, 'sn_Direct_au', $json['au'] );
				
				edd_insert_payment_note( $payment, ' شناسه = ' . $payment . '| شناسه پیگیری = ' . $json['au'] );
				echo "<div style='display:none'>{$json['form']}</div>Please wait ... <script language='javascript'>document.payment.submit(); </script>";
            echo "<html><meta http-equiv='Content-Type' content='text/html; charset=utf-8' /> <head><title>Connecting ....</title><head><body onload=\"javascript:window.location='$PayPath'\" style=\"font-family:tahoma; text-align:center;font-waight:bold;direction:rtl\">در حال اتصال به درگاه پرداخت آرین پال ...</body></html>";
            exit;
        }
        else
        {
    	   wp_die('در اتصال به درگاه خطايي رخ داده است ! <br> کد خطا : '. $Status );
    	}
   	}
    else
      {
  		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
      }
}
add_action('edd_gateway_sn', 'pp_process_payment');


function pp_verify() {
	global $edd_options;
    $resp='-';

                        // Security
                        $sec=$_GET['sec'];
                        $mdback = md5($sec.'vm');
                        $mdurl=$_GET['md'];
                        // Security

                            $transData = $_SESSION[$sec];
   
    if( isset($_GET['au']) AND isset($_GET['sec']) AND isset($_GET['md']) )
     {
      
       	if($mdback == $mdurl){

        
       $MerchantID = $edd_options['sn_Merchant'];
        $amount = $transData['price']; //

        


$au=$transData['au']; //
$payment_id=$transData['order_id'];

                      
$bank_return = $_POST + $_GET ;
$data_string = json_encode(array (
'pin' => $MerchantID,
'price' => ceil(($amount)),
'order_id' => $payment_id,
'au' => $au,
'bank_return' =>$bank_return,
));

$ch = curl_init('https://developerapi.net/api/v1/verify');
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
'Content-Type: application/json',
'Content-Length: ' . strlen($data_string))
);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 20);
$result = curl_exec($ch);
curl_close($ch);
 $json = json_decode($result,true);
				$result = $json['result'];



		edd_empty_cart();

      if ( ! empty($json['result'] ) && intval( $json['result'] ) == 1 )// Your Peyment Code Only This Event
        {
            update_post_meta( $payment, '_edd_payment_ppalrefnum', $au);

            edd_insert_payment_note( $payment_id, sprintf( __( 'شماره رسيد پرداخت : %s', 'edd' ) , $au ) );
			edd_update_payment_status($payment_id, 'publish');
			edd_send_to_success_page();

		}
       else
        {
			edd_update_payment_status($payment_id, 'failed');
			$failed_page = get_permalink($edd_options['failure_page']);
			wp_redirect( $failed_page );
			exit;
		}
	}
    else
    {
            edd_update_payment_status($payment_id, 'failed');
            update_post_meta( $payment_id, '_edd_payment_ppalrefnum','-');

			$failed_page = get_permalink($edd_options['failure_page']);
			wp_redirect( $failed_page );
			exit;
    }
    
	}
   
}
add_action('init', 'pp_verify');

function pp_add_settings ($settings) {
	$sn_settings = array (
		array (
			'id'		=>	'sn_Setting',
            'name'		=>	'<strong>پیکربندی sn</strong>',
			'desc'		=>	'پیکربندی',
			'type'		=>	'header'
		),
		array (
			'id'		=>	'sn_Merchant',
            'name'      => 'شناسه درگاه - Merchant' ,
            'desc'		=>	'',
			'type'		=>	'text',
			'size'		=>	'regular'
		) ,
        	array (
			'id'		=>	'sn_webservice',
            'name'      => 'ارسال اطلاعات به وب سرویس' ,
            'desc'		=>	'در صورت فعال بودن ، اطلاعات خریدار در پنل کاربری ثبت خواهد شد',
			'type'		=>	'checkbox',
			'default'     => 'yes',
			'size'		=>	'regular'
		) 

      //
	);
	return array_merge( $settings, $sn_settings );
}
add_filter('edd_settings_gateways', 'pp_add_settings');
?>