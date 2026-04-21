<?php

#######################
### PHP HEADER
#######################


#######################
### PHP INCLUDE
#######################



#######################
### HTML HEADER
#######################



#######################
### HTML Footer
#######################


#######################
### HTML FUNCTION GENERATOR
#######################


function genError($err){
	if($err != ''){
		$output = '
			<div class="ui-widget" style="position:relative;">
				<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;">
					<span>
						<i class="fas fa-star-exclamation" aria-hidden="true"></i>
						<span>'.$err.'</span>
					</span>
				</div>
			</div>
		';
	} else $output = false;
	return $output;
}

### Generate body footer
function genfooter($group){

	$output = '
		&copy; Copyright 2019 :
		<a href="http://www.nikkei-rc.com/" target="_blank">Nikkei Research & Consulting Ltd.</a> - All rights reserved.
	';
	return $output;
}

### Generate Question
function genQ($text, $DATA = false){
	if(empty($DATA)) global $DATA;
	
	$output = '
		<div class="question">
			<span class="question">
				'.replacePipe($DATA, $text).'
			</span>
		</div>
	';
	return $output;
}
### Generate Question
function genText($text, $DATA = false){
	if(empty($DATA)) global $DATA;
	
	$output = '
		<div class="question">
			<span class="question">
				'.$text.'
			</span>
		</div>
	';
	return $output;
}
function genTran($txt){
	$output = '';
	if(!is_array($txt)) {
		$output = $txt;
	} else {
		if(isset($txt['th'])) $output .= '<tha>'.$txt['th'].'</tha>';
		if(isset($txt['en'])) $output .= '<eng>'.$txt['en'].'</eng>';
		if(isset($txt['jp'])) $output .= '<jpn>'.$txt['jp'].'</jpn>';
	}

	return $output;
}

### Generate H1
function genH($text,$lv){
	$output = '
			<div class="h1">
				<'.$lv.'>'.genTran($text).'</'.$lv.'>
			</div>
	';
	return $output;
}
function gentbinput($label,$name,$data,$type){
	$result .= '
	<div class="tbinput">
		<div class="row">
			<div class="col-xs-6">'.$label.'</div>
			<div class="col-xs-6">'.geninput($name,$data,$type).'</div>
		</div>
	</div>';

	return $result;
}

