<?php
/**
 * LMLPHP Framework
 * Copyright (c) 2014 http://lmlphp.com All rights reserved.
 * Licensed ( http://mit-license.org/ )
 * Author: leiminglin <leiminglin@126.com>
 * 
 * A fully object-oriented PHP framework.
 * Keep it light, magnificent, lovely.
 * 
 * $id: $
 * 
 */

/**
function getRemoteLmlPhp(){
	$cache_filename = 'lml.min.php';
	$remotelib = 'http://git.oschina.net/leiminglin/LMLPHP/raw/master/lml.min.php';
	if( file_exists( $cache_filename ) ) {
		$cachemtime = filemtime($cache_filename);
		if( $cachemtime + 86400 > time() ){
			require $cache_filename;
			return;
		}
		$header = get_headers($remotelib);
		$lastmtime = 0;
		foreach ($header as $k){
			if( preg_match('/^Last-Modified:/i', $k) ){
				$lastmtime = strtotime(preg_replace('/^Last-Modified:/i', '', $k));
				break;
			}
		}
		if( $lastmtime <= $cachemtime ){
			touch($cache_filename);
			require $cache_filename;
			return;
		}
	}
	$code = file_get_contents( $remotelib );
	file_put_contents($cache_filename, $code);
	eval('?>'.$code);
}
getRemoteLmlPhp();
*/

function lml($s='') {
	if($s == ''){
		return Lmlphp::getInstance();
	}else{
		return LmlDispatch::getInstance()->setWhat($s);
	}
}

/**
 * 
 * @author leiminglin@126.com
 *
 */
class Lmlphp {

	const appName = 'LMLPHP';
	const version = '1.0';
	private static $instance = '';
	private static $split = '';
	private static $filenamePrefix = 'debug_';
	private static $filenameSuffix = '.txt';
	private static $isOutDateInfo = true;
	private static $isOutSplit = true;
	private static $flags = FILE_APPEND;
	private static $resval;
	
	private $app;

	private function __construct(){}

	public static function getInstance(){
		if( self::$instance instanceof self ) {
			return self::$instance;
		}
		define('IS_CGI',substr(PHP_SAPI, 0,3)=='cgi' ? 1 : 0 );
		define('IS_WIN',strstr(PHP_OS, 'WIN') ? 1 : 0 );
		define('IS_CLI',PHP_SAPI=='cli'? 1 : 0);
		if( IS_WIN ){
			define('ENDL', "\r\n");
		}else{
			define('ENDL', "\n");
		}
		if( IS_CLI ){
			define('BR', "\n");
		}else {
			define('BR', "<br/>");
		}
		self::$split = str_pad('', 50, '-');
		return self::$instance = new self();
	}
	
	private static function init(){
		if(version_compare(PHP_VERSION,'5.4.0','<')) {
			ini_set('magic_quotes_runtime',0);
			define('MAGIC_QUOTES_GPC',get_magic_quotes_gpc()?true:false);
		}else{
			define('MAGIC_QUOTES_GPC',false);
		}
		if( MAGIC_QUOTES_GPC ){
			$_POST = self::deepStripSlashes($_POST);
			$_GET = self::deepStripSlashes($_GET);
			$_COOKIE = self::deepStripSlashes($_COOKIE);
		}
		$p = DIRECTORY_SEPARATOR;
		defined('PATH_PARAM') || define('PATH_PARAM', 'path');
		define('SCRIPT_DIR', realpath(dirname($_SERVER['SCRIPT_FILENAME'])));
		define('SCRIPT_PATH', SCRIPT_DIR.$p);
		if( !defined('APP_DIR') ){
			define('APP_DIR', SCRIPT_PATH.'App');
			$app_abs_dir = APP_DIR;
		}else{
			if(!is_dir(APP_DIR)){
				LmlUtils::mkdirDeep(APP_DIR);
			}
			$app_abs_dir = realpath(APP_DIR);
		}
		define('APP_PATH', $app_abs_dir.$p);
		defined('CONTENT_TYPE') || define('CONTENT_TYPE', 'text/html');
		defined('CHARSET') || define('CHARSET', 'utf-8');
		defined('MODULE_DIR_NAME') || define('MODULE_DIR_NAME', 'module');
		defined('MODEL_DIR_NAME') || define('MODEL_DIR_NAME', 'model');
		defined('LIB_DIR_NAME') || define('LIB_DIR_NAME', 'lib');
		defined('LOG_DIR_NAME') || define('LOG_DIR_NAME', 'log');
		defined('THEMES_DIR_NAME') || define('THEMES_DIR_NAME', 'themes');
		defined('DEFAULT_THEME_NAME') || define('DEFAULT_THEME_NAME', 'default');
		define('MODULE_PATH', APP_PATH.MODULE_DIR_NAME.$p);
		define('MODEL_PATH', APP_PATH.MODEL_DIR_NAME.$p);
		define('LIB_PATH', APP_PATH.LIB_DIR_NAME.$p);
		define('LOG_PATH', APP_PATH.LOG_DIR_NAME.$p);
		define('THEMES_PATH', APP_PATH.THEMES_DIR_NAME.$p);
		define('DEFAULT_THEME_PATH', THEMES_PATH.DEFAULT_THEME_NAME.$p);
		define('WEB_PATH', preg_replace('/[^\/]+\.php$/', '', $_SERVER['SCRIPT_NAME']));
		if( defined('IS_REWRITE_ON') && !IS_REWRITE_ON ){
			preg_match('/([^\/]+\.php)$/', $_SERVER['SCRIPT_NAME'], $matches );
			define('WEB_APP_PATH', WEB_PATH.$matches[1].'/');
		}else{
			define('WEB_APP_PATH', WEB_PATH);
		}
		defined('TIMEZONE') || define('TIMEZONE', 'PRC');
		date_default_timezone_set(TIMEZONE);
		error_reporting(0);
		set_error_handler(array('LmlErrHandle', 'onErr'));
		set_exception_handler(array('LmlErrHandle', 'onException'));
		register_shutdown_function(array('LmlErrHandle', 'onFatalErr'));
		spl_autoload_register(array('LmlUtils', 'autoload'));
		
		return self::$instance;
	}
	
