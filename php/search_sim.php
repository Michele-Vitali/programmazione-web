<?php
// php/search_sim.php
require_once 'utils/dbconn.php';

$ricerca = isset($_GET['q']) ? $_GET['q'] : '';

// UNION di tutte e tre le tabelle SIM
$sql = "
    SELECT codice, tipoSIM, associataA, dataAttivazione, 'Attiva' AS stato, NULL AS dataDisattivazione
    FROM SIMAttiva
    WHERE codice LIKE ?
    
    UNION
    
    SELECT codice, tipoSIM, NULL AS associataA, NULL AS dataAttivazione, 'Non Attiva' AS stato, NULL AS dataDisattivazione
    FROM SIMNonAttiva
    WHERE codice LIKE ?
    
    UNION
    
    SELECT codice, tipoSIM, eraAssociataA AS associataA, dataAttivazione, 'Disattivata' AS stato, dataDisattivazione
    FROM SIMDisattiva
    WHERE codice LIKE ?
    
    ORDER BY stato, codice
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Errore nella preparazione: " . $conn->error);
}

$like = "%" . $ricerca . "%";

// Tre segnaposto, uno per ogni SELECT
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr class='clickable-row row-sim' data-sim='" . htmlspecialchars($row['codice']) . "'>";
        echo "<td>" . htmlspecialchars($row['codice']) . "</td>";
        echo "<td>" . htmlspecialchars($row['tipoSIM']) . "</td>";
        
        // Colonna STATO con colore
        echo "<td>";
        if ($row['stato'] == 'Attiva') {
            echo "<span class='text-success-bold'>Attiva</span>";
        } elseif ($row['stato'] == 'Disattivata') {
            echo "<span class='text-danger'>Disattivata</span>";
        } else {
            echo "<span class='text-warning'>Non Attiva</span>";
        }
        echo "</td>";
        
        echo "<td>" . htmlspecialchars($row['associataA'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['dataAttivazione'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['dataDisattivazione'] ?? '-') . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6' class='text-center'>Nessuna SIM trovata</td></tr>";
}

$stmt->close();
$conn->close();
?>