### Generate Input
function geninput($name,$data,$type){
	$result = '<div class="form-item">';
	$other = '';
	$u1 = explode(";", $data);

	global $DATA;

	$bullet = false;
	$bulletlabel = '';

	for($i=0;$i<count($u1);$i++){
		$n = str_replace("[]", "", $name);

		### geninput("q1","1:male;2:female","radio")
		if($bullet) $bulletlabel = trim($u2[0]).". ";

		if($type=="radio"){

			$u2 = explode(":", $u1[$i]);
			$u2[1] = replacePipe($DATA, $u2[1]);

			//############################
			// Get data from session variable
			//############################
				$active = false;
				$value = false;

				$othhide = ' hidden';
				if(isset($_SESSION[$n])) {
					if($u2[0]==$_SESSION[$n]) {
						$active = 'active';
						$value = ' checked="true"';
						$othhide = false;
					}
				}

				if(!empty($_SESSION[$n.trim($u2[0]).'_oth'])) $othvalue = $_SESSION[$n.trim($u2[0]).'_oth'];
				else $othvalue = false;
			//############################


			if(strpos($u2[1],"[oth]") > 0) {
				// add input
				$other = '
				'.$script.'
				<div class="other'.$othhide.'" id="'.$n.trim($u2[0]).'_odiv">
					<div class="form-group">
						<label>
							<input type="text" class="form-control other" id="'.$n.trim($u2[0]).'_oth" name="'.$n.trim($u2[0]).'_oth" value="'.$othvalue.'" placeholder="specify..." />
						</label>
					</div>
				</div>';
				$u2[1] = str_replace("[oth]","",$u2[1]);
			} else $other = false;

			$result .= '
				<div class="'.$active.' form-radio" col-md-12 data-form="'.$n.'" data-value="'.trim($u2[0]).'" rel="'.$n.trim($u2[0]).'">
					<label for="'.$n.trim($u2[0]).'">
						<input type="radio" class="radio" id="'.$n.trim($u2[0]).'" name="'.$name.'" value="'.trim($u2[0]).'" '.$value.'/>
						<div class="form-fa">
							<i class="fas fa-check-circle form-check" aria-hidden="true"></i>
							<i class="far fa-circle form-uncheck" aria-hidden="true"></i>
						</div>
						<span>'.$bulletlabel.$u2[1].'</span>
					</label>
					'.$other.'
				</div>
			';
		}

		### geninput("q1","1:Q1 xxx;2:Q2 xxx;3:Q3 xxx","checkbox")
		if($type=="checkbox"){
			$u2 = explode(":", $u1[$i]);
			$u2[1] = replacePipe($DATA, $u2[1]);

			//############################
			// Get data from session variable
			//############################
				$active = false;
				$value = false;
				$value0 = false;
				$othhide = " hidden";
				if(isset($_SESSION[$n.'r'.trim($u2[0])])) {
					if($_SESSION[$n.'r'.trim($u2[0])]==1) {
						$active = ' active';
						$value = ' checked="true"';
						$othhide = false;
					} elseif($_SESSION[$n.'r'.trim($u2[0])]==='0') {
						$value0 = ' checked="true"';
					}
				}

				if($_SESSION[$n.'r'.trim($u2[0]).'_oth']!='') $othvalue = $_SESSION[$n.'r'.trim($u2[0]).'_oth'];
				else $othvalue = false;

			//############################

			if(strpos($u2[1],"[oth]") > 0) {
				// add input
				$other = '
				'.$script.'
				<div class="other'.$othhide.'" class="" rel="off" id="'.$n.'r'.trim($u2[0]).'_odiv">
					<div class="form-group">
						<input type="text" class="form-control other" id="'.$n.'r'.trim($u2[0]).'_oth" name="'.$n.'r'.trim($u2[0]).'_oth" value="'.$othvalue.'" placeholder="specify..." />
					</div>
				</div>';
				$u2[1] = str_replace("[oth]","",$u2[1]);
			} else $other = false;

			
			//############################
			// Exclusive
			$inexclusive = false;
			$exclusive = false;
			if(strpos($data,"[exc]") > 0) $inexclusive = " inexclusive";
			if(strpos($u2[1],"[exc]") > 0) {
				$exclusive = " exclusive";
				$inexclusive = false;
			}
			$u2[1] = str_replace("[exc]","",$u2[1]);

			
			// row
			$dprefix = false;
			$dsuffix = false;
			if($i % 2 == 0) $dprefix = '<div class="row">';
			if($i % 2 > 0 || $i == count($u1)-1) $dsuffix = '</div>';
			$result .= '
				'.$dprefix.'
					<div class="'.$active.' form-checkbox col-md-12 '.$inexclusive.$exclusive.'" data-form="'.$n.'" data-value="'.trim($u2[0]).'" rel="'.$n.'r'.trim($u2[0]).'">
						<label for="'.$n.'r'.trim($u2[0]).'">
							<input class="hidden" type="checkbox" id="'.$n.'r'.trim($u2[0]).'n" name="'.$n.'r'.trim($u2[0]).'" value="0" '.$value0.' />
							<input type="checkbox" id="'.$n.'r'.trim($u2[0]).'" name="'.$n.'r'.trim($u2[0]).'" value="1" '.$value.' />
							<div class="form-fa">
								<i class="fas fa-check-square form-check" aria-hidden="true"></i>
								<i class="far fa-square form-uncheck" aria-hidden="true"></i>
							</div>
							<span>'.$bulletlabel.$u2[1].'</span>
							'.$other.'
						</label>
					</div>
				'.$dsuffix.'
			';
		}

		### geninput("q1","20:3","text")
		if($type=="text"){

			//############################
			// Get data from session variable
			//############################

			if(isset($_SESSION[$n])) $value = $_SESSION[$n];
			else $value = false;
			//############################

			$result .= '
				<div class="clear form-group">
					<textarea id="'.$n.'" name="'.$name.'" class="form-control">'.$value.'</textarea>
				</div>
			';
		}

		if($type=="clock"){

			//############################
			// Get data from session variable
			//############################

			if(isset($_SESSION[$n])) $value = $_SESSION[$n];
			else $value = false;

			//############################

			$result .= '
				<div class="input-group" data-autoclose="true">
					<input type="text" id="'.$n.'" name="'.$name.'" class="form-control timepicker" value="'.$value.'" maxlength="8">
					<span class="input-group-addon">
						<span class="fas fa-clock"></span>
					</span>
				</div>
			';
		}
		if($type=="datetime"){

			//############################
			// Get data from session variable
			//############################

			if(isset($_SESSION[$n])) $value = $_SESSION[$n];
			else $value = false;

			//############################

			$result .= '
				<div class="input-group">
					<input type="text" id="'.$n.'" name="'.$name.'" class="form-control datetimepicker" value="'.$value.'">
					<span class="input-group-addon">
						<span class="glyphicon glyphicon-time"></span>
					</span>
				</div>
			';
		}
		if($type=="mmyy"){

			//############################
			// Get data from session variable
			//############################

			if(isset($_SESSION[$n])) $value = $_SESSION[$n];
			else $value = false;

			//############################

			$result .= '
				<div class="input-group">
					<input type="text" id="'.$n.'" name="'.$name.'" class="form-control mmyypicker" value="'.$value.'">
					<span class="input-group-addon">
						<span class="glyphicon glyphicon-time"></span>
					</span>
				</div>
			';
		}
		if($type=="sclclock"){

			//############################
			// Get data from session variable
			//############################

			if(isset($_SESSION[$n])) $value = $_SESSION[$n];
			else $value = false;

			//############################

			$result .= '		
				<div class="row">
					<input type="text" style="display:none;" id="'.$n.'" name="'.$name.'" class="form-control sclclock-input" value="'.$value.'" readonly=true>
					<div class="col-md-6">
						<span class="">ชม</span>
						<select class="sclclock" data-time="'.$n.'-hh" rel="'.$n.'">
							<option>00</option>
							<option>01</option>
							<option>02</option>
							<option>03</option>
							<option>04</option>
							<option>05</option>
							<option>06</option>
							<option>07</option>
							<option>08</option>
							<option>09</option>
							<option>10</option>
							<option>11</option>
							<option>12</option>
							<option>13</option>
							<option>14</option>
							<option>15</option>
							<option>16</option>
							<option>17</option>
							<option>18</option>
							<option>19</option>
							<option>20</option>
							<option>21</option>
							<option>22</option>
							<option>23</option>
						</select>
					</div>
					<div class="col-md-6">
						<span class="">นาที</span>
						<select class="sclclock" data-time="'.$n.'-mm" rel="'.$n.'">
							<option>00</option>
							<option>05</option>
							<option>10</option>
							<option>15</option>
							<option>20</option>
							<option>25</option>
							<option>30</option>
							<option>35</option>
							<option>40</option>
							<option>45</option>
							<option>50</option>
							<option>55</option>
						</select>
					</div>					
				</div>
			';
		}
		### geninput("q1","20:3","input")
		if($type=="input"){

			//############################
			// Get data from session variable
			//############################
			if(strpos($name,":") !== False) $nm = explode(":",$name);
			else $nm = explode(":", $name.':'.$name);

			$n = str_replace("[]", "", $nm[0]);

			if(isset($_SESSION[$nm[1]])) $value = $_SESSION[$nm[1]];
			else $value = false;
			
			//############################			
			
			$result .= '
				<div class="form-group">
					<input type="text" autocomplete="off" class="form-control form-inputtext" name="'.$nm[0].'" placeholder="'.$data.'" value="'.$value.'" />
				</div>
			';
		}
		### geninput("q1","","float")
		if($type=="float"){

			//############################
			// Get data from session variable
			//############################

			if(isset($_SESSION[$n])) $value = $_SESSION[$n];
			else $value = false;
			//############################
			
			$result .= '
					<div class="form-group">
						<input type="text" autocomplete="off" id="'.$n.'" class="form-control float" name="'.$name.'" value="'.$value.'" />
					</div>
			';
		}
		### geninput("q1","","integer")
		if($type=="integer"){

			//############################
			// Get data from session variable
			//############################
			if(strpos($name,":") !== False) $nm = explode(":",$name);
			else $nm = explode(":", $name.':'.$name);

			$n = str_replace("[]", "", $nm[0]);

			if(isset($_SESSION[$nm[1]])) $value = $_SESSION[$nm[1]];
			else $value = false;
			//############################

			$result .= '
					<div class="form-group">
						<input type="text" autocomplete="off" class="form-control integer" name="'.$nm[0].'" value="'.$value.'" pattern="[0-9]*" inputmode="numeric" />
					</div>
			';
		}
		if($type=="touchspin"){

			//############################
			// Get data from session variable
			//############################

			if(isset($_SESSION[$n])) $value = $_SESSION[$n];
			else $value = false;
			//############################
			
			if($data) $postfix = 'postfix: "'.$data.'",';
			$result .= '
					<script>
						$(document).ready(function(){
							$("input[name='.$n.'").TouchSpin({
								min: 0,
								max: 100,
								step: 1,
								buttondown_class: "btn btn-danger",
								buttonup_class: "btn btn-info"
							});
						});					
					</script>
					<div class="row">
						<div class="col-md-6 col-lg-3 col-xl-2">
							<div class="form-group">
								<input type="text" autocomplete="off" id="'.$n.'" class="form-control touchspin" name="'.$name.'" value="'.$value.'"  />
							</div>
						</div>
					</div>
			';
		}
		if($type=="number"){

			//############################
			// Get data from session variable
			//############################

			if(isset($_SESSION[$n])) $value = $_SESSION[$n];
			else $value = false;
			//############################

			$result .= '
					<div class="form-group"><input type="number" autocomplete="off" id="'.$n.'" class="form-control dginput" name="'.$name.'" value="'.$value.'" /></div>
			';
		}
		### geninput("q1","Label","button")
		if($type=="button"){

			//############################
			// Get data from session variable
			//############################

			$u2 = explode(":", $u1[$i]);

			$result .= '
					<button id="'.$name.'" name="'.$name.'" type="button" >'.$u2[0].'</button>
			';
		}

		### geninput("q1","Label","hidden")
		if($type=="hidden"){

			//############################
			// Get data from session variable
			//############################

			if(isset($_SESSION[$name])) $value = $_SESSION[$name];
			else $value = false;

			$result .= '
					<input id="'.$name.'" type="hidden" name="'.$name.'" value="'.$value.'"/>
			';
		}
		### geninput("q1","Label","hidden")
		if($type=="readonly"){

			$result .= '
				<div class="data-readonly" id="'.$name.'">'.$data.'</div>
			';
		}

		
		### geninput("q1","99:00.png][อื่นๆ โปรดspecify...[oth];96:00.png][ไม่มี","imgsa")
		### Image selection SA
		if($type=="imgsa"){
			$u2 = explode(":", $u1[$i]);
			$u3 = explode("][", $u2[1]);

			$folder = "asset/";
			$span = false;
			$imgwidth = 250;
			$imgheight = 100;

			//############################
			// Get data from session variable
			//############################
				$active = false;
				$value = false;

				if(isset($_SESSION[$name])) {
					if($_SESSION[$name]==trim($u2[0])) {
						$active = ' imgactive';
						$value = ' checked="true"';
					}
				}

				$othvalue = false;
				$othsc = false;
				if($_SESSION[$name.trim($u2[0]).'_oth']!='') $othvalue = $_SESSION[$name.trim($u2[0]).'_oth'];
				else $othsc = '$(".imgblockoth").find("div.other").hide();';

				//############################
				$othdiv = false;
				$other = false;
				if(strpos($u2[1],"[oth]") > 0) {
					// add input
					$other = '
					<script type="text/javascript">
						$(document).ready(function(){
							'.$othsc.'
							$("input[type=radio]").change(function(){
								if($("input[name='.$name.']:checked").val()=='.trim($u2[0]).') {
									$("#'.$name.trim($u2[0]).'_odiv").fadeIn();
								}
								else {
									$("#'.$name.trim($u2[0]).'_odiv").fadeOut();
									$("#'.$name.trim($u2[0]).'_oth").val("");
								}
							});
						});
					</script>
					<div class="other" rel="off" id="'.$name.trim($u2[0]).'_odiv"><span class="other">specify...: </span> <input type="text" class="other" id="'.$name.trim($u2[0]).'_oth" name="'.$name.trim($u2[0]).'_oth" value="'.$othvalue.'" /></div>';
					$u3[1] = str_replace("[oth]","",$u3[1]);
					$othdiv = 'imgblockoth';
				}

			//############################
			if($span) $spantxt = '<br /><span>'.$bulletlabel.$u3[1].'</span>';
			else $spantxt = false;

			$result .= '
				<script type="text/javascript">
					$(document).ready(function(){
						$("input[name='.$name.']").bind("change" ,function(){
							if($(this).attr("type")=="radio"){
								$("input[name='.$name.']").parent().removeClass("imgactive")
								$(this).parent().addClass("imgactive")
							}
						});
					});
				</script>
				<div class="clear imgblock '.$othdiv.' '.$active.'">
					<input type="radio" class="radio" id="'.$name.trim($u2[0]).'" name="'.$name.'" value="'.trim($u2[0]).'" '.$value.'/>
					<label for="'.$name.trim($u2[0]).'">
						<img src="'.$folder.$u3[0].'" height="'.$imgheight.'" class="imgsa" />
						'.$spantxt.'
					</label>'.$other.'
				</div>
			';
		}

		### geninput("q1","1:Q1 xxx;2:Q2 xxx;3:Q3 xxx","imgma")
		### Image selection SA
		if($type=="imgma"){
			$u2 = explode(":", $u1[$i]);
			$u3 = explode("][", $u2[1]);

			$folder = "asset/";
			$span = true;
			$imgwidth = 200;
			$imgheight = 400;

			//############################
			// Get data from session variable
			//############################
				$active = false;
				$value = false;

				if(isset($_SESSION[$name.'r'.trim($u2[0])])) {
					if($_SESSION[$name.'r'.trim($u2[0])]==1) {
						$active = ' imgactive';
						$value = ' checked="true"';
					}
				}

				$othvalue = false;
				$othsc = false;
				if($_SESSION[$name.'r'.trim($u2[0]).'_oth']!='') $othvalue = $_SESSION[$name.'r'.trim($u2[0]).'_oth'];
				else $othsc = '$(".imgblockoth").find("div.other").hide();';

			//############################
			$othdiv = false;
			$other = false;
			if(strpos($u2[1],"[oth]") > 0) {
				// add input
				$other = '
				<script type="text/javascript">
					$(document).ready(function(){
						'.$othsc.'
						$("input[type=checkbox]").change(function(){
							if($(this).is(":checked")) {
								$("#"+$(this).attr("id")+"_odiv").fadeIn();
							}
							else {
								$("#"+$(this).attr("id")+"_odiv").fadeOut();
								$("#"+$(this).attr("id")+"_oth").val("");
							}
						});
					});
				</script>
				<div class="other" rel="off" id="'.$name.'r'.trim($u2[0]).'_odiv"><span class="other">specify...: </span> <input type="text" class="other" id="'.$name.'r'.trim($u2[0]).'_oth" name="'.$name.'r'.trim($u2[0]).'_oth" value="'.$othvalue.'" /></div>';
				$u3[1] = str_replace("[oth]","",$u3[1]);
				$othdiv = 'imgblockoth';
			}

			//############################
			if($span) $spantxt = '<br /><span>'.$bulletlabel.$u3[1].'</span>';
			else $spantxt = false;

			$result .= '
				<script type="text/javascript">
					$(document).ready(function(){
						$("input[name='.$name.'r'.trim($u2[0]).']").bind("change" ,function(){
							$("input[name='.$name.'r'.trim($u2[0]).']:checked").each(function(){
								$(this).parent().addClass("imgactive")
							});
							$("input[name='.$name.'r'.trim($u2[0]).']:not(:checked)").each(function(){
								$(this).parent().removeClass("imgactive")
							});
						});
					});
				</script>
				<div class="clear imgblock '.$othdiv.' '.$active.'">
					<input type="checkbox" id="'.$name.'r'.trim($u2[0]).'" name="'.$name.'r'.trim($u2[0]).'" value="1" '.$value.' />
					<label for="'.$name.'r'.trim($u2[0]).'">
						<img src="'.$folder.$u3[0].'" class="imgma" height="'.$imgheight.'" />
						'.$spantxt.'
					</label>'.$other.'
				</div>
			';
		}
		### geninput("q1","1:bad;2:normal;3:good","select")
		if($type=="selectsa"){

			$u2 = explode(":", $u1[$i]);

			//############################
			// Get data from session variable
			//############################
			$value = false;
			$script = false;
			$default = '<option value="-1" id="'.$n.'-1"> --- </option>';


			if(strpos($name,":") !== False) $nm = explode(":",$name);
			else $nm = explode(":", $name.':'.$name);

			$n = str_replace("[]", "", $nm[0]);

			if(isset($_SESSION[$n])) {
				if($u2[0]==$_SESSION[$n]) {
					$value = ' selected="selected"';
				} 
			} 
			
			if($i==0) $result .= '
			<select name='.$name.' id="'.$n.'" data-placeholder="เลือกคำตอบ" class="select2 '.$n.'">
				'.$default.'
			';
			$result .= '<option value="'.trim($u2[0]).'" '.$value.' id="'.$n.trim($u2[0]).'">'.$u2[1].'</option>';

			if($i==count($u1)-1) $result .= '</select>';
		}

		
		### geninput("q1","1:bad;2:normal;3:good","select")
		if($type=="selectajax"){
			include('label.php');

			if($u1[0] == "country") $array = explode(";",$country_list);
			elseif($u1[0] == "currency") $array = explode(";",$currency_list);
			for($z=0;$z<count($array);$z++){
				$arr = explode(":", $array[$z]);
				$lsarr[$arr[0]] = $arr[1];
			}

			//############################
			// Get data from session variable
			//############################
			$value = false;
			$script = false;

			if(strpos($name,":") !== False) $nm = explode(":",$name);
			else $nm = explode(":", $name.':'.$name);

			$n = str_replace("[]", "", $nm[0]);

			$default = '<option value="-1" selected="selected"> --- </option>';
			if(isset($_SESSION[$nm[1]])) {
				$default = '<option value="'.$_SESSION[$nm[1]].'" selected="selected">'.$lsarr[$_SESSION[$nm[1]]].'</option>';
			} 
			
			if($i==0) $result .= '
			<select name='.$nm[0].' data-placeholder="เลือกคำตอบ" class="select2 '.$u1[0].'">
				'.$default.'
			';

			if($i==count($u1)-1) $result .= '</select>';
		}

		### geninput("q1","1:bad;2:normal;3:good","select")
		if($type=="selectma"){

			$u2 = explode(":", $u1[$i]);			

			//############################
			// Get data from session variable
			//############################

				$value = false;
				if(isset($_SESSION[$n])) {
					if(array_search($u2[1],$_SESSION[$n])!==False) {
						$value = ' selected="selected"';
					}
				} 

			if($i==0) $result .= '
				<select id="'.$n.'" name="'.$n.'[]" data-placeholder="เลือกคำตอบ" multiple>
			';
			$result .= '<option value="'.$u2[1].'" '.$value.' id="'.$n.trim($u2[0]).'">'.$u2[1].'</option>';

			if($i==count($u1)-1) $result .= '</select>';
		}
	}
	$result .= '</div>';
	return $result;
}
function geninputOE($name,$name2,$data,$type,$open){

	global $label;

	$id = $_SESSION['type'];

	$result = '<div class="form-item">';
	$other = '';
	$array = explode(";",$open);
	$u1 = explode(";", $data);

	######################
	### OE
	######################
	for($i=0;$i<count($array);$i++){
		$val[] = ' n=="'.$array[$i].'" ';
	}
	$result .= '
	<script>
		$(document).ready(function(){			
			$("span[rel='.$name.']").text("'.$_SESSION[$name].'")
			$("div[data-form='.$name.']").click(function(){
				var n = $(this).attr("data-value")
				$("span[rel='.$name.']").text(n)
				if('.implode("||", $val).'){
					$("#'.$name2.'_odiv").removeClass("hidden");
				} else {
					$("#'.$name2.'").val("");
					$("#'.$name2.'_odiv").addClass("hidden");
				}
			});
		});
	</script>';
	
	######################
	### OE
	######################
	$othvalue = false;
	if(!empty($_SESSION[$name2])) $othvalue = $_SESSION[$name2];

	$othhide = 'hidden';
	if(!empty($_SESSION[$name]) && array_search($_SESSION[$name], $array) !== false) $othhide = false;

	// add input
	$other = '
	<div id="'.$name2.'_odiv" class="'.$othhide.'" style="margin-bottom:50px;">
		<div class="row">
			<div class="col-xs-12">'.genQ($label[$id][$name2]).'</div>
		</div>
		<div class="row">
			<div class="col-xs-12">
				<div class="form-group">
					<label style="width:100%;">
						<input type="text" class="form-control form-inputtext" id="'.$name2.'" name="'.$name2.'" value="'.$othvalue.'" placeholder="specify..." />
					</label>
				</div>
			</div>
		</div>
	</div>';

	if($type=="radio"){
		for($i=0;$i<count($u1);$i++){
			$n = str_replace("[]", "", $name);

			### geninput("q1","1:male;2:female","radio")
			$bulletlabel = false;		

			$u2 = explode(":", $u1[$i]);

			//############################
			// Get data from session variable
			//############################
			$active = false;
			$value = false;
			
			if(isset($_SESSION[$n])) {
				if($u2[0]==$_SESSION[$n]) {
					$active = 'active';
					$value = ' checked="true"';
				}
			}

			$result .= '
				<div class="'.$active.' form-radio" col-md-6 data-form="'.$n.'" data-value="'.trim($u2[0]).'" rel="'.$n.trim($u2[0]).'">
					<label for="'.$n.trim($u2[0]).'">
						<input type="radio" class="radio" id="'.$n.trim($u2[0]).'" name="'.$name.'" value="'.trim($u2[0]).'" '.$value.'/>
						<div class="form-fa">
							<i class="fas fa-check-circle form-check" aria-hidden="true"></i>
							<i class="far fa-circle form-uncheck" aria-hidden="true"></i>
						</div>
						<span>'.$u2[1].'</span>
					</label>
				</div>
			';
		}
	}
	elseif ($type=="scale"){
		$result .= genscale($name,$data);
	}
	$result .= $other.'</div>';
	return $result;
}
### Generate Grid
### genrankgrid("q11","1:Q1 xxx;2:Q2 xxx;3:Q3 xxx","1:bad;2:normal;3:good","Satisfaction Level")
function genrankgrid($name,$row,$column,$title = false){
	$result = '';
	$r = explode(";",str_replace("\t", '',(str_replace(PHP_EOL, '', $row))));
	$c = explode(";",str_replace("\t", '',(str_replace(PHP_EOL, '', $column))));
	$width = 120;

	$bullet = false;
	$bulletlabel = '';

		// Add error DIV
		$result .= '
				<div class="ui-widget errors" id="'.$name.'_err">
					<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;">
						<p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
						<strong>Alert:</strong> <span id="'.$name.'_errinfo" class="span_error"></span></p>
					</div>
				</div>
		';

	/// Table Head

	// Ranking Panel
	$rank = [];
	for($i=1;$i<=count($c);$i++){
		$rank[] = '
			<div class="row">
				<div class="col-xs-1">'.$i.'</div>
				<div class="col-xs-11"><span id="rank'.$i.'"></span></div>
			</div>
		';
	}
	$result .= '
	<div id="ranking-panel">'.implode('', $rank).'</div>
	<div class="grid-table">
	<style>
		#header-fixed {
			position: fixed;
			top: 0px; display:none;
			background-color:white;
		}
	</style>
	<table id="header-fixed"></table>
	<script>
		$(function(){
			var tableOffset = $("#'.$name.'set").offset().top;
			var $header = $("#'.$name.'set > thead").clone();
			var $fixedHeader = $("#header-fixed").append($header);
			$("#header-fixed").width($("#header-fixed").closest(".grid-table").width());

			$("#wrapper").scroll(function() {
				var offset = $(this).scrollTop();
			
				if (offset >= tableOffset && $fixedHeader.is(":hidden")) {
					$fixedHeader.show();
				}
				else if (offset < tableOffset) {
					$fixedHeader.hide();
				}
			});

			$(".form-rank").click(function(){
				var rank = $(this).attr("data-rank");
				var label = $("div[data-rank="+rank+"]").has("input[type=radio]:checked").closest("tr").children("td:eq(0)").text()
				$("#"+rank).text(label)
				$("#ranking-panel").find("span").removeClass("text-danger")

				for(i=1;i<='.count($c).';i++){					
					var label = $("#rank"+i).text()
					for(j=i;j<='.count($c).';j++){
						var label2 = $("#rank"+j).text()
						if(label == label2 && i != j){
							$("#rank"+i).addClass("text-danger")
							$("#rank"+j).addClass("text-danger")
						}
					}
				}
			});
			$(".ranking-panel").ready(function(){
				for(i=1;i<='.count($c).';i++){
					var select = $("div[data-rank=rank"+i+"]").has("input[type=radio]:checked")
					if(select.length){
						var label = select.closest("tr").children("td:eq(0)").text()
						$("#rank"+i).text(label)	
					}						
				}
				
				for(i=1;i<='.count($c).';i++){
					var label = $("#rank"+i).text()
					for(j=i;j<='.count($c).';j++){
						var label2 = $("#rank"+j).text()
						if(label == label2 && i != j){								
							$("#rank"+i).addClass("text-danger")
							$("#rank"+j).addClass("text-danger")
						}
					}			
				}
			});
		});		
	</script>
	<table border="0" cellspacing="0" cellpadding="0" width="100%" id="'.$name.'set">
	<colgroup>
		<col />
	';
	for($i=0;$i<count($c);$i++){
		$result .= '<col width="'.$width.'px" />
		';
	}
	$result .= '</colgroup>
	<thead class="grid">
		<tr class="grid_th bg-grey">
			<th></th>
	';

	// add title of column side
	$col = count($c)+1;

	if($title) {
			$result .= '<th colspan="'.$col.'">'.$title.'</th>
		</tr>
		<tr class="grid_th bg-grey">
			<th></th>
		';
	}
	for($i=0;$i<count($c);$i++){

		// Explode column to array
		$cs = explode(":",$c[$i]);
		$cs[1] = str_replace("[exc]","",$cs[1]);

		$result .= '<th width="'.$width.'" class="center font125 padding-tb">'.$cs[1].'</th>';
	}
	$result .= '</tr>
	</thead>	
	<tbody class="grid">';

	
	global $DATA;

	/// Table content
	for($i=0;$i<count($r);$i++){

		// Explode column to array
		$rsrow = replacePipe($DATA, $r[$i]);
		$rs = explode(":",$rsrow);

		$result .= '
		<tr class="grid">
			<td class="font125 ml-2">'.$rs[1].'</td>
		';
		for($x=0;$x<count($c);$x++){

			$cs = explode(":",$c[$x]);					

			$id = $name.trim($cs[0]).trim($rs[0]);
			$idr = $name.trim($cs[0]);
			$idv = trim($rs[0]);

			//############################
			// Get data from session variable
			//############################
			$active = false;
			$value = false;
			if(isset($_SESSION[$idr])) {
				if($rs[0]==$_SESSION[$idr]) {
					$active = 'active';
					$value = ' checked="true"';
				}
			}
			
			$result .= '
				<td>	
					<div class="form-item">					
						<div class="'.$active.' form-radio form-rank" data-form="'.$idr.'" data-value="'.$idv.'" rel="'.$idr.'" data-rank="rank'.$cs[0].'">
							<label for="'.$id.'">
								<input type="radio" class="radio" id="'.$id.'" name="'.$idr.'" value="'.$idv.'" '.$value.'/>
								<div class="form-fa center">
									<i class="far fa-2x fa-check-circle form-check" aria-hidden="true"></i>
									<i class="far fa-2x fa-circle form-uncheck" aria-hidden="true"></i>
								</div>
							</label>
						</div>
					</div>
				</td>					
			';

		}

		$result .= '</td></tr>';
	}

	$result .= '</tbody></table></div>';
	return $result;
}