	private static function deepStripSlashes($v) {
		if( is_array($v) ){
			foreach ( $v as $a=>$b ){
				$v[$a] = self::deepStripSlashes($b);
			}
		} else {
			return stripslashes($v);
		}
		return $v;
	}
	
	private static function deepHtmlspecialchars($v){
		if(is_array($v)){
			foreach ($v as $a=>$b){
				unset($v[$a]);
				$v[htmlspecialchars($a)] = self::deepHtmlspecialchars($b);
			}
		} else if(is_object($v)){
			foreach ($v as $a=>$b){
				unset($v->{$a});
				$v->{htmlspecialchars($a)} = self::deepHtmlspecialchars($b);
			}
		}else{
			return htmlspecialchars($v);
		}
		return $v;
	}
	
	public function getResval(&$arg=''){
		if( func_num_args() > 0 ){
			$arg = self::$resval;
			return $this;
		}
		return $this;
	}
	
	public function setOutDateInfo($flag){
		self::$isOutDateInfo = $flag;
		return $this;
	}
	
	public function setSplit($flag){
		self::$isOutSplit = $flag;
		return $this;
	}
	
	public function setSuffix($suffix){
		self::$filenameSuffix = $suffix;
		return $this;
	}
	
	public function setPrefix($preffix){
		self::$filenamePrefix = $preffix;
		return $this;
	}
	
	public function setFlag($flag){
		self::$flags = $flag;
		return $this;
	}
	
	public function app(){
		if($this->app){
			return $this->app;
		}
		self::init();
		return $this->app = LmlApp::getInstance();
	}

	/**
	 * 
	 * @param mixed $content 
	 * @param string optional $filename 
	 * @param string optional $in_charset 
	 * @param string optional $out_charset
	 */
	public function fileDebug($content='', $filename='', $in_charset='', $out_charset=''){
		if( $filename == '' ){
			$filename = self::appName.'_debug'.DIRECTORY_SEPARATOR
			.self::$filenamePrefix.date("Y-m-d").self::$filenameSuffix;
		}
		LmlUtils::logPre($filename, $content, $in_charset, $out_charset);
		file_put_contents( $filename, 
				(self::$isOutDateInfo?'['.date('c').'] ':'')
				.($content=='' ? self::$resval : $content)
				.(self::$isOutSplit ? ENDL.self::$split.ENDL : ENDL), self::$flags );
		return $this;
	}
	
	/**
	 * debug output, this function is very useful for web debug.
	 *
	 * @param mixed $v something to output
	 * @param boolean $out_entities whether convert html special chars to html entities, default not
	 * @param number $h display area height
	 * @param number $w display area width
	 */
	public function webDebug($v, $out_entities=false, $h=500, $w=800) {
		static $id_identify = '', $i = 0;
		if ( $i === 0 ) {
			$id_identify = 'debug_'.LmlUtils::getUniqueString();
			echo 
'<script>(function(){var b=document.createElement("div");b.style.cssText="z-index:99999;display:none;position:fixed;top:10px;left:10px;padding:0px 0px;'.'border:1px solid green;width:'.$w.'px;height:'.$h.'px;background-color:white;font-family:Courier New";var c;document.body?c=document.body.appendChild(b):(document.write("<div id=\"main_'.$id_identify.'\"></div>"),c=document.getElementById("main_'.$id_identify.'").appendChild(b));var e=document.createElement("div");e.style.cssText="height:20px;line-height:20px;text-align:center;background-color:yellow;cursor:pointer";e.innerHTML="Powered by LMLPHP. <span id=\"num_'.$id_identify.'\"></span> items ouput below:";c.appendChild(e);var g=document.createElement("div");g.style.cssText="overflow:auto;height:'.($h-20).'px;";g.id="'.$id_identify.'";c.appendChild(g);var h=0;document.ondblclick=function(){1==h?(c.style.display="none",h=0):(h=1,c.style.display="block")};(function(d,c){c.onmousedown=function(b){this.style.cursor="move";var f=navigator.userAgent.match(/msie\s+([\d]+)/i);f&&0<f.length&&10>f[1]?d.style.filter="alpha(opacity=30)":d.style.opacity=.3;evt=window.event||b;var e=evt.clientX,g=evt.clientY,h=d.offsetLeft,l=d.offsetTop,k=document;d.setCapture?d.setCapture():window.captureEvents&&window.captureEvents(Event.MOUSEMOVE|Event.MOUSEUP);k.onmousemove=function(a){a=a||window.event;a.pageX||(a.pageX=a.clientX);a.pageY||(a.pageY=a.clientY);var c=a.pageX-e+h-document.body.scrollLeft;a=a.pageY-g+l-document.body.scrollTop;0>a&&(a=0);var b;window.innerHeight?b=window.innerHeight:document.body&&document.body.clientHeight&&(b=document.body.clientHeight);!b&&document.documentElement&&document.documentElement.clientHeight&&(b=document.documentElement.clientHeight);a>b-20&&(a=b-20);d.style.left=c+"px";d.style.top=a+"px"};k.onmouseup=function(){d.releaseCapture?d.releaseCapture():window.captureEvents&&window.captureEvents(Event.MOUSEMOVE|Event.MOUSEUP);c.style.cursor="pointer";f&&0<f.length&&10>f[1]?d.style.filter="alpha(opacity=100)":d.style.opacity=1;k.onmousemove=null;k.onmouseup=null}}})(b,e);c.onmousedown=function(){}})();</script>';
		}
		echo '<div style="display:none;" id="'.$id_identify.'_'.$i.'">';
		var_dump($out_entities ? self::deepHtmlspecialchars($v) : $v);
		echo '</div><script>document.getElementById("'.$id_identify.'").innerHTML+="'.($i+1)
		.':<br/><div><pre>"+document.getElementById("'.$id_identify.'_'.$i.'").innerHTML+"</pre></div><hr/>";'.
		'document.getElementById("num_'.$id_identify.'").innerHTML="'.($i+1).'";</script>';
		$i++;
		return $this;
	}
	
