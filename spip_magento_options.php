<?php

// Chercher un cookie SSO
$cookie_sso = $_COOKIE['spip_lmd_a_s'] ;
if($cookie_sso){
	$cle_secrete = SSO_COOKIE_KEY ;
	$cipher = 'AES-128-CBC';
	$options = 0;
	$iv = substr($cle_secrete, -16);
	$id_magento = openssl_decrypt($cookie_sso, $cipher , $private_key , $options, $iv);

	// est-ce que le lecteur est connecté ?
	include_spip('inc/session');
	if(session_get("lecteur_connecte") AND (session_get("id_magento") != $id_magento)){
		session_set("id_magento",$id_magento);
		var_dump("je pose ma session");
		var_dump("<pre>",$GLOBALS['visiteur_session'],"</pre>");
	}
}