function genscaledesc ($scale,$mode = 1,$title = false){
	$s = explode(';',$scale);
	$low = end($s);
	$high = $s[0];

	$l = explode(':',$low);
	$h = explode(':',$high);
	
	// Reverse
	if($l[0] > $h[0]){
		$low = $s[0];
		$high = end($s);

		$l = explode(':',$low);
		$h = explode(':',$high);
	}

	$cmode = [
		1 => [1=>'bd2d26', 2=>'cc4129', 3=>'de6528', 4=>'e68439', 5=>'e89835', 6=>'f5b520', 7=>'dcc002', 8=>'b1c410', 9=>'98b934', 10=>'6ba721'],
		2 => [1=>'bd2d26', 2=>'cc4129', 3=>'de6528', 4=>'e68439', 5=>'e89835', 6=>'f5b520', 7=>'dcc002', 8=>'b1c410', 9=>'98b934', 10=>'6ba721'],
		3 => [1=>'ebc0bd', 2=>'e4aba8', 3=>'de9692', 4=>'d7817c', 5=>'d06c67', 6=>'ca5651', 7=>'c3423b', 8=>'bd2d26', 9=>'aa2822', 10=>'97241e'],	// Dark Red to light red
		4 => [1=>'ebc0bd', 2=>'e4aba8', 3=>'de9692', 4=>'d7817c', 5=>'d06c67', 6=>'ca5651', 7=>'c3423b', 8=>'bd2d26', 9=>'aa2822', 10=>'97241e'],	// Dark Red to light red
		5 => [1=>'f0f6e8', 2=>'e1edd2', 3=>'d2e4bc', 4=>'c3dba6', 5=>'b5d390', 6=>'a6ca79', 7=>'97c163', 8=>'88b84d', 9=>'79af37', 10=>'6ba721'],	// Dark Red to light red
		6 => [1=>'f0f6e8', 2=>'e1edd2', 3=>'d2e4bc', 4=>'c3dba6', 5=>'b5d390', 6=>'a6ca79', 7=>'97c163', 8=>'88b84d', 9=>'79af37', 10=>'6ba721'],	// Dark Red to light red
	];
	$chigh = $cmode[$mode][10];
	$clow = $cmode[$mode][1];
	
	$span = $h[0] - $l[0] + 1;

	// Title
	if(!empty($title)){
		$title = '<tr class="title"><td colspan="'.$span.'" style="text-align:center;font-weight:bold;">'.$title.'</td></tr>';
	}

	// Scale
	$td = [];
	if(containsAny($mode,[1,3,5])){
		$scale = '<tr class="scale"><td colspan="'.$span.'">
			<span class="high" style="float:left;background:#'.$chigh.'">'.$h[1].'</span> 
			<span class="low" style="float:right;background:#'.$clow.'">'.$l[1].'</span> 
		</td></tr>';
		
		for($i = $h[0];$i >= $l[0];$i--){
			$td[] = '<td style="color:white;background:#'.$cmode[$mode][$i].'">'.$i.'</td>';
		}
	}
	elseif(containsAny($mode,[2,4,6])){
		$scale = '<tr class="scale"><td colspan="'.$span.'">
			<span class="low" style="float:left;background:#'.$clow.'">'.$l[1].'</span> 
			<span class="high" style="float:right;color:white;background:#'.$chigh.'">'.$h[1].'</span> 
		</td></tr>';
		
		for($i = $l[0];$i <= $h[0];$i++){
			$td[] = '<td style="color:white;background:#'.$cmode[$mode][$i].'">'.$i.'</td>';
		}
	}
	// Body
	$tr = '<tr class="scale" style="background:#'.$cmode[$mode][$i].'">'.implode('',$td).'</tr>';

	$result = '
		<div class="code">
			<table class="table table-scale-desc">
				'.$title.'
				'.$scale.'
				'.$tr.'
			</table>
		</div>
	';

	return $result;
}


