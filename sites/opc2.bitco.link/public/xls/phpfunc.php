<?php
$columnArray = array("", "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z");

function setStyle($sht,$cell,$style,$opt,$opt2 = false,$opt3 = false){
	global $columnArray;
	$GLOBALS['col'] = $columnArray;

	if($style=='width'){
		$opt = $opt + 0.71;
		$sht->getColumnDimension($cell)->setWidth($opt);
	} 
	elseif($style=='height'){
		$sht->getRowDimension($cell)->setRowHeight($opt);
	} 
	elseif($style=='halign'){
		if($opt=='left') $sht->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
		if($opt=='center') $sht->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
		if($opt=='right') $sht->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
		if($opt=='wordwrap') $sht->getStyle($cell)->getAlignment()->setWrapText(true); 
	} 
	elseif($style=='fontStyle'){
		if($opt=='bold') $sht->getStyle($cell)->getFont()->setBold( true );
	} 
	elseif($style=='valign'){
		if($opt=='middle') $sht->getStyle($cell)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
		if($opt=='top') $sht->getStyle($cell)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
		if($opt=='bottom') $sht->getStyle($cell)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_BOTTOM);
	}
	elseif($style=='bgcolor'){
		$sht->getStyle($cell)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB($opt);
	}
	elseif($style=='border'){
        $c = explode(":",$cell);
        
        if(count($c)>1){
			for($x=0;$x<=1;$x++){
				
				// Find digit of row
				for($i=1;$i<=strlen($c[$x]);$i++){
					if(is_numeric(substr($c[$x], -$i))) $digit = $i; // ABC"1"
				}
				$alpha = strlen($c[$x]) - $digit; // ABC = 3
				
				// Each char
				$col[$x][0] = 0;
				$col[$x][1] = 0;
				if($alpha > 2) $col[$x][0] = array_search(substr($c[$x],$alpha-3,1), $GLOBALS['col']);
				if($alpha > 1) $col[$x][1] = array_search(substr($c[$x],$alpha-2,1), $GLOBALS['col']);
				if($alpha > 0) $col[$x][2] = array_search(substr($c[$x],$alpha-1,1), $GLOBALS['col']);

				$row[$x] = substr($c[$x],strlen($c[$x])-$digit,strlen($c[$x])-$alpha);            		

				$rng[$x] = ($col[$x][0]*26*26) + ($col[$x][1]*26) + $col[$x][2];
			}			
		}
        
		if(!$opt2) $opt2 = 'THIN';
		if($opt2=='THIN') $border = array('style' => PHPExcel_Style_Border::BORDER_THIN);
		elseif($opt2=='MEDIUM') $border = array('style' => PHPExcel_Style_Border::BORDER_MEDIUM);
		elseif($opt2=='DOTTED') $border = array('style' => PHPExcel_Style_Border::BORDER_DOTTED);
		elseif($opt2=='NONE') $border = array('style' => PHPExcel_Style_Border::BORDER_NONE);
		if($opt3) $border['color'] = ['rgb' => $opt3];

		for($i=$rng[0];$i<=$rng[1];$i++){
			
			$cel = cell($i,$row[0]).':'.cell($i,$row[1]);
			if($opt=='all') $sht->getStyle($cel)->applyFromArray(array('borders' => array('allborders' => $border)));
			if($opt=='top') $sht->getStyle($cel)->applyFromArray(array('borders' => array('top' => $border)));
			if($opt=='bottom') $sht->getStyle($cel)->applyFromArray(array('borders' => array('bottom' => $border)));
			if($opt=='left') $sht->getStyle($cel)->applyFromArray(array('borders' => array('left' => $border)));
			if($opt=='right') $sht->getStyle($cel)->applyFromArray(array('borders' => array('right' => $border)));
			if($opt=='inside') $sht->getStyle($cel)->applyFromArray(array('borders' => array('inside' => $border)));
			if($opt=='tb') $sht->getStyle($cel)->applyFromArray(array('borders' => array('top' => $border
				, 'bottom' => $border)));
			if($opt=='lr') $sht->getStyle($cel)->applyFromArray(array('borders' => array('right' => $border
				, 'left' => $border)));

		}
	}
	elseif($style=='fontSize'){
		$sht->getStyle($cell)->getFont()->setSize($opt);
	}
	elseif($style=='fontColor'){
		$sht->getStyle($cell)->getFont()->getColor()->setRGB($opt);
	}
	elseif($style=='rotation'){
		$sht->getStyle($cell)->getAlignment()->setTextRotation($opt);
	}
	elseif($style=='cellformat'){
		$sht->getStyle($cell)->getNumberFormat()->setFormatCode($opt);
	}
	elseif($style==''){
	}
	elseif($style==''){
	}
}

function cell($c,$r){
	include('label.php');

	return $colalp[$c].$r;
}
function row($c){
	include('label.php');

	return $colalp[$c];
}
function column($c){
	include('label.php');

	return $colalp[$c];
}
function getLabel($v,$list){
	$getLabel = '';
	$delimiter = ';';

	// Check if MA
	$ma = false;
	if(strpos($v,$delimiter)!==False) $ma = true;
	if($ma) $vv = explode($delimiter,$v);

	$l = explode(';',$list);
	$c = [];
	for($i=0;$i<count($l);$i++){
		$r = explode(':',$l[$i]);
		if(!$ma) {
			if($v==$r[0]) $getLabel = $r[1];
		} else {			
			if($vv[$i]) {				
				$c[] = $r[1];
			}
		}
	}
	if($ma) $getLabel = implode(', ', $c); 
	if(empty($getLabel)) $getLabel = $v;
	return $getLabel;

}
