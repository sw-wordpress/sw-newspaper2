<?php
/** Test actual build artifacts and the documented operator commands using disposable keys only. */
declare(strict_types=1);
require dirname(__DIR__) . '/bin/verify-release.php';
require dirname(__DIR__) . '/includes/update/class-sw-newspaper2-update-manifest.php';
$file = realpath($argv[1] ?? '');
if (!$file || !str_starts_with(basename($file),'sw-newspaper2-')) throw new RuntimeException('Build ZIP required.');
swnewspaper2_verify_release($file);
$root = dirname(__DIR__);
$temp = sys_get_temp_dir() . '/swnewspaper2-release-test-' . bin2hex(random_bytes(8));
mkdir($temp,0700);
$run = static function(array $cmd, array $env = [], int $expected = 0): string {
    $p=proc_open($cmd,[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,null,array_merge(getenv(),$env));
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    $status=proc_close($p);
    if($status!==$expected) throw new RuntimeException('Unexpected process status '. $status . ': ' . $out);
    return $out;
};
$remove=static function(string $path) use (&$remove): void {
    if(is_dir($path) && !is_link($path)) {foreach(new DirectoryIterator($path) as $f) if(!$f->isDot()) $remove($f->getPathname());rmdir($path);} else unlink($path);
};
try {
    $archive=new ZipArchive();$archive->open($file);
    mkdir($temp.'/boot',0700);$archive->extractTo($temp.'/boot');$archive->close();
    $run([PHP_BINARY,$root.'/tests/bootstrap.php',$temp.'/boot/sw-newspaper2']);
    // Run the three literal documentation blocks with only paths redirected into a private sandbox.
    preg_match_all('/```bash\n(.*?)\n```/s',file_get_contents($root.'/docs/GITHUB-UPDATES-BETRIEB.md'),$blocks);
    if(count($blocks[1])!==3) throw new RuntimeException('Exactly three operator blocks required.');
    mkdir($temp.'/plugin/includes/update',0700,true);
    mkdir($temp.'/home',0700);
    copy($root.'/includes/update/trusted-keys.php',$temp.'/plugin/includes/update/trusted-keys.php');
    foreach($blocks[1] as $i=>$block) {
        $block=str_replace('/var/www/html/wordpress_dev/wp-content/plugins/sw-newspaper2',$temp.'/plugin',$block);
        $block=str_replace('$HOME/.local/share/sw-newspaper2-release-keys',$temp.'/keys',$block);
        file_put_contents($temp.'/block-'.$i.'.sh',$block);
    }
    $run(['bash',$temp.'/block-0.sh']);
    $run(['bash',$temp.'/block-0.sh'],[],255); // Refuses overwriting the key directory.
    foreach(['keys'=>0700,'keys/signing-key.base64'=>0600,'keys/public-keys.php'=>0600,'keys/key-id.txt'=>0600] as $name=>$mode) {
        if((fileperms($temp.'/'.$name)&0777)!==$mode) throw new RuntimeException('Unsafe key permissions.');
    }
    $keys=require $temp.'/keys/public-keys.php';$id=array_key_first($keys);
    $pair=sodium_crypto_sign_keypair();$previous=['previous'=>base64_encode(sodium_crypto_sign_publickey($pair))];
    file_put_contents($temp.'/plugin/includes/update/trusted-keys.php','<?php return '.var_export($previous,true).';');
    $run(['git','init','--quiet',$temp.'/plugin']);
    $run(['bash',$temp.'/block-1.sh']);
    $shipped=require $temp.'/plugin/includes/update/trusted-keys.php';
    if($shipped!==array_merge($previous,$keys)) throw new RuntimeException('Public keys were not preserved.');
    file_put_contents($temp.'/plugin/includes/update/trusted-keys.php','<?php return '.var_export([$id=>$previous['previous']],true).';');
    $run(['bash',$temp.'/block-1.sh'],[],255); // Conflicting key ID.
    // Stub gh verifies secret delivery via stdin, never contacting GitHub or recording the key.
    mkdir($temp.'/fake-bin');
    file_put_contents($temp.'/fake-bin/gh', <<<'SH'
#!/usr/bin/env bash
set -eu
case "$1 $2" in
'auth status'|'secret list'|'variable list') exit 0 ;;
'secret set')
  test "$3" = SW_PLUGIN_RELEASE_SIGNING_KEY
  test "$4 $5 $6 $7" = '--repo sw-wordpress/sw-newspaper2 --env plugin-releases'
  cmp -s - "$SWNEWSPAPER2_TEST_KEYS/signing-key.base64" ;;
'variable set')
  test "$3" = SW_PLUGIN_RELEASE_KEY_ID
  test "$4 $5 $6 $7" = '--repo sw-wordpress/sw-newspaper2 --env plugin-releases'
  test "$8" = --body
  test "$9" = "$(cat "$SWNEWSPAPER2_TEST_KEYS/key-id.txt")" ;;
