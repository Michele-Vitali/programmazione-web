<?php
// php/search_sim.php
require_once 'utils/dbconn.php';

$ricerca = isset($_GET['q']) ? trim($_GET['q']) : '';

function normalizzaNumero($n) {
    return preg_replace('/[\s\+]/', '', $n);
}
$ricercaNorm = normalizzaNumero($ricerca);

$like     = '%' . $ricerca . '%';
$likeNorm = '%' . $ricercaNorm . '%';

$sql = "
    SELECT codice, tipoSIM, associataA, dataAttivazione, 'Attiva' AS stato, NULL AS dataDisattivazione
    FROM SIMAttiva
    WHERE codice LIKE ? OR associataA LIKE ? OR REPLACE(REPLACE(associataA,' ',''),'+','') LIKE ?

    UNION

    SELECT codice, tipoSIM, NULL AS associataA, NULL AS dataAttivazione, 'Non Attiva' AS stato, NULL AS dataDisattivazione
    FROM SIMNonAttiva
    WHERE codice LIKE ?

    UNION

    SELECT codice, tipoSIM, eraAssociataA AS associataA, dataAttivazione, 'Disattivata' AS stato, dataDisattivazione
    FROM SIMDisattiva
    WHERE codice LIKE ? OR eraAssociataA LIKE ? OR REPLACE(REPLACE(eraAssociataA,' ',''),'+','') LIKE ?

    ORDER BY stato, codice
";

$stmt = $conn->prepare($sql);
if (!$stmt) { die("Errore: " . $conn->error); }
$stmt->bind_param("sssssss", $like, $like, $likeNorm, $like, $like, $like, $likeNorm);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sim     = htmlspecialchars($row['codice']);
        $assoc   = htmlspecialchars($row['associataA'] ?? '-');
        $hasLink = ($row['associataA'] && $row['associataA'] !== '-');

        echo "<tr class='clickable-row row-sim' data-sim='{$sim}'>";
        echo "<td>
                {$sim}
                <button class='btn-copy' title='Copia codice SIM' onclick='copyText(\"{$sim}\", event)'><i class='fa-regular fa-copy'></i></button>
              </td>";
        echo "<td>" . htmlspecialchars($row['tipoSIM']) . "</td>";

        // Stato con badge colore
        echo "<td>";
        if ($row['stato'] === 'Attiva')       echo "<span class='text-success-bold'>Attiva</span>";
        elseif ($row['stato'] === 'Disattivata') echo "<span class='text-danger'>Disattivata</span>";
        else                                    echo "<span class='text-warning'>Non Attiva</span>";
        echo "</td>";

        // Telefono associato — cliccabile come link verso contratti
        echo "<td>";
        if ($hasLink) {
            echo "<span class='link-tel' title='Vai al contratto'>{$assoc}</span>
                  <button class='btn-copy' title='Copia numero' onclick='copyText(\"{$assoc}\", event)'><i class='fa-regular fa-copy'></i></button>";
        } else {
            echo $assoc;
        }
        echo "</td>";

        echo "<td>" . htmlspecialchars($row['dataAttivazione'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['dataDisattivazione'] ?? '-') . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6' class='text-center text-muted'><i class='fa-solid fa-circle-info'></i> Nessuna SIM trovata per \"" . htmlspecialchars($ricerca) . "\"</td></tr>";
}

$stmt->close();
$conn->close();
?>