### Generate Grid
###	("q11","1:Q1 xxx;2:Q2 xxx;3:Q3 xxx","1:bad;2:normal;3:good","40","Satisfaction Level")
function gengrid($name,$list,$scale,$qlist = false,$title = false,$DATA = false,$bscale = false){
	global $label;
	if(empty($DATA)) global $DATA;

	$l = explode(';',$list);
	$s = explode(';',$scale);
	$q = explode(';',$qlist);
	if(!empty($bscale)) $b = explode(';',$bscale);
	
	// -----------------------------
	// Header Row
	// -----------------------------
	$hrow = [];
	$header = [];
	if(!empty($qlist)) {
		foreach($q as $k => $v){
			$vv = explode(':',$v);
			$hrow[] = '<div class="grid-header-item">'.replacePipe($DATA, $vv[1]).'</div>';
		}
	}

	if(!empty($title)) 
		$header[] = '
			<div class="row grid-header-row grid-header">
				<div class="grid-header-item"></div>
				<div class="grid-header-item">'.replacePipe($DATA, $title).'</div>
			</div>
		';
	if(!empty($hrow)) 
		$header[] = '
			<div class="row grid-header-row grid-header">
				<div class="grid-header-item"></div>
				'.implode('',$hrow).'
			</div>
		';

	
	// -----------------------------
	// Body Row
	// -----------------------------
	$brow = [];

	// Sub Loop Attribute List
	foreach($l as $lk => $lv){
		$lvv = explode(':',$lv);
		$bcol = [];

		// Loop Question List
		foreach($q as $qk => $qv){
			$qvv = explode(':',$qv);
			$bsel = [];

			$ln = empty($qlist) ? false : trim($qvv[0]);		// Loop no. in variable name

			$qvar = $name.'_'.$ln.'a'.trim($lvv[0]);
			
			// Loop Scale List
			$var = 's';
			if(!empty($bscale) && $qk==0) $var = 'b';
			foreach($$var as $sk => $sv){
				$svv = explode(':',$sv);

				$bsel[] = '<option value="'.trim($svv[0]).'" '.(trim($svv[0])==$_SESSION[$qvar] ? 'selected="selected"' : false).'>'.(containsAny(trim($svv[0]),[1,10]) ? $svv[0].' ' : false).$svv[1].'</option>';

			}

			$bcol[] = '
				<div class="grid-body-item">
					<select class="grid-select" name="'.$qvar.'" id="'.$qvar.'" data-placeholder="'.$label['select-placeholder'].'">
						<option value="-1"></option>
						'.implode('',$bsel).'
					</select>
				</div>
			';
		}

		// Row Content
		if(strpos($lvv[1],'[oth]') < 1) 
			$brow[] = '
				<div class="row grid-body-row">
					<div class="grid-body-item grid-attribute">'.replacePipe($DATA, $lvv[1]).'</div>
					'.implode('',$bcol).'
				</div>
			';
		else 
			$brow[] = '
				<div class="row grid-body-row">
					<div class="grid-body-item grid-attribute">
						'.replacePipe($DATA, strtr($lvv[1],['[oth]'=>''])).'
						'.geninput($name.'_'.trim($lvv[0]).'_oth',false,'input').'
					</div>
					'.implode('',$bcol).'
				</div>
			';
	}


	// -----------------------------
	// Main Content
	// -----------------------------
	$result = '
		<div class="mrAnswerDiv">		
			<div class="grid-table">			
				'.implode('',$header).'
				'.implode('',$brow).'
			</div>
		</div>
	';


	return $result;
}

