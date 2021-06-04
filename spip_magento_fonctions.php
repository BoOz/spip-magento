<?php

/*
	Variables à définir dans mes_options.php

	// Connexion avec OAuth au WS Magento
	// Définition des identifiants
	
	define("SERVICE_PROVIDER","https://oAuth_server");
	define("CONSUMER_KEY", "XXXXXXXXXXX");
	define("CONSUMER_SECRET", "XXXXXXXXXXX");
	
	define("URL_TOKEN", SERVICE_PROVIDER . "/api/rest/xxxx/token/?key=".CONSUMER_KEY."&secret=".CONSUMER_SECRET);
	define("SERVEUR_TOKEN_MAITRE", ""); // eventuel fichier connexion bdd ou chercher des meta token.
	
	// Récuperer les infos d'un client Magento :
	define("URL_WS_CLIENT", SERVICE_PROVIDER . "/api/rest/xxx/customers");
	// Catalogue Magento
	define("URL_WS_CATALOGUE", SERVICE_PROVIDER . "/api/rest/xxx/products/list");
	
*/

include_once(find_in_path("OAuth.php"));

// Appeler un webservice Magento en gérant les tokens périmés (même si pas implémenté dans magento apparement...)
function recuperer_ws_magento($url_ws){
	// si le token est périmé ou rejetté, on en redemande un autre.
	if (!$reponse = oauth_recuperer_ws($url_ws,$GLOBALS['meta']["oauth_token"],$GLOBALS['meta']["oauth_secret"])){
		actualiser_token();
		$reponse = oauth_recuperer_ws($url_ws,$GLOBALS['meta']["oauth_token"],$GLOBALS['meta']["oauth_secret"]);
	}
	if($reponse == NULL)
		return "Serveur REST inaccessible." ;
	return $reponse ;
}

// Initialiser le token
if(!$GLOBALS['meta']["oauth_token"] OR !$GLOBALS['meta']["oauth_secret"])
	actualiser_token();

// Demander un nouveau token
function actualiser_token(){
	
	// Si on est sur le serveur maitre on récupere un token sur le serveur d'autorisation.
	if(!defined("SERVEUR_TOKEN_MAITRE")){
		$reponse = oauth_recuperer_token(URL_TOKEN) ;
		if($reponse == NULL){
			spip_log("Serveur d'autorisation inaccessible.", "OAuth-erreurs");
			return false ;
		}
		$token = $reponse['token'] ;
		$secret = $reponse['secret'] ;
	}else{
		// Si on est sur un serveur esclave, on recupère le token dans les metas du spip maitre.
		$token = sql_getfetsel("valeur", "spip_meta","nom='oauth_token'","","","","", SERVEUR_TOKEN_MAITRE);
		$secret = sql_getfetsel("valeur", "spip_meta","nom='oauth_secret'","","","","", SERVEUR_TOKEN_MAITRE);
	}
	
	// enregistrer le token et le secret dans spip_meta
	include_spip("inc/meta");
	ecrire_meta("oauth_token", $token);
	ecrire_meta("oauth_secret",$secret);
	lire_metas();
}
