<?php

namespace App\Util;  
use App\Util\WxPayConfig;
use Illuminate\Support\Facades\Log;

class Util {
  public static function decodePem($ciphertext, $nonce, $associated_data) {
    $config = new WxPayConfig();
    $key = $config->GetKey();
    $check_sodium_mod = extension_loaded('sodium'); 
    if($check_sodium_mod === false){ 
      Log::error("decodePem not install sodium");
      return null;
    } 
    $check_aes256gcm = sodium_crypto_aead_aes256gcm_is_available(); 
    if($check_aes256gcm === false){ 
      Log::error("decodePem not support check_aes256gcm");
      return null;
    } 

    $pem = sodium_crypto_aead_aes256gcm_decrypt(base64_decode($ciphertext),$associated_data,$nonce,$key); 
    Log::info("pem:" . $pem);
    return $pem;
  }

  public static function getEncrypt($str) {
    $config = new WxPayConfig(); 
    $sslCertPath = "";
    $sslKeyPath = "";
    $config->GetSSLCertPath($sslCertPath, $sslKeyPath);

    $public_key = file_get_contents($sslCertPath); 
    $encrypted = ''; 
    openssl_public_encrypt($str, $encrypted, $public_key); 
    $sign = base64_encode($encrypted); 
    return $sign; 
  }
};
