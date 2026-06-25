<?php
/**
 * Page historique des recherches SmartProspecting
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))               { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php"))            { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))         { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists(__DIR__."/../../../main.inc.php")) { $res = @include __DIR__."/../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

dol_include_once('/smartprospecting/class/SmartProspecting.class.php');

if (!isModEnabled('smartprospecting')) accessforbidden();
if (!$user->rights->smartprospecting->read) accessforbidden();

$langs->loadLangs(array('smartprospecting@smartprospecting'));

$page   = max(0, (int)GETPOST('page', 'int'));
$limit  = 20;
$offset = $page * $limit;

llxHeader('', 'SmartProspecting - Historique');
print load_fiche_titre('<i class="fas fa-history"></i> Historique des recherches', '', '');

$sp      = new SmartProspecting($db);
$list    = $sp->fetchAll($limit, $offset);

// Comptage total
$sqlCount = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."smartprospecting_search WHERE entity = ".(int)$conf->entity;
$resCount = $db->query($sqlCount);
$totalRows = 0;
if ($resCount) {
    $obj = $db->fetch_object($resCount);
    $totalRows = $obj->nb ?? 0;
}

?>

<div style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
    <span style="color:#666;"><?php echo $totalRows; ?> recherche(s) au total</span>
    <?php if ($user->rights->smartprospecting->write) : ?>
    <a href="<?php echo dol_buildpath('/smartprospecting/search.php', 1); ?>" class="butAction" style="text-decoration:none;">
        <i class="fas fa-plus"></i> Nouvelle recherche
    </a>
    <?php endif; ?>
</div>

<?php if (empty($list)) : ?>
<div style="text-align:center; padding:60px; color:#999; background:#f9f9f9; border-radius:8px; border:2px dashed #ddd;">
    <i class="fas fa-history" style="font-size:3rem; color:#ccc; display:block; margin-bottom:15px;"></i>
    Aucune recherche dans l'historique.
</div>
<?php else : ?>

<table class="noborder centpercent" style="border-collapse:collapse;">
    <thead>
        <tr class="liste_titre">
            <th>Référence</th>
            <th>Source</th>
            <th>Critères</th>
            <th class="center">Trouvés</th>
            <th class="center">Importés</th>
            <th class="center">Doublons</th>
            <th class="center">Erreurs</th>
            <th>Statut</th>
            <th>Utilisateur</th>
            <th>Date</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($list as $s) :
        $queryData = json_decode($s->search_query, true) ?? array();
        $criteres  = array();
        if (!empty($queryData['code_naf']))    $criteres[] = $queryData['code_naf'];
        if (!empty($queryData['departement'])) $criteres[] = 'Dep.'.$queryData['departement'];
        if (!empty($queryData['ville']))       $criteres[] = $queryData['ville'];
        if (!empty($queryData['radius_km']))   $criteres[] = $queryData['radius_km'].'km';

        // Chargement utilisateur
        $userObj = new User($db);
        $userObj->fetch($s->fk_user);
    ?>
    <tr class="oddeven">
        <td>
            <a href="<?php echo dol_buildpath('/smartprospecting/search_result.php?id='.$s->id, 1); ?>" style="font-weight:bold; text-decoration:none;">
                <?php echo htmlspecialchars($s->ref); ?>
            </a>
        </td>
        <td>
            <?php
            $sourceColors = array('insee' => '#2196F3', 'pappers' => '#4CAF50', 'google' => '#FF9800');
            $color = $sourceColors[$s->source] ?? '#999';
            ?>
            <span style="background:<?php echo $color; ?>; color:#fff; padding:2px 8px; border-radius:4px; font-size:.8rem;">
                <?php echo strtoupper(htmlspecialchars($s->source)); ?>
            </span>
        </td>
        <td style="color:#666; font-size:.9rem;"><?php echo htmlspecialchars(implode(' · ', $criteres)); ?></td>
        <td class="center"><?php echo number_format($s->nb_results); ?></td>
        <td class="center" style="color:#4CAF50; font-weight:bold;"><?php echo number_format($s->nb_imported); ?></td>
        <td class="center" style="color:#FF9800;"><?php echo number_format($s->nb_duplicates); ?></td>
        <td class="center" style="color:#f44336;"><?php echo number_format($s->nb_errors); ?></td>
        <td>
            <span class="<?php echo $s->getStatusBadgeClass(); ?>" style="padding:2px 8px; border-radius:4px; font-size:.8rem;">
                <?php echo $s->getStatusLabel(); ?>
            </span>
        </td>
        <td style="font-size:.85rem; color:#666;">
            <?php echo htmlspecialchars($userObj->getFullName($langs) ?: $userObj->login); ?>
        </td>
        <td style="white-space:nowrap; font-size:.85rem; color:#666;"><?php echo dol_print_date($s->date_creation, 'dayhour'); ?></td>
        <td>
            <a href="<?php echo dol_buildpath('/smartprospecting/search_result.php?id='.$s->id, 1); ?>"
               title="Voir les résultats" style="color:#2196F3;">
                <i class="fas fa-eye"></i>
            </a>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Pagination -->
<?php if ($totalRows > $limit) : ?>
<div style="text-align:center; margin-top:20px;">
    <?php if ($page > 0) : ?>
    <a href="?page=<?php echo $page-1; ?>" class="butAction" style="text-decoration:none;">← Précédent</a>
    <?php endif; ?>
    <span style="padding:0 20px; color:#666;">Page <?php echo $page+1; ?> / <?php echo ceil($totalRows/$limit); ?></span>
    <?php if (($page+1)*$limit < $totalRows) : ?>
    <a href="?page=<?php echo $page+1; ?>" class="butAction" style="text-decoration:none;">Suivant →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
llxFooter();
$db->close();
?>
