<?php
/**
 * Page de recherche SmartProspecting
 * Formulaire de recherche + lancement de l'import
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))               { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php"))            { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))         { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists(__DIR__."/../../../main.inc.php")) { $res = @include __DIR__."/../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

dol_include_once('/smartprospecting/class/SmartProspecting.class.php');
dol_include_once('/smartprospecting/class/SmartProspectingSourceINSEE.class.php');
dol_include_once('/smartprospecting/class/SmartProspectingSourcePappers.class.php');
dol_include_once('/smartprospecting/class/SmartProspectingImportManager.class.php');

if (!isModEnabled('smartprospecting')) accessforbidden('Module SmartProspecting non activé');
if (!$user->rights->smartprospecting->write) accessforbidden();

$langs->loadLangs(array('smartprospecting@smartprospecting', 'companies'));
$action = GETPOST('action', 'alpha');

// =============================================
// Traitement du formulaire
// =============================================
$searchResult = null;
$searchId     = null;
$importStats  = null;

if ($action === 'search' && !empty(GETPOST('search_source'))) {

    $source      = GETPOST('search_source', 'alpha');
    $codeNaf     = GETPOST('code_naf', 'alpha');
    $departement = GETPOST('departement', 'alpha');
    $ville       = GETPOST('ville', 'aZ09');
    $radiusKm    = (int)GETPOST('radius_km', 'int');
    $latCenter   = GETPOST('lat_center', 'float');
    $lngCenter   = GETPOST('lng_center', 'float');
    $limitImport = min((int)GETPOST('limit_import', 'int'), 500);
    $doImport    = GETPOST('do_import', 'alpha') === '1';

    // Création session de recherche
    $sp = new SmartProspecting($db);
    $searchParams = array(
        'source'      => $source,
        'code_naf'    => $codeNaf,
        'departement' => $departement,
        'ville'       => $ville,
        'radius_km'   => $radiusKm,
        'lat_center'  => $latCenter,
        'lng_center'  => $lngCenter,
        'limit'       => $limitImport,
    );

    $searchId = $sp->create($user, $source, $searchParams);
    if ($searchId > 0) {
        $sp->setStatus(SmartProspecting::STATUS_RUNNING);

        // Lancement de la recherche selon la source
        $results = array('success' => false, 'data' => array(), 'total' => 0);

        if ($source === 'insee') {
            $insee   = new SmartProspectingSourceINSEE($db);
            // Token INSEE (à configurer dans admin/setup.php)
            $consKey = getDolGlobalString('SMARTPROSPECTING_INSEE_CONSUMER_KEY');
            $consSecret = getDolGlobalString('SMARTPROSPECTING_INSEE_CONSUMER_SECRET');
            if ($consKey && $consSecret) {
                $insee->getToken($consKey, $consSecret);
            } else {
                // Mode dégradé : on passe sans token (quota très limité)
                dol_syslog('SmartProspecting: Token INSEE non configuré, mode dégradé', LOG_WARNING);
            }

            if (!empty($radiusKm) && !empty($latCenter) && !empty($lngCenter)) {
                $results = $insee->searchByRadius($latCenter, $lngCenter, $radiusKm, $codeNaf);
            } else {
                $results = $insee->searchByCriteria($codeNaf, $departement, $limitImport ?: 50);
            }

        } elseif ($source === 'pappers') {
            $apiKey = getDolGlobalString('SMARTPROSPECTING_PAPPERS_API_KEY');
            if (empty($apiKey)) {
                setEventMessages('Clé API Pappers manquante. Configurez-la dans l\'administration du module.', null, 'warnings');
            } else {
                $pappers = new SmartProspectingSourcePappers($db, $apiKey);
                $results = $pappers->search($codeNaf, $departement);
            }
        }

        $sp->nb_results = $results['total'] ?? count($results['data']);

        // Import automatique si demandé
        if ($doImport && $results['success'] && !empty($results['data'])) {
            $importManager = new SmartProspectingImportManager($db, $user);
            $batch = array_slice($results['data'], 0, $limitImport ?: 50);
            $importStats = $importManager->importBatch($batch, $searchId, array(
                'dedup_siret'    => (bool)getDolGlobalInt('SMARTPROSPECTING_AUTO_DEDUP', 1),
                'create_contact' => true,
            ));

            $sp->nb_imported   = $importStats['imported'];
            $sp->nb_duplicates = $importStats['duplicates'];
            $sp->nb_errors     = $importStats['errors'];
        }

        $sp->updateCounters();
        $sp->setStatus($results['success'] ? SmartProspecting::STATUS_DONE : SmartProspecting::STATUS_ERROR);
        $searchResult = $results;
    }
}

// =============================================
// Affichage
// =============================================
llxHeader('', 'SmartProspecting - Nouvelle recherche', '');
print load_fiche_titre('<i class="fas fa-search"></i> Nouvelle recherche de prospects', '', '');

// Codes NAF fréquents pour aide
$nafFrequents = array(
    '' => '-- Choisir un secteur --',
    '6201Z' => 'Programmation informatique',
    '6202A' => 'Conseil informatique',
    '4120A' => 'Construction maisons',
    '4321A' => 'Électricité',
    '4322A' => 'Plomberie / chauffage',
    '4332A' => 'Menuiserie',
    '4711A' => 'Alimentaire',
    '4711F' => 'Supermarchés',
    '4941A' => 'Transport routier',
    '5610A' => 'Restauration traditionnelle',
    '6920Z' => 'Comptabilité',
    '7111Z' => 'Architecture',
    '7010Z' => 'Sièges sociaux',
    '8121Z' => 'Nettoyage courant',
    '8623Z' => 'Chirurgie dentaire',
    '8690A' => 'Ambulances',
    '9602A' => 'Coiffure',
);

$departements = array(
    '' => '-- Tous --',
    '01' => '01 - Ain', '02' => '02 - Aisne', '03' => '03 - Allier',
    '06' => '06 - Alpes-Maritimes', '13' => '13 - Bouches-du-Rhône',
    '17' => '17 - Charente-Maritime', '21' => '21 - Côte-d\'Or',
    '31' => '31 - Haute-Garonne', '33' => '33 - Gironde',
    '34' => '34 - Hérault', '35' => '35 - Ille-et-Vilaine',
    '38' => '38 - Isère', '44' => '44 - Loire-Atlantique',
    '45' => '45 - Loiret', '49' => '49 - Maine-et-Loire',
    '54' => '54 - Meurthe-et-Moselle', '57' => '57 - Moselle',
    '59' => '59 - Nord', '67' => '67 - Bas-Rhin',
    '69' => '69 - Rhône', '74' => '74 - Haute-Savoie',
    '75' => '75 - Paris', '76' => '76 - Seine-Maritime',
    '78' => '78 - Yvelines', '79' => '79 - Deux-Sèvres',
    '80' => '80 - Somme', '83' => '83 - Var',
    '85' => '85 - Vendée', '86' => '86 - Vienne',
    '91' => '91 - Essonne', '92' => '92 - Hauts-de-Seine',
    '93' => '93 - Seine-Saint-Denis', '94' => '94 - Val-de-Marne',
    '95' => '95 - Val-d\'Oise',
);

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="sp-search-form">
<input type="hidden" name="token" value="<?php echo newToken(); ?>">
<input type="hidden" name="action" value="search">
<input type="hidden" name="lat_center" id="lat_center" value="">
<input type="hidden" name="lng_center" id="lng_center" value="">

<div class="fichecenter" style="max-width:900px;">

    <!-- Source de données -->
    <div class="sp-section" style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0;">
        <h3 style="margin-top:0; color:#333; border-bottom:1px solid #eee; padding-bottom:10px;">
            <i class="fas fa-database" style="color:#2196F3;"></i> Source de données
        </h3>
        <div style="display:flex; gap:15px; flex-wrap:wrap;">

            <label class="sp-source-card" style="flex:1; min-width:180px; border:2px solid #2196F3; border-radius:8px; padding:15px; cursor:pointer; transition:all .2s; background:#e3f2fd;">
                <input type="radio" name="search_source" value="insee" checked style="margin-right:8px;">
                <strong style="color:#2196F3;">INSEE SIRENE</strong><br>
                <small style="color:#666;">Gratuit · 10M d'entreprises · Légal</small>
            </label>

            <label class="sp-source-card" style="flex:1; min-width:180px; border:2px solid #ddd; border-radius:8px; padding:15px; cursor:pointer; transition:all .2s;">
                <input type="radio" name="search_source" value="pappers" style="margin-right:8px;">
                <strong style="color:#4CAF50;">Pappers.fr</strong><br>
                <small style="color:#666;">Clé API requise · Données enrichies · Dirigeants</small>
            </label>

            <label class="sp-source-card" style="flex:1; min-width:180px; border:2px solid #ddd; border-radius:8px; padding:15px; cursor:pointer; transition:all .2s; opacity:.6;">
                <input type="radio" name="search_source" value="google" disabled style="margin-right:8px;">
                <strong style="color:#FF9800;">Google Places</strong><br>
                <small style="color:#666;">Prochainement · Recherche géographique</small>
            </label>

        </div>
    </div>

    <!-- Critères de recherche -->
    <div class="sp-section" style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0;">
        <h3 style="margin-top:0; color:#333; border-bottom:1px solid #eee; padding-bottom:10px;">
            <i class="fas fa-filter" style="color:#FF9800;"></i> Critères de recherche
        </h3>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

            <div>
                <label style="font-weight:bold; display:block; margin-bottom:5px;">Secteur d'activité (NAF)</label>
                <select name="code_naf" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                    <?php foreach ($nafFrequents as $code => $label) : ?>
                    <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color:#999;">Ou saisissez un code NAF précis :</small>
                <input type="text" name="code_naf_custom" placeholder="Ex: 4322A" style="width:100%; padding:6px; border:1px solid #ddd; border-radius:4px; margin-top:4px; box-sizing:border-box;" maxlength="6">
            </div>

            <div>
                <label style="font-weight:bold; display:block; margin-bottom:5px;">Département</label>
                <select name="departement" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                    <?php foreach ($departements as $code => $label) : ?>
                    <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="font-weight:bold; display:block; margin-bottom:5px;">Ville (optionnel)</label>
                <input type="text" name="ville" placeholder="Ex: Thouars, Niort..." style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">
            </div>

            <div>
                <label style="font-weight:bold; display:block; margin-bottom:5px;">Rayon (km)</label>
                <input type="number" name="radius_km" value="50" min="5" max="200" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">
                <small style="color:#999;">Recherche dans un rayon autour de la ville</small>
            </div>

        </div>
    </div>

    <!-- Options d'import -->
    <div class="sp-section" style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0;">
        <h3 style="margin-top:0; color:#333; border-bottom:1px solid #eee; padding-bottom:10px;">
            <i class="fas fa-cogs" style="color:#9C27B0;"></i> Options d'import
        </h3>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; align-items:center;">

            <div>
                <label style="font-weight:bold; display:block; margin-bottom:5px;">Nombre max de prospects à importer</label>
                <input type="number" name="limit_import" value="50" min="1" max="500" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">
                <small style="color:#999;">Maximum 500 par recherche (recommandé : 50)</small>
            </div>

            <div style="padding-top:10px;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:bold;">
                    <input type="checkbox" name="do_import" value="1" checked style="width:20px; height:20px;">
                    Importer automatiquement dans Dolibarr
                </label>
                <small style="color:#999; display:block; margin-top:5px;">Crée les Tiers prospects automatiquement</small>
            </div>

        </div>
    </div>

    <!-- Bouton de recherche -->
    <div style="text-align:center; padding:20px;">
        <button type="submit" class="butAction" style="font-size:1.1rem; padding:12px 40px; min-width:250px;">
            <i class="fas fa-search"></i> Lancer la recherche
        </button>
    </div>

</div>
</form>

<?php
// =============================================
// Affichage des résultats
// =============================================
if ($searchResult !== null) :
    if (!$searchResult['success']) : ?>
    <div class="error" style="padding:15px; border-radius:8px; margin-top:20px;">
        <i class="fas fa-exclamation-circle"></i>
        Erreur lors de la recherche. Vérifiez votre configuration API.
        <?php if (!empty($searchResult['error'])) : ?>
        <br><small><?php echo htmlspecialchars($searchResult['error']); ?></small>
        <?php endif; ?>
    </div>
    <?php else : ?>

    <div style="margin-top:30px;">
        <h3 style="border-bottom:2px solid #4CAF50; padding-bottom:10px;">
            <i class="fas fa-check-circle" style="color:#4CAF50;"></i>
            Résultats — <?php echo number_format($searchResult['total']); ?> entreprises trouvées
        </h3>

        <?php if ($importStats) : ?>
        <div style="display:flex; gap:15px; margin-bottom:20px; flex-wrap:wrap;">
            <div style="background:#e8f5e9; border-radius:8px; padding:15px 25px; border-left:4px solid #4CAF50;">
                <strong style="font-size:1.5rem; color:#4CAF50;"><?php echo $importStats['imported']; ?></strong>
                <div style="color:#666;">Importés</div>
            </div>
            <div style="background:#fff3e0; border-radius:8px; padding:15px 25px; border-left:4px solid #FF9800;">
                <strong style="font-size:1.5rem; color:#FF9800;"><?php echo $importStats['duplicates']; ?></strong>
                <div style="color:#666;">Doublons ignorés</div>
            </div>
            <div style="background:#ffebee; border-radius:8px; padding:15px 25px; border-left:4px solid #f44336;">
                <strong style="font-size:1.5rem; color:#f44336;"><?php echo $importStats['errors']; ?></strong>
                <div style="color:#666;">Erreurs</div>
            </div>
        </div>

        <div style="margin-bottom:20px;">
            <a href="<?php echo DOL_URL_ROOT.'/societe/list.php?type=p'; ?>" class="butAction" style="text-decoration:none;">
                <i class="fas fa-list"></i> Voir les prospects dans Dolibarr
            </a>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="butActionDelete" style="text-decoration:none; margin-left:10px;">
                <i class="fas fa-search"></i> Nouvelle recherche
            </a>
        </div>
        <?php endif; ?>

        <!-- Aperçu des 20 premiers -->
        <table class="noborder centpercent">
            <thead>
                <tr class="liste_titre">
                    <th>Entreprise</th>
                    <th>SIRET</th>
                    <th>Adresse</th>
                    <th>NAF</th>
                    <th>Effectif</th>
                    <th class="center">Score</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $preview = array_slice($searchResult['data'], 0, 20);
            foreach ($preview as $p) : ?>
                <tr class="oddeven">
                    <td>
                        <strong><?php echo htmlspecialchars($p['nom']); ?></strong>
                        <?php if (!empty($p['dirigeant_prenom']) || !empty($p['dirigeant_nom'])) : ?>
                        <br><small style="color:#666;"><i class="fas fa-user"></i> <?php echo htmlspecialchars(trim($p['dirigeant_prenom'].' '.$p['dirigeant_nom'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.85rem; color:#666;"><?php echo htmlspecialchars($p['siret'] ?? ''); ?></td>
                    <td style="font-size:.85rem;"><?php echo htmlspecialchars($p['cp'].' '.$p['ville']); ?></td>
                    <td style="font-size:.85rem;"><?php echo htmlspecialchars($p['code_naf'] ?? ''); ?></td>
                    <td style="font-size:.85rem;"><?php echo htmlspecialchars($p['effectif'] ?? ''); ?></td>
                    <td class="center">
                        <?php $score = $p['score'] ?? 50; $color = $score >= 70 ? '#4CAF50' : ($score >= 50 ? '#FF9800' : '#999'); ?>
                        <span style="background:<?php echo $color; ?>; color:#fff; padding:2px 8px; border-radius:10px; font-size:.85rem; font-weight:bold;">
                            <?php echo $score; ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (count($searchResult['data']) > 20) : ?>
        <p style="color:#666; text-align:center; margin-top:10px; font-style:italic;">
            ... et <?php echo count($searchResult['data']) - 20; ?> autres résultats importés
        </p>
        <?php endif; ?>
    </div>

    <?php
    endif;
endif;

llxFooter();
$db->close();
?>
