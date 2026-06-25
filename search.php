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

// Helpers compatibilité toutes versions Dolibarr
function sp_getConf($key, $default = '') {
    global $conf;
    if (function_exists('getDolGlobalString')) return getDolGlobalString($key, $default);
    return isset($conf->global->$key) ? $conf->global->$key : $default;
}
function sp_getConfInt($key, $default = 0) {
    global $conf;
    if (function_exists('getDolGlobalInt')) return getDolGlobalInt($key, $default);
    return isset($conf->global->$key) ? (int)$conf->global->$key : $default;
}

// =============================================
// Traitement du formulaire
// =============================================
$searchResult = null;
$searchId     = null;
$importStats  = null;

if ($action === 'search' && !empty(GETPOST('search_source'))) {

    $source      = GETPOST('search_source', 'alpha');
    $codeNaf     = GETPOST('code_naf_custom', 'alpha');
    if (empty($codeNaf)) $codeNaf = GETPOST('code_naf', 'alpha');
    $departement = GETPOST('departement', 'alpha');
    $ville       = GETPOST('ville', 'alphanohtml');
    $radiusKm    = (int)GETPOST('radius_km', 'int');
    $latCenter   = GETPOST('lat_center', 'alpha');
    $lngCenter   = GETPOST('lng_center', 'alpha');
    $limitImport = min((int)GETPOST('limit_import', 'int'), 500);
    if ($limitImport <= 0) $limitImport = 50;
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

        $results = array('success' => false, 'data' => array(), 'total' => 0, 'error' => '');

        if ($source === 'insee') {
            $insee      = new SmartProspectingSourceINSEE($db);
            $consKey    = sp_getConf('SMARTPROSPECTING_INSEE_CONSUMER_KEY');
            $consSecret = sp_getConf('SMARTPROSPECTING_INSEE_CONSUMER_SECRET');

            if (!empty($consKey) && !empty($consSecret)) {
                $token = $insee->getToken($consKey, $consSecret);
                if (!$token) {
                    setEventMessages('Impossible d\'obtenir le token INSEE. Vérifiez vos clés.', null, 'warnings');
                }
            }

            if (!empty($radiusKm) && !empty($latCenter) && !empty($lngCenter)) {
                $results = $insee->searchByRadius((float)$latCenter, (float)$lngCenter, $radiusKm, $codeNaf);
            } else {
                $results = $insee->searchByCriteria($codeNaf, $departement, $limitImport);
            }

        } elseif ($source === 'pappers') {
            $apiKey = sp_getConf('SMARTPROSPECTING_PAPPERS_API_KEY');
            if (empty($apiKey)) {
                setEventMessages('Clé API Pappers manquante. Configurez-la dans l\'administration.', null, 'warnings');
                $results = array('success' => false, 'error' => 'Clé API manquante', 'data' => array(), 'total' => 0);
            } else {
                $pappers = new SmartProspectingSourcePappers($db, $apiKey);
                $results = $pappers->search($codeNaf, $departement);
            }
        }

        $sp->nb_results = isset($results['total']) ? (int)$results['total'] : count($results['data']);

        // Import automatique si demandé et résultats OK
        if ($doImport && $results['success'] && !empty($results['data'])) {
            $importManager = new SmartProspectingImportManager($db, $user);
            $batch         = array_slice($results['data'], 0, $limitImport);
            $importStats   = $importManager->importBatch($batch, $searchId, array(
                'dedup_siret'    => (bool)sp_getConfInt('SMARTPROSPECTING_AUTO_DEDUP', 1),
                'create_contact' => true,
            ));

            $sp->nb_imported   = $importStats['imported'];
            $sp->nb_duplicates = $importStats['duplicates'];
            $sp->nb_errors     = $importStats['errors'];
        }

        $sp->updateCounters();
        $sp->setStatus($results['success'] ? SmartProspecting::STATUS_DONE : SmartProspecting::STATUS_ERROR);
        $searchResult = $results;

    } else {
        setEventMessages('Erreur lors de la création de la session de recherche.', null, 'errors');
    }
}

