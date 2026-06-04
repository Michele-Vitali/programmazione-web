<?php
// php/get_residuo.php — restituisce il residuo aggiornato di un contratto
require_once 'utils/dbconn.php';

function normalizzaNumero($n) {
    return preg_replace('/[\s\+]/', '', $n);
}

$tel  = isset($_GET['tel']) ? trim($_GET['tel']) : '';
$norm = normalizzaNumero($tel);

$stmt = $conn->prepare(
    "SELECT tipo, minutiResidui, creditoResiduo, nominativo
     FROM ContrattoTelefonico
     WHERE REPLACE(REPLACE(numero,' ',''),'+','') = ?"
);
$stmt->bind_param("s", $norm);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['ok' => false]);
} else {
    $row = $res->fetch_assoc();
    $residuo  = ($row['tipo'] === 'ricarica') ? $row['creditoResiduo'] : $row['minutiResidui'];
    $unita    = ($row['tipo'] === 'ricarica') ? '€' : 'min';
    echo json_encode([
        'ok'         => true,
        'tipo'       => $row['tipo'],
        'residuo'    => $residuo,
        'unita'      => $unita,
        'nominativo' => $row['nominativo'] ?? ''
    ]);
}
$stmt->close();
$conn->close();
?>
