<?php
// php/search_contratti.php
require_once 'utils/dbconn.php';

$ricerca = isset($_GET['q']) ? trim($_GET['q']) : '';

// Se la ricerca corrisponde esattamente a un numero (chiamata dal form insert),
// rispondiamo con JSON per consentire al JS di sapere il tipo contratto
$exact_sql = "SELECT tipo, minutiResidui, creditoResiduo FROM ContrattoTelefonico WHERE numero = ?";
$stmt_exact = $conn->prepare($exact_sql);
if ($stmt_exact) {
    $stmt_exact->bind_param("s", $ricerca);
    $stmt_exact->execute();
    $res_exact = $stmt_exact->get_result();
    if ($res_exact->num_rows === 1) {
        // Risposta JSON usata dal form inserimento telefonata
        $row = $res_exact->fetch_assoc();
        $residuo = ($row['tipo'] === 'ricarica') ? $row['creditoResiduo'] : $row['minutiResidui'];
        header('Content-Type: application/json');
        echo json_encode([
            'tipo'    => $row['tipo'],
            'residuo' => $residuo
        ]);
        $stmt_exact->close();
        $conn->close();
        exit;
    }
    $stmt_exact->close();
}

// Altrimenti: ricerca per tabella (numero o nominativo) — risposta HTML
$sql = "SELECT * FROM ContrattoTelefonico
        WHERE numero LIKE ?
        OR nominativo LIKE ?
        ORDER BY nominativo";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Errore nella preparazione: " . $conn->error);
}

$like = "%" . $ricerca . "%";
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr class='clickable-row row-contratto' data-tel='" . htmlspecialchars($row['numero']) . "'>";
        echo "<td>" . htmlspecialchars($row['numero']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nominativo'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['dataAttivazione']) . "</td>";
        echo "<td>" . htmlspecialchars($row['tipo']) . "</td>";
        echo "<td>";
        if ($row['tipo'] === 'ricarica') {
            echo number_format($row['creditoResiduo'], 2, ',', '') . " €";
        } else {
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
