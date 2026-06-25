<?php
/**
 * Connecteur INSEE SIRENE
 * API officielle et gratuite - 10 millions d'entreprises françaises
 * Doc : https://api.insee.fr/catalogue/site/themes/wso2/subthemes/insee/pages/item-info.jag?name=Sirene&version=V3&provider=insee
 */

class SmartProspectingSourceINSEE
{
    private $db;
    private $apiBase = 'https://api.insee.fr/entreprises/sirene/V3.11';
    private $token   = '';

    // Mapping codes NAF fréquents vers libellés lisibles
    const NAF_LABELS = array(
        '4120A' => 'Construction de maisons individuelles',
        '4120B' => 'Construction d\'autres bâtiments',
        '4321A' => 'Travaux d\'installation électrique',
        '4322A' => 'Travaux d\'installation de plomberie',
        '4332A' => 'Menuiserie bois et PVC',
        '4711A' => 'Commerce alimentaire',
        '6201Z' => 'Programmation informatique',
        '6202A' => 'Conseil en systèmes et logiciels',
        '6920Z' => 'Activités comptables',
        '7010Z' => 'Activités des sièges sociaux',
        '8621Z' => 'Activité des médecins généralistes',
        '8690A' => 'Ambulances',
        '9601A' => 'Blanchisseries-teintureries',
        '9602A' => 'Coiffure',
    );

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Obtient un token OAuth2 INSEE
     * L'API SIRENE v3.11 nécessite un token
     * Inscription gratuite sur api.insee.fr
     */
    public function getToken($consumerKey, $consumerSecret)
    {
        $credentials = base64_encode($consumerKey.':'.$consumerSecret);

        $ch = curl_init('https://api.insee.fr/token');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => array(
                'Authorization: Basic '.$credentials,
                'Content-Type: application/x-www-form-urlencoded',
            ),
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            $data = json_decode($response, true);
            $this->token = $data['access_token'];
            return $this->token;
        }

