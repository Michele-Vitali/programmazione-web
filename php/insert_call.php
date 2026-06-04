<?php
// php/insert_call.php
require_once 'utils/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { die("Metodo non consentito."); }

function normalizzaNumero($n) {
    return preg_replace('/[\s\+]/', '', $n);
}

$telefonoRaw    = isset($_POST['telefono'])       ? trim($_POST['telefono'])        : '';
$telefono       = normalizzaNumero($telefonoRaw);
$data           = isset($_POST['data'])           ? $_POST['data']                  : '';
$ora            = isset($_POST['ora'])            ? $_POST['ora']                   : '';
$durata         = isset($_POST['durata'])         ? intval($_POST['durata'])         : 0;
$tipo_contratto = isset($_POST['tipo_contratto']) ? trim($_POST['tipo_contratto'])   : '';
$minuti_scalati = isset($_POST['minuti_scalati']) ? intval($_POST['minuti_scalati']) : 0;

// Calcolo costo: per ricarica = durata * tariffa; per consumo = 0
define('TARIFFA_AL_MINUTO', 0.28);
// Per ricarica usa il costo inviato dal frontend (già calcolato/modificato dall'admin)
// Per consumo il costo è sempre 0
if ($tipo_contratto === 'ricarica') {
    $costo = isset($_POST['costo']) ? round(floatval($_POST['costo']), 2) : round($durata * TARIFFA_AL_MINUTO, 2);
} else {
    $costo = 0.00;
}

// --- Validazione base ---
$errori = [];
if (!$telefono)       $errori[] = "Il numero di telefono è obbligatorio.";
if (!$data)           $errori[] = "La data è obbligatoria.";
if (!$ora)            $errori[] = "L'ora è obbligatoria.";
// Durata 0 non ha senso, ma negativa è ammessa per correzioni amministrative
if ($durata === 0)    $errori[] = "La durata non può essere zero.";
if (!$tipo_contratto) $errori[] = "Tipo contratto mancante — ricarica la pagina e riprova.";
// minuti_scalati negativi ammessi per correzioni
if ($tipo_contratto === 'consumo' && $minuti_scalati === 0)
    $errori[] = "I minuti da scalare non possono essere zero.";

if (!empty($errori)) {
    echo "<div class='error-message'><h4>⚠️ Errori:</h4><ul>";
    foreach ($errori as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
    echo "</ul></div>"; exit;
}

// --- CONTROLLO 1: data non nel futuro ---
if ($data > date('Y-m-d')) {
    echo "<div class='error-message'><h4>⚠️ Data non valida</h4>
          <p>La data (<strong>" . htmlspecialchars($data) . "</strong>) non può essere nel futuro.</p></div>";
    $conn->close(); exit;
}

// --- CONTROLLO 2: contratto (cerca con numero normalizzato) ---
$stmt = $conn->prepare(
    "SELECT dataAttivazione, tipo, minutiResidui, creditoResiduo
     FROM ContrattoTelefonico
     WHERE REPLACE(REPLACE(numero,' ',''),'+','') = ?"
);
$stmt->bind_param("s", $telefono);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo "<div class='error-message'><h4>⚠️ Numero non trovato</h4>
          <p>Il numero <strong>" . htmlspecialchars($telefonoRaw) . "</strong> non è associato a nessun contratto.</p></div>";
    $stmt->close(); $conn->close(); exit;
}
$contratto = $res->fetch_assoc();
// Recupera il numero esatto come salvato nel DB per usarlo negli UPDATE
$telefonoDB = $conn->query("SELECT numero FROM ContrattoTelefonico WHERE REPLACE(REPLACE(numero,' ',''),'+','') = '" . $conn->real_escape_string($telefono) . "' LIMIT 1")->fetch_assoc()['numero'];
$stmt->close();

// --- CONTROLLO 3: data vs attivazione contratto ---
if ($data < $contratto['dataAttivazione']) {
    echo "<div class='error-message'><h4>⚠️ Data antecedente all'attivazione del contratto</h4>
          <p>Data telefonata: <strong>" . htmlspecialchars($data) . "</strong> —
             Attivazione contratto: <strong>" . htmlspecialchars($contratto['dataAttivazione']) . "</strong></p></div>";
    $conn->close(); exit;
}

