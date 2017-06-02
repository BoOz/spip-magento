# spip-oauth
Connexion OAuth 1.0 avec SPIP

# Dans config/mes_options.php

Déclarer la config OAuth 1.0

/* Connexion avec OAuth au 1.0 WS Magento */

define("SERVICE_PROVIDER","http://magentohost"); 
define("CONSUMER_KEY", "cle_recue_de_la_part_du_fournisseur_d_autorisation");
define("CONSUMER_SECRET", "secret_recu_de_la_part_du_fournisseur_d_autorisation");

define("URL_TOKEN", SERVICE_PROVIDER . "http://magentohost/oauth/token/?key=".CONSUMER_KEY."&secret=".CONSUMER_SECRET);

/* Récuperer les infos d'un client : http://magentohost/api/rest/customers/[ID_MAGENTO] */
define("URL_WS_CLIENT", SERVICE_PROVIDER . "/api/rest/customers");
