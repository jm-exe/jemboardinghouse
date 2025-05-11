<?php

  function encrypt($data){
    $key = "mysecretkey12346";
    $iv = "1234566544345654";
    $encryptedData = openssl_encrypt($data, "AES-256-CBC", $key, 0, $iv);
    return $encryptedData;
  }

  function decrypt($data){
    $key = "mysecretkey12346";
    $iv = "1234566544345654";
    $decryptedData = openssl_decrypt($data, "AES-256-CBC", $key, 0, $iv);
    return $decryptedData;
  }

?>