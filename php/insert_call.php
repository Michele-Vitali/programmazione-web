<?php
// php/insert_call.php
require_once 'utils/dbconn.php';

// Controllo che la richiesta sia in POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metodo non consentito. Usa POST.");
}

// Prendo i dati dal form
$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
$data     = isset($_POST['data']) ? $_POST['data'] : '';
$ora      = isset($_POST['ora']) ? $_POST['ora'] : '';
$durata   = isset($_POST['durata']) ? intval($_POST['durata']) : 0;
$costo    = isset($_POST['costo']) ? floatval($_POST['costo']) : 0;

// Validazione base
$errori = [];

if (empty($telefono)) {
    $errori[] = "Il numero di telefono è obbligatorio.";
}
if (empty($data)) {
    $errori[] = "La data è obbligatoria.";
}
if (empty($ora)) {
    $errori[] = "L'ora è obbligatoria.";
}
if ($durata < 0) {
    $errori[] = "La durata non può essere negativa.";
}

// Se ci sono errori, mi fermo subito
if (!empty($errori)) {
    echo "<div class='error-message'>";
    echo "<h4>⚠️ Errori:</h4><ul>";
    foreach ($errori as $errore) {
        echo "<li>" . htmlspecialchars($errore) . "</li>";
    }
    echo "</ul></div>";
    exit;
}

// =============================================
// CONTROLLO: il numero esiste tra le SIM?
// =============================================

$sql_check = "
    SELECT 'Attiva' AS stato FROM SIMAttiva WHERE associataA = ?
    UNION
    SELECT 'Disattivata' FROM SIMDisattiva WHERE eraAssociataA = ?
";

$stmt_check = $conn->prepare($sql_check);
if (!$stmt_check) {
    echo "<div class='error-message'>";
    echo "<h4>❌ Errore nella verifica:</h4>";
    echo "<p>" . htmlspecialchars($conn->error) . "</p>";
    echo "</div>";
    exit;
}

$stmt_check->bind_param("ss", $telefono, $telefono);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    // Nessuna SIM trovata con quel numero
    echo "<div class='error-message'>";
    echo "<h4>⚠️ Numero non trovato</h4>";
    echo "<p>Il numero <strong>" . htmlspecialchars($telefono) . "</strong> non è associato a nessuna SIM attiva o disattivata.</p>";
    echo "<p>Controlla che il numero sia corretto.</p>";
    echo "</div>";
    $stmt_check->close();
    $conn->close();
    exit;
}

// Se arrivo qui, il numero esiste
$sim = $result_check->fetch_assoc();
$stato_sim = $sim['stato'];

$stmt_check->close();

// =============================================
// INSERIMENTO NEL DATABASE
// =============================================

$sql = "INSERT INTO Telefonata (effettuataDa, data, ora, durata, costo) 
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "<div class='error-message'>";
    echo "<h4>❌ Errore nella preparazione:</h4>";
    echo "<p>" . htmlspecialchars($conn->error) . "</p>";
    echo "</div>";
    exit;
}

$stmt->bind_param("sssid", $telefono, $data, $ora, $durata, $costo);

if ($stmt->execute()) {
    // Successo!
    echo "<div class='success-message'>";
    echo "<h4>✅ Telefonata registrata con successo!</h4>";
    echo "<p><strong>Numero:</strong> " . htmlspecialchars($telefono) . " (SIM " . $stato_sim . ")</p>";
    echo "<p><strong>Data:</strong> " . htmlspecialchars($data) . " alle " . htmlspecialchars($ora) . "</p>";
    echo "<p><strong>Durata:</strong> " . $durata . " minuti</p>";
    echo "<p><strong>Costo:</strong> " . number_format($costo, 2, ',', '') . " €</p>";
    echo "</div>";
} else {
    echo "<div class='error-message'>";
    echo "<h4>❌ Errore durante l'inserimento:</h4>";
    echo "<p>" . htmlspecialchars($stmt->error) . "</p>";
    echo "</div>";
}

$stmt->close();
$conn->close();
?>