// --- CONTROLLO 4: residuo sufficiente (solo se costo positivo) ---
if ($tipo_contratto === 'ricarica' && $costo > 0 && $contratto['creditoResiduo'] < $costo) {
    echo "<div class='error-message'><h4>⚠️ Credito insufficiente</h4>
          <p>Credito residuo: <strong>" . number_format($contratto['creditoResiduo'], 2, ',', '') . " €</strong> —
             Costo calcolato: <strong>" . number_format($costo, 2, ',', '') . " €</strong></p></div>";
    $conn->close(); exit;
}
if ($tipo_contratto === 'consumo' && $minuti_scalati > 0 && $contratto['minutiResidui'] < $minuti_scalati) {
    echo "<div class='error-message'><h4>⚠️ Minuti insufficienti</h4>
          <p>Minuti residui: <strong>" . $contratto['minutiResidui'] . " min</strong> —
             Minuti richiesti: <strong>" . $minuti_scalati . " min</strong></p></div>";
    $conn->close(); exit;
}

// --- CONTROLLO 5+6: SIM ---
$stato_sim = null; $dataAttivSIM = null; $dataDisattivSIM = null;

$stmt = $conn->prepare("SELECT dataAttivazione FROM SIMAttiva WHERE REPLACE(REPLACE(associataA,' ',''),'+','') = ?");
$stmt->bind_param("s", $telefono);
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
    $stmt->bind_param("s", $telefono);
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

if (!$stato_sim) {
    echo "<div class='error-message'><h4>⚠️ Nessuna SIM associata</h4>
          <p>Il numero <strong>" . htmlspecialchars($telefonoRaw) . "</strong> non ha SIM attiva o disattivata.</p></div>";
    $conn->close(); exit;
}
if ($data < $dataAttivSIM) {
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

// =============================================
// INSERIMENTO + AGGIORNAMENTO RESIDUO
// =============================================
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO Telefonata (effettuataDa, data, ora, durata, costo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssid", $telefonoDB, $data, $ora, $durata, $costo);
    $stmt->execute();
    $stmt->close();

    if ($tipo_contratto === 'ricarica') {
        $stmt = $conn->prepare("UPDATE ContrattoTelefonico SET creditoResiduo = creditoResiduo - ? WHERE numero = ?");
        $stmt->bind_param("ds", $costo, $telefonoDB);
        $stmt->execute(); $stmt->close();
        $nuovo_residuo = number_format($contratto['creditoResiduo'] - $costo, 2, ',', '') . ' €';
    } else {
        $stmt = $conn->prepare("UPDATE ContrattoTelefonico SET minutiResidui = minutiResidui - ? WHERE numero = ?");
        $stmt->bind_param("is", $minuti_scalati, $telefonoDB);
        $stmt->execute(); $stmt->close();
        $nuovo_residuo = ($contratto['minutiResidui'] - $minuti_scalati) . ' min';
    }

    $conn->commit();
    echo "<div class='success-message'>
          <h4>✅ Telefonata registrata con successo!</h4>
          <p><strong>Numero:</strong> " . htmlspecialchars($telefonoDB) . " (SIM " . $stato_sim . ")</p>
          <p><strong>Data:</strong> " . htmlspecialchars($data) . " alle " . htmlspecialchars($ora) . "</p>
          <p><strong>Durata:</strong> {$durata} min" .
          ($tipo_contratto === 'ricarica' ? " — Costo: " . number_format($costo, 2, ',', '') . " €" : "") . "</p>
          <p><strong>Residuo aggiornato:</strong> {$nuovo_residuo}</p>
          </div>";
} catch (Exception $ex) {
    $conn->rollback();
    echo "<div class='error-message'><h4>❌ Errore:</h4><p>" . htmlspecialchars($ex->getMessage()) . "</p></div>";
}
$conn->close();
?>
