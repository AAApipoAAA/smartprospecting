<?php
/**
 * Page d'accueil SmartProspecting
 * Dashboard avec statistiques et accès rapide
 */

// Chargement de l'environnement Dolibarr
$res = 0;
if (!$res && file_exists("../main.inc.php"))                          { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php"))                       { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))                    { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists(__DIR__."/../../../main.inc.php"))           { $res = @include __DIR__."/../../../main.inc.php"; }
if (!$res && file_exists(__DIR__."/../../../../main.inc.php"))        { $res = @include __DIR__."/../../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/smartprospecting/class/SmartProspecting.class.php');

// Sécurité
if (!isModEnabled('smartprospecting')) {
    accessforbidden('Module SmartProspecting non activé');
}
if (!$user->rights->smartprospecting->read) {
    accessforbidden();
}

// Langues
$langs->loadLangs(array('smartprospecting@smartprospecting', 'companies'));

// Titre
$title = 'SmartProspecting - Tableau de bord';
llxHeader('', $title, '');

// En-tête page
print load_fiche_titre('<i class="fas fa-crosshairs"></i> SmartProspecting', '', 'smartprospecting@smartprospecting');

// Stats globales
$smartProsp = new SmartProspecting($db);
$searches   = $smartProsp->fetchAll(5);

// Compteurs globaux
$sqlStats = "SELECT COUNT(*) as nb_search, SUM(nb_imported) as total_imported, SUM(nb_results) as total_results";
$sqlStats .= " FROM ".MAIN_DB_PREFIX."smartprospecting_search WHERE entity = ".(int)$conf->entity;
$resStats = $db->query($sqlStats);
$statsGlobal = array('nb_search' => 0, 'total_imported' => 0, 'total_results' => 0);
if ($resStats) {
    $obj = $db->fetch_object($resStats);
    if ($obj) {
        $statsGlobal['nb_search']       = $obj->nb_search ?? 0;
        $statsGlobal['total_imported']  = $obj->total_imported ?? 0;
        $statsGlobal['total_results']   = $obj->total_results ?? 0;
    }
}

// Prospects importés ce mois
$sqlMonth = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."smartprospecting_prospect";
$sqlMonth .= " WHERE status = 1 AND entity = ".(int)$conf->entity;
$sqlMonth .= " AND MONTH(date_import) = MONTH(CURDATE()) AND YEAR(date_import) = YEAR(CURDATE())";
$resMonth = $db->query($sqlMonth);
$nbThisMonth = 0;
if ($resMonth) {
    $obj = $db->fetch_object($resMonth);
    $nbThisMonth = $obj->nb ?? 0;
}

?>

<div class="sp-dashboard">

    <!-- Cartes statistiques -->
    <div class="sp-stats-row" style="display:flex; gap:20px; margin-bottom:30px; flex-wrap:wrap;">

        <div class="sp-stat-card" style="flex:1; min-width:200px; background:#fff; border-radius:8px; padding:20px; border:1px solid #e0e0e0; box-shadow:0 2px 4px rgba(0,0,0,.05);">
            <div style="font-size:2.5rem; font-weight:bold; color:#4CAF50;"><?php echo number_format($statsGlobal['total_imported']); ?></div>
            <div style="color:#666; margin-top:5px;"><i class="fas fa-building"></i> Prospects importés au total</div>
        </div>

        <div class="sp-stat-card" style="flex:1; min-width:200px; background:#fff; border-radius:8px; padding:20px; border:1px solid #e0e0e0; box-shadow:0 2px 4px rgba(0,0,0,.05);">
            <div style="font-size:2.5rem; font-weight:bold; color:#2196F3;"><?php echo number_format($nbThisMonth); ?></div>
            <div style="color:#666; margin-top:5px;"><i class="fas fa-calendar"></i> Ce mois-ci</div>
        </div>

        <div class="sp-stat-card" style="flex:1; min-width:200px; background:#fff; border-radius:8px; padding:20px; border:1px solid #e0e0e0; box-shadow:0 2px 4px rgba(0,0,0,.05);">
            <div style="font-size:2.5rem; font-weight:bold; color:#FF9800;"><?php echo number_format($statsGlobal['nb_search']); ?></div>
            <div style="color:#666; margin-top:5px;"><i class="fas fa-search"></i> Recherches effectuées</div>
        </div>

        <div class="sp-stat-card" style="flex:1; min-width:200px; background:#fff; border-radius:8px; padding:20px; border:1px solid #e0e0e0; box-shadow:0 2px 4px rgba(0,0,0,.05);">
            <?php
            $taux = ($statsGlobal['total_results'] > 0) ? round(($statsGlobal['total_imported'] / $statsGlobal['total_results']) * 100) : 0;
            ?>
            <div style="font-size:2.5rem; font-weight:bold; color:#9C27B0;"><?php echo $taux; ?>%</div>
            <div style="color:#666; margin-top:5px;"><i class="fas fa-percentage"></i> Taux d'import</div>
        </div>

    </div>

    <!-- Actions rapides -->
    <?php if ($user->rights->smartprospecting->write) : ?>
    <div class="sp-quick-actions" style="background:#f8f9fa; border-radius:8px; padding:25px; margin-bottom:30px; border:1px solid #dee2e6;">
        <h3 style="margin-top:0; color:#333;"><i class="fas fa-bolt" style="color:#FF9800;"></i> Actions rapides</h3>
        <div style="display:flex; gap:15px; flex-wrap:wrap;">
            <a href="<?php echo dol_buildpath('/smartprospecting/search.php', 1); ?>" class="butAction" style="text-decoration:none;">
                <i class="fas fa-search"></i> Nouvelle recherche
            </a>
            <a href="<?php echo dol_buildpath('/smartprospecting/history.php', 1); ?>" class="butActionRefused" style="text-decoration:none; background:#6c757d;">
                <i class="fas fa-history"></i> Voir l'historique
            </a>
            <a href="<?php echo dol_buildpath('/smartprospecting/sequences.php', 1); ?>" class="butActionRefused" style="text-decoration:none; background:#17a2b8;">
                <i class="fas fa-paper-plane"></i> Séquences de relance
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dernières recherches -->
    <div class="sp-recent-searches">
        <h3 style="color:#333; border-bottom:2px solid #e0e0e0; padding-bottom:10px;">
            <i class="fas fa-clock"></i> Dernières recherches
        </h3>

        <?php if (empty($searches)) : ?>
            <div style="text-align:center; padding:40px; color:#999; background:#f9f9f9; border-radius:8px; border:2px dashed #ddd;">
                <i class="fas fa-search" style="font-size:3rem; margin-bottom:15px; display:block; color:#ccc;"></i>
                <p style="font-size:1.1rem;">Aucune recherche effectuée pour l'instant.</p>
                <?php if ($user->rights->smartprospecting->write) : ?>
                <a href="<?php echo dol_buildpath('/smartprospecting/search.php', 1); ?>" class="butAction">
                    <i class="fas fa-play"></i> Lancer ma première recherche
                </a>
                <?php endif; ?>
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
                        <th>Statut</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($searches as $s) :
                        $queryData = json_decode($s->search_query, true) ?? array();
                        $criteres  = array();
                        if (!empty($queryData['code_naf']))    $criteres[] = 'NAF: '.$queryData['code_naf'];
                        if (!empty($queryData['departement'])) $criteres[] = 'Dép: '.$queryData['departement'];
                        if (!empty($queryData['ville']))       $criteres[] = $queryData['ville'];
                        if (!empty($queryData['radius_km']))   $criteres[] = $queryData['radius_km'].' km';
                    ?>
                    <tr class="oddeven">
                        <td>
                            <a href="<?php echo dol_buildpath('/smartprospecting/search_result.php?id='.$s->id, 1); ?>" style="font-weight:bold;">
                                <?php echo htmlspecialchars($s->ref); ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge" style="background:<?php echo $s->source == 'insee' ? '#2196F3' : '#4CAF50'; ?>; color:#fff; padding:3px 8px; border-radius:4px; font-size:.8rem;">
                                <?php echo strtoupper(htmlspecialchars($s->source)); ?>
                            </span>
                        </td>
                        <td style="color:#666; font-size:.9rem;"><?php echo htmlspecialchars(implode(' | ', $criteres)); ?></td>
                        <td class="center"><?php echo number_format($s->nb_results); ?></td>
                        <td class="center" style="color:#4CAF50; font-weight:bold;"><?php echo number_format($s->nb_imported); ?></td>
                        <td class="center" style="color:#FF9800;"><?php echo number_format($s->nb_duplicates); ?></td>
                        <td>
                            <span class="<?php echo $s->getStatusBadgeClass(); ?>" style="padding:3px 8px; border-radius:4px; font-size:.8rem;">
                                <?php echo $s->getStatusLabel(); ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap; color:#666; font-size:.85rem;"><?php echo dol_print_date($s->date_creation, 'dayhour'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:10px; text-align:right;">
                <a href="<?php echo dol_buildpath('/smartprospecting/history.php', 1); ?>" style="color:#666; font-size:.9rem;">
                    Voir tout l'historique <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Configuration rapide si clés API manquantes -->
    <?php
    $hasPappers = !empty($conf->global->SMARTPROSPECTING_PAPPERS_API_KEY);
    $hasGoogle  = !empty($conf->global->SMARTPROSPECTING_GOOGLE_PLACES_API_KEY);
    if ((!$hasPappers || !$hasGoogle) && $user->rights->smartprospecting->admin) :
    ?>
    <div style="margin-top:25px; background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:20px;">
        <h4 style="margin-top:0; color:#856404;"><i class="fas fa-exclamation-triangle"></i> Configuration incomplète</h4>
        <p style="color:#856404;">Pour profiter de toutes les sources de données, configurez vos clés API :</p>
        <ul style="color:#856404;">
            <?php if (!$hasPappers) : ?><li>Clé API <strong>Pappers.fr</strong> manquante (enrichissement données légales)</li><?php endif; ?>
            <?php if (!$hasGoogle)  : ?><li>Clé API <strong>Google Places</strong> manquante (recherche géographique)</li><?php endif; ?>
        </ul>
        <a href="<?php echo dol_buildpath('/smartprospecting/admin/setup.php', 1); ?>" class="butAction" style="text-decoration:none;">
            <i class="fas fa-cog"></i> Configurer maintenant
        </a>
    </div>
    <?php endif; ?>

</div>

<?php
llxFooter();
$db->close();
?>