function gengridS($name,$list,$scale,$qlist = false,$title = false,$DATA = false){
	global $label;
	if(empty($DATA)) global $DATA;

	$l = explode(';',$list);
	$s = explode(';',$scale);
	$q = explode(';',$qlist);
	
	// -----------------------------
	// Header Row
	// -----------------------------
	$hrow = [];
	$header = [];
	if(!empty($qlist)) {
		foreach($q as $k => $v){
			$vv = explode(':',$v);
			$hrow[] = '<div class="grid-header-item">'.replacePipe($DATA, $vv[1]).'</div>';
		}
	}

	if(!empty($title)) 
		$header[] = '
			<div class="row grid-header-row grid-header">
				<div class="grid-header-item"></div>
				<div class="grid-header-item">'.$title.'</div>
			</div>
		';
	if(!empty($hrow)) 
		$header[] = '
			<div class="row grid-header-row grid-header">
				<div class="grid-header-item"></div>
				'.implode('',$hrow).'
			</div>
		';

	
	// -----------------------------
	// Body Row
	// -----------------------------
	$brow = [];

	// Sub Loop Attribute List
	foreach($l as $lk => $lv){
		$lvv = explode(':',$lv);
		$bcol = [];

		// Loop Question List
		foreach($q as $qk => $qv){
			$qvv = explode(':',$qv);
			$bsel = [];

			$ln = empty($qlist) ? false : trim($qvv[0]);		// Loop no. in variable name

			$qvar = $name.'_'.$ln.'a'.trim($lvv[0]);
			
			if(containsAny($qvv[0],['b','c'])){
				// Loop Scale List
				foreach($s as $sk => $sv){
					$svv = explode(':',$sv);

					$bsel[] = '<option value="'.trim($svv[0]).'" '.(trim($svv[0])==$_SESSION[$qvar] ? 'selected="selected"' : false).'>'.(containsAny(trim($svv[0]),[1,10]) ? $svv[0].' ' : false).$svv[1].'</option>';

				}

				$bcol[] = '
					<div class="grid-body-item">
						<select class="grid-select" name="'.$qvar.'" id="'.$qvar.'" data-placeholder="'.$label['select-placeholder'].'">
							<option value="-1"></option>
							'.implode('',$bsel).'
						</select>
					</div>
				';
			} else {
				$cls = ' hidden';
				if(($_SESSION[$name.'_ba'.$lvv[0]] ?? 0) < ($_SESSION[$name.'_ca'.$lvv[0]] ?? 0)) $cls = false;
				$bcol[] = '
					<div class="grid-body-item form-group">
						<textarea class="form-control'.$cls.'" name="'.$qvar.'" id="'.$qvar.'">'.($_SESSION[$qvar] ?? false).'</textarea>
					</div>
				';
			}
		}

		// Row Content
		$brow[] = '
			<div class="row grid-body-row">
				<div class="grid-body-item grid-attribute">'.replacePipe($DATA, $lvv[1]).'</div>
				'.implode('',$bcol).'
			</div>
		';
	}


	// -----------------------------
	// Main Content
	// -----------------------------
	$result = '
		<div class="mrAnswerDiv">		
			<div class="grid-table">
				<script>
					$(function(){
						$(".grid-select").on("change", function(){
							// Get the value of the current select element
							var currentValue = $(this).val();
							console.log(currentValue)

							// Get the row number from the select element name attribute
							var rowNumber = $(this).attr("name").match(/a\d+/)[0];
							console.log(rowNumber)

							// Get the value of the select element in the same row but in the first column
							var firstColumnValue = $("select[name=\'q8_b" + rowNumber + "\']").val();
							console.log(firstColumnValue)

							// Get the value of the select element in the same row but in the second column
							var secondColumnValue = $("select[name=\'q8_c" + rowNumber + "\']").val();
							console.log(secondColumnValue)

							// Get the textarea element in the same row
							var textarea = $("textarea[name=\'q8_d" + rowNumber + "\']");
							
							// Check if the value in the first column is less than the value in the second column
							if (parseFloat(firstColumnValue) < parseFloat(secondColumnValue) && (parseFloat(firstColumnValue) != -1 && parseFloat(secondColumnValue) != -1 )) {
								// Show the textarea element
								textarea.removeClass("hidden");
							} else {
								// Hide the textarea element
								textarea.addClass("hidden");
							}
						});
						
					});
				</script>			
				'.implode('',$header).'
				'.implode('',$brow).'
			</div>
		</div>
	';


	return $result;
}
function genconjointgrid($name,$version,$task,$quota){

	include('label.php');
	include('task.php');
	$c = explode(";",random($t[$version][$task]));

	$result = '';

	/// Table Head
	$result .= '
	<div class="grid-table">
	<table border="0" cellspacing="0" cellpadding="0" width="1200" id="'.$name.'set">
	<tbody class="grid">
	<colgroup>
	';
	for($i=1;$i<=count($c);$i++){
		$c2 = explode(":",$c[$i-1]);
		if($quota=="MT" || ($quota=="TT" && $c2[0] <= 2)) $result .= '<col width="170"/>';
	}
	$result .= '
	</colgroup>
	<tr class="grid_th" style="background:url(asset/shelf_bg.jpg) no-repeat;">
	';
	for($i=1;$i<=count($c);$i++){
		$c2 = explode(":",$c[$i-1]);
		if($quota=="MT" || ($quota=="TT" && $c2[0] <= 2))
			$result .= '<th width="170" align="center" style="padding:5px;padding-top:25px;"><img src="asset/'.$img[$c2[0]].'" height="225"  /><br /><span style="font-size:125%;font-weight:bold;">'.$c2[1].' บาท</span></th>';
	}

	$result .= '
	</tr>';

	/* Table Body */
	$result .= '
	<tr class="grid">
	';

	for($x=1;$x<=count($c);$x++){

		$c2 = explode(":",$c[$x-1]);

		//############################
		// Get data from session variable
		//############################

			if(isset($_SESSION[$name])) {
				if($x==$_SESSION[$name]-1) {
					$value = ' checked="true"';
				} else $value = false;
			} else $value = false;
		//############################
		$x2 = $x + 1;
		if($quota=="MT" || ($quota=="TT" && $c2[0] <= 2)) {
			$result .= '
				<td>
					<center><input type="radio" id="'.$name.'_'.$x2.'" name="'.$name.'" value="'.$c2[0].'" '.$value.' /></center>
				</td>
			';
		}
	}

	$result .= '</tr>';
	$result .= '</tbody></table></div>';
	return $result;
}

