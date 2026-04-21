<?php 
class noti 
{
	public $to;
	public $head;
	public $intro;
	public $api;
	private $key;

	public function __construct(){ 
		$this->to = 'thanaphol.cha@gmail.com';
		$this->head = 'System Alert!!!';
		$this->intro = 'กรุณาตรวจสอบรายละเอียดตามด้านล่างนี้';
		$this->api = 'https://sy.nissan-csm.com/email/api.php';
		$this->key = '0e6343ee-416f-739c-b78c-f43c06f5103c57af9bd221ba8f17a9425';
    }

	public function sendNOTI($relatedFile,$message,$msgdetail){
		$res = $this->paramNoti($relatedFile,$message.'<br/>Error info: '.$msgdetail);

		return $res;
	}
	public function paramNoti($relatedFile,$message){
		$INFO['replacer1'] = $this->head;
		$INFO['replacer2'] = $this->intro;
		$INFO['replacer3'] = $message;
		$INFO['to'] = $this->to;
		$INFO['subject'] = 'Script ERROR - file '.$relatedFile;
		$res = $this->genNoti($INFO);
		
		return $res;
	}
	public function genNoti($INFO){
		$ch = curl_init();
		$JSON = json_encode($INFO); 

		curl_setopt($ch, CURLOPT_URL, $this->api);
		curl_setopt($ch, CURLOPT_USERPWD, 'api:'.$this->key);
		
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $JSON);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, 
			array(                                                                          
				'Content-Type: application/json',                                                                                
				'Content-Length: ' . strlen($JSON)
			)
		);   
		$res = curl_exec($ch);
		$err = curl_error($ch);		
		curl_close($ch);
		return $res;
	}

}