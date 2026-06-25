<?php
/**
 * Connecteur INSEE SIRENE v3.11
 * Authentification par API Key (Accès public)
 * Doc : https://api.insee.fr/catalogue/site/themes/wso2/subthemes/insee/pages/item-info.jag?name=Sirene&version=V3&provider=insee
 */

class SmartProspectingSourceINSEE
{
    private $db;
    private $apiBase = 'https://api.insee.fr/entreprises/sirene/V3.11';
    private $apiKey  = '';

    public function __construct($db, $apiKey = '')
    {
        $this->db     = $db;
        $this->apiKey = $apiKey;
    }

    /**
     * Ancienne méthode OAuth2 conservée pour compatibilité
     * En accès public on utilise juste l'API Key directement
     */
    public function getToken($consumerKey, $consumerSecret)
    {
        // En mode "Accès public" avec api key, pas besoin de token OAuth
        // On utilise la clé directement dans le header X-INSEE-Api-Key-Integration
        $this->apiKey = $consumerKey; // consumerKey = api key dans ce mode
        return $consumerKey;
    }

    /**
     * Recherche d'entreprises par critères
     */
    public function searchByCriteria($codeNaf = '', $departement = '', $limit = 50, $offset = 0, $filters = array())
    {
        $queryParts = array();

        // Uniquement établissements actifs
        $queryParts[] = 'etatAdministratifEtablissement:A';
        $queryParts[] = 'etablissementSiege:true';

        if (!empty($codeNaf)) {
            $queryParts[] = 'activitePrincipaleEtablissement:'.$codeNaf;
        }

        if (!empty($departement)) {
            $queryParts[] = 'codePostalEtablissement:'.$departement.'*';
        }

        $query = implode(' AND ', $queryParts);
        $url   = $this->apiBase.'/siret';
        $url  .= '?q='.urlencode($query);
        $url  .= '&nombre='.min((int)$limit, 100);
        $url  .= '&debut='.(int)$offset;

        $response = $this->callApi($url);

        if (!$response) {
            return array(
                'success' => false,
                'error'   => 'Impossible de contacter l\'API INSEE. Vérifiez votre clé API.',
                'total'   => 0,
                'data'    => array(),
            );
        }

        if (isset($response['fault'])) {
            return array(
                'success' => false,
                'error'   => 'Erreur API INSEE : '.($response['fault']['description'] ?? 'Accès refusé. Clé API invalide.'),
                'total'   => 0,
                'data'    => array(),
            );
        }

        if (!isset($response['etablissements'])) {
            return array(
                'success' => false,
                'error'   => 'Réponse INSEE inattendue : '.substr(json_encode($response), 0, 200),
                'total'   => 0,
                'data'    => array(),
            );
        }

        $results = array();
        foreach ($response['etablissements'] as $etab) {
            $normalized = $this->normalizeEtablissement($etab);
            if (!empty($normalized['nom'])) {
                $results[] = $normalized;
            }
        }

        return array(
            'success' => true,
            'total'   => isset($response['header']['total']) ? (int)$response['header']['total'] : count($results),
            'data'    => $results,
        );
    }