	public function refineCode($content, &$refinedCode='') {
		$refine_str = '';
		$tokens = token_get_all($content);
		$in_double_quotations = false;
		for ($i = 0, $j = count($tokens); $i < $j; $i++) {
			if (is_string($tokens[$i])) {
				$refine_str .= $tokens[$i];
				if( $tokens[$i] == '"' ){
					$in_double_quotations = !$in_double_quotations;
				}
			} else {
				switch ($tokens[$i][0]) {
					case T_COMMENT:
					case T_DOC_COMMENT:
					case T_WHITESPACE:
						break;
					case T_VARIABLE:
						$refine_str .= $tokens[$i][1];
						for($x=$i+1; $x<$j; $x++){
							if(is_array($tokens[$x]) && trim($tokens[$x][1]) != ''){
								if( $in_double_quotations && substr($tokens[$x][1], 0, 1) != '$' ){
									$refine_str .= ' ';
								}
								break;
							}
						}
						break;
					case T_OPEN_TAG:
					// php tag
					case T_CLASS:
					// class
					case T_FUNCTION:
					// function
					case T_THROW:
					// throw
					case T_INCLUDE:
					case T_INCLUDE_ONCE:
					case T_REQUIRE:
					case T_REQUIRE_ONCE:
					case T_INTERFACE:
					case T_CONST:
						$refine_str .= trim($tokens[$i][1]).' ';
						break;
					case T_LOGICAL_OR:
						$refine_str .= '||';
						break;
					case T_LOGICAL_AND:
						$refine_str .= '&&';
						break;
					case T_LOGICAL_XOR:
						$refine_str .= '^';
						break;
						
					case T_EXTENDS:
					case T_AS:
					case T_INSTANCEOF:
					case T_IMPLEMENTS:
					// as
						$refine_str .= ' '.$tokens[$i][1].' ';
						break;
						
					case T_RETURN:
					// return
					case T_PRIVATE:
					// private
					case T_PUBLIC:
					// public
					case T_PROTECTED:
					// protected
					case T_CASE:
					// case
					case T_NEW:
					// new
					case T_IMPLEMENTS:
					case T_ABSTRACT:
					case T_CLONE:
					case T_STATIC:
					// static 当后面是$,可以不加空格。
						$refine_str .= $tokens[$i][1];
						for($x=$i+1; $x<$j; $x++){
							if(is_array($tokens[$x]) && trim($tokens[$x][1]) != ''){
								if( substr($tokens[$x][1], 0, 1) != '$' ){
									$refine_str .= ' ';
								}
								break;
							}
						}
						break;
					case T_ECHO:
						$refine_str .= $tokens[$i][1];
						for($x=$i+1; $x<$j; $x++){
							if(is_array($tokens[$x]) && trim($tokens[$x][1]) != ''){
								if( token_name($tokens[$x][0]) == "T_STRING" ){
									$refine_str .= ' ';
								}
								break;
							}
						}
						break;
					case T_ELSE:
					// else 当else 后面没有{时
						$refine_str .= $tokens[$i][1];
						for($x=$i+1; $x<$j; $x++){
							if(is_array($tokens[$x]) && trim($tokens[$x][1]) != ''){
								if( $tokens[$x][1] == 'if' ){
									// else if => elseif
									break;
								}
								if( substr($tokens[$x][1], 0, 1) != '{' ){
									$refine_str .= ' ';
								}
								break;
							}elseif(is_string($tokens[$x]) && trim($tokens[$x]) ){
								break;
							}
						}
						break;
						
					default:
						$refine_str .= trim($tokens[$i][1]);
				}
			}
		}
		if( func_num_args() > 1 ){
			$refinedCode = $refine_str;
		}
		self::$resval = $refine_str;
		return $this;
	}
	
	/**
	 * var_dump with <pre>
	 * @param mixed $v
	 */
	public function dump($v=null){
		echo '<pre>';
		var_dump($v?$v:self::$resval);
		echo '</pre>';
	}
	/**
	 * var_dump with <pre>
	 * @param mixed $v
	 */
	public function dumpEx($v=null){
		echo '<pre>';
		var_dump($v?$v:self::$resval);
		echo '</pre>';
		exit;
	}
	
