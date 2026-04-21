<?php

/*******EDIT LINES 3-8*******/
include('../api/config.php');

$name = 'Magellanic';
$token = false;
$token = $_GET['token'];

if($token != '1D7A6E11085351298CC890625C16D68ABD6C85125C513FDE614B0B49533BF2A7'){
    echo "Wrong Token";
    exit;
}

/*******YOU DO NOT NEED TO EDIT ANYTHING BELOW THIS LINE*******/    
//create MySQL connection   

$sql = "SELECT *
 FROM survey_main WHERE status='FiNISH' OR lastlogic >= 20; ";

$conn = mysqli_connect($_CONFIG['host'],$_CONFIG['usr'],$_CONFIG['psw'],$_CONFIG['db']) or die("Cannot connect to SQL");
$conn->set_charset("tis620");

//execute query 
$result = @mysqli_query($conn,$sql) or die("Couldn't execute query:<br>" . mysqli_connect_error(). "<br>" . mysqli_connect_errno());    
$file_ending = "xls";
//header info for browser
header('Content-Type:text/html; charset=tis-620');
header("Content-Type: application/xls");    
header("Content-Disposition: attachment; filename={$name}_".date("m-d-y").".xls");  
header("Pragma: no-cache"); 
header("Expires: 0");
/*******Start of Formatting for Excel*******/   
//define separator (defines columns in excel & tabs in word)
$sep = "\t"; //tabbed character


//start of printing column label above names of MySQL fields
// for ($i = 0; $i < mysqli_num_fields($result); $i++) {
// 	if($lb[mysqli_field_name($result,$i)]) echo $lb[mysqli_field_name($result,$i)]. "\t";
// 	else echo "". "\t";
// }
// print("\n");    


//start of printing column names as names of MySQL fields
for ($i = 0; $i < mysqli_num_fields($result); $i++) {
	echo mysqli_field_name($result,$i) . "\t";
}
print("\n");  


//end of printing column names  
//start while loop to get data
    while($row = mysqli_fetch_row($result))
    {
        $schema_insert = "";
        for($j=0; $j<mysqli_num_fields($result);$j++)
        {
            if(!isset($row[$j]))
                $schema_insert .= "".$sep;
            elseif ($row[$j] != "")
                $schema_insert .= "$row[$j]".$sep;
            else
                $schema_insert .= "".$sep;
        }
        $schema_insert = str_replace($sep."$", "", $schema_insert);
        $schema_insert = preg_replace("/\r\n|\n\r|\n|\r/", " ", $schema_insert);
        $schema_insert .= "\t";
        print(trim($schema_insert));
        print "\n";
    }   

function mysqli_field_name($result, $field_offset)
{
    $properties = mysqli_fetch_field_direct($result, $field_offset);
    return is_object($properties) ? $properties->name : null;
}

?>