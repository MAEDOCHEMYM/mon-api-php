<?php
// Affichage des erreurs (Utile pour le développement)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- 1. CONFIGURATION CONNEXION DIRECTE (POSTGRESQL RENDER) ---
try {
    /** * REMPLACEZ CI-DESSOUS par votre "Internal Database URL"
     * Exemple : "postgres://user:password@srv-xxxxx.render.com:5432/dbname"
     */
    $databaseUrl = "postgresql://formation_db_x78g_user:UcESuOObhy9J2Sscj8qbd5rr4VcMzZpA@dpg-d70esm3uibrs73e8qg90-a/formation_db_x78g";

    // Extraction des informations de l'URL
    $dbConn = parse_url($databaseUrl);
    
    $host   = $dbConn['host'];
    $port   = $dbConn['port'] ?? 5432;
    $user   = $dbConn['user'];
    $pass   = $dbConn['pass'];
    $dbname = ltrim($dbConn['path'], '/');

    // Connexion avec le driver PGSQL (PostgreSQL)
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

} catch(PDOException $e) {
    die("Erreur de connexion PostgreSQL : " . $e->getMessage());
}

// --- 2. CONFIGURATION PAWAPAY ---
$PAWAPAY_TOKEN = "eyJraWQiOiIxIiwiYWxnIjoiRVMyNTYifQ.eyJ0dCI6IkFBVCIsInN1YiI6IjE2MTk5IiwibWF2IjoiMSIsImV4cCI6MjA4OTY5OTY3NCwiaWF0IjoxNzc0MDgwNDc0LCJwbSI6IkRBRixQQUYiLCJqdGkiOiI2OTVjZmU5Zi05YWExLTQxNTUtODRjNC0zN2M2MjY1ZTBiNDcifQ.asYDBa_NnVrAtHBubSv5jN3a2y-y0GDBxz3rfDB5TGjUG6rxzwF8WJCJrNALYgPM5TUL-3hCRuFf4EI0cecGYw"; 
$API_URL = "https://api.sandbox.pawapay.io/v2/deposits";

// --- 3. TRAITEMENT DU FORMULAIRE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = $_POST['client_name'] ?? 'Inconnu';
    $phone     = str_replace(['+', ' '], '', $_POST['phone'] ?? ''); 
    $formation = $_POST['formation'] ?? 'N/A';
    $amount    = $_POST['final_amount'] ?? 0;
    $op_code   = $_POST['op_code'] ?? ''; // Ex: ORANGE_CM, MTN_CI, etc.
    $ext_id    = uniqid('trans_'); 

    try {
        // Enregistrement en base de données
        $sql = "INSERT INTO inscriptions (nom_client, telephone, formation, montant, op_code, transaction_id) VALUES (?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $_POST['phone'], $formation, $amount, $op_code, $ext_id]);

        // Préparation du Payload PawaPay
        $payload = [
            "depositId" => $ext_id,
            "amount" => (string)$amount,
            "currency" => "USD", 
            "correspondent" => $op_code,
            "payer" => [
                "address" => ["value" => $phone]
            ],
            "customerTimestamp" => date('Y-m-d\TH:i:s\Z'),
            "statementDescription" => "Formation " . $formation,
            "type" => "H2P" 
        ];

        // Envoi vers PawaPay avec cURL
        $ch = curl_init($API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $PAWAPAY_TOKEN
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // --- 4. AFFICHAGE DU RÉSULTAT ---
        if ($http_code == 200 || $http_code == 201) {
            echo "<div style='text-align:center; padding:50px; font-family:sans-serif;'>";
            echo "<h2 style='color:green;'>Demande de paiement envoyée !</h2>";
            echo "<p>Veuillez consulter votre téléphone <b>$phone</b> et saisir votre code PIN.</p>";
            echo "</div>";
        } else {
            echo "<div style='color:red; padding:20px;'>Erreur PawaPay ($http_code) : " . htmlspecialchars($response) . "</div>";
        }

    } catch (Exception $e) {
        echo "<div style='color:red; padding:20px;'>Erreur lors de l'inscription : " . $e->getMessage() . "</div>";
    }
} else {
    echo "En attente des données du formulaire...";
}
?>