### Gen Quote
function genQuote($text){
	$output = '
		<div class="quote">'.$text.'</div>
	';
	return $output;
}
function genAllowaddInput($q,$max){
	$output = '';

	$output .= '
	<script>
		$(document).ready(function(){
			$(document).on("click", "img.addinput", function(){
				var last = parseInt($("#allowadd'.$q.'").find("input[type=text]:last").attr("rel")) + 1
				if(last <= '.$max.'){
					var insimg = ""
					if(last < '.$max.') {
						insimg = "<img class=\'addinput\' src=\'images/add.jpg\' style=\'cursor:pointer;\' align=\'absmiddle\' width=\'30\' /> "
					} else {
						insimg = ""
					}
					$("#allowadd'.$q.'").find("img.addinput").remove()
					var str = "<div style=\'margin-left:20px;margin-bottom:10px;\'><label for=\''.$q.'_"+last+"\'>"+last+". <input type=\'text\' id=\''.$q.'_"+last+"\' name=\''.$q.'_"+last+"\' class=\'allowadd\' rel=\'"+last+"\' /></label> "+insimg+"</div>"
					$("#allowadd'.$q.'").append(str)
				}
			});
		});
	</script>
	<div id="allowadd'.$q.'">
		<div style="margin-left:20px;margin-bottom:10px;">
			<label for="'.$q.'_1">1. <input type="text" id="'.$q.'_1" name="'.$q.'_1" class="allowadd" rel="1" />
			</label>
			<img class="addinput" src="images/add.jpg" style="cursor:pointer;" align="absmiddle" width="30" />
		</div>
	</div>
	';

	return $output;
}
### checkcondscale($POST,"2.","q2_111:q2_121;q2_112:q2_122",1,$return,"must")
### Logic Check Scale
function checkcondScale($POST,$qset,$qn,$cond,$return,$opt = false){

	$ERR = false;
	$qnarr = explode(";", $qn);
	$bool = true;
	$must = false;

	for($i=0;$i<count($qnarr);$i++){
		$qnarr2 = explode(":", $qnarr[$i]);
		if(!validate($POST[$qnarr2[0]],"null")) {
			$bool = false;
		}
		if($POST[$qnarr2[0]] == $cond && !validate($POST[$qnarr2[1]],"null")) {
			$bool = false;
		}
		if($POST[$qnarr2[0]] == $cond) $must = true;
	}

	if(!$bool) $ERR = 'ขาดข้อ '.$qset;
	else $ERR = false;

	if($opt=="must"){
		if(!$must) $ERR = 'ข้อ '.$qset.' คุณต้องตอบ '.$cond.' อย่างน้อย 1 ข้อ';
	}

	// Clean
	for($i=0;$i<count($qnarr);$i++){
		$qnarr2 = explode(":", $qnarr[$i]);
		if($POST[$qnarr2[0]] != $cond) $POST[$qnarr2[1]] = false;
	}

	if($return=='POST') return $POST;
	if($return=='ERR') return $ERR;
}
### ContainsAny
### containsAny($POST['q1_5'],[1,2,3,4])
function containsAny($v,$arr){
	$output = false;
	if(array_search($v,$arr) !== false) $output = true;

	return $output;
}
### ContainsAll
### containsAll($POST['q1_5'],"1,2,3,4")
function containsAll($DATA,$code){
	$c = explode(",",$code);

	$output = true;
	for($i=0;$i<count($c);$i++){
		if($DATA!=$c[$i]) $output = true;
	}

	return $output;
}
### Create Categories From Grid
### createCategoriesFromGrid($POST,"q2_1;q2_2",1);
function createCategoriesFromGrid($POST,$qn,$cond){
	$q = explode(";",$qn);
	$output = '';

	for($i=0;$i<count($q);$i++){
		if($POST[$q[$i]]==$cond) {
			if($output!='') $output .= ';';
			$output .= $q[$i];
		}
	}
	return $output;
}
### Validate
function validate($data,$type){
	$output = true;

	### validate("test","text")
	if($type=="text"){
		if(trim($data)=='') $output = false;
	}
	### validate("test","mmyy")
	if($type=="mmyy"){
		$t = explode("-",$data);
		if(count($t) != 2) $output = false;
		if(!RangeBetween(intval($t[0]),1,12)) $output = false;
		if(!RangeBetween(intval($t[1]),1950,date('Y'))) $output = false;
		if(trim($data)=='') $output = false;
	}

	### validate("1","null")
	if($type=="null"){
		if($data===false || $data == '') $output = false;
	}
	if($type=="selectma"){
		if($data===false) $output = false;
		else {
			$data = implode(",", $data);
			if($data=='') $output = false;
		}
	}
	if($type=="email"){		
		if (filter_var($data, FILTER_VALIDATE_EMAIL)) {
		  //
		} else {
		  $output = false;
		}
	}

	### validate("1","number")
	if($type=="number"){
		if($data===false) $output = false;
		if(!is_numeric($data)) $output = false;
	}
	### validate("1","float")
	if($type=="float"){
		if($data===false) $output = false;
		if(!is_float($data)) $output = false;
	}

	### validate("1","time")
	if($type=="time"){
		if($data===false) $output = false;
		if(strpos($data,'.')===False) $output = false;
		if(strlen($data) != 5) $output = false;
	}

	### validate("1","select")
	if($type=="select"){
		if($data==-1) $output = false;
	}

	### validate([1,0,0,1],"checkbox")
	if($type=="checkbox"){

		$output = false;

		foreach($data as $k => $v){
			if(containsAny($v,[1])) $output = true;
		}
	}
	### validate("3-1;0;0;1","ranking")
	if($type=="ranking"){
		$r = explode("-",$data);
		$e = explode(";",$r[1]);
		$output = true;

		### Adjust rank if qn is filtered
		if(count($e)<$r[0]) $r[0] = count($e);

		### Set rank variable
		for($j=1;$j<=$r[0];$j++){
			$rk[$j] = 0;
		}

		### Count rank
		for($i=0;$i<count($e);$i++){
			for($j=1;$j<=$r[0];$j++){
				if($e[$i]==$j) $rk[$j]++;
				if($e[$i] > $r[0]) $output = false;
			}
		}
		### Check rank
		for($j=1;$j<=$r[0];$j++){
			if($rk[$j] != 1) $output = false;
		}
	}

	### validate("1(code_other);1(response);text","other")
	if($type=="other"){
		$d = explode(";",$data);
		if($d[0]==$d[1]){
			if(trim($d[2])=="") $output = false;
		}
	}
	return $output;
}

