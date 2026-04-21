<?php

##############################
### Error reporting 
##############################

// error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 3600);
ini_set('default_socket_timeout', 3600);
date_default_timezone_set('Asia/Bangkok');


define('EOL',(PHP_SAPI == 'cli') ? PHP_EOL : '<br />');

##############################
### Include PHPExcel 
##############################
require_once dirname(__FILE__) . '/Classes/PHPExcel.php';

$filename = str_replace('.php', '.xlsx', __FILE__);

##############################
### Get last saved file 
### If less than 30 mins
##############################
// $filem = 0;
// if(file_exists($filename)) $filem = filemtime($filename);

// $ts1 = $filem;
// $ts2 = time();
// $time_diff = $ts2 - $ts1;                            
// $time = floor($time_diff/60); // output min


##############################
### H E A D E R
##############################

require('label.php');
require('phpfunc.php');

require('../api/config.php');
require('../api/apifunc.php');
require('../api/class.email.php');


##############################
### Call Dashboard API
##############################
$valid = ['id','token','type'];


foreach($valid as $k => $v){
	if(empty($_GET[$v])) {
		echo "Please define all variables".$v;
		exit;
	} else {
		$$v = $_GET[$v];
	}
}

header( "location: x-toplinepivot.php?token={$token}&id={$id}&type={$type}" );
exit;