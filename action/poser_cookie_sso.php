<?php

if (!defined("_ECRIRE_INC_VERSION")) return;

/*
	Durée du cookie : 100 jours ?
	Attention de ne pas poser le cookie plusieurs fois ? ... TODO
	Attention au nom spip_XXX forcé par le serveur ?
*/

function action_poser_cookie_sso() {

	$securiser_action = charger_fonction('securiser_action', 'inc');
	$id_magento = $securiser_action();

	$cle_secrete = SSO_COOKIE_KEY ;
	$cipher = 'AES-128-CBC';
	$options = 0;
	$iv = substr($cle_secrete, -16);
	$crypted = openssl_encrypt($id_magento, $cipher , $private_key , $options, $iv);
	include_spip('inc/cookie');
	// A cause de varnish ? si le cookie s'appelle pas spip_xxx on peut pas le lire dans $_COOKIE
	// $nom_cookie = "lmd_a_s" ;
	$nom_cookie = "lmd_a_s" ;
	// marche en https sécurisé (true).
	spip_setcookie($nom_cookie, $crypted, time()+3600*24*100,'/', SSO_COOKIE_DOMAIN, true);
}