	/**
	 * list files
	 * 
	 * @param $dir the directory path
	 * @param $flag is return . default false
	 * @return lml object
	 */
	public function showDirFile($dir, $flag=false) {
		if( $flag ){
			$retlist = array();
		}
		$dir = rtrim($dir, '/\\');
		$list = scandir ( $dir );
		foreach ( $list as $file ) {
			$file_location = $dir . DIRECTORY_SEPARATOR . $file;
			if (is_dir ( $file_location ) && $file != "." && $file != "..") {
				$this->showDirFile( $file_location, $flag )->getResval($retlist[$file_location]);
			} else {
				if( $flag ){
					$retlist[] = $file_location;
				}else{
					echo '<a href="' . $file_location . '">' . $file_location . '</a>';
				}
			}
			if( !$flag ){
				echo "<br/>";
			}
		}
		if( $flag ){
			self::$resval = $retlist;
			return $this;
		}
	}

}

class LmlUtils{

	public static function _404() {
		if( !headers_sent() ) {
			header('HTTP/1.1 404 Not Found');
			header('Status:404 Not Found');
		}
	}

	/**
	 * Get Unique String
	 * @return string
	 */
	public static function getUniqueMd5String(){
		static $i = 0;
		return md5(microtime().$i++.rand(1, 100));
	}

	/**
	 * Get Unique String
	 * @return string
	 */
	public static function getUniqueString(){
		static $i = 0;
		list($msec, $sec) = explode(' ', microtime());
		return Lmlphp::appName.$i++.'_'.$sec.'_'.substr($msec, 2);
	}
	
	public static function autoload($arg){
		$f = $arg.'.php';
		$group = defined('C_GROUP')?C_GROUP:false;
		$base = (substr($arg,0,3)=='Lml')?true:false;
		if( $base || substr($arg,0,strlen(MODULE_DIR_NAME))==ucfirst(MODULE_DIR_NAME) ){
			if( $group && file_exists(MODULE_PATH.C_GROUP.'/'.$f) ){
				require MODULE_PATH.C_GROUP.'/'.$f;
			}elseif( file_exists(MODULE_PATH.$f) ){
				require MODULE_PATH.$f;
			}
		}elseif( substr($arg,0,strlen(MODEL_DIR_NAME))==ucfirst(MODEL_DIR_NAME) ){
			if( $group && file_exists(MODEL_PATH.$group.'/'.$f) ){
				require MODEL_PATH.$group.'/'.$f;
			}elseif( file_exists(MODEL_PATH.$f) ){
				require MODEL_PATH.$f;
			}
		}elseif( file_exists(LIB_PATH.$f) ){
			require LIB_PATH.$f;
		}
	}
	
	public static function logPre($filename, &$content, $in_charset, $out_charset){
		if (!file_exists($filename)){
			$dirpath = substr($filename, 0, max(strrpos($filename, '\\'), strrpos($filename, '/')) );
			$dirpath && !file_exists($dirpath) && LmlUtils::mkdirDeep($dirpath);
		}
		if( file_exists($filename) && filesize($filename) > 2097152 ){
			$dotpos = strrpos($filename, '.');
			$pre = substr($filename, 0, $dotpos);
			$last = substr($filename, $dotpos);
			rename($filename, $pre.'_'.time().$last);
		}
		if( is_array($content) || is_object($content) ) {
			$content = var_export($content, true);
		}
		if( $in_charset != '' && $out_charset != '' ){
			$content = iconv($in_charset, $out_charset, $content);
		}
	}
	
	public static function mkdirDeep($p, $mode=0755){
		return mkdir($p, $mode, true);
	}
	
}

class LmlErrHandle{

	public static function onErr($errno, $errstr, $errfile, $errline){
		$errorStr = IS_CLI
			?(isset($_SERVER['argv'][1])?$_SERVER['argv'][1].', ':'')
			:$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].', ';
		switch ($errno) {
			case E_ERROR:
			case E_USER_ERROR:
				$errorStr .= Lmlphp::appName.' Error:['.$errno.']'.$errstr.' in '.$errfile.' line '.$errline;
				break;
			case E_STRICT:
			case E_USER_WARNING:
			case E_USER_NOTICE:
			default:
				$errorStr .= Lmlphp::appName.' Notice:['.$errno.']'.$errstr.' in '.$errfile.' line '.$errline;
				break;
		}
		self::log($errorStr);
	}
	
	public static function onException($e){
		self::log((IS_CLI?(isset($_SERVER['argv'][1])?$_SERVER['argv'][1].', ':'')
				:$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].', ').$e->__toString());
	}
	
	public static function onFatalErr() {
		if ( ($e = error_get_last())!=null ) {
			switch($e['type']){
				case E_ERROR:
				case E_PARSE:
				case E_CORE_ERROR:
				case E_COMPILE_ERROR:
				case E_USER_ERROR:
					$e['REQUEST_URI'] = IS_CLI?(isset($_SERVER['argv'][1])?$_SERVER['argv'][1].', ':'')
					:$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
					$errstr = $e['REQUEST_URI'].', '.Lmlphp::appName.' Fatal Error:'.$e['message'].' in '.$e['file'].' line '.$e['line'];
					self::log($errstr);
					break;
			}
		}
		if( !IS_CLI && session_id() != '' ){
			session_write_close();
		}
	}
	
	private static function log($content, $filename='', $in_charset='', $out_charset=''){
		if( $filename == '' ){
			$filename = LOG_PATH.'log_'.date("Y-m-d").'.txt';
		}
		LmlUtils::logPre($filename, $content, $in_charset, $out_charset);
		file_put_contents( $filename, date('[ c ] ').$content.ENDL, FILE_APPEND );
	}
}

class LmlApp{
	
	private static $instance;
	private static $mInstances;
	private static $realRequestUri;
	
	private $pathPattern;
	private $lastRoute=array();
	private $path=array('index', 'index');
	private $callback;
	private $onesloc = false;
	private $grouter = false;
	private $event = array();