### validateOE($DATA,"a1",[96,97,98],"SA/MA")
function validateOE($DATA,$q,$oe,$type){
	$output = true;
	foreach($oe as $k => $v){
		if($type=='SA'){
			if(containsAny($DATA[$q],[$v]) && !validate($DATA[$q.$v.'_oth'], "text")) $output = false;
		} elseif($type=='MA'){
			if(containsAny($DATA[$q.'r'.$v],[1]) && !validate($DATA[$q.'r'.$v.'_oth'], "text")) $output = false;
		}
	}

	return $output;
}

function validateGrid($q,$DATA,$row,$col){
	$output = true;

	$r = $row;
	$c = $col;

	foreach($r as $rk => $rv){
		$rvv = explode(':',$rv);

		// If separated by qlist
		if(!empty($col)){
			foreach($c as $ck => $cv){
				$cvv = explode(':',$cv);
				if(!validate($DATA[$q.'_'.trim($cvv[0]).'a'.trim($rvv[0])], "select")) $output = false;
			}
		} 
		// If just simple grid
		else {
			if(!validate($DATA[$q.'_a'.trim($rvv[0])], "select")) $output = false;
		}
	}

	return $output;
}
function validateGridS($q,$DATA,$row,$col){
	$output = true;

	$r = $row;
	$c = $col;

	foreach($r as $rk => $rv){
		$rvv = explode(':',$rv);

		// If separated by qlist
		if(!empty($c)){
			foreach($c as $ck => $cv){
				$cvv = explode(':',$cv);
				if(containsAny($cvv[0],['b','c'])) {
					if(!validate($DATA[$q.'_'.trim($cvv[0]).'a'.trim($rvv[0])], "select")) $output = false;
				} else {
					if($DATA[$q.'_ba'.trim($rvv[0])] < $DATA[$q.'_ca'.trim($rvv[0])] && !validate($DATA[$q.'_da'.trim($rvv[0])], "text")) $output = false;
				}
			}
		} 
	}

	return $output;
}


function saveLog($sql,$id,$group,$conn){

	include_once('config.php');

	$usql = "UPDATE $tbl[$group] SET `log`= '$sql' Where `idqr`='".$id."' ; ";
	$uquery = mysqli_query($conn,$usql);

	echo '<pre>'.$usql.'</pre><br/>'.$sorry['error'];
	exit;
}

function Redirect($url, $permanent = false)
{
    if (headers_sent() === false)
    {
    	header('Location: ' . $url, true, ($permanent === true) ? 301 : 302);
    }

    exit();
}

function rotate($txt){
	$t = explode(";",$txt);

	$n = rand(1, count($t)-1);

	for($i=0;$i<$n;$i++){
		array_push($t, array_shift($t));
	}

	$tar = implode(";", $t);
	return $tar;
}

function random($txt){
	$t = explode(";",$txt);

	shuffle($t);

	$tar = implode(";", $t);
	return $tar;
}

// Function remainRes("1:xxx;2:xxx;3:xxx",[1,0,1],[99]) no. of code has to be same
function remainRes($list,$res,$fix,$DATA = false){
	$var = explode(";",$list);
	if(empty($fix)) $fix = [];
	
	$newlist = [];
	for($i=0;$i<count($var);$i++){
		$var2 = explode(":",$var[$i]);

		if(containsAny($res[$i],[1]) || containsAny($var2[0],$fix)) {
			$newlist[] = replacePipe($DATA, trim($var[$i]));
		}
	}
	if(empty($newlist)) return false;
	else return implode(';',$newlist);
}

function remainResSA($list,$res){
	$var = explode(";",$list);
		
	foreach($var as $k => $v){
		$var2 = explode(":",$v);
		if(intval($var2[0])==$res) return $v;
	}

	return false;
}

// Function removeRes("1:xxx;2:xxx;3:xxx",[6]/[1;0;1],[99],"SA/MA")
function removeRes($list,$res,$fix,$mode,$DATA = false){
	$var = explode(";",$list);
	if(empty($fix)) $fix = [];

	// $q = array if SA, string if MA
	$newlist = [];
	for($i=0;$i<count($var);$i++){
		if($mode=="SA"){
			if(!is_array($res)) $res = [$res]; // If just integer

			$var2 = explode(":",$var[$i]);
			if(!containsAny($var2[0],$res) || containsAny($var2[0],$fix)) {
				$newlist[] = replacePipe($DATA, trim($var[$i]));
				
			}
		} elseif($mode=="MA"){
			$var2 = explode(":",$var[$i]);

			if(!containsAny($res[$i],[1]) || containsAny($var2[0],$fix)) {
				$newlist[] = replacePipe($DATA, trim($var[$i]));
			}
		}
	}
	if(empty($newlist)) return false;
	else return implode(';',$newlist);
}

function sumMAresp($q,$DATA,$more){
	$qn = 0;
	
	foreach($more as $k => $v){
		$qn = $qn + $DATA[$q.'r'.$v];
	}
	return $qn;
}
function genMAresp($q,$DATA,$more){
	$op = [];

	foreach($more as $k => $v){
		$op[] = $DATA[$q.'r'.$v];
	}
	return $op;
}

function delcookies(){
	if (isset($_SERVER['HTTP_COOKIE'])) {
		$cookies = explode(';', $_SERVER['HTTP_COOKIE']);
		foreach($cookies as $cookie) {
			$parts = explode('=', $cookie);
			$name = trim($parts[0]);
			setcookie($name, '', time()-1000);
			setcookie($name, '', time()-1000, '/');
		}
	}
}

function getLabel($v,$list){
	$getLabel = '';
	$delimiter = ';';

	// Check if MA
	$ma = false;
	if(strpos($v,$delimiter)!==False) $ma = true;
	if($ma) $vv = explode($delimiter,$v);

	$l = explode(';',$list);

	for($i=0;$i<count($l);$i++){
		$r = explode(':',$l[$i]);
		if(!$ma) {
			if($v==$r[0]) $getLabel = $r[1];
		} else {			
			if($vv[$i]) {
				if($getLabel != '') $getLabel .= ', ';
				$getLabel .= $r[1];
			}
		}
	}
	return $getLabel;

}

function replacePipe($DATA,$label){
	$replacePipe = '';

	$label = strtr($label,['PTT Trading'=>'<span class="ptt">PTT Trading</span>', 'PTT'=>'<span class="ptt">PTT</span>', '{#comp}'=>'<span class="comp">{#comp}</span>']);
	if(strpos($label,"{#")!==false){
		$l = explode("}", $label);
		for($i=0;$i<count($l);$i++){
			$pipe = substr($l[$i], strpos($l[$i],"{#")+2, strlen($l[$i])-strpos($l[$i],"{#")-2);			
			$piping = !empty($DATA[$pipe]) ? $DATA[$pipe] : false;
			$l[$i] = str_replace("{#".$pipe, $piping, $l[$i]);
		}
		$replacePipe = implode('', $l);
	} else $replacePipe = $label;

	return $replacePipe;
}