// =============================================
// Affichage
// =============================================
llxHeader('', 'SmartProspecting - Nouvelle recherche', '');
print load_fiche_titre('<i class="fas fa-search"></i> Nouvelle recherche de prospects', '', '');

// Codes NAF fréquents
$nafFrequents = array(
    '' => '-- Choisir un secteur --',
    '6201Z' => 'Programmation informatique',
    '6202A' => 'Conseil informatique',
    '6311Z' => 'Traitement de données',
    '4120A' => 'Construction maisons individuelles',
    '4120B' => 'Construction autres bâtiments',
    '4321A' => 'Travaux électricité',
    '4322A' => 'Plomberie / chauffage',
    '4332A' => 'Menuiserie bois et PVC',
    '4334Z' => 'Peinture et vitrerie',
    '4711A' => 'Commerce alimentaire',
    '4711F' => 'Supermarchés',
    '4941A' => 'Transport routier marchandises',
    '5610A' => 'Restauration traditionnelle',
    '5610C' => 'Restauration rapide',
    '6512Z' => 'Autres assurances',
    '6920Z' => 'Comptabilité / expertise comptable',
    '7010Z' => 'Sièges sociaux',
    '7111Z' => 'Architecture',
    '7112B' => 'Ingénierie',
    '7320Z' => 'Études de marché',
    '7410Z' => 'Design',
    '7490B' => 'Activités spécialisées diverses',
    '8121Z' => 'Nettoyage courant',
    '8129A' => 'Désinfection, désinsectisation',
    '8211Z' => 'Services administratifs',
    '8621Z' => 'Médecine générale',
    '8623Z' => 'Chirurgie dentaire',
    '8690A' => 'Ambulances',
    '9602A' => 'Coiffure',
    '9602B' => 'Soins de beauté',
);

$departements = array(
    '' => '-- Tous départements --',
    '01'=>'01 - Ain','02'=>'02 - Aisne','03'=>'03 - Allier','04'=>'04 - Alpes-de-Haute-Provence',
    '05'=>'05 - Hautes-Alpes','06'=>'06 - Alpes-Maritimes','07'=>'07 - Ardèche','08'=>'08 - Ardennes',
    '09'=>'09 - Ariège','10'=>'10 - Aube','11'=>'11 - Aude','12'=>'12 - Aveyron',
    '13'=>'13 - Bouches-du-Rhône','14'=>'14 - Calvados','15'=>'15 - Cantal','16'=>'16 - Charente',
    '17'=>'17 - Charente-Maritime','18'=>'18 - Cher','19'=>'19 - Corrèze','21'=>'21 - Côte-d\'Or',
    '22'=>'22 - Côtes-d\'Armor','23'=>'23 - Creuse','24'=>'24 - Dordogne','25'=>'25 - Doubs',
    '26'=>'26 - Drôme','27'=>'27 - Eure','28'=>'28 - Eure-et-Loir','29'=>'29 - Finistère',
    '30'=>'30 - Gard','31'=>'31 - Haute-Garonne','32'=>'32 - Gers','33'=>'33 - Gironde',
    '34'=>'34 - Hérault','35'=>'35 - Ille-et-Vilaine','36'=>'36 - Indre','37'=>'37 - Indre-et-Loire',
    '38'=>'38 - Isère','39'=>'39 - Jura','40'=>'40 - Landes','41'=>'41 - Loir-et-Cher',
    '42'=>'42 - Loire','43'=>'43 - Haute-Loire','44'=>'44 - Loire-Atlantique','45'=>'45 - Loiret',
    '46'=>'46 - Lot','47'=>'47 - Lot-et-Garonne','48'=>'48 - Lozère','49'=>'49 - Maine-et-Loire',
    '50'=>'50 - Manche','51'=>'51 - Marne','52'=>'52 - Haute-Marne','53'=>'53 - Mayenne',
    '54'=>'54 - Meurthe-et-Moselle','55'=>'55 - Meuse','56'=>'56 - Morbihan','57'=>'57 - Moselle',
    '58'=>'58 - Nièvre','59'=>'59 - Nord','60'=>'60 - Oise','61'=>'61 - Orne',
    '62'=>'62 - Pas-de-Calais','63'=>'63 - Puy-de-Dôme','64'=>'64 - Pyrénées-Atlantiques','65'=>'65 - Hautes-Pyrénées',
    '66'=>'66 - Pyrénées-Orientales','67'=>'67 - Bas-Rhin','68'=>'68 - Haut-Rhin','69'=>'69 - Rhône',
    '70'=>'70 - Haute-Saône','71'=>'71 - Saône-et-Loire','72'=>'72 - Sarthe','73'=>'73 - Savoie',
    '74'=>'74 - Haute-Savoie','75'=>'75 - Paris','76'=>'76 - Seine-Maritime','77'=>'77 - Seine-et-Marne',
    '78'=>'78 - Yvelines','79'=>'79 - Deux-Sèvres','80'=>'80 - Somme','81'=>'81 - Tarn',
    '82'=>'82 - Tarn-et-Garonne','83'=>'83 - Var','84'=>'84 - Vaucluse','85'=>'85 - Vendée',
    '86'=>'86 - Vienne','87'=>'87 - Haute-Vienne','88'=>'88 - Vosges','89'=>'89 - Yonne',
    '90'=>'90 - Territoire de Belfort','91'=>'91 - Essonne','92'=>'92 - Hauts-de-Seine',
    '93'=>'93 - Seine-Saint-Denis','94'=>'94 - Val-de-Marne','95'=>'95 - Val-d\'Oise',
);
?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="sp-search-form">
<input type="hidden" name="token" value="<?php echo newToken(); ?>">
<input type="hidden" name="action" value="search">
<input type="hidden" name="lat_center" id="lat_center" value="">
<input type="hidden" name="lng_center" id="lng_center" value="">

