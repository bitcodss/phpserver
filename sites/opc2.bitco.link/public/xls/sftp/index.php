<?php

##########################
### SFTP LOGIN
##########################
date_default_timezone_set('Asia/Bangkok');

##########################
### SQL LOGIN
##########################
/** Config */
require('../../api/config.php');
require('../label.php');

/** PHPExcel */
require_once '../Classes/PHPExcel.php';
 
/** PHPExcel_IOFactory - Reader */
include '../Classes/PHPExcel/IOFactory.php';

$pdo = new PDO('mysql:dbname='.$_CONFIG['db'].';host='.$_CONFIG['host'].';charset=utf8mb4', $_CONFIG['usr'], $_CONFIG['psw']) 
or die('Cannot connect mySQL');


##########################
### Loop's variables
##########################
$path = '../../source/output/';
$loop = [
    'csi' => 'csi/raw_cis_20220409.xlsx',
    'ssi' => 'ssi/raw_ssi_20220409.xlsx',
    'am' => 'am_20220411.txt',
    'new_chas' => 'chassis.dat',
    'code' => 'master_user/code_mapping_20220228.xlsx',
    'emp' => 'master_user/emp_dealer_20220228.xlsx',
    'mmth' => 'master_user/emp_mmth_20220228.xlsx',
    'onlinecsi1' => 'online/csi_survey_100_19999_20220222.xlsx',
    'onlinecsi2' => 'online/csi_survey_20000_39999_20220222.xlsx',
    'onlinecsi3' => 'online/csi_survey_morethan_40000_20220222.xlsx',
    'onlinessi' => 'online/ssi_survey_20220228.xlsx'
];
$loop = [
    'csi' => 'csi/raw_cis_20220409.xlsx',
    'ssi' => 'ssi/raw_ssi_20220409.xlsx',
    'new_chas' => 'chassis.dat'
];

$curD = date("Ymd");
$progress = [];

##########################
### Loop Contents
##########################


// $query = $pdo->prepare("DELETE FROM import_SSI;");
// $quers = $query->execute();  

// $query = $pdo->prepare("DELETE FROM import_CSI;");
// $quers = $query->execute();  

