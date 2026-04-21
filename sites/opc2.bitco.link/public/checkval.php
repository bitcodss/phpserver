<?php

//error_reporting(E_ALL);
//ini_set('display_errors', TRUE);
//ini_set('display_startup_errors', TRUE);
date_default_timezone_set('Asia/Bangkok');

if(empty($_GET['idqr'])) {
	echo "No access";
	exit;
}

############################
### PHP Header
############################

	header('Content-Type:text/html; charset=utf-8');
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Cache-Control: no-cache");
	header("Pragma: no-cache");

	require('phpfunc.php');
	
	// Notification
	require('api/apifunc.php');
	require('api/config.php');


##########################
### mySQL CONNECT
##########################
	$pdo = new PDO('mysql:dbname='.$_CONFIG['db'].';host='.$_CONFIG['host'].';charset=utf8', $_CONFIG['usr'], $_CONFIG['psw']) 
	or die('Cannot connect mySQL');

	$query = $pdo->prepare('SELECT * FROM survey_main x JOIN survey_main2 y ON x.idqr=y.idqr WHERE x.idqr=:idqr');
	$result = $query->execute(array(':idqr' => $_GET['idqr']));

	if($query->rowCount() < 1){
		echo "No data";
		exit;
	}

	$DATA = $query->fetch(PDO::FETCH_ASSOC);

	foreach($DATA as $key => $val){
		$arr[] = '<tr><td>'.$key.'</td><td>'.$val.'</td></tr>';
	}
	$array = implode("", $arr);
	$html = file_get_contents('idauthen.html');
	$output = '
	<script>
		$(document).ready(function(){
			$("#search").on("keyup", function(){
				var tbody = $("#result > tbody")
				var txt = $(this).val()
				if($(this).val() != ""){
					tbody.find("td").each(function(){
						if($(this).text().indexOf(txt) < 0) $(this).parent().hide()
					});
				} else {
					tbody.children("tr").show()
				}
			});
		});
	</script>
	<input type="text" id="search" />
	<table class="table table-striped table-hover table-firstrow table-firstcol" id="result">
		<thead>
			<tr>
				<th>Column</td><th>Value</td>
			</tr>
		</thead>
		<tbody>
			'.$array.'
		</tbody>
	</table>';

	$html = str_replace("{#content}", $output, $html);
	echo $html;

	