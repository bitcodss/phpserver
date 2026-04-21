<?php

/** 
 * Get header Authorization
 * */
function getAuthorizationHeader(){
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        }
        else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }
/**
 * get access token from header
 * */
function getBearerToken() {
    $headers = getAuthorizationHeader();
    // HEADER: Get the access token from the header
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}


function sendCurl($url,$token,$array){
	$json = json_encode($array);

	$curl = curl_init();
	if(strpos($token,":") === false) {
		$curlarray = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $json,
			CURLOPT_HTTPHEADER => array(
				"Cache-control: no-cache",
				"Authorization: Bearer ".$token,
				"Content-Type: application/json",
				"Content-Length: " . strlen($json)
			)
		);
	} else {
		$curlarray = array(
			CURLOPT_URL => $url,
			CURLOPT_USERPWD => $token,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $json,
			CURLOPT_HTTPHEADER => array(
				"Cache-control: no-cache",
				"Content-Type: application/json",
				"Content-Length: " . strlen($json)
			)
		);
	}
	curl_setopt_array($curl, $curlarray);
	$response = curl_exec($curl);
	$err = curl_error($curl);
	
	curl_close($curl);
	return $response;
}
function sendGetCurl($url,$token,$array){
  
	$params = '';
	foreach($array as $key=>$value)
			$params .= $key.'='.$value.'&';
	$params = trim($params, '&');	


	$curl = curl_init();
	$curlarray = array(
		CURLOPT_URL => $url.'?'.$params,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTPHEADER => array(
			"Cache-control: no-cache",
			"Authorization: Bearer ".$token
		)
	);

	curl_setopt_array($curl, $curlarray);
	$response = curl_exec($curl);
	$err = curl_error($curl);
	
	curl_close($curl);
	return $response;
}
function get($val){
	$value = $val > 0 ? $val : 0;
	return floatval($value);
}
function calcDivide($v1,$v2){
	if(!$v2) return "N/A";
	else return number_format(floatval($v1/$v2*100),2);
}
function sendAlert($QueryStatus,$relatedFile,$message,$errorInfo,$pdo,$email,$sms){
	// If no any error, end function
	if($QueryStatus) Goto Output;
	$sendemail = $email->sendNOTI($relatedFile,$message,$errorInfo);

	$TELNO = '66995788578';
	$MSG = "File:{$relatedFile}.php - {$message}:{$errorInfo}";
	//$sendsms = $sms->sendSMS($TELNO,$MSG);

	Output:
}
function APILog($pdo,$api,$array,$status,$msg){
	date_default_timezone_set('Asia/Bangkok');

	$param = '';
	$i = 0;
	foreach($array as $key => $val){
		if($i > 0) $param .= ';';
		$param .= $key.':'.$val;
		$i++;
	}
	$result = $pdo->prepare("INSERT INTO API_Log VALUES (:datetime, :api, :param, :status, :log) ;");
	$param = [':datetime' => date("Y-m-d H:i:s"), ':api' => $api, ':param' => $param, ':status' => $status, ':log' => $msg];
	$QueryStatus = $result->execute($param);

	return $QueryStatus;
}

function ymdhistotime($time,$type = 'date'){
	$y = substr($time, 0, 4);
	$m = substr($time, 4, 2);
	$d = substr($time, 6, 2);
	$h = substr($time, 8, 2);
	$i = substr($time, 10, 2);
	$s = substr($time, 12, 2);

	if($type=='date') $date = strtotime(implode("-", array($y, $m, $d)));
	if($type=='full') $date = strtotime(implode("-", array($y, $m, $d)).' '.implode(":", array($h, $i, $s)));

	if($type=='strdate') $date = implode("-", array($y, $m, $d));
	if($type=='strfull') $date = implode("-", array($y, $m, $d)).' '.implode(":", array($h, $i, $s));

	return $date;
}
function dateDifference($date_1 , $date_2 , $differenceFormat = '%a' )
{
    $datetime1 = date_create($date_1);
    $datetime2 = date_create($date_2);
    
    $interval = date_diff($datetime1, $datetime2);
    
    return $interval->format($differenceFormat);
    
}
function d($v,$x000 = false){
	if($v=='-') $v = '-';
	if(empty($v)) $v = null;
	$v = is_numeric($v) ? number_format($v,0,'.',$x000) : $v;
	return $v;
}
function d1($v,$x000 = false){
	if($v=='-') $v = '-';
	if(empty($v)) $v = null;
	$v = is_numeric($v) ? number_format($v,1,'.',$x000) : $v;
	return $v;
}
function d2($v,$x000 = false){
	if($v=='-') $v = '-';
	if(empty($v)) $v = null;
	$v = is_numeric($v) ? number_format($v,2,'.',$x000) : $v;
	return $v;
}
function d99($v){
	if($v=='-') $v = '-';
	return $v;
}
function avg($arr){
	
	$a = array_filter($arr, function($x) { return $x !== '' && $x !== false && $x !== null; });
	$average = array_sum($a) / count($a);

	return $average;
}
function diff($v1,$v2){
	if(empty($v1) || empty($v2)) $diff = 0;
	else $diff = ($v1 - $v2)*100/$v2;

	return number_format($diff,2,'.','');
}
function numdiff($v1,$v2){
	if(empty($v1) || empty($v2)) $diff = 0;
	else $diff = $v1 - $v2;

	return number_format($diff,2,'.','');
}
function genlistYYMM($yymm,$n,$min = 0){
	
	$mm = intval(substr($yymm,2,2));
	$yy = substr($yymm,0,2);
	
	// Find start month, year
	if($mm < $n && $n < 12) {
		$stmm = $mm + 12 - $n + 1;
		$styy = $yy-1;
	} elseif($mm == $n && $n < 12) {
		$stmm = 1;
		$styy = $yy;
	} elseif($mm > $n && $n < 12) {
		$stmm = $mm - $n + 1;
		$styy = $yy;
	} elseif ($n >= 12){
	    $cy = floor($n/12);
	    $dy = $n % 12;
	    if($mm + 1 - $dy < 1){
		    $stmm = $mm + 1 + 12 - $dy;
		    $styy = $yy - $cy - 1;
	    } elseif($mm==12) {
		    $stmm = $mm + 1 - 12;
		    $styy = $yy - $cy + 1;
	    } else {
		    $stmm = $mm + 1 - $dy;
		    $styy = $yy - $cy;
	    }
	} 
	$styymm = $styy.sprintf('%02d',$stmm);
	$arrym = [];
	$arrval = false;
	$arr['y'] = $styy;
	$arr['m'] = $stmm;
	for($i=0;$i<$n;$i++){
	    $arrval = $arr['y'].sprintf('%02d',$arr['m']);
		if($arrval >= $min) $arrym[] = $arrval;
		
		if($arr['m']+1 > 12){
			$arr['m'] = 1;
			$arr['y']++;
		} else {
			$arr['m']++;
		}
	}
	return $arrym;
}
function convThaiM($input){
	$months = array(
		'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
		'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
	);
	
	// Extract the month and year values from the input string
	$month = substr($input, 2);
	$year = substr($input, 0, 2) + 43; // Convert the year to Thai era by adding 43
	
	// Format the output string using the Thai month abbreviation and year value
	$output = $months[$month - 1] .  $year;
	
	// Output the formatted string
	return $output;
}