        return false;
    }

    /**
     * Recherche d'entreprises par critères
     *
     * @param string $codeNaf     Code NAF (ex: "6201Z")
     * @param string $departement Département (ex: "79" pour Deux-Sèvres)
     * @param int    $limit       Nombre de résultats max
     * @param int    $offset      Pagination
     * @param array  $filters     Filtres supplémentaires (effectif, etc.)
     * @return array
     */
    public function searchByCriteria($codeNaf = '', $departement = '', $limit = 50, $offset = 0, $filters = array())
    {
        $results = array();

        // Construction de la requête SIRENE Q (syntaxe Lucene)
        $queryParts = array();

        // Uniquement les entreprises actives
        $queryParts[] = 'etatAdministratifUniteLegale:A';

        if (!empty($codeNaf)) {
            // Recherche sur le code NAF principal
            $queryParts[] = 'activitePrincipaleUniteLegale:'.$codeNaf;
        }

        if (!empty($departement)) {
            $queryParts[] = 'codePostalEtablissement:'.$departement.'*';
        }

        // Filtre effectif si demandé
        if (!empty($filters['effectif_min'])) {
            $queryParts[] = 'trancheEffectifsUniteLegale:['.$filters['effectif_min'].' TO *]';
        }

        // Uniquement siège social
        $queryParts[] = 'etablissementSiege:true';

        $query = implode(' AND ', $queryParts);

        $url = $this->apiBase.'/siret?q='.urlencode($query).'&nombre='.(int)$limit.'&debut='.(int)$offset;
        $url .= '&champs=siren,siret,denominationUniteLegale,nomUsageUniteLegale,prenom1UniteLegale,nomUniteLegale,';
        $url .= 'activitePrincipaleUniteLegale,categorieJuridiqueUniteLegale,dateCreationUniteLegale,';
        $url .= 'trancheEffectifsUniteLegale,adresseEtablissement,numeroVoieEtablissement,typeVoieEtablissement,';
        $url .= 'libelleVoieEtablissement,codePostalEtablissement,libelleCommuneEtablissement,';
        $url .= 'coordonneeLambertAbscisseEtablissement,coordonneeLambertOrdonneeEtablissement';

        $response = $this->callApi($url);

        if (!$response || !isset($response['etablissements'])) {
            return array(
                'success' => false,
                'error'   => 'Réponse INSEE invalide ou token manquant',
                'total'   => 0,
                'data'    => array(),
            );
        }

        foreach ($response['etablissements'] as $etab) {
            $results[] = $this->normalizeEtablissement($etab);
        }

        return array(
            'success' => true,
            'total'   => isset($response['header']['total']) ? $response['header']['total'] : count($results),
            'data'    => $results,
        );
    }

    /**
     * Recherche par rayon géographique
     * Nécessite les coordonnées GPS du centre
     *
     * Note : L'API SIRENE ne supporte pas nativement la recherche géographique.
     * On utilise le département + filtrage par coordonnées en post-traitement,
     * ou on passe par Google Places pour la géolocalisation d'abord.
     *
     * @param float  $lat      Latitude centre
     * @param float  $lng      Longitude centre
     * @param int    $radiusKm Rayon en km
     * @param string $codeNaf  Code NAF optionnel
     */
    public function searchByRadius($lat, $lng, $radiusKm, $codeNaf = '')
    {
        // Calcul des départements dans le rayon (approximation)
        $departements = $this->getDepartementsInRadius($lat, $lng, $radiusKm);

        $allResults = array();
        foreach ($departements as $dep) {
            $res = $this->searchByCriteria($codeNaf, $dep, 100, 0);
            if ($res['success'] && !empty($res['data'])) {
                $allResults = array_merge($allResults, $res['data']);
            }
        }

        // Filtrage par distance réelle
        $filtered = array();
        foreach ($allResults as $prospect) {
            if (!empty($prospect['latitude']) && !empty($prospect['longitude'])) {
                $dist = $this->calculateDistance($lat, $lng, $prospect['latitude'], $prospect['longitude']);
                if ($dist <= $radiusKm) {
                    $prospect['distance_km'] = round($dist, 1);
                    $filtered[] = $prospect;
                }
            } else {
                // Sans coordonnées, on inclut quand même (département dans le rayon)
                $prospect['distance_km'] = null;
                $filtered[] = $prospect;
            }
        }

        // Tri par distance
        usort($filtered, function($a, $b) {
            if ($a['distance_km'] === null) return 1;
            if ($b['distance_km'] === null) return -1;
            return $a['distance_km'] <=> $b['distance_km'];
        });

        return array(
            'success' => true,
            'total'   => count($filtered),
            'data'    => $filtered,
        );
    }

    /**
     * Normalise un établissement INSEE vers le format unifié SmartProspecting
     */
    private function normalizeEtablissement($etab)
    {
        $ul = $etab['uniteLegale'] ?? array();
        $adr = $etab['adresseEtablissement'] ?? array();

        // Nom de l'entreprise
        $nom = '';
        if (!empty($ul['denominationUniteLegale'])) {
            $nom = $ul['denominationUniteLegale'];
        } elseif (!empty($ul['nomUsageUniteLegale'])) {
            $nom = $ul['prenom1UniteLegale'].' '.$ul['nomUsageUniteLegale'];
        } elseif (!empty($ul['nomUniteLegale'])) {
            $nom = ($ul['prenom1UniteLegale'] ?? '').' '.$ul['nomUniteLegale'];
        }

        // Adresse
        $adresse = trim(
            ($adr['numeroVoieEtablissement'] ?? '').' '.
            ($adr['typeVoieEtablissement'] ?? '').' '.
            ($adr['libelleVoieEtablissement'] ?? '')
        );

        // Coordonnées (Lambert -> WGS84 si disponible)
        $lat = null;
        $lng = null;
        if (!empty($etab['coordonneeLambertAbscisseEtablissement']) && !empty($etab['coordonneeLambertOrdonneeEtablissement'])) {
            list($lat, $lng) = $this->lambertToWGS84(
                floatval($etab['coordonneeLambertAbscisseEtablissement']),
                floatval($etab['coordonneeLambertOrdonneeEtablissement'])
            );
        }

        return array(
            'source'            => 'insee',
            'siret'             => $etab['siret'] ?? '',
            'siren'             => $ul['siren'] ?? substr($etab['siret'] ?? '', 0, 9),
            'nom'               => trim($nom),
            'forme_juridique'   => $this->getCatJuridiqueLabel($ul['categorieJuridiqueUniteLegale'] ?? ''),
            'code_naf'          => $ul['activitePrincipaleUniteLegale'] ?? '',
            'libelle_naf'       => self::NAF_LABELS[$ul['activitePrincipaleUniteLegale'] ?? ''] ?? '',
            'adresse'           => $adresse,
            'cp'                => $adr['codePostalEtablissement'] ?? '',
            'ville'             => $adr['libelleCommuneEtablissement'] ?? '',
            'departement'       => substr($adr['codePostalEtablissement'] ?? '', 0, 2),
            'pays'              => 'FR',
            'telephone'         => '',  // INSEE ne donne pas les téléphones
            'email'             => '',  // Pareil
            'site_web'          => '',
            'dirigeant_nom'     => $ul['nomUniteLegale'] ?? '',
            'dirigeant_prenom'  => $ul['prenom1UniteLegale'] ?? '',
            'effectif'          => $this->getEffectifLabel($ul['trancheEffectifsUniteLegale'] ?? ''),
            'date_creation_soc' => $ul['dateCreationUniteLegale'] ?? '',
            'latitude'          => $lat,
            'longitude'         => $lng,
            'score'             => 60, // Score par défaut INSEE
            'source_data'       => json_encode($etab, JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Appel HTTP vers l'API INSEE
     */
    private function callApi($url)
    {
        if (empty($this->token)) {
            dol_syslog('SmartProspecting INSEE: Token manquant', LOG_WARNING);
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => array(
                'Authorization: Bearer '.$this->token,
                'Accept: application/json',
            ),
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            dol_syslog('SmartProspecting INSEE cURL error: '.$error, LOG_ERR);
            return false;
        }

        if ($httpCode == 429) {
            dol_syslog('SmartProspecting INSEE: Rate limit atteint', LOG_WARNING);
            return false;
        }

        if ($httpCode != 200) {
            dol_syslog('SmartProspecting INSEE HTTP '.$httpCode.': '.$response, LOG_ERR);
            return false;
        }

        return json_decode($response, true);
    }

    /**
     * Calcule la distance en km entre deux points GPS (formule Haversine)
     */
    public function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $R = 6371; // Rayon Terre en km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }

    /**
     * Conversion Lambert93 → WGS84 (approximation)
     */
    private function lambertToWGS84($x, $y)
    {
        // Paramètres Lambert 93
        $n = 0.7256077650532670;
        $c = 11754255.4261;
        $xs = 700000.0;
        $ys = 12655612.0499;
        $e = 0.0818191910428158;

        $r = sqrt(($x - $xs) * ($x - $xs) + ($y - $ys) * ($y - $ys));
        $theta = atan(($x - $xs) / ($ys - $y));

        $lng = ($theta / $n + 3.0 * pi() / 180.0) * 180.0 / pi();

        $latiso = -log(abs($r / $c)) / $n;
        $lat = 2 * atan(exp($latiso)) - pi() / 2;

        // Itération correction méridien
        for ($i = 0; $i < 10; $i++) {
            $sinLat = $e * sin($lat);
            $latNew = 2 * atan(exp($latiso) * pow((1 + $sinLat) / (1 - $sinLat), $e / 2)) - pi() / 2;
            if (abs($latNew - $lat) < 1e-10) break;
            $lat = $latNew;
        }

        return array(
            round($lat * 180.0 / pi(), 7),
            round($lng, 7)
        );
    }

    /**
     * Détermine les départements approximativement dans un rayon
     */
    private function getDepartementsInRadius($lat, $lng, $radiusKm)
    {
        // Approximation : 1° lat ≈ 111 km, 1° lng ≈ 111*cos(lat) km
        $deltaLat = $radiusKm / 111.0;
        $deltaLng = $radiusKm / (111.0 * cos(deg2rad($lat)));

        // Pour l'instant retourner le département principal (à enrichir avec une vraie carte)
        // En production : requête sur une table de correspondance GPS → département
        $cp = $this->getCpFromCoords($lat, $lng);
        return array(substr($cp, 0, 2));
    }

    /**
     * Obtient le code postal approximatif depuis des coordonnées
     * (version simplifiée - à enrichir avec l'API Géo gouvernementale)
     */
    private function getCpFromCoords($lat, $lng)
    {
        // API Géo officielle : https://api-adresse.data.gouv.fr
        $url = 'https://api-adresse.data.gouv.fr/reverse/?lon='.$lng.'&lat='.$lat;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!empty($data['features'][0]['properties']['postcode'])) {
            return $data['features'][0]['properties']['postcode'];
        }
        return '75'; // Fallback Paris
    }

    /**
     * Labels tranche d'effectifs INSEE
     */
    private function getEffectifLabel($code)
    {
        $labels = array(
            'NN' => 'Non employeur',
            '00' => '0 salarié',
            '01' => '1 à 2 salariés',
            '02' => '3 à 5 salariés',
            '03' => '6 à 9 salariés',
            '11' => '10 à 19 salariés',
            '12' => '20 à 49 salariés',
            '21' => '50 à 99 salariés',
            '22' => '100 à 199 salariés',
            '31' => '200 à 249 salariés',
            '32' => '250 à 499 salariés',
            '41' => '500 à 999 salariés',
            '42' => '1 000 à 1 999 salariés',
            '51' => '2 000 à 4 999 salariés',
            '52' => '5 000 à 9 999 salariés',
            '53' => '10 000 salariés et plus',
        );
        return $labels[$code] ?? $code;
    }

    /**
     * Labels catégories juridiques INSEE (principales)
     */
    private function getCatJuridiqueLabel($code)
    {
        $labels = array(
            '1000' => 'Entrepreneur individuel',
            '5499' => 'SARL',
            '5710' => 'SAS',
            '5720' => 'SASU',
            '5498' => 'EURL',
            '6540' => 'SA',
            '9220' => 'Association',
        );
        // Correspondance approximative par préfixe
        foreach ($labels as $k => $v) {
            if (substr($code, 0, 2) == substr($k, 0, 2)) return $v;
        }
        return $code;
    }
}