function get_client_ip() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
       $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function genscale($name,$scale,$title = false,$nascale = false,$mod = 1,$color = 'nps'){

	$q = str_replace('_','.',$name);
	$sc = explode(";", $scale);


	// ------------------------
	// Form Item
	// ------------------------
	$n = count($sc);
	$formitem = [];
	$lowscale = false;
	$highscale = false;
	for($i=0;$i<$n;$i++){
		$sc[$i] = explode(":", $sc[$i]);

		if($i==0) $lowscale = $sc[$i][1];
		if($i==$n-1) $highscale = $sc[$i][1];

		#######################
		### SESSION ###########
		$checked = false;

		if(isset($_SESSION[$name]) && $_SESSION[$name]==$sc[$i][0]) {
			$checked = ' checked="checked"';						
		} 

		$formitem[] = '<input name="'.$name.'" type="radio" id="'.$name.$sc[$i][0].'" value="'.$sc[$i][0].'" title="'.$sc[$i][1].'" '.$checked.' />';
	}	

	// ------------------------
	// Scale Item
	// -----------------------
	$theme = [
		'nps' => false,
		'red' => 'b'
	];
	$scalelb = false;
	$scaleitem = [];
 	for($i=0;$i<$n;$i++){
		$checked = false;
		if(isset($_SESSION[$name]) && $_SESSION[$name]==$sc[$i][0]) {
			$checked = ' scaleact';
			$scalelb = $sc[$i][1];
		}
		$scaleitem[] = '<td class="tdscale scale'.$theme[$color].$sc[$i][0].''.$radius.''.$checked.'" rel="'.$name.$sc[$i][0].'" title="'.$sc[$i][1].'"><div class="scale" data-form="'.$name.'" data-value="'.$sc[$i][0].'">'.$sc[$i][0].'</div></td>';
	}

	// ------------------------
	// N/A Section
	// ------------------------
	$naitem = [];
	if(!empty($nascale)){
		$nascale = explode(';',$nascale);

		foreach($nascale as $k => $v){
			$checked = false;
			$na = explode(":", $v);

			if(isset($_SESSION[$name])) {
				if($na[0]==$_SESSION[$name]) {
					$active = 'active';
					$value = ' checked="true"';
				}
			}						
			$naitem[] = '
				<div class="form-item">
					<div class="tdscale-na '.$active.' form-radio" data-form="'.$name.'" data-value="'.$na[0].'" rel="'.$name.$na[0].'">
						<label for="'.$name.$na[0].'">
							<input type="radio" class="radio" id="'.$name.$na[0].'" name="'.$name.'" value="'.$na[0].'" '.$value.'/>
							<div class="form-fa">
								<i class="fas fa-check-circle form-check" aria-hidden="true"></i>
								<i class="far fa-circle form-uncheck" aria-hidden="true"></i>
							</div>
							<span>'.$na[1].'</span>
						</label>
					</div>
				</div>
			';
		}
	}	



	// ------------------------
	// Content
	// ------------------------
	$mode = [
		1 => [
			'low' => 'asset/s_angry.png',
			'high' => 'asset/s_happy.png'
		],
		2 => [
			'low' => 'asset/s_angry.png',
			'high' => 'asset/s_neutral.png'
		],
		3 => [
			'low' => 'asset/s_neutral.png',
			'high' => 'asset/s_happy.png'
		],
		4 => [
			'low' => 'asset/s_neutral.png',
			'high' => 'asset/s_angry.png'
		],
	];

	$output = '
	<div class="mrAnswerDiv">		
		<div class="content">			
			<div class="controlshide hidden">
				<div class="form-item">
					'.implode('',$formitem).'
				</div>			
			</div>
			<div class="row visible-xs visible-md" style="position:relative;top:-15px;z-index:10;margin:15px 10px 0px;">
				<div class="col-xs-6"><div class="slider-scale-label middle margin-fix-lr" style="float:left;"><span class="lowscale">'.$lowscale.'</span></div></div>
				<div class="col-xs-6"><div class="slider-scale-label middle margin-fix-lr" style="float:right;text-align:right;"><span class="highscale">'.$highscale.'</span></div></div>
			</div>
			<div class="row" style="margin:auto;">
				<div class="col-xs-12 visible-sm visible-lg" style="position:relative;">
					<div class="col-xs-6"><div class="slider-scale-label" style="float:left;"><span class="lowscale">'.$lowscale.'</span></div></div>
					<div class="col-xs-6"><div class="slider-scale-label" style="float:right;text-align:right;"><span class="highscale">'.$highscale.'</span></div></div>
				</div>
				<div class="col-xs-12 slider-box no-padding">
					<div class="slider-canvas">
						<div style="width:100%;" class="scalebg">
							<table class="table tablescale">
								<tr>
									'.implode('',$scaleitem).'
								</tr>
							</table>
							'.implode('',$naitem).'
						</div>
					</div>
				</div>
				<div class="col-xs-12 visible-sm visible-lg" style="position:relative;">
					<div class="col-xs-12"><div class="slider-scale-label" style="text-align:center;margin-top:10px;"><span class="lowscale">'.$title.'</span></div></div>
				</div>
			</div>
		</div>
	</div>
	';
	return $output;
}
function RollBackSQL($ID,$STEPTOROLL,$pdo,$tbl){
	global $rb;
	global $_CONFIG;

		if($rb[$STEPTOROLL] != ''){
			$sql = 'UPDATE '.$tbl.' SET ';
			$var = explode(",",$rb[$STEPTOROLL]);
			for($i=0;$i<count($var);$i++){
				$var2 = explode("-",$var[$i]);
				if(isset($var2[1])) $VAL = "'".$var2[1]."'";
				else $VAL = "NULL";
				if(strpos($var2[0],'[')) {
					$prefix = substr($var2[0],0,strpos($var2[0],'[')-1);
					$from = substr($var2[0],strpos($var2[0],'[')+1,strpos($var2[0],'..')-strpos($var2[0],'[')-1);
					$to = substr($var2[0],strpos($var2[0],'..')+2,strpos($var2[0],']')-strpos($var2[0],'..')-2);
					for($x=$to;$x<=$from;$x++){
						if($i>0) $sql .= ', ';
						$sql .= '`'.$prefix.$x.'`='.$VAL;
					}
				} else {
					if($i>0) $sql .= ', ';
					$sql .= '`'.$var2[0].'`='.$VAL;
				}
			}
			$sql .= ' WHERE idqr="'.$ID.'" ;';

			$query = $pdo->prepare($sql);
			$quers = $query->execute();
		}
		//sendAlert($quers,"index.php","CANNOT rollback data after Back button",false,$pdo,$email,$sms);

		return $quers;
	}

function uniqidReal($lenght = 13) {
    // uniqid gives 13 chars, but you could adjust it to your needs.
    if (function_exists("random_bytes")) {
        $bytes = random_bytes(ceil($lenght / 2));
    } elseif (function_exists("openssl_random_pseudo_bytes")) {
        $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
    } else {
        throw new Exception("no cryptographically secure random function available");
    }
    return substr(bin2hex($bytes), 0, $lenght);
}
function genProgressBar(){
	$output = '
		<div class="progressbody">
			<div id="progressnum">0%</div>
			<div class="progress">
				<div id="progressbar"></div>			
			</div>
		</div>
	';
	return $output;
}
function RangeBetween($data,$st,$ed){
	$bool = true;
	if(!is_numeric($data)) $bool = false;
	elseif($data < $st || $data > $ed) $bool = false;
	
    return $bool;
}
function thai_date_format($date_string) {
    // Define an array of Thai month names
    $months = array(
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 
        'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 
        'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    );
    
    // Extract the year, month, and day values from the input string
    $year = substr($date_string, 0, 4);
    $month = substr($date_string, 4, 2);
    $day = substr($date_string, 6, 2);

    // Convert the month value to Thai month name
    $month_name = $months[(int)$month-1];
    
    // Convert the year value to Thai era (B.E.)
    $thai_year = $year + 543;
    
    // Format the output string using the Thai day value, month name, and year value
    $output_string = $day . ' ' . $month_name . ' ' . $thai_year;
    
    // Return the formatted string
    return $output_string;
}

function genAuth(){
	$output = '
		<div class="row">
			<div id="proj-title">ระบุ Case ID:</div>
			<div class="col-xs-12">
				<div class="form-group padding-tb">
					<input type="text" class="form-control" name="id" min="1" placeholder="กรุณาระบุเลขเคส" />
				</div>
				<div class="row" style="margin-top:50px;">
					<div class="col-xs-6 col-xs-offset-3">
						<input type="submit" class="btn btn-block btn-primary" value="Start" />
					</div>
				</div>
			</div>
		</div>
	';
	return $output;
}
function rotateKey($array) {
    $length = count($array);
    // Ensure the number of positions is within the array length
    $positions = rand(1,count($array));
    
    // Split the array into two parts
    $part1 = array_slice($array, $positions);
    $part2 = array_slice($array, 0, $positions);

    // Merge the two parts in reversed order
    return array_merge($part1, $part2);
}