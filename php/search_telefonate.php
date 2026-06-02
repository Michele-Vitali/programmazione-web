<?php
// php/search_telefonate.php
require_once 'utils/dbconn.php';

$ricerca = isset($_GET['q']) ? trim($_GET['q']) : '';

// JOIN tra Telefonata e ContrattoTelefonico per avere il nominativo
$sql = "
    SELECT 
        t.id,
        t.effettuataDa,
        t.data,
        t.ora,
        t.durata,
        t.costo,
        c.nominativo
    FROM Telefonata t
    LEFT JOIN ContrattoTelefonico c ON t.effettuataDa = c.numero
    WHERE t.effettuataDa LIKE ?
       OR c.nominativo LIKE ?
    ORDER BY t.data DESC, t.ora DESC
";

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
        // Nome e cognome dal contratto, se non trovato mostro "Sconosciuto"
        $nominativo = !empty($row['nominativo']) ? $row['nominativo'] : 'Sconosciuto';
        
        // Formatto la data in formato italiano
        $data_italiana = date("d/m/Y", strtotime($row['data']));
        $ora_formattata = substr($row['ora'], 0, 5); // prendo solo HH:MM
        
        echo "<tr class='clickable-row row-chiamata' 
                  data-id-chiamata='" . htmlspecialchars($row['id']) . "'
                  data-nome='" . htmlspecialchars(explode(' ', $nominativo)[0] ?? '') . "'
                  data-cognome='" . htmlspecialchars(explode(' ', $nominativo)[1] ?? '') . "'
                  data-tel='" . htmlspecialchars($row['effettuataDa']) . "'
                  data-data='" . htmlspecialchars($row['data']) . "'
                  data-ora='" . htmlspecialchars($row['ora']) . "'
                  data-durata='" . htmlspecialchars($row['durata']) . "'
                  data-costo='" . htmlspecialchars($row['costo']) . "'>";
        
        echo "<td>" . htmlspecialchars($nominativo) . "</td>";
        echo "<td>" . htmlspecialchars($row['effettuataDa']) . "</td>";
        echo "<td>" . $data_italiana . " " . $ora_formattata . "</td>";
        echo "<td>" . $row['durata'] . "</td>";
        echo "<td>" . number_format($row['costo'], 2, ',', '') . " €</td>";
        echo "<td class='text-center action-buttons'>";
        echo "<button class='btn-action btn-edit' title='Modifica Chiamata'><i class='fa-solid fa-pen-to-square'></i></button>";
        echo "<button class='btn-action btn-delete' title='Elimina Chiamata'><i class='fa-solid fa-trash'></i></button>";
        echo "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6' class='text-center'>Nessuna telefonata trovata</td></tr>";
}

$stmt->close();
$conn->close();
?>