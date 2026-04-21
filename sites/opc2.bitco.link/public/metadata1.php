<?php

############################
### PHP HEADER
############################




function gendata($DATA,$STEP){

	############################
	### PHP INCLUDE
	############################
	global $label;
	global $desc;
	global $list;
	global $event;
	global $ROUTING;
	global $ROUTCOMP;
	global $ROUTAPPOINT;
	global $_API;

	############################
	### SET UP KEY VARIABLES
	############################
		$content = '';
		$output = '';
		$ID = $DATA['idqr'];

	#####################################
	###
	### Convert POST data to SESSION data
	### for temporary use
	#####################################
		### UNSET $_SESSION, CLEAR DATA
		if(is_array($DATA)){
			foreach ($DATA as $key => $val) {			
				if(!isset($_SESSION[$key]) && !is_numeric($key)) $_SESSION[$key] = $val;
			}
		}

	############################
	### CONTENT
	############################
		$choice = [101,102,103,104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,119,120,121,122,123,124,125,126,127,128,129,130,131,132,133,134,135,136,137,138,139,140,141,142,143,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,100,201,202,203,204,205,206,207,208,209,210,211,212,213,214,215,216,217,218,219,220,221,222,223,200,301,302,303,304,305,306,307,308,309,310,311,312,313,314,315,316,317,318,319,320,321,322,323,324,325,326,327,328,329,330,331,332,333,334,335,336,337,338,339,340,341,342,343,344,345,346,347,300,401,402,403,404,405,406,407,408,409,410,411,412,413,414,415,416,417,418,419,420,421,422,423,424,425,426,427,428,429,430,431,432,433,434,435,436,437,438,400,601,602,603,604,605,606,607,608,609,610,611,612,613,614,615,616,617,618,619,620,621,622,623,624,625,626,600,701,702,703,704,705,706,707,700,801,802,803,804,805,806,807,800,901,902,903,904,905,906,907,908,909,910,911,912,913,914,915,916,917,918,919,920,921,922,923,900,1001,1002,1003,1004,1005,1006,1007,1008,1009,1010,1011,1012,1013,1014,1015,1016,1017,1018,1019,1020,1021,1022,1023,1024,1025,1026,1000];
	
		if($STEP=='intro'){
		$content .= '
				'.genQ($label['intro']).'
				
				'.genQ($label['rd_name']).'
				'.geninput('rd_name',false,'input').'
				
				'.genQ($label['rd_tel']).'
				'.geninput('rd_tel',false,'input').'
			';
		}

		// --------------------
		// Section Screener
		// --------------------
		if($STEP=='s1'){
			$content .= '
				'.genQ($label['s1']).'
				'.geninput('s1',$list['s1'],'radio').'
				
				'.genQ($label['s2']).'
				'.geninput('s2',$list['s2'],'radio').'
			';
		}
		if($STEP=='s3'){
			$content .= '
				'.genQ($label['s3']).'
				'.geninput('s3',$list['s3'],'radio').'
			';
		}
		if($STEP=='s4'){
			$content .= '
				'.genQ($label['s4']).'
				'.geninput('s4',$list['s4'],'radio').'
			';
		}
		if($STEP=='s5'){
			$content .= '
				'.genQ($label['s5']).'
				'.geninput('s5',$list['s5'],'radio').'
				
				'.genQ($label['s6']).'
				'.geninput('s6',$list['s6'],'radio').'
			';
		}
		if($STEP=='s7'){
			$content .= '
				'.genQ($label['s7']).'
				'.geninput('s7',$list['s7'],'radio').'
			';
		}
		if($STEP=='s8'){
			$content .= '
				'.genQ($label['s8']).'
				'.geninput('s8',$list['s8'],'radio').'
			';
		}
		if($STEP=='s9a'){
			$content .= '
				'.genQ($label['s9a']).'
			';
			foreach([1,2,3,4,6,7,8,9,10,99] as $k => $v){
				$content .= '
					'.genQ($label['s9a'.$v]).'
					'.geninput('s9a',$list['s9a'.$v],'checkbox').'
				';
			}
		}
		if($STEP=='s9b'){
			// Replace Piping
			foreach([100,200,300,400,600,700,800,900,1000] as $k => $v){
				$list['s9'] = strtr($list['s9'],["{#s9ar{$v}_oth}" => $DATA["s9ar{$v}_oth"]]);
			}
			$list['s9'] = remainRes($list['s9'],genMAresp('s9a',$DATA,$choice),false);
			$content .= '
				'.genQ($label['s9b']).'
				'.geninput('s9b',$list['s9'],'checkbox').'
			';
		}
		if($STEP=='s9c'){
			// Replace Piping
			foreach([100,200,300,400,600,700,800,900,1000] as $k => $v){
				$list['s9'] = strtr($list['s9'],["{#s9ar{$v}_oth}" => $DATA["s9ar{$v}_oth"]]);
			}
			$list['s9'] = remainRes($list['s9'],genMAresp('s9b',$DATA,$choice),false);
			$content .= '
				'.genQ($label['s9c']).'
				'.geninput('s9c',$list['s9'],'radio').'
			';
		}
		if($STEP=='s9d'){
			$content .= '
				'.genQ($label['s9d']).'
			';
			foreach([1,2,3,4,6,7,8,9,10,99] as $k => $v){
				$content .= '
					'.genQ($label['s9a'.$v]).'
					'.geninput('s9d',$list['s9a'.$v],'checkbox').'
				';
			}
		}
		if($STEP=='s10'){
			$content .= '
				'.genQ($label['s10']).'
				'.geninput('s10',$list['s10'],'radio').'
				
				'.genQ($label['s11']).'
				'.geninput('s11',$list['s11'],'radio').'
			';
		}
		if($STEP=='s12'){
			$content .= '
				'.genQ($label['s12']).'
				'.geninput('s12',$list['s12'],'radio').'
			';
		}
		if($STEP=='quota'){
			$list['QUOTA_GENDER'] = remainResSA($list['QUOTA_GENDER'],$DATA['QUOTA_GENDER']);
			$list['QUOTA_AGE'] = remainResSA($list['QUOTA_AGE'],$DATA['QUOTA_AGE']);
			$list['QUOTA_SES'] = remainResSA($list['QUOTA_SES'],$DATA['QUOTA_SES']);
			$list['QUOTA_GBKK'] = remainResSA($list['QUOTA_GBKK'],$DATA['QUOTA_GBKK']);
			$list['QUOTA_BUMO'] = remainResSA($list['QUOTA_BUMO'],$DATA['QUOTA_BUMO']);

			$content .= '
				'.genQ($label['QUOTA_GENDER']).'
				'.geninput('QUOTA_GENDER',$list['QUOTA_GENDER'],'radio').'

				'.genQ($label['QUOTA_AGE']).'
				'.geninput('QUOTA_AGE',$list['QUOTA_AGE'],'radio').'

				'.genQ($label['QUOTA_SES']).'
				'.geninput('QUOTA_SES',$list['QUOTA_SES'],'radio').'

				'.genQ($label['QUOTA_GBKK']).'
				'.geninput('QUOTA_GBKK',$list['QUOTA_GBKK'],'radio').'
				
				'.genQ($label['QUOTA_BUMO']).'
				'.geninput('QUOTA_BUMO',$list['QUOTA_BUMO'],'radio').'
			';
		}
		// --------------------
		// Section Main
		// --------------------
		
		if($STEP=='l0'){
			$content .= '
				'.genQ($label['l0']).'
				'.geninput('l0',$list['l0_'.$DATA['cell']],'radio').'
			';
		}
		foreach([951,968,972,937] as $k => $v){
			if($STEP=="c1_{$v}"){
				$x = $v;
				$content .= '
					'.genH($label['c0'].$x,'h1').'

					'.genQ($label['c1']).'
					'.genscale("c1_{$x}",$list['like']).'
					
					'.genQ($label['c2']).'
					'.genscale("c2_{$x}",$list['buy']).'
					
					'.genQ($label['c3']).'
				';

				foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
					$content .= '
						'.genQ($label["c3_{$v}"]).'
						'.genscale("c3_{$v}_{$x}",$list['agree']).'
					';
				}
				
				$content .= '
					'.genQ($label['c4']).'
					'.genscale("c4_{$x}",$list['c4']).'
					
					'.genQ($label['c5']).'
					'.genscale("c5_{$x}",$list['c5']).'
					
					'.genQ($label['c7']).'
					'.genscale("c7_{$x}",$list['c7']).'
					
					'.genQ($label['c10']).'
					'.geninput("c10_{$x}",$list['c10'],'radio').'
				';
			}
		}
		if($STEP=='c11'){
			$content .= '
				'.genQ($label['c11']).'
				'.genrankgrid('c11a',$list['c11'],$list['c11a'],'อันดับ').'
			';
		}

		// -----------------------------
		// - Section 1 -
		// -----------------------------
		foreach([912,996,416,442,765,773,729,513,591] as $k => $v){
			if($STEP=="a1_{$v}"){
				$x = $v;
				$content .= '
					'.genH($label["a0a_{$x}"],'h1').'

					'.genQ($label['a0']).'

					'.genQ($label['a1']).'
					'.genscale("a1_{$x}",$list['like']).'

					'.genQ($label['a2']).'
					'.genscale("a2_{$x}",$list['buy']).'
					
					'.genQ($label['a3']).'				
				';
				foreach([1,2,3,4,5,6,7,8,9,10,11,12,13,14] as $k => $v){
					$content .= '
						'.genQ($label["a3_p{$v}"]).'	
						<div class="row">
							<div class="col-xs-6">
								<center><b>คะแนนความชอบ</b></center>
								'.genscale("a3_p{$v}_{$x}",$list['a3']).'
							</div>
							<div class="col-xs-6">
								<center><b>ระดับความพึงพอใจ</b></center>
								'.(!empty($list["a3a_p{$v}"]) ? genscale("a3a_p{$v}_{$x}",$list["a3a_p{$v}"],"กำลังดี") : false).'
							</div>
						</div>
					';
				}

				$content .= '
					'.genQ($label["a4"]).'
					'.geninput("a4_{$x}",$list['a4'],"checkbox").'
				';
			}
		}

		// -----------------------------
		// - Section 2 -
		// -----------------------------
		foreach(['912996','416442','765773729','513591'] as $k => $v){
			if($STEP=="a5_{$v}"){
				$x = $v;

				$content .= '
					'.genH($label["a5_{$x}"],'h1').'

					'.genQ($label['a5x']).'

					'.genQ($label['a5']).'
					'.genrankgrid("a5a{$x}",$list["a5_{$x}"],$list["a5a_{$x}"],'อันดับ').'
				';
			}
		}

		// -----------------------------
		// - Section 3 -
		// -----------------------------
		foreach(['912937','996937','416968','442968','765972','773972','729972','513951','591951'] as $k => $v){
			if($STEP=="b1_{$v}"){
				$x = $v;

				$b9 = ' hidden';
				if(containsAny($_SESSION["b8_{$x}"],[1,2,3,4,5,6,7,8,9,10,11,12,13,14])) $b9 = false;

				$b11 = ' hidden';
				if(containsAny($_SESSION["b10_{$x}"],[2,3,4])) $b11 = false;

				$content .= '
					'.genH($label["b0_{$x}"],'h1').'

					'.genQ($label['b0']).'

					'.genQ($label['b1']).'
					'.genscale("b1_{$x}",$list['like']).'
					
					'.genQ($label['b2']).'
					'.genscale("b2_{$x}",$list['buy']).'
					
					'.genQ($label['b3']).'
				';

				foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
					$content .= '
						'.genQ($label["b3_{$v}"]).'
						'.genscale("b3_{$v}_{$x}",$list['agree']).'
					';
				}
				
				$content .= '
					'.genQ($label['b4']).'
					'.genscale("b4_{$x}",$list['b4']).'
					
					'.genQ($label['b5']).'
					'.genscale("b5_{$x}",$list['b5']).'
					
					'.genQ($label['b6']).'
					'.genscale("b6_{$x}",$list['b6']).'
					
					'.genQ($label['b7']).'
					'.genscale("b7_{$x}",$list['b7']).'
						
					'.genQ($label['b8']).'
					'.geninput("b8_{$x}",$list['b8'],'radio').'
						
					<script>
						$(function(){
							$("div[data-form=b8_'.$x.']").click(function(){
								var n = $(this).attr("data-value")
								if(n < 15){
									$(".b9").removeClass("hidden");
								} else {
									$(".b9").addClass("hidden");
									$("input[name=b9_'.$x.'").val("")
								}
							});
						});
					</script>
					<div class="b9'.$b9.'">
						'.genQ($label['b9']).'
						'.geninput("b9_{$x}",$list['b9'],'integer').'
					</div>
						
					'.genQ($label['b10']).'
					'.geninput("b10_{$x}",$list['b10'],'radio').'
						
					<script>
						$(function(){
							$("div[data-form=b10_'.$x.']").click(function(){
								var n = $(this).attr("data-value")
								if(n > 1){
									$(".b11").removeClass("hidden");
								} else {
									$(".b11").addClass("hidden");
									$("[name=b11_'.$x.'").val("")
								}
							});
						});
					</script>
					<div class="b11'.$b11.'">
						'.genQ($label['b11']).'
						'.geninput("b11_{$x}",$list['b11'],'text').'
					</div>
				';
			}
		}
		
		// -----------------------------
		// - Section 4 -
		// -----------------------------
		foreach(['912996','416442','765773729','513591'] as $k => $v){
			if($STEP=="b12_{$v}"){
				$x = $v;

				$content .= '
					'.genH($label["b12_{$v}"],'h1').'

					'.genQ($label['b1x']).'

					'.genQ($label["b12i_{$v}"]).'
					'.genrankgrid("b12a{$x}",$list["b12_{$x}"],$list["b12a_{$x}"],'อันดับ').'
			
					'.genQ($label['b14']).'
					'.geninput("b14_{$x}",$list["b12_{$x}"],'radio').'

					'.genQ($label['b15']).'
					'.geninput("b15_{$x}",false,'text').'
				';
			}
		}


		// -----------------------------
		// - Section Final -
		// -----------------------------
		foreach([202,205] as $k => $v){
			if($STEP=="fa_{$v}"){
				$x = $v;

				$content .= '
					'.genH($label["fa_{$v}"],'h1').'

					'.genQ($label["fax_{$v}"]).'
			
					'.genQ($label['fa1']).'
					'.genscale("a1_{$x}",$list['like']).'

					'.genQ($label['fa2']).'
					'.genscale("a2_{$x}",$list['buy']).'
			
					'.genQ($label["fb1_{$v}"]).'
					'.genscale("b1_{$x}",$list['like']).'

					'.genQ($label["fb2_{$v}"]).'
					'.genscale("b2_{$x}",$list['buy']).'
				';
			}
		}

		// -----------------------------
		// - Section Final 2 -
		// -----------------------------
		if($STEP=="z1"){
			// Find 4 winners in previous sections
			$x = explode(';',$list['z1']);
			$list['winner'] = [];
			foreach($x as $kk => $vv){
				$u = explode(':',$vv);
				if($DATA['b12a9129961']==trim($u[0])) $list['winner'][] = $vv;
				if($DATA['b12a4164421']==trim($u[0])) $list['winner'][] = $vv;
				if($DATA['b12a7657737291']==trim($u[0])) $list['winner'][] = $vv;
				if($DATA['b12a5135911']==trim($u[0])) $list['winner'][] = $vv;
				if(containsAny(trim($u[0]),[202,205])) $list['winner'][] = $vv;
			}

			$content .= '
				'.genH($label["z"],'h1').'

				'.genQ($label['zx']).'

				'.genQ($label["z1"]).'
				'.genrankgrid("z1a",implode(';',$list["winner"]),$list["z1a"],'อันดับ').'
			';
		}
		if($STEP=="z7"){
			$DATA['z1a1'] = getLabel($DATA['z1a1'],$list['z7']);
			$DATA['z1a6'] = getLabel($DATA['z1a6'],$list['z7']);
			$content .= '
				'.genH($label["z"],'h1').'

				'.genQ($label["z7"],$DATA).'
				'.geninput("z7",false,'text').'

				'.genQ($label["z8"],$DATA).'
				'.geninput("z8",false,'text').'

				'.genQ($label["z9"]).'
				'.geninput("z9",$list['z9'],'radio').'
			';
		}



	if($STEP=="thank"){
		$content .= '
			<div class="question" align="center">
				<img src="asset/logo.png" style="height:100px;" /><br/>
				'.genQ($label['thank']).'
			</div>
			<div style="height:25px">&nbsp;</div>
		';
	}
	if($STEP=="TERM"){
		$content .= '
			<div class="question" align="center">
				<span class="question">
					'.genH($event['terminated']['th'],'h2').'
				</span>
			</div>
			<div style="height:100px">&nbsp;</div>
		';
	}
	if($STEP=="TERMACT"){
		$content .= '
			<style>.button-nav, .survey-title, #header-wrapper, .rdinfo-panel {display:none !important;} #wrapper {background:#555 !important;}</style>
			<div class="question" align="center">
				<div class="enddialog">
					<img src="asset/thankyou.gif" class="img img-responsive" />
					'.genH($event['terminated']['th'],'h2').'
				</div>
			</div>
			<div style="height:100px">&nbsp;</div>
		';
	}



	//##################
	//
	// PHP GENERATE CONTENT
	//##################

	// Define overall steps for progress bar	
	$STEPN = $ROUTING[$STEP];
	$MAX = $ROUTING['MAX'];
	if($STEPN > 1) $CSTEP = $STEPN/$MAX * 100;
		else $CSTEP = 0;

	// Define Previous Q Name
		$HIST = explode(";",$DATA['hist']);
		$LASTQ = $HIST[count($HIST)-2];

	// ProgressBar color
	$pgcls = 'yl';
	if(number_format($CSTEP)>=80 && number_format($CSTEP)<=99) $pgcls = 'g1';
	elseif (number_format($CSTEP)>=100) $pgcls = 'g1';

	$beginform = '
		<script type="text/javascript">
			$(document).ready(function(){
				$("#progressbar").css("width", "'.number_format($CSTEP).'%").addClass("'.$pgcls.'")
				$("#progressnum").text("'.number_format($CSTEP,0).'%").css("marginLeft", "'.number_format(max($CSTEP-8,10)).'%")
			});
		</script>
		<script src="js/basejs-v3.js?v='.date('YmdHis').'" type="text/javascript"></script>
		<div style="position:relative;top:0px;display:block;clear:both;"></div>
		<div style="clear:both"></div>
		<div class="content" style="position:relative;top:0px;">
	';

	$prevbtn = '<input type="submit" class="btn btn-danger btn-lg" Value="Back" id="back" />';
	if($STEPN < $MAX-1) $nextbtn = '<input type="submit" class="btn btn-primary btn-lg" Value="Next" id="submit" />';
	if($STEPN == $MAX-1) $nextbtn = '<input type="submit" class="btn btn-primary btn-lg" Value="Submit" id="submit" />';
	$endform = '
				<div class="hidden">
					<input type="hidden" value="'.$ID.'" name="qid" />
					<input type="hidden" value="'.$STEP.'" name="qstep" />
					<input type="hidden" name="button" />
					<input type="hidden" name="ssid" />
					<input type="hidden" name="machine" />
				</div>
				<div class="button-nav" align="left">
					<div class="row">
				';

		if($STEPN == 1) $endform .= '	
						<div class="col-xs-6">&nbsp;</div>
						<div class="col-xs-6">
							<div class="btn-next">'.$nextbtn.'</div>
						</div>
					</div>
				</div>
			';
		if($STEPN === 0 || ($STEPN > 1 && $STEPN != $MAX)) $endform .= '
						<div class="col-xs-6">
							<div class="btn-prev">'.$prevbtn.'</div>
						</div>
						<div class="col-xs-6">
							<div class="btn-next">'.$nextbtn.'</div>
						</div>					
					</div>
				</div>
			';
		if($STEPN == $MAX) $endform .= '
					</div>
				</div>
			';
			$endform .= '
		</div>
	';


	//##################
	//
	// PHP FOOTER
	//##################

	// attach beginning of form
		$output .= $beginform;

	// attach content of form
		$output .= $content;

	// attach the end of form
		$output .= $endform;

	//##################
	//
	// return variable
	//##################
		return $output;
}