*) exit 90 ;;
esac
SH);
    chmod($temp.'/fake-bin/gh',0700);
    $run(['bash',$temp.'/block-2.sh'],['PATH'=>$temp.'/fake-bin:'.getenv('PATH'),'SWNEWSPAPER2_TEST_KEYS'=>$temp.'/keys']);
    // Also execute zsh, with a new directory for generation and the preserved ring.
    if(is_executable('/usr/bin/zsh')) {
        $block=str_replace($temp.'/keys',$temp.'/zsh-keys',file_get_contents($temp.'/block-0.sh'));
        file_put_contents($temp.'/zsh.sh',$block);$run(['/usr/bin/zsh',$temp.'/zsh.sh']);
        file_put_contents($temp.'/plugin/includes/update/trusted-keys.php','<?php return '.var_export($previous,true).';');
        $run(['/usr/bin/zsh',$temp.'/block-1.sh']);
        $run(['/usr/bin/zsh',$temp.'/block-2.sh'],['PATH'=>$temp.'/fake-bin:'.getenv('PATH'),'SWNEWSPAPER2_TEST_KEYS'=>$temp.'/keys']);
    }
    mkdir($temp.'/dist');copy($file,$temp.'/dist/'.basename($file));
    $version=preg_replace('/^sw-newspaper2-(.*)\.zip$/','$1',basename($file));
    $release=['id'=>2026,'tag_name'=>'v'.$version,'draft'=>true,'prerelease'=>false,'assets'=>[['id'=>991,'name'=>basename($file),'size'=>filesize($file)]]];
    file_put_contents($temp.'/release.json',json_encode($release,JSON_THROW_ON_ERROR));
    $env=['SW_PLUGIN_RELEASE_SIGNING_KEY'=>trim(file_get_contents($temp.'/keys/signing-key.base64')),'SW_PLUGIN_RELEASE_KEY_ID'=>$id];
    $cmd=[PHP_BINARY,$root.'/bin/release-manifest.php','sign',$temp.'/release.json',$temp.'/dist',str_repeat('a',40)];
    $output=$run($cmd,['SW_PLUGIN_RELEASE_SIGNING_KEY'=>'','SW_PLUGIN_RELEASE_KEY_ID'=>''],1);
    if(file_exists($temp.'/dist/sw-plugin-release.json')) throw new RuntimeException('Unsigned artifact produced.');
    $output.=$run($cmd,$env);
    if(str_contains($output,$env['SW_PLUGIN_RELEASE_SIGNING_KEY'])) throw new RuntimeException('Private key was printed.');
    $release['draft']=false;
    $verified=SWNewspaper2_Update_Manifest::verify(file_get_contents($temp.'/dist/sw-plugin-release.json'),file_get_contents($temp.'/dist/sw-plugin-release.json.sig'),$keys,$release);
    if($verified instanceof WP_Error) throw new RuntimeException($verified->get_error_message());
    if(SWNewspaper2_Update_Package::verify($file,$verified['packages'][0])!==true) throw new RuntimeException('Built package verification failed.');
    // The publication verifier uses only the checkout's local public trust ring.
    mkdir($temp.'/verifier/bin',0700,true);mkdir($temp.'/verifier/includes/update',0700,true);
    foreach(glob($root.'/bin/*.php') as $tool) copy($tool,$temp.'/verifier/bin/'.basename($tool));
    foreach(glob($root.'/includes/update/*.php') as $code) copy($code,$temp.'/verifier/includes/update/'.basename($code));
    file_put_contents($temp.'/verifier/includes/update/trusted-keys.php','<?php return '.var_export($keys,true).';');
    $run([PHP_BINARY,$temp.'/verifier/bin/verify-signed-release.php',$temp.'/release.json',$temp.'/dist']);
    file_put_contents($temp.'/dist/'.basename($file),'tampered');
    $run([PHP_BINARY,$temp.'/verifier/bin/verify-signed-release.php',$temp.'/release.json',$temp.'/dist'],[],255);
    $ring=$temp.'/ring.php';file_put_contents($ring,'<?php return '.var_export($previous,true).';');
    $run([PHP_BINARY,$root.'/bin/release-manifest.php','public-key',$ring],$env);
    if((require $ring)!==array_merge($previous,$keys)) throw new RuntimeException('Build key ring lost previous keys.');
    file_put_contents($ring,'<?php return '.var_export([$id=>$previous['previous']],true).';');
    $run([PHP_BINARY,$root.'/bin/release-manifest.php','public-key',$ring],$env,1);
    mkdir($temp.'/checkout/bin',0700,true);
    copy($root.'/bin/release.sh',$temp.'/checkout/bin/release.sh');
    copy($root.'/Makefile',$temp.'/checkout/Makefile');
    copy($root.'/sw-newspaper2.php',$temp.'/checkout/sw-newspaper2.php');
    $gitEnv=['GIT_AUTHOR_NAME'=>'Release Test','GIT_AUTHOR_EMAIL'=>'test@example.test','GIT_COMMITTER_NAME'=>'Release Test','GIT_COMMITTER_EMAIL'=>'test@example.test'];
    $run(['git','init','--quiet','--initial-branch=main',$temp.'/checkout']);
    $run(['git','init','--quiet','--bare',$temp.'/remote.git']);
    $run(['git','-C',$temp.'/checkout','remote','add','origin',$temp.'/remote.git']);
    $run(['git','-C',$temp.'/checkout','add','.']);
    $run(['git','-C',$temp.'/checkout','commit','--quiet','-m','Fixture'],$gitEnv);
    $run(['make','-C',$temp.'/checkout','release','VERSION=invalid'],$gitEnv,2);
    $run(['make','-C',$temp.'/checkout','release','VERSION=99.0.1'],$gitEnv);
    $published=file_get_contents($temp.'/checkout/sw-newspaper2.php');
    if(!str_contains($published,"define( 'SWNEWSPAPER2_VERSION', '99.0.1' );") || !preg_match('/Version:\s+99\.0\.1/',$published)) throw new RuntimeException('Release did not update both version declarations.');
    $run(['git','--git-dir='.$temp.'/remote.git','show-ref','--verify','refs/tags/99.0.1']);
    $run(['make','-C',$temp.'/checkout','release','VERSION=99.0.1'],$gitEnv,2);
    echo "PASS: real ZIP, signer/runtime verification, missing secret, public key merge/conflict, Bash/Zsh operator blocks, stdin-only GitHub secret delivery (stub), isolated native loader boot and make release against a local bare remote.\n";
} finally { $remove($temp); }
