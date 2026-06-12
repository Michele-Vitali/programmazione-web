<?php
// php/update_call.php
require_once 'utils/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { die("Metodo non consentito."); }

function normalizzaNumero($n) {
    return preg_replace('/[\s\+]/', '', $n);
}

$id             = isset($_POST['id_chiamata'])    ? intval($_POST['id_chiamata'])      : 0;
$data           = isset($_POST['data'])           ? trim($_POST['data'])               : '';
$ora            = isset($_POST['ora'])            ? trim($_POST['ora'])                : '';
$durata         = isset($_POST['durata'])         ? intval($_POST['durata'])           : 0;
$tipo_contratto = isset($_POST['tipo_contratto']) ? trim($_POST['tipo_contratto'])     : '';
$minuti_scalati = isset($_POST['minuti_scalati']) ? intval($_POST['minuti_scalati'])   : 0;

define('TARIFFA_AL_MINUTO', 0.28);

// Costo: per ricarica usa quello inviato (modificabile), per consumo è 0
if ($tipo_contratto === 'ricarica') {
    $costo = isset($_POST['costo']) && $_POST['costo'] !== '' ? floatval($_POST['costo']) : round($durata * TARIFFA_AL_MINUTO, 2);
} else {
    $costo = 0.0;
}

// Validazione base
if ($id <= 0 || empty($data) || empty($ora) || $durata === 0) {
    echo "<div class='error-message'><h4>⚠️ Dati non validi</h4>
          <p>Verifica che tutti i campi siano compilati e la durata non sia zero.</p></div>";
    exit;
}

// ── Recupera la telefonata originale per calcolare la differenza di residuo ──
$stmt = $conn->prepare(
    "SELECT t.durata, t.costo, t.effettuataDa, c.tipo, c.minutiResidui, c.creditoResiduo
     FROM Telefonata t
     LEFT JOIN ContrattoTelefonico c ON t.effettuataDa = c.numero
     WHERE t.id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<div class='error-message'><h4>⚠️ Telefonata non trovata</h4></div>";
    $stmt->close(); $conn->close(); exit;
}
$originale = $res->fetch_assoc();
$stmt->close();

$telefono      = $originale['effettuataDa'];
$tipoContratto = $originale['tipo'] ?: $tipo_contratto; // fallback se JOIN non trova contratto
$telefonoNorm  = normalizzaNumero($telefono);

// --- CONTROLLO 1: data non nel futuro ---
if ($data > date('Y-m-d')) {
    echo "<div class='error-message'><h4>⚠️ Data non valida</h4>
          <p>La data (<strong>" . htmlspecialchars($data) . "</strong>) non può essere nel futuro.</p></div>";
    $conn->close(); exit;
}

// --- CONTROLLO 2: data vs attivazione contratto ---
$stmt = $conn->prepare(
    "SELECT dataAttivazione FROM ContrattoTelefonico
     WHERE REPLACE(REPLACE(numero,' ',''),'+','') = ?"
);
$stmt->bind_param("s", $telefonoNorm);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $contratto = $res->fetch_assoc();
    if ($data < $contratto['dataAttivazione']) {
        echo "<div class='error-message'><h4>⚠️ Data antecedente all'attivazione del contratto</h4>
              <p>Data telefonata: <strong>" . htmlspecialchars($data) . "</strong> —
                 Attivazione contratto: <strong>" . htmlspecialchars($contratto['dataAttivazione']) . "</strong></p></div>";
        $stmt->close(); $conn->close(); exit;
    }
}
$stmt->close();

// --- CONTROLLO 3+4: SIM (attiva o disattivata) ---
$stato_sim = null; $dataAttivSIM = null; $dataDisattivSIM = null;

$stmt = $conn->prepare("SELECT dataAttivazione FROM SIMAttiva WHERE REPLACE(REPLACE(associataA,' ',''),'+','') = ?");
$stmt->bind_param("s", $telefonoNorm);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $sim = $res->fetch_assoc();
    $stato_sim    = 'Attiva';
    $dataAttivSIM = $sim['dataAttivazione'];
}
$stmt->close();

