<?php
/**
 * Page séquences de relance SmartProspecting
 * V1 : placeholder - sera développé en V2
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))               { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php"))            { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))         { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists(__DIR__."/../../../main.inc.php")) { $res = @include __DIR__."/../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

if (!isModEnabled('smartprospecting')) accessforbidden();
if (!$user->rights->smartprospecting->read) accessforbidden();

$langs->loadLangs(array('smartprospecting@smartprospecting'));

llxHeader('', 'SmartProspecting - Séquences de relance');
print load_fiche_titre('<i class="fas fa-paper-plane"></i> Séquences de relance', '', '');
?>

<div style="text-align:center; padding:60px; background:#f0f7ff; border-radius:12px; border:2px dashed #2196F3; margin-top:20px;">
    <i class="fas fa-rocket" style="font-size:4rem; color:#2196F3; display:block; margin-bottom:20px;"></i>
    <h2 style="color:#2196F3; margin-bottom:10px;">Séquences de relance — Version 2</h2>
    <p style="color:#666; font-size:1.1rem; max-width:500px; margin:0 auto 20px;">
        Cette fonctionnalité permettra de créer des séquences automatiques d'emails, d'appels et de tâches pour suivre vos prospects importés.
    </p>
    <div style="display:flex; gap:20px; justify-content:center; flex-wrap:wrap; margin-bottom:30px;">
        <div style="background:#fff; border-radius:8px; padding:15px 25px; border:1px solid #ddd; min-width:150px;">
            <i class="fas fa-envelope" style="color:#4CAF50; font-size:1.5rem; display:block; margin-bottom:5px;"></i>
            <strong>Email J+0</strong><br><small style="color:#666;">Email de présentation</small>
        </div>
        <div style="background:#fff; border-radius:8px; padding:15px 25px; border:1px solid #ddd; min-width:150px;">
            <i class="fas fa-phone" style="color:#FF9800; font-size:1.5rem; display:block; margin-bottom:5px;"></i>
            <strong>Appel J+3</strong><br><small style="color:#666;">Relance téléphonique</small>
        </div>
        <div style="background:#fff; border-radius:8px; padding:15px 25px; border:1px solid #ddd; min-width:150px;">
            <i class="fas fa-envelope-open-text" style="color:#9C27B0; font-size:1.5rem; display:block; margin-bottom:5px;"></i>
            <strong>Email J+7</strong><br><small style="color:#666;">Contenu de valeur</small>
        </div>
        <div style="background:#fff; border-radius:8px; padding:15px 25px; border:1px solid #ddd; min-width:150px;">
            <i class="fas fa-tasks" style="color:#2196F3; font-size:1.5rem; display:block; margin-bottom:5px;"></i>
            <strong>Tâche J+14</strong><br><small style="color:#666;">Proposition commerciale</small>
        </div>
    </div>
    <a href="<?php echo dol_buildpath('/smartprospecting/search.php', 1); ?>" class="butAction" style="text-decoration:none;">
        <i class="fas fa-search"></i> En attendant, lancer une recherche
    </a>
</div>

<?php
llxFooter();
$db->close();
?>