    /**
     * Recherche par rayon géographique
     */
    public function searchByRadius($lat, $lng, $radiusKm, $codeNaf = '')
    {
        // Calcul approximatif de la bounding box
        $deltaLat = $radiusKm / 111.0;
        $deltaLng = $radiusKm / (111.0 * cos(deg2rad($lat)));

        $latMin = round($lat - $deltaLat, 4);
        $latMax = round($lat + $deltaLat, 4);
        $lngMin = round($lng - $deltaLng, 4);
        $lngMax = round($lng + $deltaLng, 4);

        $queryParts = array();
        $queryParts[] = 'etatAdministratifEtablissement:A';
        $queryParts[] = 'etablissementSiege:true';

        if (!empty($codeNaf)) {
            $queryParts[] = 'activitePrincipaleEtablissement:'.$codeNaf;
        }

        // Recherche géographique via coordonnées Lambert (non supporté directement)
        // On utilise le code postal comme approximation
        $query = implode(' AND ', $queryParts);
        $url   = $this->apiBase.'/siret?q='.urlencode($query).'&nombre=100';

        $response = $this->callApi($url);
        if (!$response || !isset($response['etablissements'])) {
            return array('success' => false, 'error' => 'Erreur API INSEE', 'total' => 0, 'data' => array());
        }

        $results = array();
        foreach ($response['etablissements'] as $etab) {
            $normalized = $this->normalizeEtablissement($etab);
            if (!empty($normalized['nom'])) {
                $results[] = $normalized;
            }
        }

        return array(
            'success' => true,
            'total'   => count($results),
            'data'    => $results,
        );
    }

