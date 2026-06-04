<?php
// php/search_telefonate.php
require_once 'utils/dbconn.php';

$ricerca   = isset($_GET['q'])    ? trim($_GET['q'])    : '';
$data_from = isset($_GET['from']) ? trim($_GET['from']) : '';
$data_to   = isset($_GET['to'])   ? trim($_GET['to'])   : '';

function normalizzaNumero($n) {
    return preg_replace('/[\s\+]/', '', $n);
}
$ricercaNorm = normalizzaNumero($ricerca);

$conditions = [];
$params     = [];
$types      = '';

if ($ricerca !== '') {
    $like     = '%' . $ricerca . '%';
    $likeNorm = '%' . $ricercaNorm . '%';
    $conditions[] = "(t.effettuataDa LIKE ? OR REPLACE(REPLACE(t.effettuataDa,' ',''),'+','') LIKE ? OR c.nominativo LIKE ?)";
    $params[] = $like;
    $params[] = $likeNorm;
    $params[] = $like;
    $types   .= 'sss';
}
if ($data_from !== '') {
    $conditions[] = "t.data >= ?";
    $params[] = $data_from;
    $types   .= 's';
}
if ($data_to !== '') {
    $conditions[] = "t.data <= ?";
    $params[] = $data_to;
    $types   .= 's';
}

$where = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "SELECT t.id, t.effettuataDa, t.data, t.ora, t.durata, t.costo, c.nominativo, c.tipo
        FROM Telefonata t
        LEFT JOIN ContrattoTelefonico c ON t.effettuataDa = c.numero
        {$where}
        ORDER BY t.data DESC, t.ora DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) { die("Errore: " . $conn->error); }

if (count($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $nominativo = !empty($row['nominativo']) ? $row['nominativo'] : 'Sconosciuto';
        $data_it    = date("d/m/Y", strtotime($row['data']));
        $ora_fmt    = substr($row['ora'], 0, 5);
        $tel        = htmlspecialchars($row['effettuataDa']);
        $nom_safe   = htmlspecialchars($nominativo);
        $tipo       = $row['tipo'] ?? '';

        // Formattazione costo in base al tipo contratto
        if ($tipo === 'consumo') {
            $costoFmt = htmlspecialchars($row['durata']) . ' min';
        } else {
            $costoFmt = number_format($row['costo'], 2, ',', '') . ' €';
        }

        echo "<tr class='clickable-row row-chiamata'
                  data-id-chiamata='" . htmlspecialchars($row['id']) . "'
                  data-nome='" . htmlspecialchars(explode(' ', $nominativo)[0] ?? '') . "'
                  data-cognome='" . htmlspecialchars(explode(' ', $nominativo)[1] ?? '') . "'
                  data-tel='{$tel}'
                  data-data='" . htmlspecialchars($row['data']) . "'
                  data-ora='" . htmlspecialchars($row['ora']) . "'
                  data-durata='" . htmlspecialchars($row['durata']) . "'
                  data-costo='" . htmlspecialchars($row['costo']) . "'
                  data-tipo='" . htmlspecialchars($tipo) . "'>";

        echo "<td>
                <span class='link-nominativo' title='Filtra contratti per questo nominativo'>{$nom_safe}</span>
                <button class='btn-copy' title='Copia nominativo' onclick='copyText(\"{$nom_safe}\", event)'><i class='fa-regular fa-copy'></i></button>
              </td>";
        echo "<td>
                <span class='link-tel' title='Filtra telefonate per questo numero'>{$tel}</span>
                <button class='btn-copy' title='Copia numero' onclick='copyText(\"{$tel}\", event)'><i class='fa-regular fa-copy'></i></button>
              </td>";
        echo "<td>{$data_it} {$ora_fmt}</td>";
        echo "<td>{$row['durata']}</td>";
        echo "<td>{$costoFmt}</td>";
        echo "<td class='text-center action-buttons'>
                <button class='btn-action btn-edit'   title='Modifica'><i class='fa-solid fa-pen-to-square'></i></button>
                <button class='btn-action btn-delete' title='Elimina'><i class='fa-solid fa-trash'></i></button>
              </td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6' class='text-center text-muted'><i class='fa-solid fa-circle-info'></i> Nessuna telefonata trovata</td></tr>";
}

$stmt->close();
$conn->close();
?>