	private function __construct(){
		$word_regexp = '([a-zA-Z_][\w]{0,29})';
		$this->pathPattern = '/^\/'.$word_regexp.'\/'.$word_regexp.'|^\/'.$word_regexp.'/';
		$this->setPath();
	}
	
	private function setPath(){
		if( IS_CLI ){
			$this->matchPath(self::$realRequestUri);
		}else if( isset( $_SERVER['REQUEST_URI'] ) && !$this->matchPath(preg_replace('/^\/[^\/\\\\]+\.php/','',self::$realRequestUri)) ){
			if( isset($_GET[PATH_PARAM]) ){
				$this->matchPath( $_GET[PATH_PARAM] );
			}elseif( isset($_GET['m']) ){
				$this->path = array(
					$_GET['m']?$_GET['m']:'Index',
					isset($_GET['a'])&&$_GET['a']?$_GET['a']:'index'
				);
			}
		}
	}
	
	private function matchPath($path_str){
		$matches='';
		preg_match($this->pathPattern, $path_str, $matches);
		if( count($matches) > 0 ){
			if( $matches[1] ){
				$this->path = array($matches[1], $matches[2]);
			}else{
				$this->path = array($matches[3], 'index');
			}
			return true;
		}else{
			$this->path = array('index', 'index');
		}
		return false;
	}

	public static function getInstance(){
		if( self::$instance ){
			return self::$instance;
		}
		if( IS_CLI ){
			$realRequestUri = isset($_SERVER['argv'][1])?$_SERVER['argv'][1]:'';
		}else{
			$script_name = $_SERVER['SCRIPT_NAME'];
			$request_uri = $_SERVER['REQUEST_URI'];
			if( basename($script_name) != trim($script_name, '/') ){
				// 项目入口在document root下级文件夹
				$script_dir = dirname($script_name);
				$script_dir_pattern = str_replace(array('.', '/'), array('\\.', '\/'), $script_dir);
				$path_matches = '';
				preg_match('/^'.$script_dir_pattern.'(.*)$/i', $request_uri, $path_matches);
				$realRequestUri = isset($path_matches[1])?$path_matches[1]:'';
			}else{
				$realRequestUri = $request_uri;
			}
		}
		self::$realRequestUri = $realRequestUri;
		define('LML_REQUEST_URI', $realRequestUri);
		if( !is_dir(MODULE_PATH) ){
			LmlUtils::mkdirDeep(MODULE_PATH);
			if( !file_exists(MODULE_PATH.ucfirst(MODULE_DIR_NAME).'Index.php') ){
				file_put_contents(MODULE_PATH.ucfirst(MODULE_DIR_NAME).'Index.php', "<?php\r\nclass ".ucfirst(MODULE_DIR_NAME)."Index extends LmlBase{\r\n\tpublic function index(){\r\n\t\tif( !headers_sent() ) {\r\n\t\t\theader(\"Content-type:text/html;charset=utf-8\");\r\n\t\t}\r\n\t\tif(IS_CLI){\r\n\t\t\techo'Welcome to use LMLPHP!';\r\n\t\t}else{\r\n\t\t\techo '<div style=\"margin-top:100px;line-height:30px;font-size:16px;font-weight:bold;font-family:微软雅黑;text-align:center;color:red;\">^_^,&nbsp;Welcome to use LMLPHP!<div style=\"color:#333;\">A fully object-oriented PHP framework, keep it light, magnificent, lovely.</div></div>';\r\n\t\t}\r\n\t}\r\n}");
			}
			if( !file_exists(MODULE_PATH.'LmlBase.php') ){
				file_put_contents(MODULE_PATH.'LmlBase.php', "<?php\r\nabstract class LmlBase{\r\n\tpublic \$v = array();\r\n\tpublic function __call(\$name, \$arg){\r\n\t\t// TODO handle some unknow method\r\n\t}\r\n\tpublic function assign(\$k, \$v){\r\n\t\t\$this->v[\$k] = \$v;\r\n\t}\r\n\tpublic function display(\$t='', \$f='.php'){\r\n\t\t\$s = DIRECTORY_SEPARATOR;\r\n\t\t\$d = DEFAULT_THEME_PATH;\r\n\t\tif( defined('C_GROUP') ){\r\n\t\t\t\$d .= C_GROUP.\$s;\r\n\t\t}\r\n\t\tif(\$t){\r\n\t\t\t\$arr = explode('/', \$t, 2);\r\n\t\t\tif(count(\$arr) == 1){\r\n\t\t\t\tarray_unshift(\$arr, C_MODULE);\r\n\t\t\t}\r\n\t\t\t\$this->fetch(\$d.\$arr[0].\$s.\$arr[1].\$f);\r\n\t\t}else{\r\n\t\t\t\$this->fetch(\$d.C_MODULE.\$s.C_ACTION.\$f);\r\n\t\t}\r\n\t}\r\n\tprivate function fetch(\$f){\r\n\t\textract(\$this->v, EXTR_OVERWRITE);\r\n\t\tinclude \$f;\r\n\t}\r\n\tpublic function __construct(){\r\n\t\t\r\n\t}\r\n}");
			}
			if( !is_dir(DEFAULT_THEME_PATH.'index') ){
				LmlUtils::mkdirDeep(DEFAULT_THEME_PATH.'index');
			}
			if( !is_dir(LIB_PATH) ){
				LmlUtils::mkdirDeep(LIB_PATH);
			}
			if( !is_dir(MODEL_PATH) ){
				LmlUtils::mkdirDeep(MODEL_PATH);
			}
			if( !file_exists(APP_PATH.'.htaccess') ){
				file_put_contents(APP_PATH.'.htaccess', "Deny from all");
			}
			if( !file_exists(SCRIPT_PATH.'.htaccess') ){
				file_put_contents(SCRIPT_PATH.'.htaccess', "<IfModule mod_rewrite.c>\r\nRewriteEngine on\r\nRewriteCond %{REQUEST_FILENAME} !-d\r\nRewriteCond %{REQUEST_FILENAME} !-f\r\nRewriteRule ^(.*)$ index.php/$1 [QSA,PT,L]\r\n</IfModule>");
			}
		}
		return self::$instance = new self;
	}
	
