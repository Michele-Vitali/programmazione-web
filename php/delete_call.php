<?php
// php/delete_call.php
require_once 'utils/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { die("Metodo non consentito."); }

$id = isset($_POST['id_chiamata']) ? intval($_POST['id_chiamata']) : 0;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID non valido']);
    exit;
}

// Recupero dati telefonata + tipo contratto per ripristinare residuo
$stmt = $conn->prepare(
    "SELECT t.effettuataDa, t.costo, t.durata, c.tipo, c.nominativo
     FROM Telefonata t
     LEFT JOIN ContrattoTelefonico c ON t.effettuataDa = c.numero
     WHERE t.id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['ok' => false, 'msg' => 'Telefonata non trovata']);
    $stmt->close(); $conn->close(); exit;
}
$tel = $res->fetch_assoc();
$stmt->close();

$conn->begin_transaction();
try {
    // Elimina telefonata
    $stmt = $conn->prepare("DELETE FROM Telefonata WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Ripristina residuo
    if ($tel['tipo'] === 'ricarica') {
        $stmt = $conn->prepare("UPDATE ContrattoTelefonico SET creditoResiduo = creditoResiduo + ? WHERE numero = ?");
        $stmt->bind_param("ds", $tel['costo'], $tel['effettuataDa']);
    } else {
        // Per consumo, il costo salvato è 0; usiamo la durata come minuti scalati
        $stmt = $conn->prepare("UPDATE ContrattoTelefonico SET minutiResidui = minutiResidui + ? WHERE numero = ?");
        $stmt->bind_param("is", $tel['durata'], $tel['effettuataDa']);
    }
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode([
        'ok'         => true,
        'nominativo' => $tel['nominativo'] ?? 'Sconosciuto',
        'telefono'   => $tel['effettuataDa'],
        'costo'      => $tel['costo'],
        'tipo'       => $tel['tipo']
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
$conn->close();
?>
