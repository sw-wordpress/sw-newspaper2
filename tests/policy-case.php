<?php
/** No WordPress bootstrap, state or network is needed for these early policy gates. */
declare(strict_types=1);
$mode=$argv[1];
if(in_array($mode,['WP_CLI','REST_REQUEST'],true)) define($mode,true);
class WP_Error { public function __construct(public string $code, public string $message) {} }
function is_admin():bool{return true;}
function is_multisite():bool{return false;}
function wp_doing_cron():bool{return false;}
function current_user_can(string $cap):bool{return true;}
function wp_is_file_mod_allowed(string $context):bool{return true;}
function apply_filters(string $name,mixed $value):mixed{return $name==='swnewspaper2_verified_release_public_keys'?['test'=>base64_encode(str_repeat('x',32))]:$value;}
require dirname(__DIR__).'/includes/update/class-sw-newspaper2-update-service.php';
if($mode==='zip') {
    $file=tempnam(sys_get_temp_dir(),'swnewspaper2-policy-');file_put_contents($file,'zip');
    try { $result=SWNewspaper2_Update_Package::verify($file,['bytes'=>3,'sha256'=>hash_file('sha256',$file)]); }
    finally {unlink($file);}
} else $result=SWNewspaper2_Update_Service::check();
if(!$result instanceof WP_Error) throw new RuntimeException('Policy unexpectedly allowed '.$mode);
echo 'PASS: '.$mode.' fails closed before HTTP or state reads.'.PHP_EOL;