<div style="max-width:950px;">

    <!-- Source de données -->
    <div style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0; box-shadow:0 1px 3px rgba(0,0,0,.05);">
        <h3 style="margin-top:0; color:#333; border-bottom:1px solid #eee; padding-bottom:10px;">
            <i class="fas fa-database" style="color:#2196F3;"></i> 1. Source de données
        </h3>
        <div style="display:flex; gap:15px; flex-wrap:wrap;">

            <label style="flex:1; min-width:200px; border:2px solid #2196F3; border-radius:8px; padding:15px; cursor:pointer; background:#e3f2fd;">
                <input type="radio" name="search_source" value="insee" checked style="margin-right:8px;">
                <strong style="color:#2196F3;">INSEE SIRENE</strong><br>
                <small style="color:#555; display:block; margin-top:4px;">✅ Gratuit · 10M entreprises françaises · Légal</small>
            </label>

            <label style="flex:1; min-width:200px; border:2px solid #ddd; border-radius:8px; padding:15px; cursor:pointer;">
                <input type="radio" name="search_source" value="pappers" style="margin-right:8px;">
                <strong style="color:#4CAF50;">Pappers.fr</strong><br>
                <small style="color:#555; display:block; margin-top:4px;">🔑 Clé API · Données enrichies · Dirigeants</small>
            </label>

        </div>
    </div>

    <!-- Critères -->
    <div style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0; box-shadow:0 1px 3px rgba(0,0,0,.05);">
        <h3 style="margin-top:0; color:#333; border-bottom:1px solid #eee; padding-bottom:10px;">
            <i class="fas fa-filter" style="color:#FF9800;"></i> 2. Critères de recherche
        </h3>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

            <div>
                <label style="font-weight:bold; display:block; margin-bottom:6px;">Secteur d'activité (liste)</label>
                <select name="code_naf" style="width:100%; padding:9px; border:1px solid #ccc; border-radius:4px; font-size:.95rem;">
                    <?php foreach ($nafFrequents as $code => $label) : ?>
                    <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="font-weight:bold; display:block; margin-bottom:6px;">Code NAF précis (prioritaire)</label>
                <input type="text" name="code_naf_custom"
                    placeholder="Ex: 4322A, 6201Z..."
                    style="width:100%; padding:9px; border:1px solid #ccc; border-radius:4px; font-size:.95rem; box-sizing:border-box;"
                    maxlength="6">
                <small style="color:#999;">Si renseigné, remplace la liste ci-dessus</small>
            </div>

            <div>
                <label style="font-weight:bold; display:block; margin-bottom:6px;">Département</label>
                <select name="departement" style="width:100%; padding:9px; border:1px solid #ccc; border-radius:4px; font-size:.95rem;">
                    <?php foreach ($departements as $code => $label) : ?>
                    <option value="<?php echo htmlspecialchars($code); ?>"
                        <?php echo ($code === '79') ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="font-weight:bold; display:block; margin-bottom:6px;">Ville (optionnel)</label>
                <input type="text" name="ville"
                    placeholder="Ex: Thouars, Niort, Poitiers..."
                    style="width:100%; padding:9px; border:1px solid #ccc; border-radius:4px; font-size:.95rem; box-sizing:border-box;">
            </div>

        </div>
    </div>

    <!-- Options import -->
    <div style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0; box-shadow:0 1px 3px rgba(0,0,0,.05);">
        <h3 style="margin-top:0; color:#333; border-bottom:1px solid #eee; padding-bottom:10px;">
            <i class="fas fa-cogs" style="color:#9C27B0;"></i> 3. Options d'import
        </h3>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; align-items:start;">

            <div>
                <label style="font-weight:bold; display:block; margin-bottom:6px;">Nombre max à importer</label>
                <input type="number" name="limit_import" value="20" min="1" max="500"
                    style="width:100%; padding:9px; border:1px solid #ccc; border-radius:4px; font-size:.95rem; box-sizing:border-box;">
                <small style="color:#999;">Recommandé : 20 pour le premier test, 500 max</small>
            </div>

            <div style="padding-top:8px;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:bold; margin-bottom:10px;">
                    <input type="checkbox" name="do_import" value="1" checked style="width:18px; height:18px;">
                    Importer automatiquement dans Dolibarr
                </label>
                <small style="color:#999;">Crée les Tiers prospects avec déduplication SIRET</small>
            </div>

        </div>
    </div>

    <!-- Bouton -->
    <div style="text-align:center; padding:15px 0 30px;">
        <button type="submit" class="butAction" style="font-size:1.1rem; padding:14px 50px; min-width:280px; cursor:pointer;">
            <i class="fas fa-search"></i> &nbsp; Lancer la recherche
        </button>
    </div>

