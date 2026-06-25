<?php
/**
 * Diagnostic étendu - APIs alternatives
 * SUPPRIMER APRÈS UTILISATION
 */
echo "<h2>🔍 Diagnostic APIs alternatives</h2>";
echo "<style>body{font-family:monospace;padding:20px;} .ok{color:green;} .err{color:red;} pre{background:#f5f5f5;padding:10px;border-radius:4px;overflow-x:auto;font-size:.85rem;}</style>";

function testUrl($label, $url, $headers = [], $http11 = true) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 SmartProspecting/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ];
    if ($http11) $opts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
    if ($headers) $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $ok = ($code >= 200 && $code < 300);
    echo "<strong>$label</strong> : ";
    if ($err) {
        echo "<span class='err'>❌ $err</span><br>";
        return false;
    }
    echo "<span class='".($ok ? 'ok' : 'err')."'>HTTP $code</span>";
    if ($ok) {
        echo " <span class='ok'>✅</span>";
        $preview = substr($resp, 0, 200);
        echo "<pre>".htmlspecialchars($preview)."</pre>";
    } else {
        echo "<pre>".htmlspecialchars(substr($resp, 0, 200))."</pre>";
    }
    return $ok ? $resp : false;
}

echo "<h3>1. API Recherche Entreprises (gouv.fr) — Sans clé</h3>";
$r = testUrl(
    'recherche-entreprises.api.gouv.fr',
    'https://recherche-entreprises.api.gouv.fr/search?q=plomberie&departement=79&nombre_resultats=3'
);
if ($r) {
    $d = json_decode($r, true);
    echo "<span class='ok'>🎉 ".count($d['results'] ?? [])." résultats ! Premier : ".($d['results'][0]['nom_complet'] ?? '?')."</span><br>";
}

echo "<h3>2. API Annuaire des entreprises (gouv.fr)</h3>";
testUrl(
    'annuaire-entreprises.api.gouv.fr',
    'https://annuaire-entreprises.api.gouv.fr/api/v2/search?terme=plomberie&departement=79'
);

echo "<h3>3. API Pappers (test sans clé — attend 401)</h3>";
testUrl(
    'api.pappers.fr',
    'https://api.pappers.fr/v2/entreprise?siret=35600000000048'
);

echo "<h3>4. INSEE via proxy alternatif</h3>";
// Parfois l'URL de catalogue fonctionne différemment
testUrl(
    'api.insee.fr HTTP/1.1 + User-Agent navigateur',
    'https://api.insee.fr/entreprises/sirene/V3.11/siret/35600000000048',
    [
        'X-INSEE-Api-Key-Integration: 08d6c2b9-bf84-4492-96c2-b9bf84e4927f',
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]
);

echo "<h3>5. Test file_get_contents (alternative à cURL)</h3>";
$ctx = stream_context_create([
    'http' => [
        'header'  => "X-INSEE-Api-Key-Integration: 08d6c2b9-bf84-4492-96c2-b9bf84e4927f\r\nAccept: application/json\r\n",
        'timeout' => 15,
    ],
    'ssl' => ['verify_peer' => false],
]);
$r = @file_get_contents('https://recherche-entreprises.api.gouv.fr/search?q=informatique&departement=79&nombre_resultats=2', false, $ctx);
if ($r) {
    $d = json_decode($r, true);
    echo "<span class='ok'>✅ file_get_contents fonctionne ! ".count($d['results'] ?? [])." résultats</span><br>";
    echo "<pre>".htmlspecialchars(substr($r, 0, 300))."</pre>";
} else {
    echo "<span class='err'>❌ file_get_contents bloqué aussi</span><br>";
}

echo "<h3>6. IP sortante du serveur</h3>";
$ip = @file_get_contents('https://api.ipify.org');
if ($ip) echo "<span class='ok'>✅ IP publique sortante : $ip</span><br>";
else {
    $r = testUrl('api.ipify.org', 'https://api.ipify.org');
    if ($r) echo "IP : $r";
}

echo "<br><hr><span style='color:red;font-weight:bold;'>⚠️ SUPPRIMEZ diagnostic2.php APRÈS LECTURE</span>";
?>
