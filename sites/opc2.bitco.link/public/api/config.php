<?php

	###################
	### SYSTEM SETTING
	###################
	$_CONFIG['system'] = true;
	$_CONFIG['expired'] = 99;

	### Test Purpose (turn to false to test without checking id)
		$_CONFIG['checkid'] = true;
	### Auto Generate random id
		$_CONFIG['autogen'] = false;
	### Use Sestion table
		$_CONFIG['sestbl'] = false;
	### write log file
		$_CONFIG['write'] = false;
	### Allow to ignore Complete status
		$_CONFIG['allowstep'] = [''];
	### Terminate if these calloutcome
		$_CONFIG['CallOutcome_terminate'] = [11,12,13,21,22,23,31,32,33,41,42,43,44,45,51,52,53,61,62,63,64];

	### Ignore dealer to check quota
		$_CONFIG['dealer'] = [];
	### Back button value
		$_CONFIG['back'] = ['Back', 'ย้อนกลับ'];

		$_CONFIG['p_title'] = '';				### Survey Title
		$_CONFIG['p_rd'] = []; 					### ['dbcolumn = key' => 'label => value']
		$_CONFIG['p_nav'] = ''; 				### Survey Logo

	###################
	### DB SETTING
	###################

	$_CONFIG['whitelist'] = array(
		'127.0.0.1',
		'::1'
	);
	### Check if not localhost
	if(!in_array($_SERVER['REMOTE_ADDR'], $_CONFIG['whitelist'])){

		$_CONFIG['host'] = "localhost";
		$_CONFIG['db'] = "dw_cressida";
		$_CONFIG['usr'] = "dw_spy";
		$_CONFIG['psw'] = "31l1SwBuXvyqYLdKwUZLbC1qCTJw0C8e";
	}
	
	###################
	### TABLE SETTING
	###################

	$_CONFIG['tb'] = "survey_";
	$_CONFIG['tb1'] = $_CONFIG['tb'].'main';

	############################
	// System Content FIle
	############################
	// MAIN --------------
	$_CONFIG['metadata1'] = 'metadata1.php';
	$_CONFIG['routing1'] = 'routing1.php';
	$_CONFIG['label1'] = 'label1.php';
	$_CONFIG['task1'] = 'task1.php';

	
	###################
	### API Setting
	###################

	$domain = 'https://'.$_SERVER['HTTP_HOST'];

	$_API['fwprogress'] = $domain.'/api/fwprogress/';

	