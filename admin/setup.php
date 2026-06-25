<?php
/**
 * Page de configuration SmartProspecting
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))              { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))           { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php"))        { $res = @include "../../../../main.inc.php"; }
if (!$res && file_exists(__DIR__."/../../../../main.inc.php")) { $res = @include __DIR__."/../../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

if (!isModEnabled('smartprospecting')) accessforbidden();
if (!$user->admin) accessforbidden();

$langs->loadLangs(array('smartprospecting@smartprospecting', 'admin'));
$action = GETPOST('action', 'alpha');

// Sauvegarde
if ($action === 'update') {
    $keys = array(
        'SMARTPROSPECTING_INSEE_API_KEY',
        'SMARTPROSPECTING_PAPPERS_API_KEY',
        'SMARTPROSPECTING_GOOGLE_PLACES_API_KEY',
        'SMARTPROSPECTING_HUNTER_API_KEY',
        'SMARTPROSPECTING_DROPCONTACT_API_KEY',
        'SMARTPROSPECTING_IMPORT_BATCH_SIZE',
        'SMARTPROSPECTING_AUTO_DEDUP',
    );
    foreach ($keys as $key) {
        $value = GETPOST($key, 'nohtml');
        dolibarr_set_const($db, $key, trim($value), 'chaine', 0, '', $conf->entity);
    }
    setEventMessages('Configuration sauvegardée avec succès !', null, 'mesgs');
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// Helper compat
function sp_conf($key, $default = '') {
    global $conf;
    if (function_exists('getDolGlobalString')) return getDolGlobalString($key, $default);
    return isset($conf->global->$key) ? $conf->global->$key : $default;
}

llxHeader('', 'SmartProspecting - Configuration');
print load_fiche_titre('<i class="fas fa-cog"></i> Configuration SmartProspecting', '', '');
?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<input type="hidden" name="token" value="<?php echo newToken(); ?>">
<input type="hidden" name="action" value="update">

<!-- INSEE SIRENE -->
<div style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0; box-shadow:0 1px 3px rgba(0,0,0,.05);">
    <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:12px;">
        <span style="background:#1565C0; color:#fff; padding:4px 12px; border-radius:4px; font-size:.9rem;">INSEE</span>
        &nbsp; SIRENE v3.11 — Source principale gratuite
    </h3>

    <div style="background:#e3f2fd; border-radius:6px; padding:12px 15px; margin-bottom:18px; font-size:.9rem; color:#1565C0;">
        <strong>Comment obtenir votre clé :</strong><br>
        1. Allez sur <a href="https://api.insee.fr" target="_blank" style="color:#1565C0;"><strong>api.insee.fr</strong></a>
        → Applications → votre app SmartProspecting<br>
        2. Onglet "Souscriptions" → colonne de droite "<strong>Clés d'API</strong>"<br>
        3. Copiez la clé (format : <code>xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</code>)
    </div>

    <table style="width:100%;">
        <tr>
            <td style="width:220px; padding:8px 0; font-weight:bold;">Clé API INSEE (api key)</td>
            <td>
                <input type="text" name="SMARTPROSPECTING_INSEE_API_KEY"
                    value="<?php echo dol_escape_htmltag(sp_conf('SMARTPROSPECTING_INSEE_API_KEY')); ?>"
                    style="width:100%; max-width:520px; padding:9px 12px; border:1px solid #ccc; border-radius:4px; font-family:monospace; font-size:.9rem;"
                    placeholder="08d6c2b9-bf84-4492-96c2-b9t...">
                <?php if (!empty(sp_conf('SMARTPROSPECTING_INSEE_API_KEY'))) : ?>
                <span style="color:#4CAF50; font-size:.85rem; display:block; margin-top:4px;">✅ Clé configurée</span>
                <?php else : ?>
                <span style="color:#FF9800; font-size:.85rem; display:block; margin-top:4px;">⚠️ Clé manquante — les recherches ne fonctionneront pas</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<!-- Pappers -->
<div style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0; box-shadow:0 1px 3px rgba(0,0,0,.05);">
    <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:12px;">
        <span style="background:#2E7D32; color:#fff; padding:4px 12px; border-radius:4px; font-size:.9rem;">PAPPERS</span>
        &nbsp; Pappers.fr — Données enrichies + dirigeants
    </h3>
    <div style="background:#e8f5e9; border-radius:6px; padding:12px 15px; margin-bottom:18px; font-size:.9rem; color:#2E7D32;">
        Inscription gratuite sur <a href="https://www.pappers.fr/api" target="_blank" style="color:#2E7D32;"><strong>pappers.fr/api</strong></a>
        · Quota gratuit : 500 requêtes/mois · Enrichit les données INSEE (téléphone, email, dirigeants)
    </div>
    <table style="width:100%;">
        <tr>
            <td style="width:220px; padding:8px 0; font-weight:bold;">Clé API Pappers</td>
            <td>
                <input type="text" name="SMARTPROSPECTING_PAPPERS_API_KEY"
                    value="<?php echo dol_escape_htmltag(sp_conf('SMARTPROSPECTING_PAPPERS_API_KEY')); ?>"
                    style="width:100%; max-width:520px; padding:9px 12px; border:1px solid #ccc; border-radius:4px; font-family:monospace; font-size:.9rem;"
                    placeholder="Votre clé API Pappers.fr">
                <?php if (!empty(sp_conf('SMARTPROSPECTING_PAPPERS_API_KEY'))) : ?>
                <span style="color:#4CAF50; font-size:.85rem; display:block; margin-top:4px;">✅ Clé configurée</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<!-- Google Places -->
<div style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0; box-shadow:0 1px 3px rgba(0,0,0,.05); opacity:.8;">
    <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:12px;">
        <span style="background:#E65100; color:#fff; padding:4px 12px; border-radius:4px; font-size:.9rem;">GOOGLE</span>
        &nbsp; Google Places — Recherche géographique <small style="color:#999;">(prochainement)</small>
    </h3>
    <table style="width:100%;">
        <tr>
            <td style="width:220px; padding:8px 0; font-weight:bold;">Clé API Google Places</td>
            <td>
                <input type="text" name="SMARTPROSPECTING_GOOGLE_PLACES_API_KEY"
                    value="<?php echo dol_escape_htmltag(sp_conf('SMARTPROSPECTING_GOOGLE_PLACES_API_KEY')); ?>"
                    style="width:100%; max-width:520px; padding:9px 12px; border:1px solid #ccc; border-radius:4px; font-family:monospace; font-size:.9rem;"
                    placeholder="AIzaSy...">
            </td>
        </tr>
    </table>
</div>

<!-- Enrichissement email -->
<div style="background:#fff; border-radius:8px; padding:25px; margin-bottom:20px; border:1px solid #e0e0e0; box-shadow:0 1px 3px rgba(0,0,0,.05);">
    <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:12px;">
        <span style="background:#6A1B9A; color:#fff; padding:4px 12px; border-radius:4px; font-size:.9rem;">EMAIL</span>
        &nbsp; Enrichissement emails — Hunter.io ou Dropcontact
    </h3>
    <table style="width:100%;">
        <tr>
            <td style="width:220px; padding:8px 0; font-weight:bold;">Clé API Hunter.io</td>
            <td>
                <input type="text" name="SMARTPROSPECTING_HUNTER_API_KEY"
                    value="<?php echo dol_escape_htmltag(sp_conf('SMARTPROSPECTING_HUNTER_API_KEY')); ?>"
                    style="width:100%; max-width:520px; padding:9px 12px; border:1px solid #ccc; border-radius:4px; font-family:monospace; font-size:.9rem;"
                    placeholder="hunter.io API key (facultatif)">
            </td>
        </tr>
        <tr>
            <td style="padding:8px 0; font-weight:bold;">Clé API Dropcontact</td>
            <td>
                <input type="text" name="SMARTPROSPECTING_DROPCONTACT_API_KEY"
                    value="<?php echo dol_escape_htmltag(sp_conf('SMARTPROSPECTING_DROPCONTACT_API_KEY')); ?>"
                    style="width:100%; max-width:520px; padding:9px 12px; border:1px solid #ccc; border-radius:4px; font-family:monospace; font-size:.9rem;"
                    placeholder="dropcontact.com API key (facultatif)">
            </td>
        </tr>
    </table>
</div>

<!-- Paramètres -->
<div style="background:#fff; border-radius:8px; padding:25px; margin-bottom:25px; border:1px solid #e0e0e0; box-shadow:0 1px 3px rgba(0,0,0,.05);">
    <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:12px;">
        <i class="fas fa-sliders-h"></i> Paramètres généraux
    </h3>
    <table style="width:100%;">
        <tr>
            <td style="width:220px; padding:8px 0; font-weight:bold;">Taille des batches</td>
            <td>
                <input type="number" name="SMARTPROSPECTING_IMPORT_BATCH_SIZE" min="10" max="200"
                    value="<?php echo (int)(sp_conf('SMARTPROSPECTING_IMPORT_BATCH_SIZE') ?: 50); ?>"
                    style="width:100px; padding:9px; border:1px solid #ccc; border-radius:4px;">
                <small style="color:#999; margin-left:10px;">prospects traités par lot (recommandé : 50)</small>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 0; font-weight:bold;">Déduplication auto</td>
            <td>
                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="SMARTPROSPECTING_AUTO_DEDUP" value="1"
                        <?php echo (sp_conf('SMARTPROSPECTING_AUTO_DEDUP', '1') == '1') ? 'checked' : ''; ?>
                        style="width:16px; height:16px;">
                    Éviter les doublons par SIRET et nom d'entreprise
                </label>
            </td>
        </tr>
    </table>
</div>

<div style="text-align:center; padding:10px 0 30px;">
    <input type="submit" value="💾  Enregistrer la configuration" class="butAction"
        style="padding:13px 45px; font-size:1.05rem; cursor:pointer; min-width:280px;">
</div>

</form>

<?php
llxFooter();
$db->close();
?>
