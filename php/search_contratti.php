<?php
// php/search_contratti.php
require_once 'utils/dbconn.php';

$ricerca = isset($_GET['q']) ? trim($_GET['q']) : '';
$mode    = isset($_GET['mode']) ? $_GET['mode'] : 'html'; // 'json' per lookup insert

function normalizzaNumero($n) {
    return preg_replace('/[\s\+]/', '', $n);
}
$ricercaNorm = normalizzaNumero($ricerca);

// Modalità JSON: usata dal form insert per lookup tipo contratto
if ($mode === 'json') {
    $sql_exact = "SELECT tipo, minutiResidui, creditoResiduo FROM ContrattoTelefonico WHERE REPLACE(REPLACE(numero,' ',''),'+','') = ?";
    $stmt_exact = $conn->prepare($sql_exact);
    if ($stmt_exact) {
        $stmt_exact->bind_param("s", $ricercaNorm);
        $stmt_exact->execute();
        $res_exact = $stmt_exact->get_result();
        if ($res_exact->num_rows === 1) {
            $row = $res_exact->fetch_assoc();
            $residuo = ($row['tipo'] === 'ricarica') ? $row['creditoResiduo'] : $row['minutiResidui'];
            header('Content-Type: application/json');
            echo json_encode(['tipo' => $row['tipo'], 'residuo' => $residuo]);
            $stmt_exact->close();
            $conn->close();
            exit;
        }
        $stmt_exact->close();
    }
    header('Content-Type: application/json');
    echo json_encode(['tipo' => null]);
    $conn->close();
    exit;
}

// Modalità HTML: risposta per la tabella contratti
if (!$ricerca) {
    echo "<tr><td colspan='6' class='text-center text-muted'><i class='fa-solid fa-circle-info'></i> Inserisci un numero o nominativo da cercare</td></tr>";
    $conn->close();
    exit;
}

$like     = '%' . $ricerca . '%';
$likeNorm = '%' . $ricercaNorm . '%';

$sql = "SELECT * FROM ContrattoTelefonico
        WHERE nominativo LIKE ?
           OR numero LIKE ?
           OR REPLACE(REPLACE(numero,' ',''),'+','') LIKE ?
        ORDER BY nominativo";

$stmt = $conn->prepare($sql);
if (!$stmt) { die("Errore: " . $conn->error); }
$stmt->bind_param("sss", $like, $like, $likeNorm);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tel     = htmlspecialchars($row['numero']);
        $nomin   = htmlspecialchars($row['nominativo'] ?? '-');
        $dataAtt = htmlspecialchars($row['dataAttivazione']);
        $tipo    = htmlspecialchars($row['tipo']);

        if ($row['tipo'] === 'ricarica') {
            $residuo = number_format($row['creditoResiduo'], 2, ',', '') . ' €';
        } else {
            $residuo = $row['minutiResidui'] . ' Minuti';
        }

        echo "<tr class='clickable-row row-contratto'
                  data-tel='{$tel}'
                  data-nominativo='{$nomin}'>";
        echo "<td>
                <span class='link-tel copyable' data-copy='{$tel}' title='Clicca per filtrare telefonate'>{$tel}</span>
                <button class='btn-copy' title='Copia numero' onclick='copyText(\"{$tel}\", event)'><i class='fa-regular fa-copy'></i></button>
              </td>";
        echo "<td>
                <span class='link-nominativo copyable' data-copy='{$nomin}' title='Clicca per filtrare contratti'>{$nomin}</span>
                <button class='btn-copy' title='Copia nominativo' onclick='copyText(\"{$nomin}\", event)'><i class='fa-regular fa-copy'></i></button>
              </td>";
        echo "<td>{$dataAtt}</td>";
        echo "<td>{$tipo}</td>";
        echo "<td>{$residuo}</td>";
        echo "<td class='text-center'>
                <button class='btn-action btn-refresh-residuo' title='Aggiorna residuo' data-tel='{$tel}'>
                  <i class='fa-solid fa-rotate'></i>
                </button>
                <button class='btn-action btn-go-inserisci' title='Vai a Inserisci telefonata' data-tel='{$tel}'>
                  <i class='fa-solid fa-arrow-right-to-bracket'></i>
                </button>
              </td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6' class='text-center text-muted'><i class='fa-solid fa-circle-info'></i> Nessun contratto trovato per \"" . htmlspecialchars($ricerca) . "\"</td></tr>";
}

$stmt->close();
$conn->close();
?>