	public function addDomain($domain){
		if(!is_array($domain)){
			return $this;
		}
		foreach ($domain as $k=>$v){
			if( $k==$_SERVER['HTTP_HOST'] ){
				if( is_string($v) ){
					$g = $v;
				}else{
					if( isset($v[0]) && is_string($v[0]) ){
						$g = $v[0];
					}else{
						$g = key($v);
					}
				}
				$p = '/^(\/[^\/]+\.php)?\//';
				self::$realRequestUri = preg_replace($p, '$1/'.$g.'/', self::$realRequestUri);
				return $this->addGroup(is_array($v)?$v:array($v));
			}
		}
		return $this;
	}
	
	public function addGroup($group){
		if( !is_array($group) || defined('C_GROUP') ){
			return $this;
		}
		foreach ($group as $k=>$v){
			if( is_string($v) ){
				$k = $v;
			}
			$o = false;
			$kp = str_replace(array('.', '/'), array('\\.', '\/'), $k);
			if( isset($_GET['g']) && $_GET['g']==$k ){
				$o = true;
			}elseif( isset($_GET[PATH_PARAM]) ){
				if( preg_match('/^\/'.$kp.'/', $_GET[PATH_PARAM]) ){
					$_GET[PATH_PARAM] = preg_replace('/^\/'.$kp.'/', '/', $_GET[PATH_PARAM]);
					$o = true;
				}
			}else{
				$p = '/^(?:\/[^\/]+\.php)?\/'.$kp.'\/?/';
				if( preg_match($p, self::$realRequestUri) ){
					self::$realRequestUri = preg_replace($p, '/', self::$realRequestUri);
					$o = true;
				}
			}
			if($o){
				define('C_GROUP', str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $k));
				$this->setPath();
				if( is_array($v) ){
					$this->addRouter($v);
					$this->grouter = true;
				}elseif($v===false || is_string($v)){
					$this->grouter = true;
				}elseif($v === true){
					// extend the main router
					$this->grouter = false;
				}
				return $this;
			}
		}
		return $this;
	}
	
	public function addRouter($r){
		if( !is_array($r) || empty($r) ){
			return $this;
		}
		if( $this->grouter ){
			return $this;
		}
		$path = '';
		if( IS_CLI ){
			$path = isset($_SERVER['argv'][1])?$_SERVER['argv'][1]:'';
		}else{
			$path = self::$realRequestUri;
		}
		foreach ($r as $k=>$v){
			$matches = '';
			if( preg_match($k, $path, $matches) ){
				if( count($matches) > 1 && isset($v['param']) && is_array($v['param']) ){
					foreach ( $v['param'] as $x=>$y ){
						$_GET[$y] = isset($matches[$x+1])?$matches[$x+1]:'';
					}
				}
				if( isset( $v['callback'] ) && is_array($v['callback']) ){
					$this->callback = $v['callback'];
				}else if( isset($v['m']) ){
					if( $v['m'] ){
						$this->path[0] = $v['m'];
					}
					if( isset($v['a']) && $v['a'] ){
						$this->path[1] = $v['a'];
					}
				}
				return $this;
			}
		}
		return $this;
	}
	
	public function addLastRouter($v){
		$this->lastRoute = $v;
		return $this;
	}
	
	private function callUserFunc($cb){
		if( count($cb) == 1 ){
			if( function_exists($cb[0]) ){
				call_user_func($cb[0]);
			}else{
				throw new LmlException('Function:'.$cb[0].' not found');
			}
		}else if( count($cb) == 2 ){
			if( method_exists($cb[0], $cb[1]) ){
				call_user_func($cb);
			}else{
				throw new LmlException('Class:'.$cb[0].',method:'.$cb[1].' not found');
			}
		}
	}
	
	public function attachEvent( $e ){
		$this->event = array_merge((array)$this->event, (array)$e);
		return $this;
	}

	public function run($onesloc=false){
		if( isset($this->event['onRun']) ){
			$this->callUserFunc( $this->event['onRun'] );
		}
		$this->onesloc = $onesloc;
		ob_start();
		ob_implicit_flush(0);
		$cb = $this->callback;
		if( is_array($cb) ){
			$this->callUserFunc($cb);
			return $this->show();
		}
		$path = $this->path;
		$m = ucfirst(MODULE_DIR_NAME).ucfirst($path[0]);
		$a = $path[1];
		defined('C_MODULE') || define('C_MODULE', strtolower($path[0]));
		defined('C_ACTION') || define('C_ACTION', strtolower($a));
		$c_theme_path = defined('CURRENT_THEME_PATH') ? CURRENT_THEME_PATH : DEFAULT_THEME_PATH;
		$v = $c_theme_path.(defined('C_GROUP')?C_GROUP.DIRECTORY_SEPARATOR:'').C_MODULE.DIRECTORY_SEPARATOR.C_ACTION.'.php';
		if( class_exists($m) ){
			$class = new ReflectionClass($m);
		}else{
			if( file_exists( $v ) ){
				include $v;
			}else if( $this->lastRoute ){
				$this->callUserFunc($this->lastRoute);
			}else{
				LmlUtils::_404();
				throw new LmlException(Lmlphp::appName.' Exception:Class '.$m.' not found.');
			}
			return $this->show();
		}
		if( $class->isAbstract() ){
			throw new LmlException(Lmlphp::appName.' Exception:Class '.$m.' is Abstact.');
		}
		if( self::$mInstances[$m.$a] ){
			$o = self::$mInstances[$m.$a];
		}else{
			$o = new $m;
			self::$mInstances[$m.$a] = $o;
		}
		
		try{
			$class->hasProperty('conditions') 
			/* 
			 * since php 5.3
			 * && $class->getProperty('conditions')->setAccessible(true) 
			 */
			&& ($conditions = (array)$class->getProperty('conditions')->getValue($o));

			if( isset($conditions[$a]) && $class->hasMethod($conditions[$a]) ){
				$confunc = $class->getMethod( $conditions[$a] );
				if( !$confunc->invoke($o) ) {
					return $this->show();
				}
			}

			$method = new ReflectionMethod($o, $a);
			if($method->isPublic()) {
				if($class->hasMethod('_front_'.$a)) {
					$before = $class->getMethod('_front_'.$a);
					if($before->isPublic()) {
						$before->invoke($o);
					}
				}
				$method->invoke($o);
				if($class->hasMethod('_rear_'.$a)) {
					$after = $class->getMethod('_rear_'.$a);
					if($after->isPublic()) {
						$after->invoke($o);
					}
				}
			}else{
				throw new ReflectionException();
			}
		}catch (ReflectionException $e){
			if(file_exists($v)){
				include $v;
			}else{
				$method = new ReflectionMethod($o,'__call');
				$method->invokeArgs($o, array($a,''));
			}
		}
		return $this->show();
	}
	
	public function setOneSloc($v){
		$this->onesloc = $v;
	}
	
	private function show(){
		if( !headers_sent() ){
			$h = headers_list();
			if( $h ){
				foreach ($h as $v){
					if(preg_match('/^Content-Type:(.*)$/i', $v, $m)){
						break;
					}
				}
			}
			header('Content-Type:'.(isset($m[1])&&$m[1]?$m[1]:CONTENT_TYPE.'; charset='.CHARSET));
			header('X-Powered-By:LMLPHP');
		}
		$o = ob_get_clean();
		if(!IS_CLI){
			$p = strpos(substr($o, 0, 200), "<html>");
			if(!($p===false)){
				$o = substr($o, 0, $p).'<!--Powered By LMLPHP-->'.substr($o, $p);
				preg_match_all('/<pre[^>]*?>[\s\S]*?<\/pre>/i', $o, $matches, PREG_OFFSET_CAPTURE);
				if( isset($matches[0][0][1]) ){
					$pos = 0;
					foreach( $matches[0] as $v ){
						$str = substr($o, $pos, $v[1]-$pos);
						$this->output($str);
						echo $v[0];
						$pos = $v[1] + strlen($v[0]);
					}
					$str = substr($o, $pos);
					return $this->output($str);
				}
			}
		}
		$this->output($o);
	}
	
	private function output($o){
		echo $this->onesloc?str_replace(array("\t", "\r", "\n"), '', $o):$o;
	}
}


