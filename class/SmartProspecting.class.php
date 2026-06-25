<?php
/**
 * Classe principale SmartProspecting
 * Orchestre les recherches et imports de prospects
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class SmartProspecting extends CommonObject
{
    public $element        = 'smartprospecting';
    public $table_element  = 'smartprospecting_search';
    public $picto          = 'smartprospecting@smartprospecting';

    // Champs de la table llx_smartprospecting_search
    public $ref;
    public $fk_user;
    public $status;
    public $source;
    public $search_query;
    public $nb_results   = 0;
    public $nb_imported  = 0;
    public $nb_duplicates = 0;
    public $nb_errors    = 0;

    // Statuts
    const STATUS_DRAFT    = 0;
    const STATUS_RUNNING  = 1;
    const STATUS_DONE     = 2;
    const STATUS_ERROR    = 3;

    /**
     * Constructeur
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Crée une nouvelle session de recherche en base
     */
    public function create($user, $source, $searchParams)
    {
        global $conf;

        $this->ref          = $this->getNextRef();
        $this->fk_user      = $user->id;
        $this->source       = $source;
        $this->search_query = json_encode($searchParams, JSON_UNESCAPED_UNICODE);
        $this->status       = self::STATUS_DRAFT;
        $this->entity       = $conf->entity;

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."smartprospecting_search";
        $sql .= " (ref, fk_user, date_creation, status, source, search_query, nb_results, nb_imported, nb_duplicates, nb_errors, entity)";
        $sql .= " VALUES ('".$this->db->escape($this->ref)."', ".(int)$this->fk_user.", '".$this->db->idate(dol_now())."',";
        $sql .= " ".(int)$this->status.", '".$this->db->escape($this->source)."', '".$this->db->escape($this->search_query)."',";
        $sql .= " 0, 0, 0, 0, ".(int)$this->entity.")";

        $resql = $this->db->query($sql);
        if ($resql) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."smartprospecting_search");
            return $this->id;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Met à jour les compteurs de la session
     */
    public function updateCounters()
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."smartprospecting_search";
        $sql .= " SET nb_results=".(int)$this->nb_results;
        $sql .= ", nb_imported=".(int)$this->nb_imported;
        $sql .= ", nb_duplicates=".(int)$this->nb_duplicates;
        $sql .= ", nb_errors=".(int)$this->nb_errors;
        $sql .= ", date_last_update='".$this->db->idate(dol_now())."'";
        $sql .= " WHERE rowid=".(int)$this->id;

        return $this->db->query($sql) ? 1 : -1;
    }

    /**
     * Change le statut de la session
     */
    public function setStatus($status)
    {
        $this->status = $status;
        $sql = "UPDATE ".MAIN_DB_PREFIX."smartprospecting_search";
        $sql .= " SET status=".(int)$status;
        $sql .= ", date_last_update='".$this->db->idate(dol_now())."'";
        $sql .= " WHERE rowid=".(int)$this->id;
        return $this->db->query($sql) ? 1 : -1;
    }

    /**
     * Charge une session depuis son ID
     */
    public function fetch($id)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."smartprospecting_search WHERE rowid=".(int)$id;
        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj) {
                $this->id           = $obj->rowid;
                $this->ref          = $obj->ref;
                $this->fk_user      = $obj->fk_user;
                $this->status       = $obj->status;
                $this->source       = $obj->source;
                $this->search_query = $obj->search_query;
                $this->nb_results   = $obj->nb_results;
                $this->nb_imported  = $obj->nb_imported;
                $this->nb_duplicates = $obj->nb_duplicates;
                $this->nb_errors    = $obj->nb_errors;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                return 1;
            }
            return 0;
        }
        $this->error = $this->db->lasterror();
        return -1;
    }

    /**
     * Génère une référence unique pour la session
     */
    private function getNextRef()
    {
        return 'SP-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 6));
    }

    /**
     * Liste les sessions de recherche
     */
    public function fetchAll($limit = 20, $offset = 0, $orderBy = 'date_creation DESC')
    {
        global $conf;
        $list = array();

        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."smartprospecting_search";
        $sql .= " WHERE entity=".(int)$conf->entity;
        $sql .= " ORDER BY ".$orderBy;
        $sql .= " LIMIT ".(int)$limit." OFFSET ".(int)$offset;

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $row = new SmartProspecting($this->db);
                $row->id           = $obj->rowid;
                $row->ref          = $obj->ref;
                $row->fk_user      = $obj->fk_user;
                $row->status       = $obj->status;
                $row->source       = $obj->source;
                $row->search_query = $obj->search_query;
                $row->nb_results   = $obj->nb_results;
                $row->nb_imported  = $obj->nb_imported;
                $row->nb_duplicates = $obj->nb_duplicates;
                $row->date_creation = $this->db->jdate($obj->date_creation);
                $list[] = $row;
            }
            return $list;
        }
        $this->error = $this->db->lasterror();
        return array();
    }

    /**
     * Retourne le libellé du statut
     */
    public function getStatusLabel($status = null)
    {
        global $langs;
        $langs->load('smartprospecting@smartprospecting');
        $s = ($status !== null) ? $status : $this->status;
        $labels = array(
            self::STATUS_DRAFT   => 'Brouillon',
            self::STATUS_RUNNING => 'En cours',
            self::STATUS_DONE    => 'Terminé',
            self::STATUS_ERROR   => 'Erreur',
        );
        return isset($labels[$s]) ? $labels[$s] : 'Inconnu';
    }

    /**
     * Retourne la classe CSS du badge statut
     */
    public function getStatusBadgeClass($status = null)
    {
        $s = ($status !== null) ? $status : $this->status;
        $classes = array(
            self::STATUS_DRAFT   => 'badge badge-status0',
            self::STATUS_RUNNING => 'badge badge-status4',
            self::STATUS_DONE    => 'badge badge-status1',
            self::STATUS_ERROR   => 'badge badge-status8',
        );
        return isset($classes[$s]) ? $classes[$s] : 'badge';
    }
}
