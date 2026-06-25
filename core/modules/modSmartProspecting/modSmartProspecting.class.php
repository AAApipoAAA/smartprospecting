<?php
/* Copyright (C) 2024 Smart Prospecting Module
 * Module SmartProspecting pour Dolibarr ERP
 * Compatible Dolibarr 17+
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modSmartProspecting extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // Identifiant unique du module (utiliser un ID entre 500000 et 599999 pour les modules tiers)
        $this->numero = 500001;
        $this->rights_class = 'smartprospecting';
        $this->family = "crm";
        $this->module_position = '500';
        $this->name = "SmartProspecting";
        $this->description = "Module de prospection intelligente : trouvez automatiquement vos prospects depuis INSEE, Pappers, Google Places et plus encore.";
        $this->descriptionlong = "SmartProspecting vous permet de rechercher des entreprises par secteur d'activité, zone géographique et taille, puis de les importer automatiquement comme Tiers dans Dolibarr avec leurs informations complètes.";
        $this->editor_name = 'SmartProspecting';
        $this->editor_url = 'https://github.com/votre-repo/smartprospecting';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_SMARTPROSPECTING';
        $this->picto = 'smartprospecting@smartprospecting';

        // Dépendances
        $this->depends = array('modSociete');
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array("smartprospecting@smartprospecting");
        $this->phpmin = array(7, 4);
        $this->need_dolibarr_version = array(17, 0);
        $this->warnings_activation = array();
        $this->warnings_activation_ext = array();

        // Tables SQL créées par le module
        $this->module_parts = array(
            'triggers' => 0,
            'login'    => 0,
            'substitutions' => 0,
            'menus'    => 0,
            'tpl'      => 0,
            'barcode'  => 0,
            'models'   => 0,
            'cronjobs' => array(
                1 => array(
                    'label'     => 'SmartProspecting - Import planifié',
                    'jobtype'   => 'method',
                    'class'     => '/smartprospecting/class/SmartProspectingCron.class.php',
                    'objectname' => 'SmartProspectingCron',
                    'method'    => 'runScheduledImport',
                    'parameters' => '',
                    'comment'   => 'Import automatique planifié de prospects',
                    'frequency' => 1,
                    'unitfrequency' => 86400,
                    'priority'  => 50,
                    'status'    => 0,
                    'test'      => true,
                ),
            ),
            'hooks'    => array(
                'data'          => array(
                    'thirdpartylist',
                    'thirdpartydao',
                ),
                'entity'        => '0',
            ),
        );

        // Tables SQL
        $this->tabs = array();
        $this->dictionaries = array();

        // Constantes de configuration
        $this->const = array(
            0 => array(
                'SMARTPROSPECTING_PAPPERS_API_KEY',
                'chaine',
                '',
                'Clé API Pappers.fr',
                0,
                'current',
            ),
            1 => array(
                'SMARTPROSPECTING_GOOGLE_PLACES_API_KEY',
                'chaine',
                '',
                'Clé API Google Places',
                0,
                'current',
            ),
            2 => array(
                'SMARTPROSPECTING_HUNTER_API_KEY',
                'chaine',
                '',
                'Clé API Hunter.io (enrichissement email)',
                0,
                'current',
            ),
            3 => array(
                'SMARTPROSPECTING_DROPCONTACT_API_KEY',
                'chaine',
                '',
                'Clé API Dropcontact (enrichissement email)',
                0,
                'current',
            ),
            4 => array(
                'SMARTPROSPECTING_DEFAULT_PROSPECT_STATUS',
                'chaine',
                '2',
                'Statut par défaut des prospects importés (2=Prospect)',
                0,
                'current',
            ),
            5 => array(
                'SMARTPROSPECTING_IMPORT_BATCH_SIZE',
                'chaine',
                '50',
                'Nombre de prospects importés par batch',
                0,
                'current',
            ),
            6 => array(
                'SMARTPROSPECTING_AUTO_DEDUP',
                'chaine',
                '1',
                'Déduplication automatique par SIRET',
                0,
                'current',
            ),
        );

        // Droits d'accès
        $this->rights = array();
        $r = 0;

        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = 'Voir les recherches SmartProspecting';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'read';
        $r++;

        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = 'Lancer une recherche et importer des prospects';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'write';
        $r++;

        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = 'Configurer SmartProspecting (clés API, paramètres)';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'admin';
        $r++;

        // Menus
        $this->menu = array();
        $r = 0;

        // Menu principal
        $this->menu[$r++] = array(
            'fk_menu'  => 0,
            'type'     => 'top',
            'titre'    => 'SmartProspecting',
            'mainmenu' => 'smartprospecting',
            'leftmenu' => '',
            'url'      => '/smartprospecting/index.php',
            'langs'    => 'smartprospecting@smartprospecting',
            'position' => 300,
            'enabled'  => 'isModEnabled("smartprospecting")',
            'perms'    => '$user->rights->smartprospecting->read',
            'target'   => '',
            'user'     => 0,
        );

        // Sous-menu : Nouvelle recherche
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=smartprospecting',
            'type'     => 'left',
            'titre'    => 'Nouvelle recherche',
            'mainmenu' => 'smartprospecting',
            'leftmenu' => 'search',
            'url'      => '/smartprospecting/search.php',
            'langs'    => 'smartprospecting@smartprospecting',
            'position' => 100,
            'enabled'  => 'isModEnabled("smartprospecting")',
            'perms'    => '$user->rights->smartprospecting->write',
            'target'   => '',
            'user'     => 0,
        );

        // Sous-menu : Historique des imports
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=smartprospecting',
            'type'     => 'left',
            'titre'    => 'Historique imports',
            'mainmenu' => 'smartprospecting',
            'leftmenu' => 'history',
            'url'      => '/smartprospecting/history.php',
            'langs'    => 'smartprospecting@smartprospecting',
            'position' => 110,
            'enabled'  => 'isModEnabled("smartprospecting")',
            'perms'    => '$user->rights->smartprospecting->read',
            'target'   => '',
            'user'     => 0,
        );

        // Sous-menu : Séquences de relance
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=smartprospecting',
            'type'     => 'left',
            'titre'    => 'Séquences relance',
            'mainmenu' => 'smartprospecting',
            'leftmenu' => 'sequences',
            'url'      => '/smartprospecting/sequences.php',
            'langs'    => 'smartprospecting@smartprospecting',
            'position' => 120,
            'enabled'  => 'isModEnabled("smartprospecting")',
            'perms'    => '$user->rights->smartprospecting->write',
            'target'   => '',
            'user'     => 0,
        );

        // Sous-menu : Configuration
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=smartprospecting',
            'type'     => 'left',
            'titre'    => 'Configuration',
            'mainmenu' => 'smartprospecting',
            'leftmenu' => 'config',
            'url'      => '/smartprospecting/admin/setup.php',
            'langs'    => 'smartprospecting@smartprospecting',
            'position' => 200,
            'enabled'  => 'isModEnabled("smartprospecting")',
            'perms'    => '$user->rights->smartprospecting->admin',
            'target'   => '',
            'user'     => 0,
        );
    }

    /**
     * Fonction appelée à l'activation du module
     */
    public function init($options = '')
    {
        global $conf, $langs;
        $result = $this->_load_tables('/smartprospecting/sql/');
        if ($result < 0) return -1;
        return $this->_init(array(), $options);
    }

    /**
     * Fonction appelée à la désactivation du module
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
