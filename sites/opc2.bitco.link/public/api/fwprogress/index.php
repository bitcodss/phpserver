<?php

##############################
### Error reporting 
##############################

error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
ini_set('memory_limit', '-1');
date_default_timezone_set('Etc/GMT+7');

require('../config.php');
require('../apifunc.php');
require('../class.email.php');

// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
	// Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
	// you want to allow, and if so:
	header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

	if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
		// may also be using PUT, PATCH, HEAD etc
		header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         

	if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
		header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

	exit(0);
}


##########################
### Header Validation
##########################	
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
	header('WWW-Authenticate: Basic realm="Access to the restrict area"');
    header('HTTP/1.0 401 Bad Request');
    echo 'Bad Request - Only POST METHOD Accept';
	http_response_code(401);

    die();
}

##############################
### GET Variable
##############################
	$event = json_decode(file_get_contents('php://input'),true);

	$arr = ['cat','type'];
	$event['cat'] = 'dashboard';

	if(!empty($arr)){
		foreach($arr as $k => $v){
			if(!empty($event[$v])) $$v = $event[$v];		
		}
	}

##############################
### mySQL Connect
##############################
	$pdo = new PDO('mysql:dbname='.$_CONFIG['db'].';host='.$_CONFIG['host'].';charset=utf8', $_CONFIG['usr'], $_CONFIG['psw']) 
	or die('Cannot connect mySQL');

	$relatedFile = basename(__FILE__, '.php'); 
	$email = new noti();
	$sms = null;

##############################
### Columns
##############################

	$header = ['table0' => 'Overall', 'table1' => 'เพศ', 'table2' => 'SES', 'table3' => 'อายุ', 'table4' => 'Location', 'table5' => 'Cell'];
	$table['table0'] = [
		[
			'label' => 'เสร็จแล้ว',
			'quota' => 240,
			'val' => 0
		], [
			'label' => 'ยังไม่เสร็จ (เคยเข้าลิงค์)',
			'quota' => 0,
			'val' => 0
		]
	];
	$table['table1'] = [
		[				
			'label' => 'หญิง',
			'quota' => 240,
			'val' => 0
		], [				
			'label' => 'LGBT',
			'quota' => 0,
			'val' => 0
		]
	];
	$table['table2'] = [
		[				
			'label' => 'B',
			'quota' => 80,
			'val' => 0
		], [				
			'label' => 'C+',
			'quota' => 80,
			'val' => 0
		], [				
			'label' => 'C',
			'quota' => 80,
			'val' => 0
		]
	];
	$table['table3'] = [
		[
			'label' => '20-29',
			'quota' => 80,
			'val' => 0
		], [
			'label' => '30-39',
			'quota' => 80,
			'val' => 0
		], [
			'label' => '40-45',
			'quota' => 80,
			'val' => 0
		]
	];
	$table['table4'] = [
		[
			'label' => 'Inner BKK',
			'quota' => 80,
			'val' => 0
		], [
			'label' => 'Middle BKK',
			'quota' => 80,
			'val' => 0
		], [
			'label' => 'Outer BKK',
			'quota' => 80,
			'val' => 0
		]
	];
	$table['table5'] = [
		[
			'label' => 'ผู้ที่ดื่ม สปายไวน์คูลเลอร์ บ่อยสุด',
			'quota' => 80,
			'val' => 0
		], [
			'label' => 'ผู้ที่ดื่ม เครื่องดื่มผสมพร้อมดื่ม ไวน์คูลเลอร์ ค๊อกเทล ไฮบอล บรรจุขวด,กระป๋องพร้อมดื่ม บ่อยสุด',
			'quota' => 80,
			'val' => 0
		], [
			'label' => 'ผู้ที่ดื่ม เบียร์ บ่อยสุด และ มีสปายไวน์คูลเลอร์ เป็น หนึ่งในตัวเลือก P3M',
			'quota' => 80,
			'val' => 0
		]
	];
	$graph['graph1'] = [
		'title' => 'ข้อมูลชุดสำเร็จย้อนหลังจากวันแรก',
		'table' => []
	];

##############################
### S Q L   Q U E R Y
##############################
		
	if($cat=='dashboard'){
		$sql = "SELECT idqr, IF(lastq='thank','FINISH',status) status, lastlogic, lastq, IF(lastq='thank',left(lastact, 8),NULL) enddate,  
			QUOTA_GENDER, QUOTA_AGE, QUOTA_GBKK, QUOTA_SES, QUOTA_BUMO
		FROM survey_main ORDER BY left(enddate,8);";
		$param = []; 

	} elseif($cat=='xlsx'){
		$sql = "";
		$param = [];
	}
	
	$query = $pdo->prepare($sql);
	$QueryResult = $query->execute($param);
	sendAlert($QueryResult,$relatedFile,"CANNOT Query Data",$query->errorInfo()[2],$pdo,$email,$sms);