class LmlDispatch{

	public static $instance;
	private $config;
	private $what;
	public function __set($arg, $val){
		$this->{$arg} = $val;
		return $this;
	}
	public static function getInstance(){
		if( self::$instance instanceof self ) {
			return self::$instance;
		}
		return self::$instance = new self();
	}

	private function __construct(){}

	public function setConfig($config){
		$this->config = $config;
		return $this;
	}
	public function setWhat($w){
		$this->what = $w;
		return $this;
	}

	public function start(){
		eval('Lml'.ucfirst($this->what).'::start($this->config);');
	}
}

class LmlException extends Exception{}

interface LmlToolInterface{
	public static function start($conf);
}

class LmlSpider implements LmlToolInterface{
	
	// 当前访问URL
	private $currentUrl;
	// 当前页面返回内容
	private $currentContent;
	// 当前页面所有图片
	private $currentImages;
	// 当前页面所有链接
	private $currentLinks;
	// 当前页面编码
	private $charset;
	// 运行信息
	private $info = array();
	
	// ====================
	// 保存路径
	private static $saveDir;
	// 原始配置信息
	private static $config;
	// 域名地址
	private static $domain;
	// 已经请求的地址
	private static $visitedUrl=array();
	// 已经请求的图片
	private static $visitedImg=array();
	// 链接计数器
	private static $linkc=0;
	// 图片计数器
	private static $imgc=0;
	
	protected function __construct( $url ){
		$this->currentUrl = $url;
		$this->getRemoteData();
		$this->getCharset();
		$this->matchImgTag();
		$this->matchLink();
		$this->saveImg();
	}
	
	/* protected function __get($arg){
		return $this->{$arg};
	} */
	
	protected function getCharset(){
		// <meta http-equiv="Content-Type" content="text/html; charset=gb2312">
		$matches = '';
		preg_match("/<meta.+charset=([a-zA-Z0-9]{1,10}).*?>/", $this->currentContent, $matches);
		$this->charset = isset($matches[1])?$matches[1]:'';
	}
	
	protected function getRemoteData(){
		if( preg_match('/^javascript:/i', $this->currentUrl) ){
			return;
		}
// 		$this->currentContent = self::getRemoteContents($this->currentUrl);
		$this->currentContent = file_get_contents($this->currentUrl);
		self::$visitedUrl[] = $this->currentUrl;
	}
	
