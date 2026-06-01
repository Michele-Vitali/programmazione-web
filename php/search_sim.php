<?php
// php/search_sim.php
require_once 'utils/dbconn.php';  // ← percorso corretto

$ricerca = isset($_GET['q']) ? $_GET['q'] : '';

$sql = "SELECT * FROM SIMAttiva WHERE codice LIKE ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Errore nella preparazione: " . $conn->error);
}

$like = "%" . $ricerca . "%";
$stmt->bind_param("s", $like);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr class='clickable-row row-sim' data-sim='" . htmlspecialchars($row['codice']) . "'>";
        echo "<td>" . htmlspecialchars($row['codice']) . "</td>";
        echo "<td>" . htmlspecialchars($row['tipoSIM']) . "</td>";
        // Stato non esiste nella tabella? Mettiamo un placeholder o lo togliamo
        // echo "<td>" . htmlspecialchars($row['stato'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['associataA']) . "</td>";
        echo "<td>" . htmlspecialchars($row['dataAttivazione']) . "</td>";
        // Data disattivazione non esiste? La saltiamo
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='4' class='text-center'>Nessuna SIM trovata</td></tr>";
}

$stmt->close();
$conn->close();
?>