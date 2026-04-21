<?php

############################
### PHP Header
############################

	ob_start();
	session_start();

	header('Content-Type:text/html; charset=utf-8');
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Cache-Control: no-cache");
	header("Pragma: no-cache");	

	if(isset($_GET["token"])) $token = $_GET["token"];
	if(isset($_GET["type"])) $type = $_GET["type"];


############################
### PHP INCLUDE
############################
	include('api/config.php');
	include('api/apifunc.php');
	include('api/class.email.php');


##########################
### Validate Exit
##########################
	$arr = ['token','type'];

	if(!empty($arr)){
		foreach($arr as $k => $v){
			if(!empty($_GET[$v])) $$v = $_GET[$v];
			else {
				header( "location: 404.html" );
				exit;
			}		
		}
	}
	if($token != "rDhs2NpYw2nrTyzq") {
		header( "location: 404.html" );
		exit;
	}
	
	
	############################
	### API Request
	############################	
		$array = ['type' => $type];
		$res = sendCurl($_API['fwprogress'],$token,$array);
		$data = json_decode($res, true);


	############################
	### Function
	############################

	function genFWProgress($data){
		$table = ['table0','table1','table2','table3','table4','table5'];
		$graph = ['graph1'];

		$header = ['', 'โควต้า', 'จำนวน', 'คงเหลือ', '%'];

		$eTable = [];
		$eGraph = [];
		foreach($table as $k => $v){
			$eTable[] = gengrid($data['data'][$v]['table'], $header, $data['data'][$v]['title']);
		}
		foreach($graph as $k => $v){
			$eGraph[] = genDaily($data['data'][$v]['table'], $data['data'][$v]['title']);
		}
		
		$output = "
			<script type='text/javascript' src='https://cdn.fusioncharts.com/fusioncharts/latest/fusioncharts.js'></script>
			<script type='text/javascript' src='https://cdn.fusioncharts.com/fusioncharts/latest/themes/fusioncharts.theme.fusion.js'></script>

			".genheader()."
			".implode('', $eTable)."
			".implode('', $eGraph)."
			".DLbutton()."
		";

		return $output;
	}


	function genheader(){
		global $type;
		$TYPE = strtoupper($type);
		
		$output = "<div id='proj-title'>FW Progress - {$TYPE}</div>";

		return $output;
	}
	function gengrid($data,$header,$title){
		$th = [];
		$tr = [];
		$td = [];

		// Column width
		$w = floor(70/count($header));
		foreach($header as $k => $v){
			if(empty($th)) $th[] = "<th>{$v}</th>";
			else $th[] = "<th scope='col' style='width:{$w}%'>{$v}</th>";
		}
		foreach($data as $k => $v){
			$td = [];
			foreach($v as $k1 => $v1){
				$td[] = "<td>{$v1}</td>";
			}
			$tr[] = '<tr>'.implode('', $td).'</tr>';
		}
		
		$output = "
			<div class='table-responsive col-xs-12'>
				<table class='table table-hover table-condensed'>
					<caption>{$title}</caption>
					<thead class='bg-grey'>
						<tr>".implode('', $th)."</tr>
					</thead>
					<tbody>
						".implode('', $tr)."
					</tbody>
				</table>
			</div>
		";
		return $output;
	}
	function genDaily($data,$title){	  
		$row = [];
		foreach($data as $k => $v){
			$row[] = "['{$k}', {$v}]";
		}

		$output = '
			<script>
				var timesData = {
					fw : [
						'.implode(',', $row).'
					]
				}
				var schema = [{
					"name": "Time",
					"type": "date",
					"format": "%Y-%m-%d"
				}, {
					"name": "N",
					"type": "number"
				}];
		
				var dataStore = new FusionCharts.DataStore();
				var dataSource = {
					fw : {
						chart: {
							showlegend: 0
						},
						yAxis:
						[
							{
								plot: [
									{
										value: "N",
										type: "column"
									}
								]
							}
						],
						tooltip: {        
							style: {
								text: {
								"font-size": "16px"
								}
							}
						}
					}
				};
			
				dataSource["fw"].data = dataStore.createDataTable(timesData["fw"], schema);
			
				var chart1Config = {
					type: "timeseries",
					renderAt: "c1",
					width: "100%",
					height: "500",
					dataSource: dataSource["fw"]
				};

				
				FusionCharts.ready(function(){
					var fusioncharts = new FusionCharts(chart1Config).render();
				});
			</script>
			<table class="table">
				<caption>'.$title.'</caption>
			</table>
			<div id="c1"></div>
		';
		return $output;
	}
	function DLbutton(){
		global $type;
		$output = "
			<script>
				$(function(){
					var button = \"<a class='btn btn-success btn-rounded btn-lg btn-block' href='./xls/exportxls.php?token=1D7A6E11085351298CC890625C16D68ABD6C85125C513FDE614B0B49533BF2A7&section=rawdata'><i class='fa fa-download'></i> Download Rawdata [.xlsx]</a>\"
					$('#progress').html(button)
				});
			</script>
			
		";
		
		return $output;
	}




	########################
	### Write HTML File
	########################
	$html = file_get_contents("theme.html");


	// ===========================
	// Main Body
	// ===========================
		$array = [
			'<!-- ## === Title === ## -->' => $_CONFIG['p_title'],
			'<!-- ## === Navigator === ## -->' => '<img src="asset/logo.png" height="80" />',
			'<!-- ## === Content === ## -->' => genFWProgress($data)
		];
		$html = strtr($html, $array);


########################
### RETURN WHOLE HTML
########################
	echo $html;


	$pdo = null;