<?php

include_once(find_in_path("OAuth.php"));

// Les infos sur un client
// $tout = infos_client(12345);
// $email = infos_client(12345,"email");

function infos_client($id_client, $info=''){
	$url_ws_client = URL_WS_CLIENT . "/" . $id_client ;
	$ws = recuperer_ws($url_ws_client);
	
	foreach($ws['Subscriptions'] as $a){
		if($a['StatusCode'] == "ENCOURS")
			$code_magazine[] = $a['ProductCode'] ;
	}
	
	sort($code_magazine);
	
	$infos_client = array_merge(
									$ws['Customer'], 
									array('code_magazine' => join("-", $code_magazine)),
									array("ws" => $ws)
								) ;
	
	if($infos_client[$info])
		return $infos_client[$info] ;
	
	return $infos_client ;
}

// Initialiser le token
if(!$GLOBALS['meta']["oauth_token"])
	actualiser_token();

// Demander un nouveau token
function actualiser_token(){
	$reponse = oauth_recuperer_token(URL_TOKEN) ;
	if($reponse == NULL){
		spip_log("Serveur d'autorisation inaccessible.", "OAuth-erreurs");
		return false ;
	}
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
	if($reponse == NULL)
		return "Serveur REST inaccessible." ;
	return $reponse ;
}

// Vérifier si le mot de passe hashé envoyé par magento correspond au mot de passe de l'utilisateur.
function verifier_mot_de_passe($mdp_saisi, $mdp_hash){
	// Décoder le mot de passe
	// $cle = chaine de longueur X (X =2 ou = 32)
	// $mot_de_passe_hashé = md5($mot_de_passe_en_clair . $cle) . ":" .$cle;
	list($mdp_hashe,$salt) = explode(":" , $mdp_hash) ;
	
	if(md5($mdp_saisi . $salt) == $mdp_hashe)
		return true ;
	else
		return false ;
}
