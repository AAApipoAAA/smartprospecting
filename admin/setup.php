<?php
/**
 * Page de configuration SmartProspecting
 * Gestion des clés API et paramètres
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))              { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))           { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php"))        { $res = @include "../../../../main.inc.php"; }
if (!$res && file_exists(__DIR__."/../../../../main.inc.php")) { $res = @include __DIR__."/../../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/smartprospecting/lib/smartprospecting.lib.php');

if (!isModEnabled('smartprospecting')) accessforbidden('Module SmartProspecting non activé');
if (!$user->rights->smartprospecting->admin) accessforbidden();
if (!$user->admin) accessforbidden();

$langs->loadLangs(array('smartprospecting@smartprospecting', 'admin'));
$action = GETPOST('action', 'alpha');

// Sauvegarde de la configuration
if ($action === 'update') {
    $keys = array(
        'SMARTPROSPECTING_PAPPERS_API_KEY',
        'SMARTPROSPECTING_GOOGLE_PLACES_API_KEY',
        'SMARTPROSPECTING_HUNTER_API_KEY',
        'SMARTPROSPECTING_DROPCONTACT_API_KEY',
        'SMARTPROSPECTING_INSEE_CONSUMER_KEY',
        'SMARTPROSPECTING_INSEE_CONSUMER_SECRET',
        'SMARTPROSPECTING_DEFAULT_PROSPECT_STATUS',
        'SMARTPROSPECTING_IMPORT_BATCH_SIZE',
        'SMARTPROSPECTING_AUTO_DEDUP',
    );

    foreach ($keys as $key) {
        $value = GETPOST($key, 'nohtml');
        dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity);
    }

    setEventMessages('Configuration sauvegardée avec succès.', null, 'mesgs');
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// =============================================
// Affichage
// =============================================
llxHeader('', 'SmartProspecting - Configuration');

$head = smartprospectingAdminPrepareHead();
print dol_get_fiche_head($head, 'setup', 'SmartProspecting', -1, 'smartprospecting@smartprospecting');

print load_fiche_titre('Configuration SmartProspecting', '', '');

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<input type="hidden" name="token" value="<?php echo newToken(); ?>">
<input type="hidden" name="action" value="update">

<!-- API INSEE SIRENE -->
<div class="sp-api-section" style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0;">
    <h3 style="margin-top:0; color:#2196F3; border-bottom:1px solid #eee; padding-bottom:10px;">
        <i class="fas fa-university"></i> INSEE SIRENE — Source principale (gratuite)
    </h3>
    <p style="color:#666; font-size:.9rem;">
        L'API SIRENE v3 nécessite un compte sur <a href="https://api.insee.fr" target="_blank">api.insee.fr</a> (inscription gratuite).
        Le quota gratuit est de 30 requêtes/minute.
    </p>

    <table class="noborder" style="width:100%;">
        <tr>
            <td style="width:300px; padding:10px 0;"><label><strong>Consumer Key (INSEE)</strong></label></td>
            <td>
                <input type="text" name="SMARTPROSPECTING_INSEE_CONSUMER_KEY"
                    value="<?php echo dol_escape_htmltag(getDolGlobalString('SMARTPROSPECTING_INSEE_CONSUMER_KEY')); ?>"
                    style="width:100%; max-width:500px; padding:8px; border:1px solid #ddd; border-radius:4px;"
                    placeholder="Votre Consumer Key INSEE">
            </td>
        </tr>
        <tr>
            <td style="padding:10px 0;"><label><strong>Consumer Secret (INSEE)</strong></label></td>
            <td>
                <input type="password" name="SMARTPROSPECTING_INSEE_CONSUMER_SECRET"
                    value="<?php echo dol_escape_htmltag(getDolGlobalString('SMARTPROSPECTING_INSEE_CONSUMER_SECRET')); ?>"
                    style="width:100%; max-width:500px; padding:8px; border:1px solid #ddd; border-radius:4px;"
                    placeholder="Votre Consumer Secret INSEE">
            </td>
        </tr>
    </table>
    <small style="color:#999;">
        Sans token INSEE : la recherche fonctionne avec un quota très réduit (tests seulement).
    </small>
</div>

<!-- API Pappers -->
<div class="sp-api-section" style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0;">
    <h3 style="margin-top:0; color:#4CAF50; border-bottom:1px solid #eee; padding-bottom:10px;">
        <i class="fas fa-balance-scale"></i> Pappers.fr — Données légales enrichies
    </h3>
    <p style="color:#666; font-size:.9rem;">
        <a href="https://www.pappers.fr/api" target="_blank">Inscrivez-vous sur Pappers.fr</a> pour obtenir une clé API.
        Quota gratuit : 500 requêtes/mois. Plans payants disponibles.
    </p>
    <table class="noborder" style="width:100%;">
        <tr>
            <td style="width:300px; padding:10px 0;"><label><strong>Clé API Pappers</strong></label></td>
            <td>
                <input type="text" name="SMARTPROSPECTING_PAPPERS_API_KEY"
                    value="<?php echo dol_escape_htmltag(getDolGlobalString('SMARTPROSPECTING_PAPPERS_API_KEY')); ?>"
                    style="width:100%; max-width:500px; padding:8px; border:1px solid #ddd; border-radius:4px;"
                    placeholder="Votre clé API Pappers">
            </td>
        </tr>
    </table>
</div>

<!-- API Google Places -->
<div class="sp-api-section" style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0;">
    <h3 style="margin-top:0; color:#FF9800; border-bottom:1px solid #eee; padding-bottom:10px;">
        <i class="fab fa-google"></i> Google Places API — Recherche géographique
    </h3>
    <p style="color:#666; font-size:.9rem;">
        Créez une clé sur <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a>.
        Activer : "Places API" et "Geocoding API". Tarification à l'usage (~0,017$/requête).
    </p>
    <table class="noborder" style="width:100%;">
        <tr>
            <td style="width:300px; padding:10px 0;"><label><strong>Clé API Google Places</strong></label></td>
            <td>
                <input type="text" name="SMARTPROSPECTING_GOOGLE_PLACES_API_KEY"
                    value="<?php echo dol_escape_htmltag(getDolGlobalString('SMARTPROSPECTING_GOOGLE_PLACES_API_KEY')); ?>"
                    style="width:100%; max-width:500px; padding:8px; border:1px solid #ddd; border-radius:4px;"
                    placeholder="AIzaSy...">
            </td>
        </tr>
    </table>
</div>

<!-- API Enrichissement email -->
<div class="sp-api-section" style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0;">
    <h3 style="margin-top:0; color:#9C27B0; border-bottom:1px solid #eee; padding-bottom:10px;">
        <i class="fas fa-at"></i> Enrichissement Email (optionnel)
    </h3>
    <p style="color:#666; font-size:.9rem;">
        Choisissez Hunter.io OU Dropcontact pour trouver les emails professionnels.
    </p>
    <table class="noborder" style="width:100%;">
        <tr>
            <td style="width:300px; padding:10px 0;"><label><strong>Clé API Hunter.io</strong></label></td>
            <td>
                <input type="text" name="SMARTPROSPECTING_HUNTER_API_KEY"
                    value="<?php echo dol_escape_htmltag(getDolGlobalString('SMARTPROSPECTING_HUNTER_API_KEY')); ?>"
                    style="width:100%; max-width:500px; padding:8px; border:1px solid #ddd; border-radius:4px;"
                    placeholder="Clé Hunter.io (facultatif)">
            </td>
        </tr>
        <tr>
            <td style="padding:10px 0;"><label><strong>Clé API Dropcontact</strong></label></td>
            <td>
                <input type="text" name="SMARTPROSPECTING_DROPCONTACT_API_KEY"
                    value="<?php echo dol_escape_htmltag(getDolGlobalString('SMARTPROSPECTING_DROPCONTACT_API_KEY')); ?>"
                    style="width:100%; max-width:500px; padding:8px; border:1px solid #ddd; border-radius:4px;"
                    placeholder="Clé Dropcontact (facultatif)">
            </td>
        </tr>
    </table>
</div>

<!-- Paramètres généraux -->
<div class="sp-api-section" style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0;">
    <h3 style="margin-top:0; color:#333; border-bottom:1px solid #eee; padding-bottom:10px;">
        <i class="fas fa-sliders-h"></i> Paramètres généraux
    </h3>
    <table class="noborder" style="width:100%;">
        <tr>
            <td style="width:300px; padding:10px 0;"><label><strong>Taille des batches d'import</strong></label></td>
            <td>
                <input type="number" name="SMARTPROSPECTING_IMPORT_BATCH_SIZE" min="10" max="200"
                    value="<?php echo (int)getDolGlobalInt('SMARTPROSPECTING_IMPORT_BATCH_SIZE', 50); ?>"
                    style="width:100px; padding:8px; border:1px solid #ddd; border-radius:4px;">
                <small style="color:#999; margin-left:10px;">Prospects traités par lot (recommandé : 50)</small>
            </td>
        </tr>
        <tr>
            <td style="padding:10px 0;"><label><strong>Déduplication automatique</strong></label></td>
            <td>
                <label style="cursor:pointer;">
                    <input type="checkbox" name="SMARTPROSPECTING_AUTO_DEDUP" value="1"
                        <?php echo getDolGlobalInt('SMARTPROSPECTING_AUTO_DEDUP', 1) ? 'checked' : ''; ?>>
                    Éviter les doublons par SIRET et nom
                </label>
            </td>
        </tr>
    </table>
</div>

<!-- Bouton de sauvegarde -->
<div style="text-align:center; padding:20px;">
    <input type="submit" value="💾 Enregistrer la configuration" class="butAction" style="padding:12px 40px; font-size:1rem;">
</div>

</form>

<?php
print dol_get_fiche_end();
llxFooter();
$db->close();
?>