	/**
	 * match img tag
	 */
	protected function matchImgTag(){
		preg_match_all('/<img[^>]*?>/', $this->currentContent, $this->currentImages);
	}
	
	/**
	 * match link
	 */
	protected function matchLink(){
		preg_match_all('/<a.*?<\/a>/', $this->currentContent, $this->currentLinks);
	}
	
	/**
	 * save img
	 */
	protected function saveImg(){
		if(empty($this->currentImages)){
			return;
		}
		foreach ( $this->currentImages[0] as $k=>$v ) {
			$url_matches = '';
			preg_match('/src\s*=\s*["\']+([^"\']+).*/', $v, $url_matches);
			$url = $url_matches[1];
			if(!$url){
				continue;
			}
			$filename = '';
			preg_match('/([^\/]+)(\.\w{1,4})/', $url, $filename);
			if( substr( $url, 0, 4 ) != 'http' ) {
				$url = self::getPageLinkUrl($this->currentUrl, $url);
			}
			
			if( in_array($url, self::$visitedImg) ){
				continue ;
			}
			$data = file_get_contents( $url );
			file_put_contents(self::$saveDir.$filename[1].'_'.++self::$imgc.$filename[2], $data);
			self::$visitedImg[] = $url;
			$this->info[] = 'down:'.$url;
		}
		$this->toString();
	}
	
	protected function toString(){
		$arr = array(
			'currentUrl' => $this->currentUrl,
			'currentImages' => $this->currentImages,
			'currentLinks' => $this->currentLinks,
			'currentContent' => $this->currentContent,
			'info' => $this->info
		);
		print_r($this->info)."\n";
		lml()->fileDebug($arr, '', $this->charset, 'utf-8');
	}
	
	public static function start($config){
		self::$config = $config;
		
		$savepath = $config['savedir'];
		if( $savepath == '' ){
			$savepath = dirname(__FILE__).'/data'.date("YMd");
		}
		if( substr($savepath, -1) != DIRECTORY_SEPARATOR ){
			$savepath .= DIRECTORY_SEPARATOR;
		}
		if( !file_exists($savepath) && !LmlUtils::mkdirDeep($savepath) ){
			throw new LmlException('mkdir fail. path is '.$savepath);
		}
		
		$url = $config['url'];
		if( $url == '' ){
			throw new LmlException('url is null');
		}
		if( substr($url, -1) == '/' ){
			$url = substr($url, 0, -1);
		}
		
		self::$saveDir = $savepath;
		$uri_matches = '';
		preg_match('/^(\w{1,5}:\/\/[^\/]+).*?$/', $url, $uri_matches);
		self::$domain = $uri_matches[1];
		self::down($url);
		lml()->fileDebug('linkc='.self::$linkc.', imgc='.self::$imgc);
		lml()->fileDebug('saveDir='.self::$saveDir.', domain='.self::$domain);
		lml()->fileDebug(self::$config);
		lml()->fileDebug(self::$visitedUrl);
		lml()->fileDebug(self::$visitedImg);
	}
	
	protected static function down($url){
		if( in_array($url, self::$visitedUrl) ){
			return;
		}
		$a = new self($url);
		
		if( empty($a->currentLinks[0]) ){
			return;
		}
		foreach ($a->currentLinks[0] as $k=>$v){
			$page_link = self::matchLinkFromTag($v);
			
			if( preg_match('/^javascript:/i', $page_link ) ){
				continue;
			}
			$page_link = self::getPageLinkUrl($url, $page_link);
			/* if(++self::$linkc > 300){
				exit;
			} */
			++self::$linkc;
			if( preg_match('/'.self::$config['target_url_regexp'].'/', $page_link) ){
				self::down($page_link);
			}else{
				continue;
			}
		}
	}
	
	/**
	 * 
	 * alias of matchHrefFromATag
	 * 
	 * @param string $tagString
	 * @return string
	 */
	protected static function matchLinkFromTag($tagString){
		return self::matchHrefFromATag($tagString);
	}
	
	/**
	 * match link href
	 * @param string $tagString
	 * @return string
	 * 
	 */
	protected static function matchHrefFromATag($tagString){
		$match='';
		preg_match('/href=.([^"\']+).*/', $tagString, $match);
		return $match[1];
	}
	
	/**
	 * 
	 * Request Remote url
	 * 
	 * @param string $url
	 * @param array $post_data
	 * @return string
	 */
	protected static function getRemoteContents($url, $post_data=array()){
		$context = array(
			'http' => array (
				'timeout' => 10,
				'method'  => 'POST',
				'content' => http_build_query($post_data, '', '&')
			)
		);
		return file_get_contents( $url, false, stream_context_create($context) );
	}
	
	protected static function getPageLinkUrl($page_url, $link_url){
		if(preg_match('/^http:\/\/|^https:\/\//', $link_url)){
			return $link_url;
		}
		$return_link = '';
		if( substr($link_url, 0, 1) == '/' ) {
			// 首字母是/时，在域名后加上当前地址
			$return_link = self::$domain.$link_url;
		}else if( substr($link_url, 0, 1) == '?' ) {
			// 当首字母是问号时，需要匹配页面URL中问号之前的部分加上当前地址。
			$url_arr = explode('?', $page_url, 2);
			$return_link = $url_arr[0].'?'.$link_url;
		}else{
			// 首字母是是其他字母时，在页面URL中最后一个 / 后加上当前地址
			if($page_url != self::$domain){
				$path = substr($page_url, 0, strrpos($page_url, '/')+1);
			}else{
				$path = self::$domain.'/';
			}
			$return_link = $path.$link_url;
		}
		return $return_link;
	}

}
