<?php
/** Separate processes prove constant-based request contexts and missing extensions fail closed. */
declare(strict_types=1);
foreach (['WP_CLI','REST_REQUEST','sodium','zip'] as $mode) {
    $cmd=[PHP_BINARY];
    if($mode==='sodium') array_push($cmd,'-d','disable_functions=sodium_crypto_sign_verify_detached');
    if($mode==='zip') array_push($cmd,'-d','disable_classes=ZipArchive');
    array_push($cmd,__DIR__.'/policy-case.php',$mode);
    $p=proc_open($cmd,[STDIN,STDOUT,STDERR],$pipes);
    if(!is_resource($p)||proc_close($p)!==0) throw new RuntimeException('Policy failure: '.$mode);
}
