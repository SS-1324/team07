<?php
// CLI only. Uses real configured DB; writes no application data.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$path=$argv[1]??'';if(!preg_match('~^pages/(teams|players|contracts|analysis)/[a-z]+\.php$~',$path))throw new InvalidArgumentException('Page path required');
parse_str($argv[2]??'',$_GET);$_SERVER['REQUEST_METHOD']='GET';
require __DIR__.'/../backend/bootstrap.php';
set_exception_handler(function($e){fwrite(STDERR,(string)$e);exit(1);});
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$_SESSION['watchlist']=['judgeaa01','seageco01'];
require __DIR__.'/../'.$path;
