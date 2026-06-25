<?php
/**
 * Connecteur Pappers.fr
 * API officielle - Données légales, dirigeants, bilans
 * Doc : https://api.pappers.fr/documentation
 * Gratuit jusqu'à 500 req/mois, puis forfaits payants
 */

class SmartProspectingSourcePappers
{
    private $db;
    private $apiKey  = '';
    private $apiBase = 'https://api.pappers.fr/v2';

    public function __construct($db, $apiKey = '')
    {
        $this->db     = $db;
        $this->apiKey = $apiKey;
    }

    /**
     * Enrichit un prospect (SIRET connu) avec les données Pappers
     * Utilisé après un import INSEE pour compléter les données
     *
     * @param string $siret SIRET à enrichir
     * @return array|false
     */
    public function enrichBySiret($siret)
    {
        if (empty($this->apiKey)) return false;

        $url = $this->apiBase.'/entreprise?api_token='.urlencode($this->apiKey);
        $url .= '&siret='.urlencode($siret);
        $url .= '&extrait_kbis=false&statuts=false&actes=false&publications_bodacc=false';
        $url .= '&dispositif_fiscaux=false&comptes=false&beneficiaires_effectifs=true';

        $response = $this->callApi($url);
        if (!$response) return false;

        return $this->normalizeEntreprise($response);
    }

    /**
     * Recherche d'entreprises par critères Pappers
     *
     * @param string $codeNaf         Code NAF
     * @param string $departement     Département
     * @param string $formeJuridique  Forme juridique
     * @param int    $effectifMin     Effectif minimum
     * @param int    $effectifMax     Effectif maximum
     * @param int    $page            Pagination
     */
    public function search($codeNaf = '', $departement = '', $formeJuridique = '', $effectifMin = null, $effectifMax = null, $page = 1)
    {
        if (empty($this->apiKey)) {
            return array('success' => false, 'error' => 'Clé API Pappers manquante', 'data' => array());
        }

        $url = $this->apiBase.'/recherche-entreprises?api_token='.urlencode($this->apiKey);
        $url .= '&par_page=20&page='.(int)$page;
        $url .= '&precision=standard';

        if (!empty($codeNaf))        $url .= '&code_naf='.urlencode($codeNaf);
        if (!empty($departement))    $url .= '&departement='.urlencode($departement);
        if (!empty($formeJuridique)) $url .= '&forme_juridique='.urlencode($formeJuridique);
        if ($effectifMin !== null)   $url .= '&tranche_effectif_min='.urlencode($effectifMin);
        if ($effectifMax !== null)   $url .= '&tranche_effectif_max='.urlencode($effectifMax);

        // Uniquement entreprises actives
        $url .= '&statut=actif';

        $response = $this->callApi($url);

        if (!$response || !isset($response['resultats'])) {
            return array(
                'success' => false,
                'error'   => 'Réponse Pappers invalide',
                'data'    => array(),
                'total'   => 0,
            );
        }

        $results = array();
        foreach ($response['resultats'] as $entreprise) {
            $results[] = $this->normalizeEntreprise($entreprise);
        }

        return array(
            'success' => true,
            'total'   => $response['total'] ?? count($results),
            'page'    => $page,
            'data'    => $results,
        );
    }

    /**
     * Recherche du dirigeant principal d'une entreprise
     */
    public function getDirigeant($siret)
    {
        $data = $this->enrichBySiret($siret);
        if (!$data) return array();

        return array(
            'nom'     => $data['dirigeant_nom'] ?? '',
            'prenom'  => $data['dirigeant_prenom'] ?? '',
            'qualite' => $data['dirigeant_qualite'] ?? '',
        );
    }

