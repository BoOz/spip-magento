<?php

/*
	Variables à définir dans mes_options.php

	// Connexion avec OAuth au WS Magento
	// Définition des identifiants
	
	define("SERVICE_PROVIDER","https://magento_server"); //  Attention en prod passer en httpS
	define("CONSUMER_KEY", "XXXXXXXXXXX");
	define("CONSUMER_SECRET", "XXXXXXXXXXX");
	
	define("URL_TOKEN", SERVICE_PROVIDER . "/api/rest/glm_rest/token/?key=".CONSUMER_KEY."&secret=".CONSUMER_SECRET);
	
	// Récuperer les infos d'un client : /api/rest/glm_rest/customers/[ID_MAGENTO]
	define("URL_WS_CLIENT", SERVICE_PROVIDER . "/api/rest/glm_rest/customers");
	
	// Cookie SSO
	// pre-prod
	define("SSO_COOKIE_KEY", "a9be83ba3816d180aca878b9124d1643");
	define("SSO_COOKIE_DOMAIN", "mon_domaine.com");

*/


include_once(find_in_path("OAuth.php"));

// Les infos sur un client
// $tout = infos_client(12345);
// $email = infos_client(12345,"email");

function infos_client($ws, $info = ''){
	
	if($ws[$info])
		return $ws[$info] ;
	
	return $ws ;
}

function mettre_a_jour_client_magento($id_client, $email){
	// Check le ws, enregistrer en bdd et renvoyer la réponse du ws.
	// On l'appelle avec l'id_magento car l'email peut changer
	// On peut aussi l'appeler avec l'email si on ne connait pas l'id_magento.
	// A appeler quand :
	// - Login d'un nouveau
	// - Cookie reset posé par Arvato
	// - Cron après la connexion d'un lecteur connu
	
	if($id_client)
		$url_ws_client = URL_WS_CLIENT . "/" . $id_client ;
	elseif($email)
		$url_ws_client = URL_WS_CLIENT . "/" . $email . "?email=1" ;
	else
		return false ;
		
	if($ws = recuperer_ws_magento($url_ws_client)){
		
		// faire du ménage et quelques controles
		if($ws['Subscriptions']["error"])
			// le ws Arvato à calé, que fait-on ?
			return array_merge(
						array('Erreur Arvato' => "Panne CSW chez Arvato..."),
						$ws['Customer'],
						array("ws" => $ws)
					);
		
		// Controle des droits
		foreach($ws['Subscriptions'] as $a){
			if($a['StatusCode'] == "ENCOURS")
				$code_magazine[] = $a['ProductCode'] ;
		}
		
		sort($code_magazine);
		
		// Enregistrer en BDD
		// TODO
		
		$ws = array_merge(
						$ws['Customer'],
						array('code_magazine' => join("-", $code_magazine)),
						array("ws" => json_encode($ws, JSON_PRETTY_PRINT))
		) ;
		
		return $ws ;
	}
	else
		return false ;
}

function authentifier_client_magento($id_magento, $email, $mdp_saisi){

	// Ouverture des droits d'accès 
	// a chaque connexion appeler le WS avec l'email car infos à jour (suspension etc).
	// si infos locales donnent deja acces => appel en job_queue
	// si infos locales ne donnent pas d'acces => appel en direct
	// recevoir la réponse $xml
	// verifier si le $xml['pass'] ==  $pass (le pass enregistré est le même que celui saisi)
	
	// utiliser juste les infos locales.
	// puis si echec mdp interroger le WS avec e-mail 

/*
	// Cherchons en local si on a des infos recentes qui correspondent au mail. TODO
	include_spip("base/abstract_sql");
	$client_local = sql_fetsel(array('password_hash', 'xml'), array('abonnes'), "email='".$email."' and nouvel_email=''", "", "date_maj desc", "0,1");
	$client_local_pass = $client_local['password_hash'] ;
	$client_local_xml = $client_local['xml'] ;

*/

	// Si echec ouverture de droits avec infos locales (mail inconnu, mauvais mdp, pas de droits) interroger le WS avec l'email (changement de mot de passe, nouveau client, commande périmée) ;
	
	if(is_array($client_local))
		$nouveau = "non" ;
	else 
		$nouveau="oui";
	
	$ws = mettre_a_jour_client_magento($id_magento, $email, $nouveau) ;
	
	// Le WS ne reconnait pas le lecteur => retour saisie. 
	if(!$ws) // Identifiant ou mot de passe invalide.
		return false ;
	
	// vérifier le mot de passe
	if(verifier_mot_de_passe_magento($mdp_saisi, $ws['password_hash']))
		return $ws ;
	else
		return false ;
}

// Vérifier si le mot de passe hashé envoyé par magento correspond au mot de passe de l'utilisateur.
function verifier_mot_de_passe_magento($mdp_saisi, $mdp_hash){
	// Décoder le mot de passe
	// $cle = chaine de longueur X (X =2 ou = 32)
	// $mot_de_passe_hashé = md5($mot_de_passe_en_clair . $cle) . ":" .$cle;
	list($mdp_hashe,$salt) = explode(":" , $mdp_hash) ;
	
	if(md5($mdp_saisi . $salt) == $mdp_hashe)
		return true ;
	else
		return false ;
}

// Appeler un webservice en gérant les tokens périmés (même si pas implémenté dans magento apparement...)
function recuperer_ws_magento($url_ws){
	// si le token est périmé, on en redemande un autre.
	if (!$reponse = oauth_recuperer_ws($url_ws,$GLOBALS['meta']["oauth_token"],$GLOBALS['meta']["oauth_secret"])){
		actualiser_token();
		$reponse = oauth_recuperer_ws($url_ws,$GLOBALS['meta']["oauth_token"],$GLOBALS['meta']["oauth_secret"]);
	}
	if($reponse == NULL)
		return "Serveur REST inaccessible." ;
	return $reponse ;
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

