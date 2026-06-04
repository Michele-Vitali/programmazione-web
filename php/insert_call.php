<?php
// php/insert_call.php
require_once 'utils/dbconn.php';

// Controllo che la richiesta sia in POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metodo non consentito. Usa POST.");
}

// Prendo i dati dal form
$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
$data     = isset($_POST['data']) ? $_POST['data'] : '';
$ora      = isset($_POST['ora']) ? $_POST['ora'] : '';
$durata   = isset($_POST['durata']) ? intval($_POST['durata']) : 0;
$costo    = isset($_POST['costo']) ? floatval($_POST['costo']) : 0;

// Validazione base
$errori = [];

if (empty($telefono)) {
    $errori[] = "Il numero di telefono è obbligatorio.";
}
if (empty($data)) {
    $errori[] = "La data è obbligatoria.";
}
if (empty($ora)) {
    $errori[] = "L'ora è obbligatoria.";
}
if ($durata <= 0) {
    $errori[] = "La durata deve essere maggiore di zero.";
}

// Se ci sono errori base, mi fermo subito
if (!empty($errori)) {
    echo "<div class='error-message'>";
    echo "<h4>⚠️ Errori:</h4><ul>";
    foreach ($errori as $errore) {
        echo "<li>" . htmlspecialchars($errore) . "</li>";
    }
    echo "</ul></div>";
    exit;
}

// =============================================
// CONTROLLO 1: la data non è nel futuro
// =============================================
$oggi = date('Y-m-d');
if ($data > $oggi) {
    echo "<div class='error-message'>";
    echo "<h4>⚠️ Data non valida</h4>";
    echo "<p>La data della telefonata (<strong>" . htmlspecialchars($data) . "</strong>) non può essere nel futuro.</p>";
    echo "</div>";
    $conn->close();
    exit;
}

// =============================================
// CONTROLLO 2: il numero esiste nel contratto?
// =============================================
$sql_contratto = "SELECT dataAttivazione FROM ContrattoTelefonico WHERE numero = ?";
$stmt_contratto = $conn->prepare($sql_contratto);
if (!$stmt_contratto) {
    echo "<div class='error-message'><h4>❌ Errore nella verifica contratto:</h4><p>" . htmlspecialchars($conn->error) . "</p></div>";
    exit;
}
$stmt_contratto->bind_param("s", $telefono);
$stmt_contratto->execute();
$result_contratto = $stmt_contratto->get_result();

if ($result_contratto->num_rows === 0) {
    echo "<div class='error-message'>";
    echo "<h4>⚠️ Numero non trovato</h4>";
    echo "<p>Il numero <strong>" . htmlspecialchars($telefono) . "</strong> non è associato a nessun contratto.</p>";
    echo "<p>Controlla che il numero sia corretto.</p>";
    echo "</div>";
    $stmt_contratto->close();
    $conn->close();
    exit;
}

$contratto = $result_contratto->fetch_assoc();
$dataAttivazioneContratto = $contratto['dataAttivazione'];
$stmt_contratto->close();

// CONTROLLO 3: la data telefonata non precede l'attivazione del contratto
if ($data < $dataAttivazioneContratto) {
    echo "<div class='error-message'>";
    echo "<h4>⚠️ Data antecedente all'attivazione del contratto</h4>";
    echo "<p>La data della telefonata (<strong>" . htmlspecialchars($data) . "</strong>) è precedente alla data di attivazione del contratto (<strong>" . htmlspecialchars($dataAttivazioneContratto) . "</strong>).</p>";
    echo "</div>";
    $conn->close();
    exit;
}

// =============================================
// CONTROLLO 4: la SIM associata al numero
// Cerca prima in SIMAttiva, poi in SIMDisattiva
// =============================================
$stato_sim      = null;
$dataAttivSIM   = null;
$dataDisattivSIM = null;

// Cerca in SIMAttiva
$sql_attiva = "SELECT dataAttivazione FROM SIMAttiva WHERE associataA = ?";
$stmt_attiva = $conn->prepare($sql_attiva);
if (!$stmt_attiva) {
    echo "<div class='error-message'><h4>❌ Errore verifica SIM attiva:</h4><p>" . htmlspecialchars($conn->error) . "</p></div>";
    exit;
}
$stmt_attiva->bind_param("s", $telefono);
$stmt_attiva->execute();
$result_attiva = $stmt_attiva->get_result();

