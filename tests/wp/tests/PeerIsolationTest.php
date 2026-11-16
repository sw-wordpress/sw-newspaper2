<?php
/** Two independently prefixed SW providers coexist in the same WordPress process. */
final class PeerIsolationTest extends WP_UnitTestCase {
    public function test_independent_provider_copies_preserve_hooks_keys_and_state(): void {
        require_once SWNEWSPAPER2_DIR.'includes/update/class-sw-newspaper2-update-service.php';
        $dir=get_temp_dir().'swnewspaper2-peer-'.wp_generate_uuid4();wp_mkdir_p($dir);
        $source=SWNEWSPAPER2_DIR.'includes/update/';
        $pair=sodium_crypto_sign_keypair();$public=base64_encode(sodium_crypto_sign_publickey($pair));
        foreach(glob($source.'*.php') as $file) {
            $text=str_replace(array('SWNewspaper2','SWNEWSPAPER2','swnewspaper2','sw-newspaper2'),array('SWNewspaper2Peer','SWNEWSPAPER2PEER','swnewspaper2peer','sw-peer'),file_get_contents($file));
            file_put_contents($dir.'/'.str_replace('sw-newspaper2','sw-peer',basename($file)),$text);
        }
        file_put_contents($dir.'/descriptors.php',"<?php return array('sw-peer/sw-peer.php'=>array('slug'=>'sw-peer','version'=>'1.0.0')); ");
        file_put_contents($dir.'/trusted-keys.php','<?php return '.var_export(array('peer'=>$public),true).';');
        require $dir.'/class-sw-peer-update-provider.php';
        require $dir.'/class-sw-peer-update-service.php';
        $admin=self::factory()->user->create(array('role'=>'administrator'));
        wp_set_current_user($admin);if(is_multisite()) grant_super_admin($admin);
        set_current_screen(is_multisite()?'plugins-network':'plugins');
        try {
            $this->assertTrue(class_exists('SWNewspaper2_Update_Provider'));
            $this->assertTrue(has_action('wp_ajax_swnewspaper2_check_github_updates')!==false);
            $this->assertTrue(has_action('wp_ajax_swnewspaper2peer_check_github_updates')!==false);
            $own=array_key_first(SWNewspaper2_Update_Provider::descriptors());
            $this->assertFalse(apply_filters('auto_update_plugin',true,(object)array('plugin'=>$own)));
            $this->assertFalse(apply_filters('auto_update_plugin',true,(object)array('plugin'=>'sw-peer/sw-peer.php')));
            $this->assertTrue(apply_filters('auto_update_plugin',true,(object)array('plugin'=>'foreign/plugin.php')));
            $marker=(object)array('foreign'=>true);
            $this->assertSame($marker,SWNewspaper2Peer_Update_Provider::update_uri($marker,array('UpdateURI'=>'https://github.com/sw-wordpress/sw-newspaper2','Version'=>'1.0.0'),$own));
            SWNewspaper2_Update_Store::write(array('marker'=>'main'));
            SWNewspaper2Peer_Update_Store::write(array('marker'=>'peer'));
            $this->assertSame('main',SWNewspaper2_Update_Store::read()['marker']);
            $this->assertSame('peer',SWNewspaper2Peer_Update_Store::read()['marker']);
            $one=SWNewspaper2_Update_Store::acquire();$two=SWNewspaper2Peer_Update_Store::acquire();
            $this->assertIsArray($one);$this->assertIsArray($two);
            SWNewspaper2_Update_Store::release($one);
            $this->assertTrue(SWNewspaper2Peer_Update_Store::owns($two));
            SWNewspaper2Peer_Update_Store::release($two);
            $this->assertSame(array('peer'=>$public),SWNewspaper2Peer_Update_Service::keys());
            $this->assertArrayNotHasKey('peer',SWNewspaper2_Update_Service::keys());
            $this->assertNotSame(SWNewspaper2_Update_Store::STATE,SWNewspaper2Peer_Update_Store::STATE);
            $this->assertNotSame(SWNewspaper2_Update_Store::LOCK,SWNewspaper2Peer_Update_Store::LOCK);
        } finally {
            if(is_multisite()) revoke_super_admin($admin);
            foreach(glob($dir.'/*') as $file) unlink($file);rmdir($dir);
        }
    }
}
