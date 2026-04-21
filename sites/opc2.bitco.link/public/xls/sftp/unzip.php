<?php
include('Net/SFTP.php');

##########################
### SFTP LOGIN
##########################
date_default_timezone_set('Asia/Bangkok');
$sftp = new Net_SFTP('159.223.55.145');
if (!$sftp->login('mmth', 'HQfSg7ENd26aVfMR')) {
    exit('Login Failed');
}


##########################
### Loop's variables
##########################

$curD = date("Ymd");

$path = "uploads/";
$list = $sftp->nlist($path);

$destpath = "/home/runcloud/webapps/app-mmth/source/";


##########################
### M A I N  C O N T E N T
##########################

// ----------------------------------
// Clear all files and folder in destination folder
// ----------------------------------
deleteAll($destpath);
// ----------------------------------
// Mkdir output/
// ----------------------------------
mkdir($destpath.'output');



// ----------------------------------
// Download Files
// ----------------------------------
foreach($list as $k => $v){

    if(in_array($v,['.','..'])) Goto _EndLoopDL;

    $fromfile = $path.$v;
    $tofile = $destpath.$v;
    $move = $sftp->get($fromfile, $tofile);

    if($move === true) {
        echo "Move from {$fromfile} to {$tofile} is done!<br/>";
    } else {
        echo "Move from {$fromfile} to {$tofile} is error!<br/>";
    }

    _EndLoopDL:
}



// ----------------------------------
// Unzip Files
// ----------------------------------
$zip = new ZipArchive;
$zipextpath = $destpath.'output/';
foreach($list as $k => $v){

    if(in_array($v,['.','..'])) Goto _EndLoopZIP;
    if(!in_array(substr($v,-4),['.zip'])) Goto _EndLoopZIP;
    
    $zipfile = $destpath.$v;
    $res = $zip->open($zipfile);

    if ($res === TRUE) {
        $zip->extractTo($zipextpath);
        $zip->close();
        echo "Extracting Zip from {$zipfile} is done!<br/>";
    } else {
        echo "Extracting Zip from {$zipfile} is error!<br/>";
    }
    _EndLoopZIP:
}

// ----------------------------------
// Unzip again Files
// ----------------------------------
$dircontents = scandir($zipextpath);
foreach ($dircontents as $filename) {
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if ($extension == 'zip') {
        $zipfile = $zipextpath.$filename;
        $res = $zip->open($zipfile);

        if ($res === TRUE) {
            $zip->extractTo($zipextpath);
            $zip->close();
            unlink($zipfile);
            echo "Extracting Zip from {$zipfile} is done!<br/>";
        } else {
            echo "Extracting Zip from {$zipfile} is error!<br/>";
        }
    }
}


// ----------------------------------
// Unzip GZ Files
// ----------------------------------

// Raising this value may increase performance
$buffer_size = 10240; // read 10kb at a time

$dircontents = scandir($destpath);
foreach ($dircontents as $filename) {
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if ($extension == 'gz') {
        $outfilename = strtr($filename,['.gz'=>'']);
        un_gzip($destpath.$filename, $zipextpath.$outfilename.'.dat', true);
        echo "Extracting GZip from {$filename} is done!<br/>";
    }
}



// ----------------------------------
// Output
// ----------------------------------

_EndFile:






// ----------------------------------
// Function
// ----------------------------------

function deleteAll($dir) {
    foreach(glob($dir . '/*') as $file) {
        if(is_dir($file)){
            deleteAll($file);
            rmdir($file);
        }
        else {
            unlink($file);
        }
    }
}

////////////////////////////////////
//
// Ink Plant Function to Unzip .gz Files
// https://inkplant.com/code/unzip-gz-files-php-function
// Last updated May 6, 2019
//
////////////////////////////////////

function un_gzip($gz_filename, $output_filename=null, $allow_overwrite=false, $read_chunk_length=10240) {

	//error check zipped file
	if (!$gz_filename) { return un_gzip_error('Can’t unzip without a filename.'); }
	if (strtolower(substr($gz_filename,-3)) != '.gz') { return un_gzip_error('The provided filename does not have the expected .gz extension.'); }
	if (!file_exists($gz_filename)) { return un_gzip_error('The zipped file does not exist.'); }

	//error check output file
	if (!$output_filename) { $output_filename = substr($gz_filename,0,-3); } //just drop the .gz from incoming file by default
	if ((!$allow_overwrite) && file_exists($output_filename)) { return un_gzip_error('A file already exists at the output file location.'); }
	if (file_exists($output_filename) && (!is_writable($output_filename))) { return un_gzip_error('The output file location is not writeable.'); }

	//open the files
	$gz = gzopen($gz_filename, 'rb');
	if (!$gz) { return un_gzip_error('The zipped file cannot be opened for reading.'); }
	$out = fopen($output_filename, 'wb');
	if (!$out) { return un_gzip_error('The output file cannot be opened for writing.'); }

	//keep unzipping $read_chunk_length bytes at a time until we hit the end of the file
	while (!gzeof($gz)) {
		$unzipped = gzread($gz, $read_chunk_length);
	if (fwrite($out, $unzipped) === false) { return un_gzip_error('There was an error writing to the output file.'); }
	}

	//close the files
	gzclose($gz);
	fclose($out);

	//return the output filename
	return $output_filename;
}

function un_gzip_error($error) {
	echo '<div class="alert alert-danger">Error: '.$error.'</div>';
	return false;
}

