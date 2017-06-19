<?php

/* Connexion avec OAuth au WS Magento
https://hueniverse.com/beginners-guide-to-oauth-part-iii-security-architecture-e9394f5263b5
https://oauth.net/core/1.0/
*/

// Token
function oauth_recuperer_token($url_token){
	// GET via curl
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url_token);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$output = curl_exec($ch);
	curl_close($ch);
	$reponse  = json_decode($output,true);
	//var_dump($reponse);
	return $reponse ;
}

// Parametres OAuth
// Parameters are sorted by name, using lexicographical byte value ordering.

function oauth_generer_parametres($token){
	$parametres = array_map("rawurlencode", array(
	'oauth_version' => '1.0',
	'oauth_signature_method' => 'HMAC-SHA1',
	'oauth_nonce' => oauth_generer_nonce(),
	'oauth_timestamp' => time(),
	'oauth_consumer_key' => CONSUMER_KEY,
	'oauth_token' => $token
	));
	uksort($parametres, 'strcmp');
	return $parametres ;
}

/* The base string defined as the method, the url
* and the parameters (normalized), each urlencoded
* and the concated with &.
*/

function oauth_generer_base_string($method, $url_ws, $signable_parameters){
	$method = strtoupper($method);
	$base_string = rawurlencode($method) . "&" . rawurlencode($url_ws) . "&" . rawurlencode($signable_parameters) ;
	return $base_string ;
}

// Identifiant unique de la requete : number used once = nonce
function oauth_generer_nonce(){
	$mt = microtime();
	$rand = mt_rand();
	$nonce = md5($mt . $rand);
	return $nonce ;
}

function oauth_generer_parametres_signables($parametres){
	// For each parameter, the name is separated from the corresponding value by an '=' character (ASCII code 61)
	// On ne signe pas la signature...
	$pairs = array();
	foreach ($parametres as $parameter => $value) {
		if (is_array($value)) {
			// If two or more parameters share the same name, they are sorted by their value
			// Ref: Spec: 9.1.1 (1)
			natsort($value);
			foreach ($value as $duplicate_value) {
				$pairs[] = $parameter . '=' . $duplicate_value;
			}
		} else {
			$pairs[] = $parameter . '=' . $value;
		}
	}
	
	// Each name-value pair is separated by an '&' character (ASCII code 38)
	$signable_parameters = implode('&', $pairs);
	return $signable_parameters ;
}

function oauth_generer_signature($consumer_secret,$secret,$base_string){
	/**
	* The HMAC-SHA1 signature method uses the HMAC-SHA1 signature algorithm as defined in [RFC2104]
	* where the Signature Base String is the text and the key is the concatenated values (each first
	* encoded per Parameter Encoding) of the Consumer Secret and Token Secret, separated by an '&'
	* character (ASCII code 38) even if empty.
	*   - Chapter 9.2 ("HMAC-SHA1")
	*/
	
	$key_parts = array(rawurlencode($consumer_secret),($secret) ? rawurlencode($secret) : "");
	$key = implode('&', $key_parts);
	$signature = base64_encode(hash_hmac('sha1', $base_string, $key, true));
	return $signature ;
}

# Parameter names and values are encoded per Parameter Encoding.
# For each parameter, the name is immediately followed by an ‘=’ character (ASCII code 61), a ‘”’ character (ASCII code 34), the parameter value (MAY be empty), and another ‘”’ character (ASCII code 34).
function oauth_generer_entetes($oauth){
	$values = array();
	foreach($oauth as $key=>$value)
		$values[] = rawurlencode($key) .'="' . rawurlencode($value) . '"'; 
	
	$r = implode(',', $values);
	return "Authorization:OAuth," . $r; 
}

function oauth_recuperer_ws($url_ws,$token,$secret){
	$parametres = oauth_generer_parametres($token) ;
	$signable_parameters = oauth_generer_parametres_signables($parametres);
	$base_string = oauth_generer_base_string("GET", $url_ws, $signable_parameters);
	$signature = oauth_generer_signature(CONSUMER_SECRET,$secret,$base_string);

	// On ajoute la signature dans les parametres pour les entetes.
	$parametres['oauth_signature'] = $signature ;
	// GET via curl
	$c = curl_init();
	curl_setopt($c, CURLOPT_URL, $url_ws);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
	//setting custom header
	curl_setopt($c, CURLOPT_HTTPHEADER, array(oauth_generer_entetes($parametres))); 
	$output = curl_exec($c);
	curl_close($c);
	$reponse  = json_decode($output,true);
	
	// si token expiré ou rejeté, en redemander un et relancer la requete.
	// http://devdocs.magento.com/guides/m1x/api/rest/authentication/oauth_authentication.html
	if($erreur = $reponse['messages']['error'][0]["message"] == "oauth_problem=token_expired"){
		spip_log($erreur, "OAuth-erreurs");
		return false ;
	}elseif($erreur = $reponse['messages']['error'][0]["message"] == "oauth_problem=token_rejected"){
		spip_log($erreur, "OAuth-erreurs");
		return false ;
	}
	elseif($reponse['messages']['error'][0]["message"]){
		spip_log($erreur,$reponse['messages']['error'][0]["message"], "OAuth-erreurs") ;
	}
	
	return $reponse ;
}
