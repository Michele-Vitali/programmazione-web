<?php
// php/search_contratti.php
require_once 'utils/dbconn.php';

$ricerca = isset($_GET['q']) ? $_GET['q'] : '';

// Cerchiamo nel numero di telefono OPPURE nel nominativo
$sql = "SELECT * FROM ContrattoTelefonico 
        WHERE numero LIKE ? 
        OR nominativo LIKE ? 
        ORDER BY nominativo";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Errore nella preparazione: " . $conn->error);
}

$like = "%" . $ricerca . "%";

// Due segnaposto: uno per numero, uno per nominativo
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr class='clickable-row row-contratto' data-tel='" . htmlspecialchars($row['numero']) . "'>";
        
        // Numero di telefono
        echo "<td>" . htmlspecialchars($row['numero']) . "</td>";
        
        // Nominativo (se NULL mostriamo un trattino)
        echo "<td>" . htmlspecialchars($row['nominativo'] ?? '-') . "</td>";
        
        // Data attivazione
        echo "<td>" . htmlspecialchars($row['dataAttivazione']) . "</td>";
        
        // Tipo contratto
        echo "<td>" . htmlspecialchars($row['tipo']) . "</td>";
        
        // Residuo: mostriamo il valore giusto in base al tipo
        echo "<td>";
        if ($row['tipo'] == 'ricarica') {
            // Mostra credito in euro
            echo number_format($row['creditoResiduo'], 2, ',', '') . " €";
        } else {
            // Mostra minuti
            echo $row['minutiResidui'] . " Minuti";
        }
        echo "</td>";
        
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5' class='text-center'>Nessun contratto trovato</td></tr>";
}

$stmt->close();
$conn->close();
?>