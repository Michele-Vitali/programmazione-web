<?php
// php/insert_call.php
require_once 'utils/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metodo non consentito. Usa POST.");
}

$telefono       = isset($_POST['telefono'])       ? trim($_POST['telefono'])          : '';
$data           = isset($_POST['data'])           ? $_POST['data']                    : '';
$ora            = isset($_POST['ora'])            ? $_POST['ora']                     : '';
$durata         = isset($_POST['durata'])         ? intval($_POST['durata'])           : 0;
$tipo_contratto = isset($_POST['tipo_contratto']) ? trim($_POST['tipo_contratto'])     : '';
$costo          = isset($_POST['costo'])          ? floatval($_POST['costo'])          : 0.0;
$minuti_scalati = isset($_POST['minuti_scalati']) ? intval($_POST['minuti_scalati'])   : 0;

// --- Validazione base ---
$errori = [];
if (empty($telefono))       $errori[] = "Il numero di telefono è obbligatorio.";
if (empty($data))           $errori[] = "La data è obbligatoria.";
if (empty($ora))            $errori[] = "L'ora è obbligatoria.";
if ($durata <= 0)           $errori[] = "La durata deve essere maggiore di zero.";
if (empty($tipo_contratto)) $errori[] = "Tipo contratto mancante — ricarica la pagina e riprova.";

if ($tipo_contratto === 'ricarica' && $costo <= 0)
    $errori[] = "Il costo in euro deve essere maggiore di zero.";
if ($tipo_contratto === 'consumo' && $minuti_scalati <= 0)
    $errori[] = "I minuti da scalare devono essere maggiori di zero.";

if (!empty($errori)) {
    echo "<div class='error-message'><h4>⚠️ Errori:</h4><ul>";
    foreach ($errori as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
    echo "</ul></div>";
    exit;
}

// --- CONTROLLO 1: data non nel futuro ---
if ($data > date('Y-m-d')) {
    echo "<div class='error-message'><h4>⚠️ Data non valida</h4>
          <p>La data (<strong>" . htmlspecialchars($data) . "</strong>) non può essere nel futuro.</p></div>";
    $conn->close(); exit;
}

// --- CONTROLLO 2: contratto esistente + data attivazione contratto ---
$stmt = $conn->prepare("SELECT dataAttivazione, tipo, minutiResidui, creditoResiduo FROM ContrattoTelefonico WHERE numero = ?");
$stmt->bind_param("s", $telefono);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo "<div class='error-message'><h4>⚠️ Numero non trovato</h4>
          <p>Il numero <strong>" . htmlspecialchars($telefono) . "</strong> non è associato a nessun contratto.</p></div>";
    $stmt->close(); $conn->close(); exit;
}
$contratto = $res->fetch_assoc();
$stmt->close();

if ($data < $contratto['dataAttivazione']) {
    echo "<div class='error-message'><h4>⚠️ Data antecedente all'attivazione del contratto</h4>
          <p>Data telefonata: <strong>" . htmlspecialchars($data) . "</strong> —
             Attivazione contratto: <strong>" . htmlspecialchars($contratto['dataAttivazione']) . "</strong></p></div>";
    $conn->close(); exit;
}

// --- CONTROLLO 3: residuo sufficiente ---
if ($tipo_contratto === 'ricarica' && $contratto['creditoResiduo'] < $costo) {
    echo "<div class='error-message'><h4>⚠️ Credito insufficiente</h4>
          <p>Credito residuo: <strong>" . number_format($contratto['creditoResiduo'], 2, ',', '') . " €</strong> —
             Costo telefonata: <strong>" . number_format($costo, 2, ',', '') . " €</strong></p></div>";
    $conn->close(); exit;
}
if ($tipo_contratto === 'consumo' && $contratto['minutiResidui'] < $minuti_scalati) {
    echo "<div class='error-message'><h4>⚠️ Minuti insufficienti</h4>
          <p>Minuti residui: <strong>" . $contratto['minutiResidui'] . " min</strong> —
             Minuti da scalare: <strong>" . $minuti_scalati . " min</strong></p></div>";
    $conn->close(); exit;
}

// --- CONTROLLO 4+5: SIM (data attivazione e data disattivazione) ---
$stato_sim = null; $dataAttivSIM = null; $dataDisattivSIM = null;

$stmt = $conn->prepare("SELECT dataAttivazione FROM SIMAttiva WHERE associataA = ?");
$stmt->bind_param("s", $telefono);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $sim = $res->fetch_assoc();
    $stato_sim    = 'Attiva';
    $dataAttivSIM = $sim['dataAttivazione'];
}
$stmt->close();

if ($stato_sim === null) {
    $stmt = $conn->prepare("SELECT dataAttivazione, dataDisattivazione FROM SIMDisattiva WHERE eraAssociataA = ?");
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

if ($stato_sim === null) {
    echo "<div class='error-message'><h4>⚠️ Nessuna SIM associata</h4>
          <p>Il numero <strong>" . htmlspecialchars($telefono) . "</strong> non ha nessuna SIM attiva o disattivata.</p></div>";
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
    // 1. Inserimento telefonata
    $stmt = $conn->prepare("INSERT INTO Telefonata (effettuataDa, data, ora, durata, costo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssid", $telefono, $data, $ora, $durata, $costo);
    $stmt->execute();
    $stmt->close();

    // 2. Aggiornamento residuo sul contratto
    if ($tipo_contratto === 'ricarica') {
        $stmt = $conn->prepare("UPDATE ContrattoTelefonico SET creditoResiduo = creditoResiduo - ? WHERE numero = ?");
        $stmt->bind_param("ds", $costo, $telefono);
        $stmt->execute();
        $stmt->close();
        $nuovo_residuo = number_format($contratto['creditoResiduo'] - $costo, 2, ',', '') . " €";
    } else {
        $stmt = $conn->prepare("UPDATE ContrattoTelefonico SET minutiResidui = minutiResidui - ? WHERE numero = ?");
        $stmt->bind_param("is", $minuti_scalati, $telefono);
        $stmt->execute();
        $stmt->close();
        $nuovo_residuo = ($contratto['minutiResidui'] - $minuti_scalati) . " min";
    }

    $conn->commit();

    echo "<div class='success-message'>
          <h4>✅ Telefonata registrata con successo!</h4>
          <p><strong>Numero:</strong> " . htmlspecialchars($telefono) . " (SIM " . $stato_sim . ")</p>
          <p><strong>Data:</strong> " . htmlspecialchars($data) . " alle " . htmlspecialchars($ora) . "</p>
          <p><strong>Durata:</strong> " . $durata . " minuti</p>
          <p><strong>Residuo aggiornato:</strong> " . $nuovo_residuo . "</p>
          </div>";

} catch (Exception $e) {
    $conn->rollback();
    echo "<div class='error-message'><h4>❌ Errore durante l'inserimento:</h4>
          <p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
}

$conn->close();
?>
