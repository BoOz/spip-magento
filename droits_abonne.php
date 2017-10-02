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
	
	// lister les abonnements actifs
	foreach($abonnements as $a){
		// Abonnement actif
		if($a['StatusCode'] == "ENCOURS"){
			
			// enregistrer le type d'abo
			if(!in_array($a["TitleCode"], $code_magazine))
				$code_magazine[] = $a["TitleCode"] ;
			
			if($a["SubscriptionType"] == "ADD")
				$type_contrat[] = 1;
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
				if(in_array($a["TitleCode"], $titres_archives))
					$droits = 3;
			}
		}
	}
	
	// applatir les données
	foreach(array("code_magazine", "groupeur", "type_contrat") as $d){
		sort($$d);
		$$d = implode("-",$$d) ;
	}
	
	$r = array(
		'droits' => $droits, 
		'date_fin' => $date_fin_abo ,
		'code_magazine' => $code_magazine, 
		'type_contrat' => $type_contrat, 
		'groupeur' => $groupeur, 
		'date_achat' => $date_achat 
	);
	
	return $r ;
}
