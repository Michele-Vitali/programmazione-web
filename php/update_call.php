<?php
// php/update_call.php
require_once 'utils/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metodo non consentito.");
}

$id     = isset($_POST['id_chiamata']) ? intval($_POST['id_chiamata']) : 0;
$data   = isset($_POST['data'])   ? $_POST['data']           : '';
$ora    = isset($_POST['ora'])    ? $_POST['ora']            : '';
$durata = isset($_POST['durata']) ? intval($_POST['durata']) : 0;
$costo  = isset($_POST['costo'])  ? floatval($_POST['costo']): 0;

// Validazione: durata e costo possono essere negativi (correzioni admin)
if ($id <= 0 || empty($data) || empty($ora)) {
    echo "<div class='error-message'><h4>⚠️ Dati non validi</h4></div>";
    exit;
}

$sql = "UPDATE Telefonata SET data = ?, ora = ?, durata = ?, costo = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "<div class='error-message'><h4>❌ Errore:</h4><p>" . htmlspecialchars($conn->error) . "</p></div>";
    exit;
}

$stmt->bind_param("ssidi", $data, $ora, $durata, $costo, $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "<div class='success-message'>";
        echo "<h4>✅ Telefonata aggiornata con successo!</h4>";
        echo "<p>Data: " . htmlspecialchars($data) . " alle " . htmlspecialchars($ora) . "</p>";
        echo "<p>Durata: " . $durata . " min — Costo: " . number_format($costo, 2, ',', '') . " €</p>";
        echo "</div>";
    } else {
        echo "<div class='info-message'><h4>ℹ️ Nessuna modifica effettuata</h4></div>";
    }
} else {
    echo "<div class='error-message'><h4>❌ Errore:</h4><p>" . htmlspecialchars($stmt->error) . "</p></div>";
}

$stmt->close();
$conn->close();
?>
