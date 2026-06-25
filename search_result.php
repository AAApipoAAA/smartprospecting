<?php
/**
 * Page de résultats d'une session de recherche SmartProspecting
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

$id   = (int)GETPOST('id', 'int');
$page = max(0, (int)GETPOST('page', 'int'));
$limit = 50;

if ($id <= 0) {
    header('Location: '.dol_buildpath('/smartprospecting/history.php', 1));
    exit;
}

// Chargement de la session
$sp = new SmartProspecting($db);
$res = $sp->fetch($id);
if ($res <= 0) {
    setEventMessages('Session introuvable.', null, 'errors');
    header('Location: '.dol_buildpath('/smartprospecting/history.php', 1));
    exit;
}

$queryData = json_decode($sp->search_query, true) ?? array();

// Chargement des prospects de cette session
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."smartprospecting_prospect";
$sql .= " WHERE fk_search = ".(int)$id;
$sql .= " ORDER BY score DESC, rowid ASC";
$sql .= " LIMIT ".(int)$limit." OFFSET ".(int)($page * $limit);

$prospects = array();
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $prospects[] = $obj;
    }
}

// Total
$sqlCount = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."smartprospecting_prospect WHERE fk_search = ".(int)$id;
$resCount = $db->query($sqlCount);
$totalProspects = 0;
if ($resCount) {
    $obj = $db->fetch_object($resCount);
    $totalProspects = $obj->nb ?? 0;
}

// Utilisateur créateur
$userCreateur = new User($db);
$userCreateur->fetch($sp->fk_user);

llxHeader('', 'SmartProspecting - '.$sp->ref);
print load_fiche_titre(
    '<i class="fas fa-search-plus"></i> Résultats : '.$sp->ref,
    '<a href="'.dol_buildpath('/smartprospecting/history.php', 1).'" class="butAction" style="font-size:.85rem; padding:5px 15px; text-decoration:none;"><i class="fas fa-arrow-left"></i> Retour historique</a>',
    ''
);
?>

<!-- Infos session -->
<div style="display:flex; gap:20px; margin-bottom:25px; flex-wrap:wrap;">

    <div style="background:#fff; border-radius:8px; padding:20px; border:1px solid #e0e0e0; flex:2; min-width:300px;">
        <h4 style="margin-top:0; color:#555; font-size:.9rem; text-transform:uppercase; letter-spacing:.5px;">Détails de la recherche</h4>
        <table style="width:100%; font-size:.95rem;">
            <tr>
                <td style="color:#888; padding:4px 0; width:130px;">Source</td>
                <td><span style="background:#2196F3; color:#fff; padding:2px 10px; border-radius:4px; font-size:.85rem;"><?php echo strtoupper(htmlspecialchars($sp->source)); ?></span></td>
            </tr>
            <?php if (!empty($queryData['code_naf'])) : ?>
            <tr><td style="color:#888; padding:4px 0;">Code NAF</td><td><?php echo htmlspecialchars($queryData['code_naf']); ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($queryData['departement'])) : ?>
            <tr><td style="color:#888; padding:4px 0;">Département</td><td><?php echo htmlspecialchars($queryData['departement']); ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($queryData['ville'])) : ?>
            <tr><td style="color:#888; padding:4px 0;">Ville</td><td><?php echo htmlspecialchars($queryData['ville']); ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($queryData['radius_km'])) : ?>
            <tr><td style="color:#888; padding:4px 0;">Rayon</td><td><?php echo htmlspecialchars($queryData['radius_km']); ?> km</td></tr>
            <?php endif; ?>
            <tr><td style="color:#888; padding:4px 0;">Date</td><td><?php echo dol_print_date($sp->date_creation, 'dayhour'); ?></td></tr>
            <tr><td style="color:#888; padding:4px 0;">Utilisateur</td><td><?php echo htmlspecialchars($userCreateur->getFullName($langs) ?: $userCreateur->login); ?></td></tr>
            <tr><td style="color:#888; padding:4px 0;">Statut</td>
                <td><span class="<?php echo $sp->getStatusBadgeClass(); ?>" style="padding:2px 10px; border-radius:4px; font-size:.85rem;"><?php echo $sp->getStatusLabel(); ?></span></td>
            </tr>
        </table>
    </div>

    <div style="display:flex; flex-direction:column; gap:12px; flex:1; min-width:200px;">
        <div style="background:#e8f5e9; border-radius:8px; padding:15px 20px; border-left:4px solid #4CAF50; text-align:center;">
            <div style="font-size:2.2rem; font-weight:bold; color:#4CAF50;"><?php echo number_format($sp->nb_imported); ?></div>
            <div style="color:#555; font-size:.9rem;">Importés</div>
        </div>
        <div style="background:#fff3e0; border-radius:8px; padding:15px 20px; border-left:4px solid #FF9800; text-align:center;">
            <div style="font-size:2.2rem; font-weight:bold; color:#FF9800;"><?php echo number_format($sp->nb_duplicates); ?></div>
            <div style="color:#555; font-size:.9rem;">Doublons</div>
        </div>
        <div style="background:#fce4ec; border-radius:8px; padding:15px 20px; border-left:4px solid #f44336; text-align:center;">
            <div style="font-size:2.2rem; font-weight:bold; color:#f44336;"><?php echo number_format($sp->nb_errors); ?></div>
            <div style="color:#555; font-size:.9rem;">Erreurs</div>
        </div>
    </div>

</div>

<!-- Actions -->
<div style="margin-bottom:20px;">
    <?php if ($sp->nb_imported > 0) : ?>
    <a href="<?php echo DOL_URL_ROOT.'/societe/list.php?type=p'; ?>" class="butAction" style="text-decoration:none;">
        <i class="fas fa-building"></i> Voir les prospects dans Dolibarr
    </a>
    <?php endif; ?>
    <a href="<?php echo dol_buildpath('/smartprospecting/search.php', 1); ?>" class="butActionRefused" style="text-decoration:none; background:#6c757d; margin-left:10px;">
        <i class="fas fa-search"></i> Nouvelle recherche
    </a>
</div>

<!-- Liste des prospects -->
<div style="background:#fff; border-radius:8px; border:1px solid #e0e0e0; overflow:hidden;">

    <div style="padding:15px 20px; background:#f8f9fa; border-bottom:1px solid #e0e0e0; display:flex; justify-content:space-between; align-items:center;">
        <strong><?php echo $totalProspects; ?> prospect(s) dans cette session</strong>
        <small style="color:#888;">Page <?php echo $page + 1; ?><?php if ($totalProspects > $limit) echo ' / '.ceil($totalProspects / $limit); ?></small>
    </div>

    <?php if (empty($prospects)) : ?>
    <div style="padding:50px; text-align:center; color:#999;">
        <i class="fas fa-inbox" style="font-size:2.5rem; display:block; margin-bottom:15px; color:#ddd;"></i>
        Aucun prospect enregistré pour cette session.
    </div>
    <?php else : ?>
    <table class="noborder centpercent" style="margin:0;">
        <thead>
            <tr class="liste_titre">
                <th>Entreprise</th>
                <th>SIRET</th>
                <th>Localisation</th>
                <th>NAF</th>
                <th>Contact</th>
                <th class="center">Score</th>
                <th class="center">Statut</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($prospects as $p) :
            $statusLabels = array(0 => 'Trouvé', 1 => 'Importé', 2 => 'Doublon', 3 => 'Erreur', 4 => 'Exclu');
            $statusColors = array(0 => '#999', 1 => '#4CAF50', 2 => '#FF9800', 3 => '#f44336', 4 => '#9E9E9E');
            $statusLabel  = $statusLabels[$p->status] ?? '?';
            $statusColor  = $statusColors[$p->status] ?? '#999';
            $score        = (int)($p->score ?? 50);
            $scoreColor   = $score >= 70 ? '#4CAF50' : ($score >= 50 ? '#FF9800' : '#999');
        ?>
        <tr class="oddeven">
            <td>
                <?php if ($p->status == 1 && !empty($p->fk_societe)) : ?>
                <a href="<?php echo DOL_URL_ROOT.'/societe/card.php?socid='.$p->fk_societe; ?>" style="font-weight:bold; text-decoration:none;">
                    <?php echo htmlspecialchars($p->nom); ?>
                </a>
                <?php else : ?>
                <strong><?php echo htmlspecialchars($p->nom); ?></strong>
                <?php endif; ?>
                <?php if (!empty($p->forme_juridique)) : ?>
                <br><small style="color:#aaa;"><?php echo htmlspecialchars($p->forme_juridique); ?></small>
                <?php endif; ?>
            </td>
            <td style="font-size:.82rem; color:#888; white-space:nowrap;"><?php echo htmlspecialchars($p->siret ?? ''); ?></td>
            <td style="font-size:.88rem;">
                <?php echo htmlspecialchars(($p->cp ?? '').' '.($p->ville ?? '')); ?>
                <?php if (!empty($p->distance_km)) : ?>
                <br><small style="color:#aaa;"><?php echo $p->distance_km; ?> km</small>
                <?php endif; ?>
            </td>
            <td style="font-size:.82rem; color:#666;"><?php echo htmlspecialchars($p->code_naf ?? ''); ?></td>
            <td style="font-size:.85rem;">
                <?php
                $contactName = trim(($p->dirigeant_prenom ?? '').' '.($p->dirigeant_nom ?? ''));
                if ($contactName) echo '<i class="fas fa-user" style="color:#ccc;"></i> '.htmlspecialchars($contactName).'<br>';
                if (!empty($p->telephone)) echo '<i class="fas fa-phone" style="color:#ccc;"></i> '.htmlspecialchars($p->telephone).'<br>';
                if (!empty($p->email)) echo '<i class="fas fa-at" style="color:#ccc;"></i> '.htmlspecialchars($p->email);
                ?>
            </td>
            <td class="center">
                <span style="background:<?php echo $scoreColor; ?>; color:#fff; padding:2px 10px; border-radius:12px; font-size:.82rem; font-weight:bold;">
                    <?php echo $score; ?>
                </span>
            </td>
            <td class="center">
                <span style="background:<?php echo $statusColor; ?>; color:#fff; padding:2px 10px; border-radius:4px; font-size:.82rem;">
                    <?php echo $statusLabel; ?>
                </span>
            </td>
            <td>
                <?php if ($p->status == 1 && !empty($p->fk_societe)) : ?>
                <a href="<?php echo DOL_URL_ROOT.'/societe/card.php?socid='.$p->fk_societe; ?>" title="Voir le tiers" style="color:#2196F3;">
                    <i class="fas fa-external-link-alt"></i>
                </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalProspects > $limit) : ?>
    <div style="padding:15px; text-align:center; border-top:1px solid #eee;">
        <?php if ($page > 0) : ?>
        <a href="?id=<?php echo $id; ?>&page=<?php echo $page-1; ?>" class="butAction" style="text-decoration:none; margin-right:10px;">← Précédent</a>
        <?php endif; ?>
        <span style="color:#888;">Page <?php echo $page+1; ?> / <?php echo ceil($totalProspects/$limit); ?></span>
        <?php if (($page+1)*$limit < $totalProspects) : ?>
        <a href="?id=<?php echo $id; ?>&page=<?php echo $page+1; ?>" class="butAction" style="text-decoration:none; margin-left:10px;">Suivant →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php
llxFooter();
$db->close();
?>
