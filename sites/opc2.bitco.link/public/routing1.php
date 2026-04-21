<?php


############################
### PHP Header
############################
	
	
	############################
	### FUNCTION LOGIC CHECK START
	function routing($pdo,$POST,$DATA,$STEP){

############################
### PHP INCLUDE
############################

	############################
	### SEVARIABLEST UP 
	############################		
		$id = $POST['qid'];
		global $_API;
		global $_CONFIG;
		global $ROUTING;

	############################
	### TEST PURPOSE
	############################	
		//$id = 'A';
		//$group = 'A';

	############################
	### SET UP VARIABLES
	############################	
		$STEPN = 0;
		$NEXTSTEP = false;
		$COMPLETE = false;
		$ERR = false;
		$defaulterr = "กรุณาระบุคำตอบให้ครบ";
		$defaultOEerr = "กรุณาระบุอื่นๆ ให้ครบ";
		$contact = "";

	############################
	### LOGIC ROUTING START HERE
	############################	
	$STEPN = $ROUTING[$STEP];

	$choice = [101,102,103,104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,119,120,121,122,123,124,125,126,127,128,129,130,131,132,133,134,135,136,137,138,139,140,141,142,143,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,100,201,202,203,204,205,206,207,208,209,210,211,212,213,214,215,216,217,218,219,220,221,222,223,200,301,302,303,304,305,306,307,308,309,310,311,312,313,314,315,316,317,318,319,320,321,322,323,324,325,326,327,328,329,330,331,332,333,334,335,336,337,338,339,340,341,342,343,344,345,346,347,300,401,402,403,404,405,406,407,408,409,410,411,412,413,414,415,416,417,418,419,420,421,422,423,424,425,426,427,428,429,430,431,432,433,434,435,436,437,438,400,601,602,603,604,605,606,607,608,609,610,611,612,613,614,615,616,617,618,619,620,621,622,623,624,625,626,600,701,702,703,704,705,706,707,700,801,802,803,804,805,806,807,800,901,902,903,904,905,906,907,908,909,910,911,912,913,914,915,916,917,918,919,920,921,922,923,900,1001,1002,1003,1004,1005,1006,1007,1008,1009,1010,1011,1012,1013,1014,1015,1016,1017,1018,1019,1020,1021,1022,1023,1024,1025,1026,1000,99999];
	$a4choice = ['1_1','1_2','2_1','2_2','3_1','3_2','4_1','4_2','5_1','5_2','6_1','6_2','7_1','7_2','8_1','8_2','9_1','9_2','10_1','10_2',99];

	if($STEP=='intro'){
		$NEXTSTEP = 's1';

		if(!validate($POST['rd_name'],'text')) $ERR = $defaulterr;
		if(!validate($POST['rd_tel'],'text')) $ERR = $defaulterr;
	}
	if($STEP=='s1'){
		$NEXTSTEP = 's3';
		if(!validate($POST['s1'],'null')) $ERR = $defaulterr;
		if(!validate($POST['s2'],'null')) $ERR = $defaulterr;

		$POST['QUOTA_GENDER'] = $POST['s1'];
		$POST['QUOTA_AGE'] = $POST['s2']-1;

		if(containsAny($POST['s2'],[1,5,6])) $NEXTSTEP = 'TERM';
		
	}
	if($STEP=='s3'){
		$NEXTSTEP = 's4';
		if(!validate($POST['s3'],'null')) $ERR = $defaulterr;

		if(!containsAny($POST['s3'],[9])) $NEXTSTEP = 'TERM';
	}
	if($STEP=='s4'){
		$NEXTSTEP = 's5';
		if(!validate($POST['s4'],'null')) $ERR = $defaulterr;

		if(containsAny($POST['s4'],[1])) $NEXTSTEP = 'TERM';
	}
	if($STEP=='s5'){
		$NEXTSTEP = 's7';
		if(!validate($POST['s5'],'null')) $ERR = $defaulterr;
		if(!validate($POST['s6'],'null')) $ERR = $defaulterr;
		
		if(containsAny($POST['s5'],[1])) $POST['QUOTA_GBKK'] = 1;
		if(containsAny($POST['s5'],[2])) $POST['QUOTA_GBKK'] = 2;
		if(containsAny($POST['s5'],[3,4])) $POST['QUOTA_GBKK'] = 3;

		if(containsAny($POST['s6'],[5])) $POST['QUOTA_SES'] = 1;
		if(containsAny($POST['s6'],[4])) $POST['QUOTA_SES'] = 2;
		if(containsAny($POST['s6'],[3])) $POST['QUOTA_SES'] = 3;

		if(containsAny($POST['s6'],[1,2,6,7,8])) $NEXTSTEP = 'TERM';
	}
	if($STEP=='s7'){
		$NEXTSTEP = 's8';
		if(!validate($POST['s7'],'null')) $ERR = $defaulterr;

		if(containsAny($POST['s7'],[5,6,7])) $NEXTSTEP = 'TERM';
	}
	if($STEP=='s8'){
		$NEXTSTEP = 's9a';
		if(!validate($POST['s8'],'null')) $ERR = $defaulterr;

		if(containsAny($POST['s8'],[4,5])) $NEXTSTEP = 'TERM';
	}
	if($STEP=='s9a'){
		$NEXTSTEP = 's9b';
		if(!validate(genMAresp('s9a',$POST,$choice),'checkbox')) $ERR = $defaulterr;
		if(containsAny($POST['s9ar99999'],[1])) $NEXTSTEP = 'TERM';
		
		foreach([100,200,300,400,600,700,800,900,1000] as $k => $v){
			if($POST["s9ar{$v}"]==1 && !validate($POST["s9ar{$v}_oth"],'text')) $ERR = 'กรุณาระบุอื่นๆ';
		}
	}
	if($STEP=='s9b'){
		$NEXTSTEP = 's9c';
		if(!validate(genMAresp('s9b',$POST,$choice),'checkbox')) $ERR = $defaulterr;
	}
	if($STEP=='s9c'){
		$NEXTSTEP = 's9d';
		if(!validate($POST['s9c'],'null')) $ERR = $defaulterr;
	}
	if($STEP=='s9d'){
		$NEXTSTEP = 's10';
		if(!validate(genMAresp('s9d',$POST,$choice),'checkbox')) $ERR = $defaulterr;
		
		foreach([100,200,300,400,600,700,800,900,1000] as $k => $v){
			if($POST["s9dr{$v}"]==1 && !validate($POST["s9dr{$v}_oth"],'text')) $ERR = 'กรุณาระบุอื่นๆ';
		}
		if(sumMAresp("s9d",$POST,$choice) > 1 && $POST['s9dr99999']) $ERR = 'Code 99999 ไม่สามารถออกร่วมกับโค้ดอื่นได้';

		if(containsAny($POST['s9dr'.$DATA['s9c']],[1])) $ERR = "ไม่สามารถตอบยี่ห้อที่จะไม่ซื้อดื่ม เป็นยี่ห้อเดียวกับยี่ห้อที่ดื่มบ่อยที่สุดได้";

		if(!$ERR){
			// Cell 1: ผู้ที่ดื่มสปายไวน์คูลเลอร์ (33%) = 80
			if(containsAny($DATA['s9c'],[101,102,103,104,105])){

				//•	ปิดการสัมภาษณ์หากไม่ได้ดื่มเครื่องดื่มแอลกอฮอล์ผสมพร้อมดื่มภายใน 12 & 3 เดือนที่ผ่านมา 
				// (ไม่ตอบโค้ด 101 - 223 ในข้อ S9a and S9b) 
				//•	ปิดการสัมภาษณ์หาก “สปาย” ไม่ใช่เครื่องดื่มที่เคยดื่มภายใน 12 & 3 เดือนที่ผ่านมา 
				// (ไม่ตอบโค้ด 101 - 113 ในข้อ S9a and S9b) 
				$bool = false;
				foreach([101,102,103,104,105,106,107,108,109,110,111,112,113] as $k => $v){
					if($DATA['s9br'.$v]==1) $bool = true;
				}
				if(empty($bool)) Goto _s9_end;

				//•	ปิดการสัมภาษณ์หาก “สปาย” เป็นเครื่องดื่มที่ไม่คิดจะซื้อในอนาคต 
				// (ตอบโค้ด 101 – 113, 804 – 807 ในข้อ S9d)
				$bool = true;
				foreach([101,102,103,104,105,106,107,108,109,110,111,112,113,804,805,806,807] as $k => $v){
					if($DATA['s9dr'.$v]==1) $bool = false;
				}
				if(empty($bool)) Goto _s9_end;

				$POST['cell'] = 1;
			}
			// Cell 2: ผู้ที่ดื่ม เครื่องดื่มผสมพร้อมดื่ม ไวน์คูลเลอร์ ค๊อกเทล ไฮบอล บรรจุขวด,กระป๋องพร้อมดื่ม P3M (33%) = 80
			elseif(containsAny($DATA['s9c'],[114,115,116,117,118,119,120,121,122,123,124,125,126,127,128,129,130,131,132,133,134,135,136,137,138,139,140,141,142,143,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,201,202,203,204,205,206,207,208,209,210,211,212,213,214,215,216,217,218,219,220,221,222,223])){

				//•	ปิดการสัมภาษณ์หากไม่ได้ดื่มเครื่องดื่มแอลกอฮอล์ผสมพร้อมดื่มภายใน 12 & 3 เดือนที่ผ่านมา 
				// (ไม่ตอบโค้ด 101 – 223 ในข้อ S9a and S9b) 
				$bool = [];
				foreach([101,102,103,104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,119,120,121,122,123,124,125,126,127,128,129,130,131,132,133,134,135,136,137,138,139,140,141,142,143,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,100,201,202,203,204,205,206,207,208,209,210,211,212,213,214,215,216,217,218,219,220,221,222,223] as $k => $v){
					if($DATA['s9br'.$v]==1) $bool = true;
				}
				if(empty($bool)) Goto _s9_end;

				//•	ปิดการสัมภาษณ์หาก “สปาย” เป็นเครื่องดื่มที่ไม่คิดจะซื้อในอนาคต 
				// (ตอบโค้ด 101 – 113, 804 – 807 ในข้อ S9d)
				$bool = true;
				foreach([101,102,103,104,105,106,107,108,109,110,111,112,113,804,805,806,807] as $k => $v){
					if($DATA['s9dr'.$v]==1) $bool = false;
				}
				if(empty($bool)) Goto _s9_end;

				$POST['cell'] = 2;
			}
			// Cell 3: ผู้ที่ดื่ม เบียร์ และ มี สปายไวน์คูลเลอร์ เป็น หนึ่งในตัวเลือก (33%) = 80
			elseif(containsAny($DATA['s9c'],[401,402,403,404,405,406,407,408,409,410,411,412,413,414,415,416,417,418,419,420,421,422,423,424,425,426,427,428,429,430,431,432,433,434,435,436,437,438])){

				//•	ปิดการสัมภาษณ์หากไม่ได้ดื่ม เบียร์ และ เครื่องดื่มแอลกอฮอล์ผสมพร้อมดื่มภายใน 12 & 3 เดือนที่ผ่านมา 
				// (ไม่ตอบโค้ด 101 – 223, 401 – 438 ในข้อ S9a & S9b) 
				//•	ปิดการสัมภาษณ์หาก “สปายไวน์คูลเลอร์” ไม่ใช่เครื่องดื่มที่เคยดื่มภายใน 12 & 3 เดือนที่ผ่านมา 
				// (ไม่ตอบโค้ด 101 - 105 ในข้อ S9a and S9b) 
				$bool = [];
				foreach([101,102,103,104,105] as $k => $v){
					if($DATA['s9br'.$v]==1) $bool = true;
				}
				if(empty($bool)) Goto _s9_end;

				//•	ปิดการสัมภาษณ์หาก “สปาย” เป็นเครื่องดื่มที่ไม่คิดจะซื้อในอนาคต 
				// (ตอบโค้ด 101 – 113, 804 – 807 ในข้อ S9d)
				$bool = true;
				foreach([101,102,103,104,105,106,107,108,109,110,111,112,113,804,805,806,807] as $k => $v){
					if($DATA['s9dr'.$v]==1) $bool = false;
				}
				if(empty($bool)) Goto _s9_end;

				$POST['cell'] = 3;
			}
			else {
				$NEXTSTEP = 'TERM';
			}

			Goto _s9_next;
			
			_s9_end:
			$NEXTSTEP = 'TERM';

			_s9_next:
			$POST['QUOTA_BUMO'] = $POST['cell'];
		}
	}
	if($STEP=='s10'){
		$NEXTSTEP = 's12';
		if(!validate($POST['s10'],'null')) $ERR = $defaulterr;
		
		if(!validate($POST['s11'],'null')) $ERR = $defaulterr;
		
		if($POST['s10']==6 && !validate($POST['s106_oth'],'text')) $ERR = 'กรุณาระบุอื่นๆ';
		if($POST['s10']==7 && !validate($POST['s107_oth'],'text')) $ERR = 'กรุณาระบุอื่นๆ';
		if($POST['s11']==99 && !validate($POST['s1199_oth'],'text')) $ERR = 'กรุณาระบุอื่นๆ';
	}
	if($STEP=='s12'){
		$NEXTSTEP = 'quota';
		if(!validate($POST['s12'],'null')) $ERR = $defaulterr;

		if(containsAny($POST['s12'],[2])) $NEXTSTEP = 'TERM';
	}
	if($STEP=='quota'){
		$NEXTSTEP = 'l0';
	}

	// ----------------------
	// MAIN PART
	// ----------------------

	if(!empty($DATA['cell1'])){
		$a = $DATA['cell1'] ?? 0;
		$b = $DATA['cell2'] ?? 0;
		$c = $DATA['cell3'] ?? 0;
		$d = $DATA['cell4'] ?? 0;
	}

	if($STEP=='l0'){
		$NEXTSTEP = '';
		if(!validate($POST['l0'],'null')) $ERR = $defaulterr;
		
		if(containsAny($POST['l0'],[1,5,9])){
			$POST['cell1'] = 951;
			$POST['cell2'] = 968;
			$POST['cell3'] = 972;
			$POST['cell4'] = 937;
			$NEXTSTEP = 'c1_951';
		}
		elseif(containsAny($POST['l0'],[2,6,10])){
			$POST['cell1'] = 968;
			$POST['cell2'] = 972;
			$POST['cell3'] = 937;
			$POST['cell4'] = 951;
			$NEXTSTEP = 'c1_968';
		}
		elseif(containsAny($POST['l0'],[3,7,11])){
			$POST['cell1'] = 972;
			$POST['cell2'] = 937;
			$POST['cell3'] = 951;
			$POST['cell4'] = 968;
			$NEXTSTEP = 'c1_972';
		}
		elseif(containsAny($POST['l0'],[4,8,12])){
			$POST['cell1'] = 937;
			$POST['cell2'] = 951;
			$POST['cell3'] = 968;
			$POST['cell4'] = 972;
			$NEXTSTEP = 'c1_937';
		}
	}

	// ----------------------
	// Section 0
	// ----------------------
	if($STEP=='c1_'.$a){
		$NEXTSTEP = 'c1_'.$b;
		$x = $a;
		if(!validate($POST['c1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c2_'.$x],'null')) $ERR = $defaulterr;
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["c3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['c4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c10_'.$x],'null')) $ERR = $defaulterr;
	}
	if($STEP=='c1_'.$b){
		$NEXTSTEP = 'c1_'.$c;
		$x = $b;
		if(!validate($POST['c1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c2_'.$x],'null')) $ERR = $defaulterr;
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["c3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['c4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c10_'.$x],'null')) $ERR = $defaulterr;
	}
	if($STEP=='c1_'.$c){
		$NEXTSTEP = 'c1_'.$d;
		$x = $c;
		if(!validate($POST['c1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c2_'.$x],'null')) $ERR = $defaulterr;
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["c3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['c4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c10_'.$x],'null')) $ERR = $defaulterr;
	}
	if($STEP=='c1_'.$d){
		$NEXTSTEP = 'c11';
		$x = $d;
		if(!validate($POST['c1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c2_'.$x],'null')) $ERR = $defaulterr;
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["c3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['c4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['c10_'.$x],'null')) $ERR = $defaulterr;
	}
	if($STEP=='c11'){
		$NEXTSTEP = 'a1_912';

		if(!validate($POST['c11a1'],'null')) $ERR = $defaulterr;
		if(!validate($POST['c11a2'],'null')) $ERR = $defaulterr;
		if(!validate($POST['c11a3'],'null')) $ERR = $defaulterr;
		if(!validate($POST['c11a4'],'null')) $ERR = $defaulterr;

		foreach([1,2,3,4] as $k => $v){
			foreach([1,2,3,4] as $kk => $vv){
				if($v != $vv){
					if($POST['c11a'.$v]==$POST['c11a'.$vv]) $ERR = 'อันดับห้ามซ้ำกัน';
					// Check overall score
					if(($v < $vv) && ($DATA['c1_'.$POST['c11a'.$v]] < $DATA['c1_'.$POST['c11a'.$vv]])) $ERR = 'ดีไซน์ที่ได้คะแนนมากกว่า อันดับต้องสูงกว่าเสมอ';
				}
			}
		}
	}

	// -------------------------------- A ------------------------------------
	// ----------------------
	// Section 1A
	// ----------------------
	if($STEP=='a1_912'){
		$NEXTSTEP = 'a1_996';
		$x = '912';
		if(!validate($POST['a1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['a2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['a1_'.$x],[7]) && containsAny($POST['a2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		if(containsAny($POST['a1_'.$x],[1]) && containsAny($POST['a2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		foreach([1,2,3,4,5,6,7,8,9,10,11,12,13,14] as $k => $v){
			if(!validate($POST["a3_p{$v}_{$x}"],'null')) $ERR = $defaulterr;
			if(in_array($v,[2,3,4,5,6,7,10,11,12,13,14])){
				if(!validate($POST["a3a_p{$v}_{$x}"],'null')) $ERR = $defaulterr;	
				if(containsAny($POST["a3_p{$v}_{$x}"],[7]) && containsAny($POST["a3a_p{$v}_{$x}"],[1,2])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
				if(containsAny($POST["a3_p{$v}_{$x}"],[1]) && containsAny($POST["a3a_p{$v}_{$x}"],[4,5])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
			}
		}
		if(!validate(genMAresp("a4_{$x}",$POST,$a4choice),'checkbox')) $ERR = $defaulterr;
		foreach([1,2,3,4,5,6,7,8,9,10] as $k => $v){
			if(!empty($POST["a4_{$x}r{$v}_1"]) && !empty($POST["a4_{$x}r{$v}_2"])) $ERR = 'A4 คู่ตรงข้าม ออกคู่กันไม่ได้';
		}
	}
	if($STEP=='a1_996'){
		$NEXTSTEP = 'a5_912996';
		$x = '996';
		if(!validate($POST['a1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['a2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['a1_'.$x],[7]) && containsAny($POST['a2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		if(containsAny($POST['a1_'.$x],[1]) && containsAny($POST['a2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		foreach([1,2,3,4,5,6,7,8,9,10,11,12,13,14] as $k => $v){
			if(!validate($POST["a3_p{$v}_{$x}"],'null')) $ERR = $defaulterr;
			if(in_array($v,[2,3,4,5,6,7,10,11,12,13,14])){
				if(!validate($POST["a3a_p{$v}_{$x}"],'null')) $ERR = $defaulterr;	
				if(containsAny($POST["a3_p{$v}_{$x}"],[7]) && containsAny($POST["a3a_p{$v}_{$x}"],[1,2])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
				if(containsAny($POST["a3_p{$v}_{$x}"],[1]) && containsAny($POST["a3a_p{$v}_{$x}"],[4,5])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
			}
		}
		if(!validate(genMAresp("a4_{$x}",$POST,$a4choice),'checkbox')) $ERR = $defaulterr;
		foreach([1,2,3,4,5,6,7,8,9,10] as $k => $v){
			if(!empty($POST["a4_{$x}r{$v}_1"]) && !empty($POST["a4_{$x}r{$v}_2"])) $ERR = 'A4 คู่ตรงข้าม ออกคู่กันไม่ได้';
		}
	}
	// ----------------------
	// Section 2A
	// ----------------------
	if($STEP=='a5_912996'){
		$NEXTSTEP = 'b1_912937';
		$x = '912996';
		
		if(!validate($POST['a5a'.$x.'1'],'null')) $ERR = $defaulterr;
		if(!validate($POST['a5a'.$x.'2'],'null')) $ERR = $defaulterr;

		foreach([1,2] as $k => $v){
			foreach([1,2] as $kk => $vv){
				if($v != $vv){
					if($POST['a5a'.$x.$v]==$POST['a5a'.$x.$vv]) $ERR = 'อันดับห้ามซ้ำกัน';
					// Check overall score
					if(($v < $vv) && ($DATA['a1_'.$POST['a5a'.$x.$v]] < $DATA['a1_'.$POST['a5a'.$x.$vv]])) $ERR = 'ดีไซน์ที่ได้คะแนนมากกว่า อันดับต้องสูงกว่าเสมอ';
				}
			}
		}
	}
	// ----------------------
	// Section 3A
	// ----------------------
	if($STEP=='b1_912937'){
		$NEXTSTEP = 'b1_996937';
		$x = '912937';
		if(!validate($POST['b1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b1_'.$x],[7]) && containsAny($POST['b2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['b1_'.$x],[1]) && containsAny($POST['b2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["b3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['b4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b6_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b8_'.$x],'null')) $ERR = $defaulterr;
		if(!containsAny($POST['b8_'.$x],[15]) && !validate($POST['b9_'.$x],'number')) $ERR = $defaulterr;
		if(!validate($POST['b10_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b10_'.$x],[2,3,4]) && !validate($POST['b11_'.$x],'text')) $ERR = $defaulterr;
	}
	if($STEP=='b1_996937'){
		$NEXTSTEP = 'b12_912996';
		$x = '996937';
		if(!validate($POST['b1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b1_'.$x],[7]) && containsAny($POST['b2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['b1_'.$x],[1]) && containsAny($POST['b2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["b3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['b4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b6_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b8_'.$x],'null')) $ERR = $defaulterr;
		if(!containsAny($POST['b8_'.$x],[15]) && !validate($POST['b9_'.$x],'number')) $ERR = $defaulterr;
		if(!validate($POST['b10_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b10_'.$x],[2,3,4]) && !validate($POST['b11_'.$x],'text')) $ERR = $defaulterr;
	}
	// ----------------------
	// Section 4A
	// ----------------------
	if($STEP=='b12_912996'){
		$NEXTSTEP = 'a1_416';
		$x = '912996';
		
		if(!validate($POST['b12a'.$x.'1'],'null')) $ERR = $defaulterr;
		if(!validate($POST['b12a'.$x.'2'],'null')) $ERR = $defaulterr;

		foreach([1,2] as $k => $v){
			foreach([1,2] as $kk => $vv){
				if($v != $vv){
					if($POST['b12a'.$x.$v]==$POST['b12a'.$x.$vv]) $ERR = 'อันดับห้ามซ้ำกัน';
					// Check overall score
					if(($v < $vv) && ($DATA['b1_'.$POST['b12a'.$x.$v]] < $DATA['b1_'.$POST['b12a'.$x.$vv]])) $ERR = 'ดีไซน์ที่ได้คะแนนมากกว่า อันดับต้องสูงกว่าเสมอ';
				}
			}
		}
		if(!validate($POST['b14_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b15_'.$x],'text')) $ERR = $defaulterr;
	}

	// -------------------------------- B ------------------------------------
	// ----------------------
	// Section 1B
	// ----------------------
	if($STEP=='a1_416'){
		$NEXTSTEP = 'a1_442';
		$x = '416';
		if(!validate($POST['a1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['a2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['a1_'.$x],[7]) && containsAny($POST['a2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		if(containsAny($POST['a1_'.$x],[1]) && containsAny($POST['a2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		foreach([1,2,3,4,5,6,7,8,9,10,11,12,13,14] as $k => $v){
			if(!validate($POST["a3_p{$v}_{$x}"],'null')) $ERR = $defaulterr;
			if(in_array($v,[2,3,4,5,6,7,10,11,12,13,14])){
				if(!validate($POST["a3a_p{$v}_{$x}"],'null')) $ERR = $defaulterr;	
				if(containsAny($POST["a3_p{$v}_{$x}"],[7]) && containsAny($POST["a3a_p{$v}_{$x}"],[1,2])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
				if(containsAny($POST["a3_p{$v}_{$x}"],[1]) && containsAny($POST["a3a_p{$v}_{$x}"],[4,5])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
			}
		}
		if(!validate(genMAresp("a4_{$x}",$POST,$a4choice),'checkbox')) $ERR = $defaulterr;
		foreach([1,2,3,4,5,6,7,8,9,10] as $k => $v){
			if(!empty($POST["a4_{$x}r{$v}_1"]) && !empty($POST["a4_{$x}r{$v}_2"])) $ERR = 'A4 คู่ตรงข้าม ออกคู่กันไม่ได้';
		}
	}
	if($STEP=='a1_442'){
		$NEXTSTEP = 'a5_416442';
		$x = '442';
		if(!validate($POST['a1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['a2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['a1_'.$x],[7]) && containsAny($POST['a2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		if(containsAny($POST['a1_'.$x],[1]) && containsAny($POST['a2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		foreach([1,2,3,4,5,6,7,8,9,10,11,12,13,14] as $k => $v){
			if(!validate($POST["a3_p{$v}_{$x}"],'null')) $ERR = $defaulterr;
			if(in_array($v,[2,3,4,5,6,7,10,11,12,13,14])){
				if(!validate($POST["a3a_p{$v}_{$x}"],'null')) $ERR = $defaulterr;	
				if(containsAny($POST["a3_p{$v}_{$x}"],[7]) && containsAny($POST["a3a_p{$v}_{$x}"],[1,2])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
				if(containsAny($POST["a3_p{$v}_{$x}"],[1]) && containsAny($POST["a3a_p{$v}_{$x}"],[4,5])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
			}
		}
		if(!validate(genMAresp("a4_{$x}",$POST,$a4choice),'checkbox')) $ERR = $defaulterr;
		foreach([1,2,3,4,5,6,7,8,9,10] as $k => $v){
			if(!empty($POST["a4_{$x}r{$v}_1"]) && !empty($POST["a4_{$x}r{$v}_2"])) $ERR = 'A4 คู่ตรงข้าม ออกคู่กันไม่ได้';
		}
	}
	// ----------------------
	// Section 2B
	// ----------------------
	if($STEP=='a5_416442'){
		$NEXTSTEP = 'b1_416968';
		$x = '416442';
		
		if(!validate($POST['a5a'.$x.'1'],'null')) $ERR = $defaulterr;
		if(!validate($POST['a5a'.$x.'2'],'null')) $ERR = $defaulterr;

		foreach([1,2] as $k => $v){
			foreach([1,2] as $kk => $vv){
				if($v != $vv){
					if($POST['a5a'.$x.$v]==$POST['a5a'.$x.$vv]) $ERR = 'อันดับห้ามซ้ำกัน';
					// Check overall score
					if(($v < $vv) && ($DATA['a1_'.$POST['a5a'.$x.$v]] < $DATA['a1_'.$POST['a5a'.$x.$vv]])) $ERR = 'ดีไซน์ที่ได้คะแนนมากกว่า อันดับต้องสูงกว่าเสมอ';
				}
			}
		}
	}
	// ----------------------
	// Section 3B
	// ----------------------
	if($STEP=='b1_416968'){
		$NEXTSTEP = 'b1_442968';
		$x = '416968';
		if(!validate($POST['b1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b1_'.$x],[7]) && containsAny($POST['b2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['b1_'.$x],[1]) && containsAny($POST['b2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["b3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['b4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b6_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b8_'.$x],'null')) $ERR = $defaulterr;
		if(!containsAny($POST['b8_'.$x],[15]) && !validate($POST['b9_'.$x],'number')) $ERR = $defaulterr;
		if(!validate($POST['b10_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b10_'.$x],[2,3,4]) && !validate($POST['b11_'.$x],'text')) $ERR = $defaulterr;
	}
	if($STEP=='b1_442968'){
		$NEXTSTEP = 'b12_416442';
		$x = '442968';
		if(!validate($POST['b1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b1_'.$x],[7]) && containsAny($POST['b2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['b1_'.$x],[1]) && containsAny($POST['b2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["b3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['b4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b6_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b8_'.$x],'null')) $ERR = $defaulterr;
		if(!containsAny($POST['b8_'.$x],[15]) && !validate($POST['b9_'.$x],'number')) $ERR = $defaulterr;
		if(!validate($POST['b10_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b10_'.$x],[2,3,4]) && !validate($POST['b11_'.$x],'text')) $ERR = $defaulterr;
	}
	// ----------------------
	// Section 4B
	// ----------------------
	if($STEP=='b12_416442'){
		$NEXTSTEP = 'a1_765';
		$x = '416442';
		
		if(!validate($POST['b12a'.$x.'1'],'null')) $ERR = $defaulterr;
		if(!validate($POST['b12a'.$x.'2'],'null')) $ERR = $defaulterr;

		foreach([1,2] as $k => $v){
			foreach([1,2] as $kk => $vv){
				if($v != $vv){
					if($POST['b12a'.$x.$v]==$POST['b12a'.$x.$vv]) $ERR = 'อันดับห้ามซ้ำกัน';
					// Check overall score
					if(($v < $vv) && ($DATA['b1_'.$POST['b12a'.$x.$v]] < $DATA['b1_'.$POST['b12a'.$x.$vv]])) $ERR = 'ดีไซน์ที่ได้คะแนนมากกว่า อันดับต้องสูงกว่าเสมอ';
				}
			}
		}
		if(!validate($POST['b14_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b15_'.$x],'text')) $ERR = $defaulterr;
	}


	// -------------------------------- C ------------------------------------
	// ----------------------
	// Section 1C
	// ----------------------
	if($STEP=='a1_765'){
		$NEXTSTEP = 'a1_773';
		$x = '765';
		if(!validate($POST['a1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['a2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['a1_'.$x],[7]) && containsAny($POST['a2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		if(containsAny($POST['a1_'.$x],[1]) && containsAny($POST['a2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		foreach([1,2,3,4,5,6,7,8,9,10,11,12,13,14] as $k => $v){
			if(!validate($POST["a3_p{$v}_{$x}"],'null')) $ERR = $defaulterr;
			if(in_array($v,[2,3,4,5,6,7,10,11,12,13,14])){
				if(!validate($POST["a3a_p{$v}_{$x}"],'null')) $ERR = $defaulterr;	
				if(containsAny($POST["a3_p{$v}_{$x}"],[7]) && containsAny($POST["a3a_p{$v}_{$x}"],[1,2])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
				if(containsAny($POST["a3_p{$v}_{$x}"],[1]) && containsAny($POST["a3a_p{$v}_{$x}"],[4,5])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
			}
		}
		if(!validate(genMAresp("a4_{$x}",$POST,$a4choice),'checkbox')) $ERR = $defaulterr;
		foreach([1,2,3,4,5,6,7,8,9,10] as $k => $v){
			if(!empty($POST["a4_{$x}r{$v}_1"]) && !empty($POST["a4_{$x}r{$v}_2"])) $ERR = 'A4 คู่ตรงข้าม ออกคู่กันไม่ได้';
		}
	}
	if($STEP=='a1_773'){
		$NEXTSTEP = 'a1_729';
		$x = '773';
		if(!validate($POST['a1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['a2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['a1_'.$x],[7]) && containsAny($POST['a2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		if(containsAny($POST['a1_'.$x],[1]) && containsAny($POST['a2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		foreach([1,2,3,4,5,6,7,8,9,10,11,12,13,14] as $k => $v){
			if(!validate($POST["a3_p{$v}_{$x}"],'null')) $ERR = $defaulterr;
			if(in_array($v,[2,3,4,5,6,7,10,11,12,13,14])){
				if(!validate($POST["a3a_p{$v}_{$x}"],'null')) $ERR = $defaulterr;	
				if(containsAny($POST["a3_p{$v}_{$x}"],[7]) && containsAny($POST["a3a_p{$v}_{$x}"],[1,2])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
				if(containsAny($POST["a3_p{$v}_{$x}"],[1]) && containsAny($POST["a3a_p{$v}_{$x}"],[4,5])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
			}
		}
		if(!validate(genMAresp("a4_{$x}",$POST,$a4choice),'checkbox')) $ERR = $defaulterr;
		foreach([1,2,3,4,5,6,7,8,9,10] as $k => $v){
			if(!empty($POST["a4_{$x}r{$v}_1"]) && !empty($POST["a4_{$x}r{$v}_2"])) $ERR = 'A4 คู่ตรงข้าม ออกคู่กันไม่ได้';
		}
	}
	if($STEP=='a1_729'){
		$NEXTSTEP = 'a5_765773729';
		$x = '729';
		if(!validate($POST['a1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['a2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['a1_'.$x],[7]) && containsAny($POST['a2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		if(containsAny($POST['a1_'.$x],[1]) && containsAny($POST['a2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		foreach([1,2,3,4,5,6,7,8,9,10,11,12,13,14] as $k => $v){
			if(!validate($POST["a3_p{$v}_{$x}"],'null')) $ERR = $defaulterr;
			if(in_array($v,[2,3,4,5,6,7,10,11,12,13,14])){
				if(!validate($POST["a3a_p{$v}_{$x}"],'null')) $ERR = $defaulterr;	
				if(containsAny($POST["a3_p{$v}_{$x}"],[7]) && containsAny($POST["a3a_p{$v}_{$x}"],[1,2])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
				if(containsAny($POST["a3_p{$v}_{$x}"],[1]) && containsAny($POST["a3a_p{$v}_{$x}"],[4,5])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
			}
		}
		if(!validate(genMAresp("a4_{$x}",$POST,$a4choice),'checkbox')) $ERR = $defaulterr;
		foreach([1,2,3,4,5,6,7,8,9,10] as $k => $v){
			if(!empty($POST["a4_{$x}r{$v}_1"]) && !empty($POST["a4_{$x}r{$v}_2"])) $ERR = 'A4 คู่ตรงข้าม ออกคู่กันไม่ได้';
		}
	}
	// ----------------------
	// Section 2C
	// ----------------------
	if($STEP=='a5_765773729'){
		$NEXTSTEP = 'b1_765972';
		$x = '765773729';
		
		if(!validate($POST['a5a'.$x.'1'],'null')) $ERR = $defaulterr;
		if(!validate($POST['a5a'.$x.'2'],'null')) $ERR = $defaulterr;
		if(!validate($POST['a5a'.$x.'3'],'null')) $ERR = $defaulterr;

		foreach([1,2,3] as $k => $v){
			foreach([1,2,3] as $kk => $vv){
				if($v != $vv){
					if($POST['a5a'.$x.$v]==$POST['a5a'.$x.$vv]) $ERR = 'อันดับห้ามซ้ำกัน';
					// Check overall score
					if(($v < $vv) && ($DATA['a1_'.$POST['a5a'.$x.$v]] < $DATA['a1_'.$POST['a5a'.$x.$vv]])) $ERR = 'ดีไซน์ที่ได้คะแนนมากกว่า อันดับต้องสูงกว่าเสมอ';
				}
			}
		}
	}
	// ----------------------
	// Section 3C
	// ----------------------
	if($STEP=='b1_765972'){
		$NEXTSTEP = 'b1_773972';
		$x = '765972';
		if(!validate($POST['b1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b1_'.$x],[7]) && containsAny($POST['b2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['b1_'.$x],[1]) && containsAny($POST['b2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["b3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['b4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b6_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b8_'.$x],'null')) $ERR = $defaulterr;
		if(!containsAny($POST['b8_'.$x],[15]) && !validate($POST['b9_'.$x],'number')) $ERR = $defaulterr;
		if(!validate($POST['b10_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b10_'.$x],[2,3,4]) && !validate($POST['b11_'.$x],'text')) $ERR = $defaulterr;
	}
	if($STEP=='b1_773972'){
		$NEXTSTEP = 'b1_729972';
		$x = '773972';
		if(!validate($POST['b1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b1_'.$x],[7]) && containsAny($POST['b2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['b1_'.$x],[1]) && containsAny($POST['b2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["b3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['b4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b6_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b8_'.$x],'null')) $ERR = $defaulterr;
		if(!containsAny($POST['b8_'.$x],[15]) && !validate($POST['b9_'.$x],'number')) $ERR = $defaulterr;
		if(!validate($POST['b10_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b10_'.$x],[2,3,4]) && !validate($POST['b11_'.$x],'text')) $ERR = $defaulterr;
	}
	if($STEP=='b1_729972'){
		$NEXTSTEP = 'b12_765773729';
		$x = '729972';
		if(!validate($POST['b1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b1_'.$x],[7]) && containsAny($POST['b2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['b1_'.$x],[1]) && containsAny($POST['b2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["b3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['b4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b6_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b8_'.$x],'null')) $ERR = $defaulterr;
		if(!containsAny($POST['b8_'.$x],[15]) && !validate($POST['b9_'.$x],'number')) $ERR = $defaulterr;
		if(!validate($POST['b10_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b10_'.$x],[2,3,4]) && !validate($POST['b11_'.$x],'text')) $ERR = $defaulterr;
	}
	// ----------------------
	// Section 4C
	// ----------------------
	if($STEP=='b12_765773729'){
		$NEXTSTEP = 'a1_513';
		$x = '765773729';
		
		if(!validate($POST['b12a'.$x.'1'],'null')) $ERR = $defaulterr;
		if(!validate($POST['b12a'.$x.'2'],'null')) $ERR = $defaulterr;
		if(!validate($POST['b12a'.$x.'3'],'null')) $ERR = $defaulterr;

		foreach([1,2,3] as $k => $v){
			foreach([1,2,3] as $kk => $vv){
				if($v != $vv){
					if($POST['b12a'.$x.$v]==$POST['b12a'.$x.$vv]) $ERR = 'อันดับห้ามซ้ำกัน';
					// Check overall score
					if(($v < $vv) && ($DATA['b1_'.$POST['b12a'.$x.$v]] < $DATA['b1_'.$POST['b12a'.$x.$vv]])) $ERR = 'ดีไซน์ที่ได้คะแนนมากกว่า อันดับต้องสูงกว่าเสมอ';
				}
			}
		}
		if(!validate($POST['b14_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b15_'.$x],'text')) $ERR = $defaulterr;
	}


	// -------------------------------- D ------------------------------------
	// ----------------------
	// Section 1D
	// ----------------------
	if($STEP=='a1_513'){
		$NEXTSTEP = 'a1_591';
		$x = '513';
		if(!validate($POST['a1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['a2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['a1_'.$x],[7]) && containsAny($POST['a2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		if(containsAny($POST['a1_'.$x],[1]) && containsAny($POST['a2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		foreach([1,2,3,4,5,6,7,8,9,10,11,12,13,14] as $k => $v){
			if(!validate($POST["a3_p{$v}_{$x}"],'null')) $ERR = $defaulterr;
			if(in_array($v,[2,3,4,5,6,7,10,11,12,13,14])){
				if(!validate($POST["a3a_p{$v}_{$x}"],'null')) $ERR = $defaulterr;	
				if(containsAny($POST["a3_p{$v}_{$x}"],[7]) && containsAny($POST["a3a_p{$v}_{$x}"],[1,2])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
				if(containsAny($POST["a3_p{$v}_{$x}"],[1]) && containsAny($POST["a3a_p{$v}_{$x}"],[4,5])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
			}
		}
		if(!validate(genMAresp("a4_{$x}",$POST,$a4choice),'checkbox')) $ERR = $defaulterr;
		foreach([1,2,3,4,5,6,7,8,9,10] as $k => $v){
			if(!empty($POST["a4_{$x}r{$v}_1"]) && !empty($POST["a4_{$x}r{$v}_2"])) $ERR = 'A4 คู่ตรงข้าม ออกคู่กันไม่ได้';
		}
	}
	if($STEP=='a1_591'){
		$NEXTSTEP = 'a5_513591';
		$x = '591';
		if(!validate($POST['a1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['a2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['a1_'.$x],[7]) && containsAny($POST['a2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		if(containsAny($POST['a1_'.$x],[1]) && containsAny($POST['a2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ A1 vs A2 ต่างขั้วกัน';
		foreach([1,2,3,4,5,6,7,8,9,10,11,12,13,14] as $k => $v){
			if(!validate($POST["a3_p{$v}_{$x}"],'null')) $ERR = $defaulterr;
			if(in_array($v,[2,3,4,5,6,7,10,11,12,13,14])){
				if(!validate($POST["a3a_p{$v}_{$x}"],'null')) $ERR = $defaulterr;	
				if(containsAny($POST["a3_p{$v}_{$x}"],[7]) && containsAny($POST["a3a_p{$v}_{$x}"],[1,2])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
				if(containsAny($POST["a3_p{$v}_{$x}"],[1]) && containsAny($POST["a3a_p{$v}_{$x}"],[4,5])) $ERR = 'กรุณาเช็คคะแนนความชอบ vs ความพึงพอใจ';
			}
		}
		if(!validate(genMAresp("a4_{$x}",$POST,$a4choice),'checkbox')) $ERR = $defaulterr;
		foreach([1,2,3,4,5,6,7,8,9,10] as $k => $v){
			if(!empty($POST["a4_{$x}r{$v}_1"]) && !empty($POST["a4_{$x}r{$v}_2"])) $ERR = 'A4 คู่ตรงข้าม ออกคู่กันไม่ได้';
		}
	}
	// ----------------------
	// Section 2D
	// ----------------------
	if($STEP=='a5_513591'){
		$NEXTSTEP = 'b1_513951';
		$x = '513591';
		
		if(!validate($POST['a5a'.$x.'1'],'null')) $ERR = $defaulterr;
		if(!validate($POST['a5a'.$x.'2'],'null')) $ERR = $defaulterr;

		foreach([1,2] as $k => $v){
			foreach([1,2] as $kk => $vv){
				if($v != $vv){
					if($POST['a5a'.$x.$v]==$POST['a5a'.$x.$vv]) $ERR = 'อันดับห้ามซ้ำกัน';
					// Check overall score
					if(($v < $vv) && ($DATA['a1_'.$POST['a5a'.$x.$v]] < $DATA['a1_'.$POST['a5a'.$x.$vv]])) $ERR = 'ดีไซน์ที่ได้คะแนนมากกว่า อันดับต้องสูงกว่าเสมอ';
				}
			}
		}
	}
	// ----------------------
	// Section 3D
	// ----------------------
	if($STEP=='b1_513951'){
		$NEXTSTEP = 'b1_591951';
		$x = '513951';
		if(!validate($POST['b1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b1_'.$x],[7]) && containsAny($POST['b2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['b1_'.$x],[1]) && containsAny($POST['b2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["b3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['b4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b6_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b8_'.$x],'null')) $ERR = $defaulterr;
		if(!containsAny($POST['b8_'.$x],[15]) && !validate($POST['b9_'.$x],'number')) $ERR = $defaulterr;
		if(!validate($POST['b10_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b10_'.$x],[2,3,4]) && !validate($POST['b11_'.$x],'text')) $ERR = $defaulterr;
	}
	if($STEP=='b1_591951'){
		$NEXTSTEP = 'b12_513591';
		$x = '591951';
		if(!validate($POST['b1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b1_'.$x],[7]) && containsAny($POST['b2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['b1_'.$x],[1]) && containsAny($POST['b2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		foreach(['i1','i2','i3','i4','i5','i6','i7','t1','t2','t3','t4','t5','t6','t7','t8'] as $k => $v){
			if(!validate($POST["b3_{$v}_{$x}"],'null')) $ERR = $defaulterr;
		}
		if(!validate($POST['b4_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b5_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b6_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b7_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b8_'.$x],'null')) $ERR = $defaulterr;
		if(!containsAny($POST['b8_'.$x],[15]) && !validate($POST['b9_'.$x],'number')) $ERR = $defaulterr;
		if(!validate($POST['b10_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b10_'.$x],[2,3,4]) && !validate($POST['b11_'.$x],'text')) $ERR = $defaulterr;
	}
	// ----------------------
	// Section 4D
	// ----------------------
	if($STEP=='b12_513591'){
		$NEXTSTEP = 'fa_202';
		$x = '513591';
		
		if(!validate($POST['b12a'.$x.'1'],'null')) $ERR = $defaulterr;
		if(!validate($POST['b12a'.$x.'2'],'null')) $ERR = $defaulterr;

		foreach([1,2] as $k => $v){
			foreach([1,2] as $kk => $vv){
				if($v != $vv){
					if($POST['b12a'.$x.$v]==$POST['b12a'.$x.$vv]) $ERR = 'อันดับห้ามซ้ำกัน';
					// Check overall score
					if(($v < $vv) && ($DATA['b1_'.$POST['b12a'.$x.$v]] < $DATA['b1_'.$POST['b12a'.$x.$vv]])) $ERR = 'ดีไซน์ที่ได้คะแนนมากกว่า อันดับต้องสูงกว่าเสมอ';
				}
			}
		}
		if(!validate($POST['b14_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b15_'.$x],'text')) $ERR = $defaulterr;
	}


	// ----------------------
	// Section Final 1
	// ----------------------
	if($STEP=='fa_202'){
		$NEXTSTEP = 'fa_205';
		$x = '202';
		if(!validate($POST['a1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['a2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['a1_'.$x],[7]) && containsAny($POST['a2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['a1_'.$x],[1]) && containsAny($POST['a2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(!validate($POST['b1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b1_'.$x],[7]) && containsAny($POST['b2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['b1_'.$x],[1]) && containsAny($POST['b2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
	}
	if($STEP=='fa_205'){
		$NEXTSTEP = 'z1';
		$x = '205';
		if(!validate($POST['a1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['a2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['a1_'.$x],[7]) && containsAny($POST['a2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['a1_'.$x],[1]) && containsAny($POST['a2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(!validate($POST['b1_'.$x],'null')) $ERR = $defaulterr;
		if(!validate($POST['b2_'.$x],'null')) $ERR = $defaulterr;
		if(containsAny($POST['b1_'.$x],[7]) && containsAny($POST['b2_'.$x],[1,2])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
		if(containsAny($POST['b1_'.$x],[1]) && containsAny($POST['b2_'.$x],[4,5])) $ERR = 'เช็คคำตอบ B1 vs B2 ต่างขั้วกัน';
	}

	// ----------------------
	// Section Final 2
	// ----------------------
	if($STEP=='z1'){
		$NEXTSTEP = 'z7';

		foreach([1,2,3,4,5,6] as $k => $v){
			foreach([1,2,3,4,5,6] as $kk => $vv){
				if($v != $vv){
					if($POST['z1a'.$v]==$POST['z1a'.$vv]) $ERR = 'อันดับห้ามซ้ำกัน';
					// Check overall score
					if(($v < $vv) && ($DATA['b1_'.$POST['z1a'.$v]] < $DATA['b1_'.$POST['z1a'.$vv]])) $ERR = 'ดีไซน์ที่ได้คะแนนมากกว่า อันดับต้องสูงกว่าเสมอ';
				}
			}
		}
	}
	if($STEP=='z7'){
		$NEXTSTEP = 'thank';
		
		if(!validate($POST['z7'],'text')) $ERR = $defaulterr;
		if(!validate($POST['z8'],'text')) $ERR = $defaulterr;
		if(!validate($POST['z9'],'null')) $ERR = $defaulterr;
		if($POST['z9']==3 && !validate($POST['z93_oth'],'text')) $ERR = $defaulterr;

	}

	if($STEP=="thank"){
		$NEXTSTEP = false;
		
	}
	if($STEP=='TERM'){
		$NEXTSTEP = "TERMACT";		
	}
	if($STEP=='TERMACT'){
		$NEXTSTEP = false;
	}
	
	############################
	### LOGIC ROUTING END
	############################		
		
		// allow to click Next without validating
		// $ERR = "";


	############################
	### FUNCTION RETURN OPTION
	############################		
		$ARR = [];
		$ARR['ERR'] = $ERR;
		$ARR['POST'] = $POST;
		$ARR['COMPLETE'] = $COMPLETE;
		$ARR['STEPN'] = $STEPN;
		$ARR['NEXTSTEP'] = $NEXTSTEP;

		return $ARR;
	}
