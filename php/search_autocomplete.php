<?php
// php/search_autocomplete.php
// Restituisce suggerimenti JSON per l'autocompletamento
require_once 'utils/dbconn.php';

$q    = isset($_GET['q'])    ? trim($_GET['q'])    : '';
$tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : 'telefonate';

if (strlen($q) < 1) { echo json_encode([]); exit; }

$like = '%' . $q . '%';
$results = [];

if ($tipo === 'sim') {
    // Autocomplete per SIM: codice o telefono associato
    $sql = "SELECT codice AS label, associataA AS sub, 'Attiva' AS stato FROM SIMAttiva WHERE codice LIKE ? OR associataA LIKE ?
            UNION
            SELECT codice AS label, eraAssociataA AS sub, 'Disattivata' AS stato FROM SIMDisattiva WHERE codice LIKE ? OR eraAssociataA LIKE ?
            UNION
            SELECT codice AS label, NULL AS sub, 'Non Attiva' AS stato FROM SIMNonAttiva WHERE codice LIKE ?
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $like, $like, $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $label = $row['label'];
        if ($row['sub']) $label .= ' (' . $row['sub'] . ')';
        $results[] = ['value' => $row['label'], 'label' => $label, 'stato' => $row['stato']];
    }
    $stmt->close();
} elseif ($tipo === 'contratti') {
    // Autocomplete per contratti: numero o nominativo
    $sql = "SELECT numero, nominativo FROM ContrattoTelefonico WHERE numero LIKE ? OR nominativo LIKE ? LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $label = $row['numero'];
        if ($row['nominativo']) $label .= ' — ' . $row['nominativo'];
        $results[] = ['value' => $row['numero'], 'label' => $label, 'nominativo' => $row['nominativo'] ?? ''];
    }
    $stmt->close();
} else {
    // Autocomplete per telefonate e form insert: numero o nominativo
    $sql = "SELECT numero, nominativo FROM ContrattoTelefonico WHERE numero LIKE ? OR nominativo LIKE ? LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $label = $row['numero'];
        if ($row['nominativo']) $label .= ' — ' . $row['nominativo'];
        $results[] = ['value' => $row['numero'], 'label' => $label, 'nominativo' => $row['nominativo'] ?? ''];
    }
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($results);
$conn->close();
?>
