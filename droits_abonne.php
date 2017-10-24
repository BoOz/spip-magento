<?php

/* 
	Calculer les droits numériques à partir des informations envoyées 
		Renvoyer :
		$r = array(
			'droits' => 0/1/3, 
			'date_fin' => $date_fin_abo ,
			'numero_abonne' => $id_abonne,
			'code_magazine' => MD-MDNUM, 
			'type_contrat' => 2-2, 
			'groupeur' => $groupeur, 
			'date_achat' => $date_achat
		) ;
	
	// droit 0 : pas abonné
	// droit 1 : web || print + web 
	// droit 3 : web + archives
	
	// gestion de droits dans le xml arvato
	// statuts des abonnements {0,vérifié,1,Crée,2,Interrompu,3,Suspendu,4,Terminé,5,Incomplet}
	// contract_type {1,ADD,0,ADL ?,2,ADL ?}

	// abonnement pour un ami : 0/PAYER/0 != 0/RECEIVER/0 à traiter

	// controle des droits :
	// - si commande de moins de 5 jours => envoi du code magazine et droits
	// - si abo perso statut < 3 => envoi du code magazine et droits
	// - si pas/plus abonné, pas de code magazine et droits = 0

*/

function droits_abonne($ws_infos){
	
	$abonnements = $ws_infos["abonnements"] ;
	$vpc = $ws_infos["vpc"] ;
	
	// valeur des droits
	$titres_web = array('MD','MDNUM'); // droits = 1
	$titres_archives = array('MDA'); // droits = 3
	$titres_mav = array('MV'); // droits_mav = 1
	$titres = array_merge($titres_web, $titres_archives, $titres_mav);
	$code_magazine = array();
	
	include_spip("inc/filtres");
	// lister les abonnements actifs
	foreach($abonnements as $a){
		// Abonnements avec une date de fin dans le futur,
		// pas suspendu (AND $a['StatusCode'] != "SUSPENDU") mais se débloque qu'au prochain tirage donc non fiable TODO.
		// attention que strtotime de 2099-12-31T00:00:00 ne marche pas (ADI)
		
		if( (strtotime($a['EndDateCoMd']) > time() OR annee($a['EndDateCoMd']) == 2099 ) AND in_array($a["TitleCode"], $titres) ){
			// enregistrer le type d'abo
			if(!in_array($a["TitleCode"], $code_magazine))
				$code_magazine[] = $a["TitleCode"] ;
			
			if($a["SubscriptionType"] == "ADD"){
				$type_contrat[] = 1;
				// date de fin du titre maitre
				if(is_null($a["MasterSubscriptionId"])){
					$date_fin_abo = date('Y-m-d H:i:s', strtotime($a['EndDateCoMd'])) ;
					if(!$date_fin OR $date_fin < $date_fin_abo)
						$date_fin = $date_fin_abo ;
				}
			}
			elseif($a["SubscriptionType"] == "ADI")
				$type_contrat[] = 2;
			else
				$type_contrat[] = $a["SubscriptionType"] ;
			
			// bloquer ou pas des groupeurs
			if($a["IntermediateId"] AND $a["IntermediateId"] != "0010140366" AND $a["IntermediateId"] !="0010447676"){
				$groupeur[] = $a["IntermediateId"] ;
				$droits = 0 ;
			}else{
				// Calcul des droits
				// 1 pour le web seul
				if(in_array($a["TitleCode"], $titres_web) AND intval($droits) < 3)
					$droits = 1;
				// 3 pour le web + archives
				if(in_array($a["TitleCode"], $titres_archives)){
					// cas d'un abo 5 jours
					// pas d'identifiant du titre maitre // ADD
					if(is_null($a["MasterSubscriptionId"]) AND $a["SubscriptionType"] == "ADD"){
						// laisser les droits ouverts 5 jours après la commande
						$date_achat = strtotime($a["OrderDate"]) ;
						$date_fin = date('Y-m-d H:i:s', $date_achat + 5*24*3600);
						
						// si l'offre n'est pas perimee
						if(strtotime($date_fin) > time()){
							$droits = 3;
						}
						
					}else{ // cas général
						$droits = 3;
					}
				}
				// droits_mav pour mav
				if(in_array($a["TitleCode"], $titres_mav))
					$droits_mav = 1 ;
			}
		}
	}
	
	// applatir les données
	foreach(array("code_magazine", "groupeur", "type_contrat") as $d){
		if(is_array($$d)){
			sort($$d);
			$$d = implode("-",$$d) ;
		}
	}
	
	$r = array(
		'droits' => $droits, 
		'date_fin' => $date_fin ,
		'code_magazine' => $code_magazine, 
		'type_contrat' => $type_contrat, 
		'groupeur' => $groupeur, 
		'date_achat' => $date_achat, 
		'droits_mav' => $droits_mav
	);
	
	return $r ;
}
