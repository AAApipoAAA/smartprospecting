<?php
/**
 * Gestionnaire d'import des prospects vers Dolibarr
 * Crée les Tiers (societe) avec déduplication par SIRET
 */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';

class SmartProspectingImportManager
{
    private $db;
    private $user;
    private $errors  = array();
    private $stats   = array(
        'imported'   => 0,
        'duplicates' => 0,
        'errors'     => 0,
        'total'      => 0,
    );

    public function __construct($db, $user)
    {
        $this->db   = $db;
        $this->user = $user;
    }

    /**
     * Import d'un batch de prospects
     *
     * @param array $prospects    Liste de prospects normalisés
     * @param int   $fkSearch     ID de la session de recherche
     * @param array $options      Options d'import
     * @return array Statistiques d'import
     */
    public function importBatch($prospects, $fkSearch, $options = array())
    {
        global $conf;

        $defaultOptions = array(
            'dedup_siret'       => true,    // Déduplication par SIRET
            'dedup_nom'         => true,    // Déduplication par nom si pas de SIRET
            'statut_client'     => 2,       // 2 = Prospect
            'create_contact'    => true,    // Créer un contact si dirigeant connu
            'tag_source'        => true,    // Ajouter un tag source (INSEE, Pappers...)
            'fk_categorie'      => 0,       // Catégorie tiers à assigner
        );
        $options = array_merge($defaultOptions, $options);

        $this->stats = array('imported' => 0, 'duplicates' => 0, 'errors' => 0, 'total' => count($prospects));

        foreach ($prospects as $prospect) {
            $result = $this->importOne($prospect, $fkSearch, $options);

            if ($result === 'imported')   $this->stats['imported']++;
            elseif ($result === 'duplicate') $this->stats['duplicates']++;
            else                             $this->stats['errors']++;

            // Pause courte pour ne pas saturer la BDD
            if ($this->stats['imported'] % 50 === 0 && $this->stats['imported'] > 0) {
                usleep(100000); // 100ms tous les 50 imports
            }
        }

        return $this->stats;
    }