    /**
     * Normalise une entreprise Pappers vers le format unifié SmartProspecting
     */
    private function normalizeEntreprise($data)
    {
        // Récupération du dirigeant principal
        $dirigeant = array();
        if (!empty($data['dirigeants'])) {
            foreach ($data['dirigeants'] as $d) {
                if (!empty($d['qualite']) && in_array(strtolower($d['qualite']), array('président', 'gérant', 'directeur général', 'pdg', 'associé gérant'))) {
                    $dirigeant = $d;
                    break;
                }
            }
            if (empty($dirigeant)) {
                $dirigeant = $data['dirigeants'][0];
            }
        }

        // Bénéficiaire effectif
        $beneficiaire = array();
        if (!empty($data['beneficiaires_effectifs'])) {
            $beneficiaire = $data['beneficiaires_effectifs'][0];
        }

        return array(
            'source'            => 'pappers',
            'siret'             => $data['siret'] ?? '',
            'siren'             => $data['siren'] ?? '',
            'nom'               => $data['nom_entreprise'] ?? $data['denomination'] ?? '',
            'forme_juridique'   => $data['forme_juridique'] ?? '',
            'code_naf'          => $data['code_naf'] ?? '',
            'libelle_naf'       => $data['libelle_code_naf'] ?? '',
            'adresse'           => $this->buildAdresse($data),
            'cp'                => $data['siege']['code_postal'] ?? '',
            'ville'             => $data['siege']['ville'] ?? '',
            'departement'       => $data['siege']['departement'] ?? '',
            'pays'              => 'FR',
            'telephone'         => $data['siege']['telephone'] ?? '',
            'email'             => $data['siege']['email'] ?? '',
            'site_web'          => $data['siege']['site_web'] ?? '',
            'dirigeant_nom'     => $dirigeant['nom'] ?? '',
            'dirigeant_prenom'  => $dirigeant['prenom'] ?? '',
            'dirigeant_email'   => '', // Pappers ne donne pas l'email dirigeant
            'dirigeant_qualite' => $dirigeant['qualite'] ?? '',
            'effectif'          => $data['tranche_effectif_salarie'] ?? '',
            'chiffre_affaires'  => $data['chiffre_affaires'] ?? null,
            'date_creation_soc' => $data['date_creation'] ?? '',
            'latitude'          => $data['siege']['latitude'] ?? null,
            'longitude'         => $data['siege']['longitude'] ?? null,
            'score'             => $this->calculateScore($data),
            'source_data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Construit l'adresse complète depuis les données Pappers
     */
    private function buildAdresse($data)
    {
        $siege = $data['siege'] ?? array();
        $parts = array();
        if (!empty($siege['numero_voie'])) $parts[] = $siege['numero_voie'];
        if (!empty($siege['type_voie']))   $parts[] = $siege['type_voie'];
        if (!empty($siege['libelle_voie'])) $parts[] = $siege['libelle_voie'];
        if (!empty($siege['complement_adresse'])) $parts[] = $siege['complement_adresse'];
        return implode(' ', $parts);
    }

    /**
     * Calcule un score de pertinence basé sur la richesse des données
     */
    private function calculateScore($data)
    {
        $score = 50;

        // Bonus données disponibles
        if (!empty($data['siege']['telephone']))  $score += 10;
        if (!empty($data['siege']['email']))      $score += 15;
        if (!empty($data['siege']['site_web']))   $score += 5;
        if (!empty($data['dirigeants']))          $score += 10;
        if (!empty($data['chiffre_affaires']))    $score += 5;
        if (!empty($data['siege']['latitude']))   $score += 5;

        // Bonus activité récente
        if (!empty($data['date_mise_a_jour'])) {
            $miseAJour = strtotime($data['date_mise_a_jour']);
            if ($miseAJour > strtotime('-6 months')) $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Appel HTTP vers l'API Pappers
     */
    private function callApi($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'SmartProspecting-Dolibarr/1.0',
            CURLOPT_HTTPHEADER     => array('Accept: application/json'),
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            dol_syslog('SmartProspecting Pappers cURL error: '.$error, LOG_ERR);
            return false;
        }

        if ($httpCode == 401) {
            dol_syslog('SmartProspecting Pappers: Clé API invalide', LOG_WARNING);
            return false;
        }

        if ($httpCode == 429) {
            dol_syslog('SmartProspecting Pappers: Quota API dépassé', LOG_WARNING);
            return false;
        }

        if ($httpCode != 200) {
            dol_syslog('SmartProspecting Pappers HTTP '.$httpCode.': '.$response, LOG_ERR);
            return false;
        }

        return json_decode($response, true);
    }
}