foreach($loop as $k => $v){
    $filename = "{$v}";
    $filePath = "{$path}{$filename}";

    if (!file_exists($filePath)) {
        $progress[] = "Cannot find '{$filePath}', stop processing on ".date("H:i:s").PHP_EOL."<br/>";
        Goto _EndLoop;
    }

    $progress[] = "Found '{$filePath}', start importing on ".date("H:i:s").PHP_EOL."<br/>";

    // -------------------------------
    // E a c h  F i l e
    // -------------------------------
    if($k=='ssi'){
        $arr = ['SEQ','DEALERCODE','CONTNO','SALETYPE','TITLENAME','FISTNAME','LASTNAME','CUSTNO','PHONE','CHASNO','ENGNO','DELIVERYDTE','MODELGROUP','COLOR','YEAR','OPTIONS','PRDCODE','THDESC','SALEMANNAME','SALEMAN_TELNO','UPDDTE','ACTION'];

        $inputFileName = $filePath;
        $inputFileType = PHPExcel_IOFactory::identify($inputFileName);  
        $objReader = PHPExcel_IOFactory::createReader($inputFileType);  
        $objReader->setReadDataOnly(true);  
        $objPHPExcel = $objReader->load($inputFileName);  
         
        $objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
        $highestRow = $objWorksheet->getHighestRow();
        $highestColumn = $colalp[count($arr)];
         
        $headingsArray = $objWorksheet->rangeToArray('A1:'.$highestColumn.'1',null, true, true, true);
        $headingsArray = $headingsArray[1];

        $r = -1;
        $namedDataArray = array();
        for ($row = 2; $row <= $highestRow; ++$row) {
            $dataRow = $objWorksheet->rangeToArray('A'.$row.':'.$highestColumn.$row,null, true, true, true);
            if ((isset($dataRow[$row]['A'])) && ($dataRow[$row]['A'] > '')) {
                ++$r;
                foreach($headingsArray as $columnKey => $columnHeading) {
                    $namedDataArray[$r][$columnHeading] = $dataRow[$row][$columnKey];
                }
            }
        }
        
        $sql_header = "INSERT INTO import_SSI (SEQ,DEALERCODE,CONTNO,SALETYPE,TITLENAME,FIRSTNAME,LASTNAME,CUSTNO,PHONE,CHASNO,ENGNO,DELIVERYDTE,MODELGROUP,COLOR,YEAR,OPTIONS,PRDCODE,THDESC,SALEMANNAME,SALEMAN_TELNO,UPDDTE,ACTION) VALUES "; 
        $sql_body = [];

        foreach ($namedDataArray as $resx) {
            $sql_body[] = "('".implode("','", $resx)."')";
        }
        
        // -------------------------
        // SQL
        $sql = $sql_header.implode(',',$sql_body);

    }
    elseif($k=='csi'){
        $arr = ['NO','OFFICECODE','ROCODE','RODATE','CLOSEDATE','MODELCODE','MODELDESC','CARCODE','CHASSIS','ENGINECODE','MILEAGE','OWNERNAME','IDNO','OWNERTEL1','OWNERTEL2','CUSTOMER','TELNO','ROTYPE','OPERATIONTYPE','OPERATIONCODE','CATGORYDESCRIPTION','PRODUCTCODE','PRODUCTDESCRIPTION','CHARGETYPE','QTY','UNITPRICE','DISCOUNT','SA_NAME','PART_NAME','TECHNECAIL_NAME'];

        $inputFileName = $filePath;
        $inputFileType = PHPExcel_IOFactory::identify($inputFileName);  
        $objReader = PHPExcel_IOFactory::createReader($inputFileType);  
        $objReader->setReadDataOnly(true);  
        $objPHPExcel = $objReader->load($inputFileName);  
         
        $objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
        $highestRow = $objWorksheet->getHighestRow();
        $highestColumn = $colalp[count($arr)];
         
        $headingsArray = $objWorksheet->rangeToArray('A1:'.$highestColumn.'1',null, true, true, true);
        $headingsArray = $headingsArray[1];

        $r = -1;
        $namedDataArray = array();
        for ($row = 2; $row <= $highestRow; ++$row) {
            $dataRow = $objWorksheet->rangeToArray('A'.$row.':'.$highestColumn.$row,null, true, true, true);
            if ((isset($dataRow[$row]['A'])) && ($dataRow[$row]['A'] > '')) {
                ++$r;
                foreach($headingsArray as $columnKey => $columnHeading) {
                    $namedDataArray[$r][$columnHeading] = $dataRow[$row][$columnKey];
                }
            }
        }
        
        $sql_header = "INSERT INTO import_CSI (NO,OFFICECODE,ROCODE,RODATE,CLOSEDATE,MODELCODE,MODELDESC,CARCODE,CHASSIS,ENGINECODE,MILEAGE,OWNERNAME,IDNO,OWNERTEL1,OWNERTEL2,CUSTOMER,TELNO,ROTYPE,OPERATIONTYPE,OPERATIONCODE,CATEGORYDESCRIPTION,PRODUCTCODE,PRODUCTDESCRIPTION,CHARGETYPE,QTY,UNITPRICE,DISCOUNT,SA_NAME,PART_NAME,TECHNECAIL_NAME) VALUES "; 
        $sql_body = [];

        foreach ($namedDataArray as $resx) {
            $sql_body[] = "('".implode("','", $resx)."')";
        }
        
        // -------------------------
        // SQL
        $sql = $sql_header.implode(',',$sql_body);
    }
    elseif($k=='am'){
        $arr = ['DEALER_CODE','AM_ID','LEADER_ID','FLAG'];

        

    }
    elseif($k=='new_chas'){
        $arr = ['CHASSIS','SOLDATE','DEALER','TYPE'];

        $open = fopen($filePath,"r");
        $sql_header = "INSERT INTO info_CHASSIS (CHASSIS,SOLDATE,DEALER,TYPE) VALUES "; 
        $sql_body = [];
        
        while (!feof($open)) 
        {
            $getTextLine = fgets($open);
            if(empty($getTextLine)) Goto _End_new_chas;

            $explodeLine = explode("|",$getTextLine);

            list($CHASSIS,$SOLDATE,$DEALER,$TYPE) = $explodeLine;

            // Skip
            if(empty($CHASSIS) || $CHASSIS=='CHASSIS') Goto _End_new_chas;
            
            $sql_body[] = "('{$CHASSIS}','{$SOLDATE}','{$DEALER}','{$TYPE}')";

            _End_new_chas:
        }
        
        fclose($open);

        // -------------------------
        // SQL
        $sql = $sql_header.implode(',',$sql_body);
        $sql .= " ON DUPLICATE KEY UPDATE SOLDATE=IF(SOLDATE='' AND VALUES(SOLDATE) NOT IN ('','00000000','0'), VALUES(SOLDATE), SOLDATE), DEALER=IF(DEALER='' AND VALUES(DEALER)!='', VALUES(DEALER), DEALER) ;";

    }
    elseif($k=='code'){
        $arr[0] = ['SAP','COMMERCIAL'];
        $arr[1] = ['OPERATION CODE','OPERATION DESC','Ref. Code','MILEAGE'];
        $arr[2] = ['SAP','SAP','COMMERCIAL'];
        for($i=0;$i<3;$i++){
        }

    }
    elseif($k=='emp'){
        $arr = ['เลขที่บัตรประชาชน','ชื่อ','นามสกุล','emp_telnumber'];

    }
    elseif($k=='mmth'){
        $arr = ['No.','Employee ID','Frist Name','Last Name','Number'];

    }
    elseif($k=='onlinecsi1'){
        $arr = ['CSI_ID','DEALERCODE','Chassis No.','Customer Name','TEL.','CLOSEDATE','MILEAGE','OPERATIONCODE','OPERATIONTYPE','PRODUCTDESCRIPTION','SA_NAME','CARCODE','Car Aging (Year)','Question_1','Question_2','Question_3','Question_4','Question_5','Question_6','Question_7','Question_8','Question_9','Question_10','Question_11','Question_12','Question_13','Question_14','Question_15','Question_16','Question_17','Question_18','Question_19','Question_20','Question_21','Question_22','Question_23','Question_24','Question_25','Question_26'];

    }
    elseif($k=='onlinecsi2'){
        $arr = ['CSI_ID','DEALERCODE','Chassis No.','Customer Name','TEL.','CLOSEDATE','MILEAGE','OPERATIONCODE','OPERATIONTYPE','PRODUCTDESCRIPTION','SA_NAME','CARCODE','Car Aging (Year)','Question_1','Question_2','Question_3','Question_4','Question_5','Question_6','Question_7','Question_8','Question_9','Question_10','Question_11','Question_12','Question_13','Question_14','Question_15','Question_16','Question_17','Question_18','Question_19','Question_20','Question_21','Question_22','Question_23','Question_24','Question_25','Question_26','Question_27'];

    }
    elseif($k=='onlinecsi3'){
        $arr = ['CSI_ID','DEALERCODE','Chassis No.','Customer Name','TEL.','CLOSEDATE','MILEAGE','OPERATIONCODE','OPERATIONTYPE','PRODUCTDESCRIPTION','SA_NAME','CARCODE','Car Aging (Year)','Question_1','Question_2','Question_3','Question_4','Question_5','Question_6','Question_7','Question_8','Question_9','Question_10','Question_11','Question_12','Question_13','Question_14','Question_15','Question_16','Question_17','Question_18','Question_19','Question_20','Question_21','Question_22','Question_23','Question_24','Question_25','Question_26','Question_27','Question_28'];

    }
    elseif($k=='onlinessi'){
        $arr = ['SSI_ID','DEALERCODE','CHASNO','Customer Name','TEL.','DELIVERYDTE','SALEMANNAME','CARCDE','COLOR','SALETYPE','MODELGROUP','Question_1','Question_2','Question_3','Question_4','Question_5','Question_6','Question_7','Question_8','Question_9','Question_10','Question_11','Question_12','Question_13','Question_14','Question_15','Question_16','Question_17','Question_18','Question_19','Question_20'];

    }



    ##########################
    ### SQL INSERT
    ##########################


    $query = $pdo->prepare($sql);
    $quers = $query->execute();   
    if($quers) $progress[] = "Import ".$query->rowCount()." rows for ".$v."; filepath=".$filePath." has been done.".PHP_EOL."<br/>";
    else $progress[] = "Failed to import with error ".$query->errorInfo()[2]."; filepath=".$filePath.".".PHP_EOL."<br/>";

    _EndLoop:
    $progress[] = "Finish importing on ".date("H:i:s").PHP_EOL."<br/><br/>";
    
}

##########################
### Output
##########################

_EndFile:
echo implode("\n", $progress);