    /**
     * Import d'un prospect individuel
     */
    public function importOne($prospect, $fkSearch, $options = array())
    {
        $this->db->begin();

        try {
            // 1. Vérification doublon
            if ($options['dedup_siret'] && !empty($prospect['siret'])) {
                $existingId = $this->findExistingBySiret($prospect['siret']);
                if ($existingId) {
                    $this->saveProspectRecord($fkSearch, $prospect, 'duplicate', $existingId);
                    $this->db->rollback();
                    return 'duplicate';
                }
            }

            if ($options['dedup_nom'] && !empty($prospect['nom'])) {
                $existingId = $this->findExistingByNom($prospect['nom'], $prospect['cp'] ?? '');
                if ($existingId) {
                    $this->saveProspectRecord($fkSearch, $prospect, 'duplicate', $existingId);
                    $this->db->rollback();
                    return 'duplicate';
                }
            }

            // 2. Création du Tiers Dolibarr
            $societe = new Societe($this->db);
            $societe->name           = $this->sanitizeString($prospect['nom']);
            $societe->siret          = $prospect['siret'] ?? '';
            $societe->siren          = $prospect['siren'] ?? '';
            $societe->address        = $this->sanitizeString($prospect['adresse'] ?? '');
            $societe->zip            = $prospect['cp'] ?? '';
            $societe->town           = $this->sanitizeString($prospect['ville'] ?? '');
            $societe->country_id     = 74; // France
            $societe->country_code   = 'FR';
            $societe->phone          = $this->formatPhone($prospect['telephone'] ?? '');
            $societe->email          = filter_var($prospect['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $prospect['email'] : '';
            $societe->url            = $prospect['site_web'] ?? '';
            $societe->code_naf       = $prospect['code_naf'] ?? '';
            $societe->effectif       = $prospect['effectif'] ?? '';
            $societe->client         = 0;
            $societe->fournisseur    = 0;
            $societe->prospect       = 1;
            $societe->status         = $options['statut_client'];
            $societe->entity         = isset($conf) ? $conf->entity : 1;

            // Champ personnalisé : source prospection
            $societe->array_options['options_sp_source'] = $prospect['source'] ?? 'smartprospecting';

            // Latitude / Longitude si disponibles
            if (!empty($prospect['latitude'])) {
                $societe->array_options['options_sp_latitude']  = $prospect['latitude'];
                $societe->array_options['options_sp_longitude'] = $prospect['longitude'];
            }

            $societeId = $societe->create($this->user);

            if ($societeId <= 0) {
                $this->errors[] = 'Erreur création tiers '.$prospect['nom'].': '.$societe->error;
                $this->saveProspectRecord($fkSearch, $prospect, 'error');
                $this->db->rollback();
                return 'error';
            }

            // 3. Création du contact (dirigeant) si données disponibles
            if ($options['create_contact'] && !empty($prospect['dirigeant_nom'])) {
                $this->createContact($societeId, $prospect);
            }

            // 4. Note interne avec source de données
            $this->createNote($societeId, $prospect);

            // 5. Sauvegarde dans la table smartprospecting_prospect
            $this->saveProspectRecord($fkSearch, $prospect, 'imported', $societeId);

            $this->db->commit();
            return 'imported';

        } catch (Exception $e) {
            $this->db->rollback();
            $this->errors[] = 'Exception import '.$prospect['nom'].': '.$e->getMessage();
            dol_syslog('SmartProspecting importOne exception: '.$e->getMessage(), LOG_ERR);
            return 'error';
        }
    }

    /**
     * Crée un contact pour le dirigeant de l'entreprise
     */
    private function createContact($societeId, $prospect)
    {
        $contact = new Contact($this->db);
        $contact->socid    = $societeId;
        $contact->lastname = $this->sanitizeString($prospect['dirigeant_nom'] ?? '');
        $contact->firstname = $this->sanitizeString($prospect['dirigeant_prenom'] ?? '');
        $contact->poste    = $prospect['dirigeant_qualite'] ?? 'Dirigeant';
        $contact->email    = filter_var($prospect['dirigeant_email'] ?? '', FILTER_VALIDATE_EMAIL) ? $prospect['dirigeant_email'] : '';
        $contact->phone_pro = $this->formatPhone($prospect['telephone'] ?? '');

        if (!empty($contact->lastname)) {
            $contactId = $contact->create($this->user);
            if ($contactId <= 0) {
                dol_syslog('SmartProspecting createContact error: '.$contact->error, LOG_WARNING);
            }
        }
    }

    /**
     * Crée une note interne avec les infos source
     */
    private function createNote($societeId, $prospect)
    {
        $note = "=== Importé par SmartProspecting ===\n";
        $note .= "Source : ".strtoupper($prospect['source'] ?? 'inconnu')."\n";
        $note .= "Date import : ".date('d/m/Y H:i')."\n";
        if (!empty($prospect['code_naf']))    $note .= "Code NAF : ".$prospect['code_naf']." - ".($prospect['libelle_naf'] ?? '')."\n";
        if (!empty($prospect['effectif']))    $note .= "Effectif : ".$prospect['effectif']."\n";
        if (!empty($prospect['chiffre_affaires'])) $note .= "CA : ".number_format($prospect['chiffre_affaires'], 0, ',', ' ')." €\n";
        if (!empty($prospect['date_creation_soc'])) $note .= "Création : ".$prospect['date_creation_soc']."\n";
        if (!empty($prospect['score']))       $note .= "Score prospection : ".$prospect['score']."/100\n";

        $sql = "UPDATE ".MAIN_DB_PREFIX."societe SET note_private = '".$this->db->escape($note)."'";
        $sql .= " WHERE rowid = ".(int)$societeId;
        $this->db->query($sql);
    }

    /**
     * Enregistre le prospect dans la table de suivi
     */
    private function saveProspectRecord($fkSearch, $prospect, $status, $fkSociete = null)
    {
        global $conf;

        $statusCode = array(
            'found'     => 0,
            'imported'  => 1,
            'duplicate' => 2,
            'error'     => 3,
        );

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."smartprospecting_prospect";
        $sql .= " (fk_search, siret, siren, nom, forme_juridique, code_naf, libelle_naf,";
        $sql .= " adresse, cp, ville, departement, pays, telephone, email, site_web,";
        $sql .= " dirigeant_nom, dirigeant_prenom, effectif, latitude, longitude, score,";
        $sql .= " status, fk_societe, source_data, date_creation, date_import, entity)";
        $sql .= " VALUES (";
        $sql .= (int)$fkSearch.",";
        $sql .= "'".$this->db->escape($prospect['siret'] ?? '')."',";
        $sql .= "'".$this->db->escape($prospect['siren'] ?? '')."',";
        $sql .= "'".$this->db->escape(substr($prospect['nom'] ?? '', 0, 255))."',";
        $sql .= "'".$this->db->escape($prospect['forme_juridique'] ?? '')."',";
        $sql .= "'".$this->db->escape($prospect['code_naf'] ?? '')."',";
        $sql .= "'".$this->db->escape($prospect['libelle_naf'] ?? '')."',";
        $sql .= "'".$this->db->escape(substr($prospect['adresse'] ?? '', 0, 500))."',";
        $sql .= "'".$this->db->escape($prospect['cp'] ?? '')."',";
        $sql .= "'".$this->db->escape($prospect['ville'] ?? '')."',";
        $sql .= "'".$this->db->escape($prospect['departement'] ?? '')."',";
        $sql .= "'".$this->db->escape($prospect['pays'] ?? 'FR')."',";
        $sql .= "'".$this->db->escape($prospect['telephone'] ?? '')."',";
        $sql .= "'".$this->db->escape($prospect['email'] ?? '')."',";
        $sql .= "'".$this->db->escape($prospect['site_web'] ?? '')."',";
        $sql .= "'".$this->db->escape($prospect['dirigeant_nom'] ?? '')."',";
        $sql .= "'".$this->db->escape($prospect['dirigeant_prenom'] ?? '')."',";
        $sql .= "'".$this->db->escape($prospect['effectif'] ?? '')."',";
        $sql .= (!empty($prospect['latitude']) ? floatval($prospect['latitude']) : 'NULL').",";
        $sql .= (!empty($prospect['longitude']) ? floatval($prospect['longitude']) : 'NULL').",";
        $sql .= (int)($prospect['score'] ?? 50).",";
        $sql .= (int)($statusCode[$status] ?? 0).",";
        $sql .= ($fkSociete ? (int)$fkSociete : 'NULL').",";
        $sql .= "'".$this->db->escape(substr($prospect['source_data'] ?? '{}', 0, 65000))."',";
        $sql .= "'".$this->db->idate(dol_now())."',";
        $sql .= ($status === 'imported' ? "'".$this->db->idate(dol_now())."'" : 'NULL').",";
        $sql .= (int)(isset($conf) ? $conf->entity : 1);
        $sql .= ")";

        $this->db->query($sql);
        // On ne remonte pas l'erreur ici pour ne pas bloquer l'import
    }

    /**
     * Vérifie si un SIRET existe déjà dans les Tiers Dolibarr
     */
    private function findExistingBySiret($siret)
    {
        if (strlen($siret) < 9) return false;

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe";
        $sql .= " WHERE siret = '".$this->db->escape($siret)."'";
        $sql .= " OR siren = '".$this->db->escape(substr($siret, 0, 9))."'";
        $sql .= " LIMIT 1";

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return $obj ? $obj->rowid : false;
        }
        return false;
    }

    /**
     * Vérifie si un nom d'entreprise existe déjà (approximatif)
     */
    private function findExistingByNom($nom, $cp = '')
    {
        $nomClean = $this->normalizeNom($nom);

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe";
        $sql .= " WHERE UPPER(REPLACE(REPLACE(name, ' ', ''), '-', '')) = '".$this->db->escape(strtoupper(str_replace(array(' ', '-'), '', $nomClean)))."'";
        if (!empty($cp)) {
            $sql .= " AND zip = '".$this->db->escape($cp)."'";
        }
        $sql .= " LIMIT 1";

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return $obj ? $obj->rowid : false;
        }
        return false;
    }

