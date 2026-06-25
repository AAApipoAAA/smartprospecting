<?php
/**
 * Fonctions helpers SmartProspecting
 */

/**
 * Prépare les onglets de la page d'administration
 */
function smartprospectingAdminPrepareHead()
{
    global $langs, $conf;
    $langs->load('smartprospecting@smartprospecting');

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath('/smartprospecting/admin/setup.php', 1);
    $head[$h][1] = '<i class="fas fa-cog"></i> '.$langs->trans('Configuration');
    $head[$h][2] = 'setup';
    $h++;

    $head[$h][0] = dol_buildpath('/smartprospecting/admin/about.php', 1);
    $head[$h][1] = '<i class="fas fa-info-circle"></i> '.$langs->trans('About');
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'smartprospecting@smartprospecting');
    complete_head_from_modules($conf, $langs, null, $head, $h, 'smartprospecting@smartprospecting', 'remove');

    return $head;
}