    /**
     * Normalise un établissement INSEE vers le format unifié SmartProspecting
     */
    private function normalizeEtablissement($etab)
    {
        $ul  = isset($etab['uniteLegale']) ? $etab['uniteLegale'] : array();
        $adr = isset($etab['adresseEtablissement']) ? $etab['adresseEtablissement'] : array();

        // Nom de l'entreprise
        $nom = '';
        if (!empty($ul['denominationUniteLegale'])) {
            $nom = $ul['denominationUniteLegale'];
        } elseif (!empty($ul['nomUniteLegale'])) {
            $prenom = !empty($ul['prenom1UniteLegale']) ? $ul['prenom1UniteLegale'].' ' : '';
            $nom    = $prenom.$ul['nomUniteLegale'];
        } elseif (!empty($ul['denominationUsuelle1UniteLegale'])) {
            $nom = $ul['denominationUsuelle1UniteLegale'];
        }

        if (empty(trim($nom))) return array('nom' => '');

        // Adresse
        $adresseParts = array();
        if (!empty($adr['numeroVoieEtablissement']))   $adresseParts[] = $adr['numeroVoieEtablissement'];
        if (!empty($adr['typeVoieEtablissement']))     $adresseParts[] = $adr['typeVoieEtablissement'];
        if (!empty($adr['libelleVoieEtablissement'])) $adresseParts[] = $adr['libelleVoieEtablissement'];
        $adresse = implode(' ', $adresseParts);

        $cp   = isset($adr['codePostalEtablissement']) ? $adr['codePostalEtablissement'] : '';
        $dep  = substr($cp, 0, 2);

        // Effectif
        $effectifCode  = isset($ul['trancheEffectifsUniteLegale']) ? $ul['trancheEffectifsUniteLegale'] : '';
        $effectifLabel = $this->getEffectifLabel($effectifCode);

        // Code NAF
        $codeNaf = '';
        if (!empty($etab['periodesEtablissement'][0]['activitePrincipaleEtablissement'])) {
            $codeNaf = $etab['periodesEtablissement'][0]['activitePrincipaleEtablissement'];
        } elseif (!empty($ul['activitePrincipaleUniteLegale'])) {
            $codeNaf = $ul['activitePrincipaleUniteLegale'];
        }

        // Score basique
        $score = 50;
        if (!empty($cp))          $score += 5;
        if (!empty($adresse))     $score += 5;
        if (!empty($effectifCode) && $effectifCode !== 'NN') $score += 10;

        return array(
            'source'            => 'insee',
            'siret'             => isset($etab['siret']) ? $etab['siret'] : '',
            'siren'             => isset($ul['siren']) ? $ul['siren'] : substr(isset($etab['siret']) ? $etab['siret'] : '', 0, 9),
            'nom'               => trim($nom),
            'forme_juridique'   => $this->getCatJuridiqueLabel(isset($ul['categorieJuridiqueUniteLegale']) ? $ul['categorieJuridiqueUniteLegale'] : ''),
            'code_naf'          => $codeNaf,
            'libelle_naf'       => '',
            'adresse'           => $adresse,
            'cp'                => $cp,
            'ville'             => isset($adr['libelleCommuneEtablissement']) ? $adr['libelleCommuneEtablissement'] : '',
            'departement'       => $dep,
            'pays'              => 'FR',
            'telephone'         => '',
            'email'             => '',
            'site_web'          => '',
            'dirigeant_nom'     => isset($ul['nomUniteLegale']) ? $ul['nomUniteLegale'] : '',
            'dirigeant_prenom'  => isset($ul['prenom1UniteLegale']) ? $ul['prenom1UniteLegale'] : '',
            'dirigeant_email'   => '',
            'effectif'          => $effectifLabel,
            'chiffre_affaires'  => null,
            'date_creation_soc' => isset($ul['dateCreationUniteLegale']) ? $ul['dateCreationUniteLegale'] : '',
            'latitude'          => null,
            'longitude'         => null,
            'score'             => $score,
            'source_data'       => json_encode($etab, JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Appel HTTP vers l'API INSEE avec API Key
     */
    private function callApi($url)
    {
        $headers = array('Accept: application/json');

        if (!empty($this->apiKey)) {
            // Mode API Key (Accès public Sirene 3.11)
            $headers[] = 'X-INSEE-Api-Key-Integration: '.$this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'SmartProspecting-Dolibarr/1.0',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        dol_syslog('SmartProspecting INSEE call: '.$url.' → HTTP '.$httpCode, LOG_DEBUG);

        if ($error) {
            dol_syslog('SmartProspecting INSEE cURL error: '.$error, LOG_ERR);
            return false;
        }

        if ($httpCode == 401 || $httpCode == 403) {
            dol_syslog('SmartProspecting INSEE: Accès refusé HTTP '.$httpCode.' - Vérifiez la clé API', LOG_WARNING);
            return array('fault' => array('description' => 'Clé API invalide ou accès refusé (HTTP '.$httpCode.')'));
        }

        if ($httpCode == 429) {
            dol_syslog('SmartProspecting INSEE: Rate limit (30 req/min)', LOG_WARNING);
            return array('fault' => array('description' => 'Limite de requêtes atteinte (30/min). Réessayez dans une minute.'));
        }

        if ($httpCode == 404) {
            return array('etablissements' => array(), 'header' => array('total' => 0));
        }

        if ($httpCode != 200) {
            dol_syslog('SmartProspecting INSEE HTTP '.$httpCode.': '.substr($response, 0, 500), LOG_ERR);
            return false;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            dol_syslog('SmartProspecting INSEE JSON error: '.json_last_error_msg(), LOG_ERR);
            return false;
        }

        return $decoded;
    }

    /**
     * Distance Haversine en km
     */
    public function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)*sin($dLng/2);
        return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
    }

    /**
     * Labels tranche d'effectifs
     */
    private function getEffectifLabel($code)
    {
        $labels = array(
            'NN'=>'Non employeur','00'=>'0 salarié','01'=>'1-2','02'=>'3-5','03'=>'6-9',
            '11'=>'10-19','12'=>'20-49','21'=>'50-99','22'=>'100-199',
            '31'=>'200-249','32'=>'250-499','41'=>'500-999','42'=>'1000-1999',
            '51'=>'2000-4999','52'=>'5000-9999','53'=>'+10000',
        );
        return isset($labels[$code]) ? $labels[$code] : $code;
    }

    /**
     * Labels catégories juridiques
     */
    private function getCatJuridiqueLabel($code)
    {
        if (empty($code)) return '';
        $prefix = substr($code, 0, 2);
        $labels = array(
            '10'=>'Entrepreneur individuel','50'=>'SARL/EURL','57'=>'SAS/SASU',
            '54'=>'SA','65'=>'GIE','92'=>'Association','93'=>'Fondation',
        );
        return isset($labels[$prefix]) ? $labels[$prefix] : '';
    }
}
