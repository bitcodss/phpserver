<?php

error_reporting(E_ALL ^ E_NOTICE);
//ini_set('display_errors', TRUE);
//ini_set('display_startup_errors', TRUE);
date_default_timezone_set('Asia/Bangkok');

############################
### PHP Header
############################

	header('Content-Type:text/html; charset=utf-8');
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Cache-Control: no-cache");
	header("Pragma: no-cache");

	ob_start();
	require('phpfunc.php');
	
	// Notification
	require('api/apifunc.php');
	require('api/class.email.php');
	require('api/config.php');

	$email = new noti();
	$sms = false;
	
	$relatedFile = 'index.php'; 
	$MODE = 'sms';
	$time_start = microtime(true); 
	
############################
### PRE SETTING
############################
	// set session
	$SES = session_id();
	if(empty($SES)) {
		session_start();
		$SES = session_id();
	}

	// If assign qstep
	if(isset($_REQUEST["qstep"])) $STEP = $_REQUEST["qstep"];
		else $STEP = "intro";

##########################
### GET, POST, REQUEST VARIABLES
##########################
	// If different ID, clear session
	if(!empty($_GET["id"])) {
		$id = $_GET["id"];
		if(!empty($_SESSION['id']) && $id != $_SESSION['id']) {
			session_destroy();
			session_start();
		}
		
		$_SESSION['id'] = $id;
	} 
	// Clear session
	if(empty($_REQUEST['qstep'])) {
		session_destroy();
		session_start();
	}

############################
### PHP INCLUDE
############################	

	include($_CONFIG['label1']);
	include($_CONFIG['metadata1']);
	include($_CONFIG['routing1']);
	include($_CONFIG['task1']);
	$tbl = $_CONFIG['tb1'];	


##########################
### Exit 1 - Close system
##########################
	// System on/off
	if(!$_CONFIG['system']) {
		$theme = file_get_contents('event.html');
		$replacer = [':tha' => $event['close']['th'], ':eng' => $event['close']['en']];
		$theme = strtr($theme, $replacer);
		echo $theme;
		exit;
	} 

##########################
### SET UP KEY VARIABLES
##########################
	$complete = false;  // complete status
	$time = date("YmdHis"); // Time now


##########################
### mySQL CONNECT
##########################
	$pdo = new PDO('mysql:dbname='.$_CONFIG['db'].';host=mariadb;charset=utf8', $_CONFIG['usr'], $_CONFIG['psw']) 
	or die('Cannot connect mySQL');

	##########################
	### Auto Generate random ID
	##########################
	if (!isset($id) && $_CONFIG['autogen']){
		
		$id = uniqidReal();

		$sql = 'INSERT INTO '.$tbl.' (`idqr`,`startdate`,`lastq`,`hist`) VALUES (:id,:time,"intro","intro;") ;';
		$result = $pdo->prepare($sql);
		$param = [':id' => $id, ':time' => $time];
		$QueryResult = $result->execute($param);
		sendAlert($QueryResult,$relatedFile,"CANNOT Insert New Case",$result->errorInfo()[2],$pdo,$email,$sms);
	}

	##########################
	### Exit 2 - No ID and not autogen
	##########################
	elseif(empty($id) && !$_CONFIG['autogen']) {
		$_CONFIG['p_form'] = "
		<script>
			$(document).ready(function(){
				$(document).find('form').attr({'method':'get'});
			});
		</script>";

		$param = [
			'<!-- ## === Navigator === ## -->' => $_CONFIG['p_nav'],
			'<!-- ## === Title === ## -->' => $_CONFIG['p_title'],
			'<!-- ## === Content === ## -->' => genAuth(),
			'<!-- ## === Action === ## -->' => false,
			'<!-- ## === Refresh === ## -->' => $_CONFIG['p_form']
			];
	
		$html = file_get_contents("theme.html");
		$html = strtr($html, $param);
		echo $html;
		exit;
	} 
	