    /**
     * Normalise un nom d'entreprise pour comparaison
     */
    private function normalizeNom($nom)
    {
        // Supprime les formes juridiques courantes
        $formes = array('SARL ', 'SAS ', 'SASU ', 'SA ', 'EURL ', 'SNC ', 'EI ', 'EI.');
        $nom = str_ireplace($formes, '', $nom);
        return trim($nom);
    }

    /**
     * Formate un numéro de téléphone français
     */
    private function formatPhone($phone)
    {
        if (empty($phone)) return '';
        // Supprime tout sauf les chiffres et +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        // Convertit 33XXXXXXXXX en 0XXXXXXXXX
        if (substr($phone, 0, 2) == '33' && strlen($phone) == 11) {
            $phone = '0'.substr($phone, 2);
        }
        if (substr($phone, 0, 3) == '+33') {
            $phone = '0'.substr($phone, 3);
        }
        // Formate en XX XX XX XX XX
        if (strlen($phone) == 10) {
            return implode(' ', str_split($phone, 2));
        }
        return $phone;
    }

    /**
     * Nettoie une chaîne de caractères
     */
    private function sanitizeString($str)
    {
        $str = strip_tags($str);
        $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        return trim($str);
    }

    /**
     * Retourne les statistiques d'import
     */
    public function getStats()
    {
        return $this->stats;
    }

    /**
     * Retourne les erreurs rencontrées
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