##############################
### Error Code
##############################
if(!$query){
	$output = [
		'message' => $query->errorInfo()[2],
		'code' => 500,
		'success' => false
	];
	Goto Output;
}
elseif($query->rowCount() < 1){
	$output = [
		'message' => strtr($sql,$param),
		'code' => 204,
		'success' => false
	];
	Goto Output;
}


##############################
### Content Loop
##############################

	if($cat=='dashboard'){		
		$output = []; // Reset
		$output = [
			'message' => 'Success',
			'code' => 200,
			'success' => true
		];

		$data = [];
		while($rs = $query->fetch(PDO::FETCH_ASSOC)){
			$data[] = $rs;
		}


		foreach($data as $k => $v){
			// Table 0
			if(in_array($v['status'],['FINISH','REVIEW'])) $table['table0'][0]['val']++;
			if(in_array($v['status'],['ACTIVE'])) $table['table0'][1]['val']++;
			
			if(in_array($v['status'],['FINISH','REVIEW']) || $v['lastlogic'] >= 20){				

				// Graph Daily
				if(!empty($v['enddate'])){
					$v['enddate'] = substr($v['enddate'],0,4).'-'.substr($v['enddate'],4,2).'-'.substr($v['enddate'],6,2);
					if(!array_key_exists($v['enddate'],$graph['graph1']['table'])) $graph['graph1']['table'][$v['enddate']] = 1;
					else $graph['graph1']['table'][$v['enddate']]++;
				}
				
				// Table 1
				switch($v['QUOTA_GENDER']) {
					case 1:
						$table['table1'][0]['val']++;
						break;
					case 2:
						$table['table1'][1]['val']++;
						break;
				} 

				// Table 2
				switch($v['QUOTA_SES']) {
					case 1:
						$table['table2'][0]['val']++;
						break;
					case 2:
						$table['table2'][1]['val']++;
						break;
					case 3:
						$table['table2'][2]['val']++;
						break;
				} 

				// Table 3
				switch($v['QUOTA_AGE']) {
					case 1:
						$table['table3'][0]['val']++;
						break;
					case 2:
						$table['table3'][1]['val']++;
						break;
					case 3:
						$table['table3'][2]['val']++;
						break;
				} 

				// Table 4
				switch($v['QUOTA_GBKK']) {
					case 1:
						$table['table4'][0]['val']++;
						break;
					case 2:
						$table['table4'][1]['val']++;
						break;
					case 3:
						$table['table4'][2]['val']++;
						break;
				} 

				// Table 5
				switch($v['QUOTA_BUMO']) {
					case 1:
						$table['table5'][0]['val']++;
						break;
					case 2:
						$table['table5'][1]['val']++;
						break;
					case 3:
						$table['table5'][2]['val']++;
						break;
				} 
			}
		}

		// API
		foreach($header as $k => $v){
			$output['data'][$k] = [
				'title' => $header[$k],
				'type' => 'normal',
				'row' => '',
				'column' => '',
				'table' => []
			];	

			foreach($table[$k] as $k1 => $v1){
				$v1['rem'] = max($v1['quota']-$v1['val'], 0);
				$v1['per'] = $v1['quota'] < 1 ? 0 : d1($v1['val']*100/$v1['quota']);
				$output['data'][$k]['table'][] = [
					$v1['label'], 
					$v1['quota'],
					$v1['val'],
					$v1['rem'],
					$v1['per']
				];
			}
		}


		$output['data']['graph1'] = [
			'title' => $graph['graph1']['title'],
			'table' => $graph['graph1']['table']
		];

	} elseif($cat=='xlsx'){
		
	}

##############################
### Footer
##############################

Output:

// // --------------------------
// // Log
// // --------------------------
// if($o['success']) $apireturn = 'success';
// else $apireturn = 'failed';
// APILog($pdo,$relatedFile,$apiparam,$apireturn,$o['message']);

// // --------------------------
// // Alert if failed
// // --------------------------
// sendAlert($o['success'],$relatedFile,"Query Dashboard Failed",$o['message'],$pdo,$email,$sms);

header('Content-Type: application/json;charset=utf-8');
$json = json_encode($output);
if ($json === false) {
    // Avoid echo of empty string (which is invalid JSON), and
    // JSONify the error message instead:
    $json = json_encode(array("jsonError", json_last_error_msg()));
    if ($json === false) {
        // This should not happen, but we go all the way now:
        $json = '{"jsonError": "unknown"}';
    }
    // Set HTTP response status code to: 500 - Internal Server Error
    http_response_code(500);
} else {
	http_response_code(200);
}
echo $json;