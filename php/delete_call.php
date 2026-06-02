<?php
// php/delete_call.php
require_once 'utils/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metodo non consentito.");
}

$id = isset($_POST['id_chiamata']) ? intval($_POST['id_chiamata']) : 0;

if ($id <= 0) {
    echo "<div class='error-message'><h4>❌ ID telefonata non valido</h4></div>";
    exit;
}

// Prima recupero i dati per mostrare cosa sto eliminando
$sql_info = "SELECT t.*, c.nominativo 
             FROM Telefonata t 
             LEFT JOIN ContrattoTelefonico c ON t.effettuataDa = c.numero 
             WHERE t.id = ?";
$stmt_info = $conn->prepare($sql_info);
$stmt_info->bind_param("i", $id);
$stmt_info->execute();
$result_info = $stmt_info->get_result();

if ($result_info->num_rows === 0) {
    echo "<div class='error-message'><h4>❌ Telefonata non trovata</h4></div>";
    $stmt_info->close();
    $conn->close();
    exit;
}

$tel = $result_info->fetch_assoc();
$stmt_info->close();

// Elimino la telefonata
$sql = "DELETE FROM Telefonata WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo "<div class='success-message'>";
    echo "<h4>✅ Telefonata eliminata con successo!</h4>";
    echo "<p><strong>Nominativo:</strong> " . htmlspecialchars($tel['nominativo'] ?? 'Sconosciuto') . "</p>";
    echo "<p><strong>Telefono:</strong> " . htmlspecialchars($tel['effettuataDa']) . "</p>";
    echo "<p><strong>Costo:</strong> " . number_format($tel['costo'], 2, ',', '') . " €</p>";
    echo "</div>";
} else {
    echo "<div class='error-message'><h4>❌ Errore:</h4><p>" . htmlspecialchars($stmt->error) . "</p></div>";
}

$stmt->close();
$conn->close();
?>