</div>
</form>

<?php
// =============================================
// Résultats
// =============================================
if ($searchResult !== null) :
    if (!$searchResult['success']) : ?>
    <div style="background:#ffebee; border:1px solid #f44336; border-radius:8px; padding:20px; margin-top:20px; color:#c62828;">
        <strong><i class="fas fa-exclamation-circle"></i> Erreur lors de la recherche</strong><br>
        <?php if (!empty($searchResult['error'])) echo htmlspecialchars($searchResult['error']); else echo 'Vérifiez votre configuration API (token INSEE requis pour les vraies données).'; ?>
        <br><br>
        <a href="<?php echo dol_buildpath('/smartprospecting/admin/setup.php', 1); ?>" class="butAction" style="text-decoration:none; font-size:.9rem;">
            <i class="fas fa-cog"></i> Configurer les APIs
        </a>
    </div>
    <?php else : ?>

    <div style="margin-top:30px; background:#fff; border-radius:8px; padding:25px; border:1px solid #e0e0e0;">

        <h3 style="margin-top:0; color:#333; border-bottom:2px solid #4CAF50; padding-bottom:10px;">
            <i class="fas fa-check-circle" style="color:#4CAF50;"></i>
            <?php echo number_format($searchResult['total']); ?> entreprises trouvées
        </h3>

        <?php if ($importStats) : ?>
        <div style="display:flex; gap:15px; margin-bottom:25px; flex-wrap:wrap;">
            <div style="background:#e8f5e9; border-radius:8px; padding:15px 30px; border-left:4px solid #4CAF50; flex:1; min-width:120px;">
                <div style="font-size:2rem; font-weight:bold; color:#4CAF50;"><?php echo $importStats['imported']; ?></div>
                <div style="color:#555;">Importés</div>
            </div>
            <div style="background:#fff3e0; border-radius:8px; padding:15px 30px; border-left:4px solid #FF9800; flex:1; min-width:120px;">
                <div style="font-size:2rem; font-weight:bold; color:#FF9800;"><?php echo $importStats['duplicates']; ?></div>
                <div style="color:#555;">Doublons ignorés</div>
            </div>
            <div style="background:#ffebee; border-radius:8px; padding:15px 30px; border-left:4px solid #f44336; flex:1; min-width:120px;">
                <div style="font-size:2rem; font-weight:bold; color:#f44336;"><?php echo $importStats['errors']; ?></div>
                <div style="color:#555;">Erreurs</div>
            </div>
        </div>

        <div style="margin-bottom:25px;">
            <a href="<?php echo DOL_URL_ROOT.'/societe/list.php?type=p'; ?>" class="butAction" style="text-decoration:none;">
                <i class="fas fa-list"></i> Voir les prospects dans Dolibarr
            </a>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" style="text-decoration:none; margin-left:15px; color:#666;">
                <i class="fas fa-redo"></i> Nouvelle recherche
            </a>
        </div>
        <?php endif; ?>

        <!-- Aperçu résultats -->
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
            $preview = array_slice($searchResult['data'], 0, 25);
            if (empty($preview)) : ?>
                <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">
                    Aucun résultat — essayez sans token INSEE (données limitées) ou configurez vos clés API
                </td></tr>
            <?php else :
                foreach ($preview as $p) : ?>
                <tr class="oddeven">
                    <td>
                        <strong><?php echo htmlspecialchars($p['nom']); ?></strong>
                        <?php if (!empty($p['dirigeant_nom'])) : ?>
                        <br><small style="color:#888;"><i class="fas fa-user"></i> <?php echo htmlspecialchars(trim($p['dirigeant_prenom'].' '.$p['dirigeant_nom'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.85rem; color:#666; white-space:nowrap;"><?php echo htmlspecialchars($p['siret'] ?? ''); ?></td>
                    <td style="font-size:.85rem;"><?php echo htmlspecialchars(($p['cp'] ?? '').' '.($p['ville'] ?? '')); ?></td>
                    <td style="font-size:.85rem;"><?php echo htmlspecialchars($p['code_naf'] ?? ''); ?></td>
                    <td style="font-size:.85rem; white-space:nowrap;"><?php echo htmlspecialchars($p['effectif'] ?? ''); ?></td>
                    <td class="center">
                        <?php
                        $score = (int)($p['score'] ?? 50);
                        $color = $score >= 70 ? '#4CAF50' : ($score >= 50 ? '#FF9800' : '#999');
                        ?>
                        <span style="background:<?php echo $color; ?>; color:#fff; padding:3px 10px; border-radius:12px; font-size:.85rem; font-weight:bold;">
                            <?php echo $score; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach;
            endif; ?>
            </tbody>
        </table>

        <?php if (count($searchResult['data']) > 25) : ?>
        <p style="color:#888; text-align:center; margin-top:15px; font-style:italic;">
            Affichage des 25 premiers sur <?php echo count($searchResult['data']); ?> résultats importés
        </p>
        <?php endif; ?>

    </div>

    <?php endif; endif; ?>

<?php
llxFooter();
$db->close();
?>
