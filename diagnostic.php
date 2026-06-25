<?php
/**
 * Script de diagnostic SmartProspecting
 * Teste la connexion à l'API INSEE depuis ce serveur
 * SUPPRIMER CE FICHIER APRÈS DIAGNOSTIC
 */

// Clé à tester
$apiKey = '08d6c2b9-bf84-4492-96c2-b9bf84e4927f';

echo "<h2>🔍 Diagnostic SmartProspecting — Test INSEE</h2>";
echo "<style>body{font-family:monospace; padding:20px;} .ok{color:green;} .err{color:red;} .info{color:#666;} pre{background:#f5f5f5; padding:10px; border-radius:4px; overflow-x:auto;}</style>";

// 1. Test cURL disponible
echo "<h3>1. Extension cURL</h3>";
if (function_exists('curl_init')) {
    echo "<span class='ok'>✅ cURL disponible</span><br>";
    $curlVersion = curl_version();
    echo "<span class='info'>Version : ".$curlVersion['version']." — SSL : ".$curlVersion['ssl_version']."</span><br>";
} else {
    echo "<span class='err'>❌ cURL non disponible — module PHP manquant</span><br>";
    exit;
}

// 2. Test connexion réseau basique
echo "<h3>2. Connexion réseau</h3>";
$ch = curl_init('https://www.google.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($httpCode > 0) {
    echo "<span class='ok'>✅ Connexion internet OK (google.com → HTTP $httpCode)</span><br>";
} else {
    echo "<span class='err'>❌ Pas de connexion internet : $err</span><br>";
}

// 3. Test DNS insee.fr
echo "<h3>3. Résolution DNS api.insee.fr</h3>";
$ip = gethostbyname('api.insee.fr');
if ($ip !== 'api.insee.fr') {
    echo "<span class='ok'>✅ DNS résolu : api.insee.fr → $ip</span><br>";
} else {
    echo "<span class='err'>❌ DNS non résolu pour api.insee.fr</span><br>";
}

// 4. Test HTTPS vers api.insee.fr sans authentification
echo "<h3>4. Accès HTTPS api.insee.fr</h3>";
$ch = curl_init('https://api.insee.fr/entreprises/sirene/V3.11/informations');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'SmartProspecting/1.0',
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    echo "<span class='err'>❌ Erreur cURL : $err</span><br>";
    // Retry sans vérif SSL
    $ch = curl_init('https://api.insee.fr/entreprises/sirene/V3.11/informations');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $resp2 = curl_exec($ch);
    $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err2  = curl_error($ch);
    curl_close($ch);
    if ($err2) {
        echo "<span class='err'>❌ Même sans SSL verify : $err2</span><br>";
    } else {
        echo "<span class='ok'>⚠️ Fonctionne sans SSL verify (HTTP $code2) — problème de certificat</span><br>";
        echo "<span class='info'>Solution : désactiver SSL_VERIFYPEER dans le connecteur</span><br>";
    }
} else {
    echo "<span class='ok'>✅ Connexion HTTPS OK → HTTP $code</span><br>";
    if ($code == 401) echo "<span class='info'>→ 401 normal sans clé API</span><br>";
}

// 5. Test avec la vraie clé API
echo "<h3>5. Test clé API INSEE</h3>";
echo "<span class='info'>Clé utilisée : ".substr($apiKey, 0, 12)."...</span><br>";

$url = 'https://api.insee.fr/entreprises/sirene/V3.11/siret?q=codePostalEtablissement:79100*+AND+etatAdministratifEtablissement:A+AND+etablissementSiege:true&nombre=3';

// Essai 1 : header X-INSEE-Api-Key-Integration
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'SmartProspecting/1.0',
    CURLOPT_HTTPHEADER     => [
        'X-INSEE-Api-Key-Integration: '.$apiKey,
        'Accept: application/json',
    ],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

echo "<strong>Essai header X-INSEE-Api-Key-Integration :</strong> ";
if ($err) {
    echo "<span class='err'>❌ Erreur : $err</span><br>";
} else {
    echo "<span class='".($code == 200 ? 'ok' : 'err')."'>HTTP $code</span><br>";
    if ($code == 200) {
        $data = json_decode($resp, true);
        $nb = count($data['etablissements'] ?? []);
        echo "<span class='ok'>✅ SUCCÈS ! $nb établissements retournés</span><br>";
        if ($nb > 0) {
            echo "<pre>".json_encode($data['etablissements'][0]['uniteLegale'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."</pre>";
        }
    } else {
        echo "<pre>".htmlspecialchars(substr($resp, 0, 500))."</pre>";
    }
}

// Essai 2 : Authorization Bearer
echo "<br><strong>Essai Authorization Bearer :</strong> ";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'SmartProspecting/1.0',
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer '.$apiKey,
        'Accept: application/json',
    ],
]);
$resp2 = curl_exec($ch);
$code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err2  = curl_error($ch);
curl_close($ch);

if ($err2) {
    echo "<span class='err'>❌ Erreur : $err2</span><br>";
} else {
    echo "<span class='".($code2 == 200 ? 'ok' : 'err')."'>HTTP $code2</span><br>";
    if ($code2 == 200) {
        $data2 = json_decode($resp2, true);
        $nb2 = count($data2['etablissements'] ?? []);
        echo "<span class='ok'>✅ SUCCÈS avec Bearer ! $nb2 résultats</span><br>";
        echo "<pre>".htmlspecialchars(substr($resp2, 0, 300))."</pre>";
    } else {
        echo "<pre>".htmlspecialchars(substr($resp2, 0, 300))."</pre>";
    }
}

// Essai 3 : sans SSL verify
echo "<br><strong>Essai sans SSL verify :</strong> ";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_USERAGENT      => 'SmartProspecting/1.0',
    CURLOPT_HTTPHEADER     => [
        'X-INSEE-Api-Key-Integration: '.$apiKey,
        'Accept: application/json',
    ],
]);
$resp3 = curl_exec($ch);
$code3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err3  = curl_error($ch);
curl_close($ch);

if ($err3) {
    echo "<span class='err'>❌ Erreur : $err3</span><br>";
} else {
    echo "<span class='".($code3 == 200 ? 'ok' : 'err')."'>HTTP $code3</span><br>";
    if ($code3 == 200) {
        echo "<span class='ok'>✅ Fonctionne sans SSL verify !</span><br>";
    } else {
        echo "<pre>".htmlspecialchars(substr($resp3, 0, 300))."</pre>";
    }
}

echo "<h3>6. Résumé</h3>";
echo "<span class='info'>IP de ce serveur : ".($_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()))."</span><br>";
echo "<span class='info'>PHP : ".PHP_VERSION."</span><br>";
echo "<span class='info'>Serveur : ".($_SERVER['SERVER_SOFTWARE'] ?? 'inconnu')."</span><br>";

echo "<br><span style='color:red; font-weight:bold;'>⚠️ SUPPRIMEZ CE FICHIER APRÈS UTILISATION (diagnostic.php)</span>";
?>