if (!$stato_sim) {
    $stmt = $conn->prepare("SELECT dataAttivazione, dataDisattivazione FROM SIMDisattiva WHERE REPLACE(REPLACE(eraAssociataA,' ',''),'+','') = ?");
    $stmt->bind_param("s", $telefonoNorm);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $sim             = $res->fetch_assoc();
        $stato_sim       = 'Disattivata';
        $dataAttivSIM    = $sim['dataAttivazione'];
        $dataDisattivSIM = $sim['dataDisattivazione'];
    }
    $stmt->close();
}

if ($stato_sim && $data < $dataAttivSIM) {
    echo "<div class='error-message'><h4>⚠️ Data antecedente all'attivazione della SIM</h4>
          <p>Data telefonata: <strong>" . htmlspecialchars($data) . "</strong> —
             Attivazione SIM: <strong>" . htmlspecialchars($dataAttivSIM) . "</strong></p></div>";
    $conn->close(); exit;
}
if ($stato_sim === 'Disattivata' && $data > $dataDisattivSIM) {
    echo "<div class='error-message'><h4>⚠️ SIM già disattivata</h4>
          <p>Data telefonata: <strong>" . htmlspecialchars($data) . "</strong> —
             Disattivazione SIM: <strong>" . htmlspecialchars($dataDisattivSIM) . "</strong></p></div>";
    $conn->close(); exit;
}

$conn->begin_transaction();
try {
    // 1. Aggiorna la telefonata
    $stmt = $conn->prepare("UPDATE Telefonata SET data = ?, ora = ?, durata = ?, costo = ? WHERE id = ?");
    $stmt->bind_param("ssidi", $data, $ora, $durata, $costo, $id);
    $stmt->execute();
    $stmt->close();

    // 2. Aggiorna il residuo sul contratto:
    //    ripristina il vecchio valore, poi scala il nuovo
    if ($tipoContratto === 'ricarica') {
        $vecchioCosto = floatval($originale['costo']);
        $diff = $vecchioCosto - $costo; // positivo = si riaccredita la differenza
        if ($diff != 0) {
            $stmt = $conn->prepare(
                "UPDATE ContrattoTelefonico SET creditoResiduo = creditoResiduo + ? WHERE numero = ?"
            );
            $stmt->bind_param("ds", $diff, $telefono);
            $stmt->execute();
            $stmt->close();
        }
        $nuovoResiduo = round(floatval($originale['creditoResiduo']) + $diff, 2);
        $residuoFmt   = number_format($nuovoResiduo, 2, ',', '') . ' €';
    } else {
        // consumo: scala minuti
        $vecchiadurata = intval($originale['durata']);
        $diffMin = $vecchiadurata - $minuti_scalati; // positivo = si riaccreditano minuti
        if ($diffMin != 0) {
            $stmt = $conn->prepare(
                "UPDATE ContrattoTelefonico SET minutiResidui = minutiResidui + ? WHERE numero = ?"
            );
            $stmt->bind_param("is", $diffMin, $telefono);
            $stmt->execute();
            $stmt->close();
        }
        $nuovoResiduo = intval($originale['minutiResidui']) + $diffMin;
        $residuoFmt   = $nuovoResiduo . ' min';
    }

    $conn->commit();

    echo "<div class='success-message'>
          <h4>✅ Telefonata aggiornata con successo!</h4>
          <p><strong>Data:</strong> " . htmlspecialchars($data) . " alle " . htmlspecialchars($ora) . "</p>
          <p><strong>Durata:</strong> {$durata} min" .
          ($tipoContratto === 'ricarica' ? " — <strong>Costo:</strong> " . number_format($costo, 2, ',', '') . " €" : "") . "</p>
          <p><strong>Residuo contratto aggiornato:</strong> {$residuoFmt}</p>
          </div>";

} catch (Exception $e) {
    $conn->rollback();
    echo "<div class='error-message'><h4>❌ Errore:</h4><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
}

$conn->close();
?>