##########################
### GET CASE DATA
##########################
	$msql = 'SELECT * FROM survey_main x WHERE x.idqr=:id ;';
	$param = [':id' => $id ];
	$result = $pdo->prepare($msql);
	$QueryResult = $result->execute($param);
	sendAlert($QueryResult,$relatedFile,"CANNOT Query Case",$result->errorInfo()[2],$pdo,$email,$sms);

	$DATA = $result->fetch(PDO::FETCH_ASSOC);
	$dbstatus = $DATA['status'];


	##########################
	### Goto last step
	if(!isset($_REQUEST['qstep']) && $DATA['lastq']) $STEP = $DATA['lastq'];
	if($dbstatus != 'FINISH' and isset($_GET['qstep'])) $STEP = $DATA['lastq'];
	
	##########################
	### CHECK ID
	##########################
	if($_CONFIG['checkid']){

		##########################
		### Exit 5 - ID not found
		##########################
		if($result->rowCount() < 1){
			$theme = file_get_contents("event.html");
			$replacer = [':tha' => $event['unauthen']['th'], ':eng' => $event['unauthen']['en']];
			$theme = strtr($theme, $replacer);
			echo $theme;
			exit;
		} 
		
		##########################
		### Exit 6 - Already completed
		##########################
		if(($dbstatus=="FINISH" || $dbstatus=="REVIEW") && ($DATA['status_lock'] || !in_array($STEP, $_CONFIG['allowstep']))){
			$theme = file_get_contents("event.html");
			$replacer = [':tha' => $event['comp']['en'], ':eng' => $event['comp']['en']];
			$theme = strtr($theme, $replacer);
			echo $theme;
			exit;
		}


		##########################
		### Exit 8 - Screen Out
		##########################
		if($DATA['lastq']=='TERMACT'){	
			
			$theme = file_get_contents("event.html");
			$replacer = [':tha' => $event['terminated']['th'], ':eng' => $event['terminated']['en']];
			$theme = strtr($theme, $replacer);
			echo $theme;
			exit;
		}	
	}


	#####################################
	###
	### GET INFO FROM SCRIPT
	###
	#####################################		
		$STEPN = $ROUTING[$STEP];

	#####################################
	###
	### Convert POST data to SESSION data
	### for temporary use
	#####################################
		if(!empty($_POST)) {
			$ARROUTPUT = routing($pdo,$_POST,$DATA,$STEP);
			$POST = $ARROUTPUT['POST'];
			$ERR = $ARROUTPUT['ERR'];


			// Customization
			if($STEP=='s1_1'){
				$choice = [101,102,103,104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,119,120,121,122,123,124,125,126,127,128,129,130,131,132,133,134,135,100,201,202,203,204,205,206,207,208,209,210,211,212,213,214,215,216,217,218,219,220,221,222,223,224,225,200,301,302,303,304,305,306,307,308,309,310,311,312,313,314,315,316,317,318,319,320,321,322,323,324,325,326,327,328,329,330,331,332,333,334,335,336,300,401,402,403,404,405,406,407,408,409,410,411,412,413,414,415,416,417,418,400,511,512,513,514,515,516,517,518,519,520,521,522,523,524,525,526,500,601,602,603,604,605,606,607,608,609,610,611,612,613,600,701,702,703,700,801,802,803,804,805,806,807,800,901,902,903,904,905,906,907,908,909,910,911,912,913,914,915,916,917,918,919,900,1001,1002,1003,1004,1005,1006,1007,1008,1009,1010,1011,1012,1013,1014,1015,1016,1017,1018,1000];
				$coth = [100,200,300,400,500,600,700,800,900,1000];
				foreach($choice as $ck => $cv){
					unset($_POST['s13a_r'.$cv]);
				}
				foreach($coth as $ck => $cv){
					unset($_POST['s13a_r'.$cv.'_oth']);
				}
			}
			foreach ($_POST as $key => $val) {
				if(!is_numeric($key)){	
					$POST[$key] = $val;
					if(isset($POST[$key])) $_SESSION[$key] = $val;
					elseif(isset($DATA[$key]) && $DATA[$key] != '') $_SESSION[$key] = $DATA[$key];
				}
			}
			foreach ($POST as $key => $val) {
				if(!is_numeric($key)){	
					if(isset($POST[$key])) $_SESSION[$key] = $val;
					elseif(isset($DATA[$key]) && $DATA[$key] != '') $_SESSION[$key] = $DATA[$key];
				}
			}
			
		}

	#####################################
	### CHECK IF BACK BUTTON
	#####################################
		$isBACK = false;
		if(!empty($POST['button']) && in_array($POST['button'], $_CONFIG['back'])) $isBACK = true;

	#####################################
	###
	### PROCESS THAT HAVE QSTEP VARIABLE
	###
	#####################################
	
	### If back button, ignore ERR
	if($isBACK) $ERR = '';

	##########################
	### If no error
	###
	##########################
	if($ERR == ''){

		// destroy all cookies
		// delcookies();		

		#####################################
		###
		### If Not BACK button
		#####################################
		if(!$isBACK){

			#####################################
			###
			### UPDATE DATA in SQL if no error return
			#####################################
			$upsql = '';
			$upparam = [];

			$sql = 'UPDATE survey_main x SET ';
			if(isset($POST)){
				foreach($POST as $k => $v){
					if(!in_array($k, array("qid","qstep","button"))){
						if(is_array($v)) $v = implode(', ', $v);
						if(strpos($v,'"') !== false) $v = str_replace('"', '\'', $v);

						if($upsql != '') $upsql .= ', ';
						$upsql .= '`'.$k.'`=:'.$k.' ';

						$upparam[':'.$k] = $v;
					}
				}
				### update route // Check if not exist
				if(strpos($DATA['hist'],$POST['qstep'].';') === False) {
					$upsql .= ', `hist`=concat(hist,:qstep,";") ';
					$upparam[':qstep'] = $POST['qstep'];
				}
			}
			
			$comma = ', ';
			if($upsql=='') $comma = false;
			$upsql .= $comma.' `ip`=:ip, `lastact`=:lastact, `status`="ACTIVE" ';
			$upparam[':ip'] = $_SERVER['REMOTE_ADDR'];
			$upparam[':lastact'] = $time;

			if($STEP=='intro') {
				$upsql .= ', startdate=:startdate ';
				$upparam[':startdate'] = $time;
			}
			if($MODE && $DATA['sinvite'] != $MODE){
				$upsql .= ', sinvite=:sinvite ';
				$upparam[':sinvite'] = $MODE;
			}

			### WHERE
			$sql .= $upsql.' WHERE x.idqr=:id ;';
			$upparam[':id'] = $id;

			$result = $pdo->prepare($sql);
			$QueryResult = $result->execute($upparam);				
			sendAlert($QueryResult,$relatedFile,"CANNOT update Main Query",$result->errorInfo()[2],$pdo,$email,$sms);
		
			########################
			### Get Update Data from DB
			###
			########################
			if(isset($POST)){
				$param = [':id' => $id];
				$result = $pdo->prepare($msql);
				$QueryResult = $result->execute($param);
				sendAlert($QueryResult,$relatedFile,"CANNOT get update Query",$result->errorInfo()[2],$pdo,$email,$sms);

				$DATA = $result->fetch(PDO::FETCH_ASSOC);

			########################
			### Get NEXTSTEP, WHETHER COMPLETE & NO.OF STEP
			### After Update DB
			########################

				$ARROUTPUT = routing($pdo,$POST,$DATA,$STEP);
				$APPOINT = $ROUTAPPOINT[$STEP] ?? false;
				$STEP = $ARROUTPUT['NEXTSTEP'];
				$STEPN = $ROUTING[$STEP];
				$COMPLETE = $ROUTCOMP[$STEP] ?? false;
			}
		} // close if not back button

		########################
		###
		### If Back button
		########################
		else {

			########################
			### Rollback Data
			### 
			########################
			RollBackSQL($id,$STEP,$pdo,$tbl); // Back STEP

			
			########################
			### Remove from hist
			###
			########################
				$hist = $DATA['hist'];

				### Find previous step
				$route = explode(";",$hist);
				$STEP = $route[count($route)-2];

				### Update new hist
				if($hist!='intro;') $hist = str_replace($STEP.";","",$hist);

				$STEPN = $ROUTING[$STEP];

			

		} // close if back button section

	########################
	### UPDATE LASTQ
	### AFTER GET NEXTSTEP
	########################

		if(!$isBACK) 
			$sql = 'UPDATE '.$tbl.' SET `lastq`="'.$STEP.'",`lastlogic`="'.$ROUTING[$STEP].'" WHERE `idqr`="'.$id.'" ;';
		else $sql = 'UPDATE '.$tbl.' SET `hist`="'.$hist.'", `lastq`="'.$STEP.'", `lastlogic`="'.$ROUTING[$STEP].'" where idqr="'.$id.'" ;';

		$result = $pdo->prepare($sql);
		$QueryResult = $result->execute();
		sendAlert($QueryResult,$relatedFile,"CANNOT Update lastq",$result->errorInfo()[2],$pdo,$email,$sms);

	########################
	### UPDATE STATUS TO BE FINISH
	### IF $COMPLETE = TRUE
	########################
		if($COMPLETE){
			$sql = 'UPDATE '.$tbl.' SET `status`="FINISH", `enddate`="'.date("YmdHis").'",`lastq`="'.$STEP.'",`lastlogic`="'.$STEPN.'" WHERE `idqr`="'.$id.'" ;';

			$result = $pdo->prepare($sql);
			$QueryResult = $result->execute();
			sendAlert($QueryResult,$relatedFile,"CANNOT Update status to be FINISH",$result->errorInfo()[2],$pdo,$email,$sms);

			session_destroy();
				
		} 
	}
	

	########################
	### Error Message
	########################
		$error = false;
		if(!empty($ERR)) $error = genError($ERR);


	########################
	### Write HTML File
	########################
	_output:
		$html = file_get_contents("theme.html");
		
		$p_title = 'Vinalia Survey';
		$p_nav = '';
		$p_desc = '';
		$p_menu = '';
		$p_otp = '';
		$p_tmodal = '';
		$refresh = ''; 
		
		$p_info = false;
		$p_act = 'index.php?id='.$id;
		$p_progress = false;
		$p_lang = false;


		####################
		### Error Message
		####################

	// ===========================
	// Insert PHP Variables in HTML
	// ===========================
		$param = [
			'<!-- ## === Progress === ## -->' => genProgressBar(),
			'<!-- ## === Navigator === ## -->' => $p_nav,
			'<!-- ## === Refresh === ## -->' => $refresh,
			'<!-- ## === Title === ## -->' => $p_title,
			'<!-- ## === Language === ## -->' => $p_lang,
			'<!-- ## === Menu === ## -->' => $p_menu,
			'<!-- ## === Description === ## -->' => false,
			'<!-- ## === Error MSG === ## -->' => $error,
			'<!-- ## === Content === ## -->' => gendata($DATA,$STEP),
			'<!-- ## === Action === ## -->' => $p_act
		];

		$html = strtr($html, $param);

	########################
	### RETURN WHOLE HTML
	########################
		//ob_clean();		
		echo $html;

		//echo 'Total execution time in seconds: ' . (microtime(true) - $time_start);
		$pdo = null;
