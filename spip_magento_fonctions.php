<?php

include_once(find_in_path("OAuth.php"));

// Initialiser le token
if(!$GLOBALS['meta']["oauth_token"])
	actualiser_token();

// Demander un nouveau token
function actualiser_token(){
	$reponse = oauth_recuperer_token(URL_TOKEN) ;
	$token = $reponse['token'] ;
	$secret = $reponse['secret'] ;
	// enregistrer le token et le secret dans spip_meta
	include_spip("inc/meta");
	ecrire_meta("oauth_token", $token);
	ecrire_meta("oauth_secret",$secret);
	lire_metas();
}

// Appeler un webservice en gérant les tokens périmés (même si pas implémenté dans magento apparement...)
function recuperer_ws($url_ws){
	// si le token est périmé, on en redemande un autre.
	if (!$reponse = oauth_recuperer_ws($url_ws,$GLOBALS['meta']["oauth_token"],$GLOBALS['meta']["oauth_secret"])){
		actualiser_token();
		$reponse = oauth_recuperer_ws($url_ws,$GLOBALS['meta']["oauth_token"],$GLOBALS['meta']["oauth_secret"]);
	}
	return $reponse ;
}
