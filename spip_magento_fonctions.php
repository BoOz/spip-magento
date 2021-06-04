<?php

/*
	Variables à définir dans mes_options.php

	// Connexion avec OAuth au WS Magento
	// Définition des identifiants
	
	define("SERVICE_PROVIDER","https://magento_server"); //  Attention en prod passer en httpS
	define("CONSUMER_KEY", "XXXXXXXXXXX");
	define("CONSUMER_SECRET", "XXXXXXXXXXX");
	
	define("URL_TOKEN", SERVICE_PROVIDER . "/api/rest/glm_rest/token/?key=".CONSUMER_KEY."&secret=".CONSUMER_SECRET);
	define("SERVEUR_TOKEN_MAITRE", ""); // eventuel fichier connexion bdd ou chercher des meta token.
	
	// Récuperer les infos d'un client : /api/rest/glm_rest/customers/[ID_MAGENTO]
	define("URL_WS_CLIENT", SERVICE_PROVIDER . "/api/rest/glm_rest/customers");
	define("URL_WS_CATALOGUE", SERVICE_PROVIDER . "/api/rest/glm_rest/products/list");
	
	// Cookie SSO
	// pre-prod
	define("SSO_COOKIE_KEY", "xxx");
	define("SSO_COOKIE_DOMAIN", "mon_domaine.com");
	
	// Prévoir une option pour enregistrer les connexions en BDD (TODO)
	
	// Prévoir de lister les produits VPC avec accès numériques (TODO)

*/

include_once(find_in_path("OAuth.php"));

function mettre_a_jour_client_magento($id_client, $email=""){
	// Check le ws, enregistrer en bdd et renvoyer la réponse du ws.
	// On l'appelle avec l'id_magento car l'email peut changer
	// On peut aussi l'appeler avec l'email si on ne connait pas l'id_magento.
	// A appeler quand :
	// - Login d'un nouveau
	// - Cookie reset posé coté Magento
	// - Cron après la connexion d'un lecteur connu
	
	if($id_client)
		$url_ws_client = URL_WS_CLIENT . "/" . $id_client ;
	elseif($email)
		$url_ws_client = URL_WS_CLIENT . "/" . rawurlencode($email) . "?email=1" ;
	else
		return false ;
	
	if($ws = recuperer_ws_magento($url_ws_client)){
		
		//var_dump("<pre>",$ws,"<pre>");
		if(!is_array($ws))
			return false ;
		
		// faire du ménage et quelques controles
		if($ws['abonnements']["error"])
			// le ws a calé, que fait-on ?
			return array_merge(
				array('Erreur WS' => "Panne WS..."),
					$ws['abonne'],
					array(
					'prenom_nom' => $ws['abonne']["prenom"] . " " . $ws['abonne']["nom"],
					'code_postal' => $ws['abonne']["ADDRESSE_principale"]["ZIP_CODE"],
					'ville' => $ws['abonne']["ADDRESSE_principale"]["CITY"],
					'pays' => $ws['abonne']["ADDRESSE_principale"]["COUNTRY"],
					),
						array("ws" => json_encode($ws, JSON_PRETTY_PRINT))
					);
		return $ws ;
	}
	else
		return false ;
}

// Appeler un webservice en gérant les tokens périmés (même si pas implémenté dans magento apparement...)
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

function lire_cookie_sso_magento($cookie_sso){
	$cle_secrete = SSO_COOKIE_KEY ;
	$cipher = 'AES-128-CBC';
	$options = 0;
	$iv = substr($cle_secrete, -16);
	$c = openssl_decrypt($cookie_sso, $cipher , $cle_secrete , $options, $iv);
	return $c ;
}