if ($result_attiva->num_rows > 0) {
    $sim_row = $result_attiva->fetch_assoc();
    $stato_sim    = 'Attiva';
    $dataAttivSIM = $sim_row['dataAttivazione'];
}
$stmt_attiva->close();

// Se non trovata in SIMAttiva, cerca in SIMDisattiva
if ($stato_sim === null) {
    $sql_disattiva = "SELECT dataAttivazione, dataDisattivazione FROM SIMDisattiva WHERE eraAssociataA = ?";
    $stmt_disattiva = $conn->prepare($sql_disattiva);
    if (!$stmt_disattiva) {
        echo "<div class='error-message'><h4>❌ Errore verifica SIM disattivata:</h4><p>" . htmlspecialchars($conn->error) . "</p></div>";
        exit;
    }
    $stmt_disattiva->bind_param("s", $telefono);
    $stmt_disattiva->execute();
    $result_disattiva = $stmt_disattiva->get_result();

    if ($result_disattiva->num_rows > 0) {
        $sim_row         = $result_disattiva->fetch_assoc();
        $stato_sim       = 'Disattivata';
        $dataAttivSIM    = $sim_row['dataAttivazione'];
        $dataDisattivSIM = $sim_row['dataDisattivazione'];
    }
    $stmt_disattiva->close();
}

// Nessuna SIM trovata (né attiva né disattivata)
if ($stato_sim === null) {
    echo "<div class='error-message'>";
    echo "<h4>⚠️ Nessuna SIM associata</h4>";
    echo "<p>Il numero <strong>" . htmlspecialchars($telefono) . "</strong> non è associato a nessuna SIM attiva o disattivata.</p>";
    echo "<p>Controlla che il numero sia corretto.</p>";
    echo "</div>";
    $conn->close();
    exit;
}

// CONTROLLO 5: la data telefonata non precede l'attivazione della SIM
if ($data < $dataAttivSIM) {
    echo "<div class='error-message'>";
    echo "<h4>⚠️ Data antecedente all'attivazione della SIM</h4>";
    echo "<p>La data della telefonata (<strong>" . htmlspecialchars($data) . "</strong>) è precedente alla data di attivazione della SIM (<strong>" . htmlspecialchars($dataAttivSIM) . "</strong>).</p>";
    echo "</div>";
    $conn->close();
    exit;
}

// CONTROLLO 6: se la SIM è disattivata, la telefonata non può essere dopo la disattivazione
if ($stato_sim === 'Disattivata' && $data > $dataDisattivSIM) {
    echo "<div class='error-message'>";
    echo "<h4>⚠️ SIM già disattivata</h4>";
    echo "<p>La data della telefonata (<strong>" . htmlspecialchars($data) . "</strong>) è successiva alla data di disattivazione della SIM (<strong>" . htmlspecialchars($dataDisattivSIM) . "</strong>).</p>";
    echo "</div>";
    $conn->close();
    exit;
}

// =============================================
// INSERIMENTO NEL DATABASE
// =============================================

$sql = "INSERT INTO Telefonata (effettuataDa, data, ora, durata, costo) 
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "<div class='error-message'>";
    echo "<h4>❌ Errore nella preparazione:</h4>";
    echo "<p>" . htmlspecialchars($conn->error) . "</p>";
    echo "</div>";
    exit;
}

$stmt->bind_param("sssid", $telefono, $data, $ora, $durata, $costo);

if ($stmt->execute()) {
    // Successo!
    echo "<div class='success-message'>";
    echo "<h4>✅ Telefonata registrata con successo!</h4>";
    echo "<p><strong>Numero:</strong> " . htmlspecialchars($telefono) . " (SIM " . $stato_sim . ")</p>";
    echo "<p><strong>Data:</strong> " . htmlspecialchars($data) . " alle " . htmlspecialchars($ora) . "</p>";
    echo "<p><strong>Durata:</strong> " . $durata . " minuti</p>";
    echo "<p><strong>Costo:</strong> " . number_format($costo, 2, ',', '') . " €</p>";
    echo "</div>";
} else {
    echo "<div class='error-message'>";
    echo "<h4>❌ Errore durante l'inserimento:</h4>";
    echo "<p>" . htmlspecialchars($stmt->error) . "</p>";
    echo "</div>";
}

$stmt->close();
$conn->close();
?>