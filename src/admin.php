<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['kayttajanimi'], $_SESSION['rooli']) || $_SESSION['rooli'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$kysymykset = [];
$kategoriat = [];

try {
    $conn = connectDatabase();
    $kategoriat = $conn->query('SELECT kategoriaID, aihe FROM Kategoriat ORDER BY aihe')->fetchAll(PDO::FETCH_ASSOC);
    $kayttajaKysely = $conn->prepare('SELECT UserID FROM kayttajat WHERE Nimi = :nimi');
    $kayttajaKysely->execute(['nimi' => $_SESSION['kayttajanimi']]);
    $omistajaID = $kayttajaKysely->fetchColumn();
    if ($omistajaID === false) {
        throw new RuntimeException('Kirjautunutta käyttäjää ei löytynyt.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = $_POST['csrf'] ?? '';
        if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
            $message = 'Istunto vanhentui. Päivitä sivu ja yritä uudelleen.';
        } else {
            $action = $_POST['action'] ?? '';

            if ($action === 'create') {
                $annettuVisaNimi = $_POST['visa_nimi'] ?? '';
                $visaNimi = is_string($annettuVisaNimi) ? trim($annettuVisaNimi) : '';
                $kategoriaID = filter_var($_POST['kategoria_id'] ?? null, FILTER_VALIDATE_INT);
                $annetutKysymykset = $_POST['kysymykset'] ?? [];
                $kysymykset = [];
                if (is_array($annetutKysymykset)) {
                    foreach ($annetutKysymykset as $kysymys) {
                        if (!is_array($kysymys)) {
                            continue;
                        }
                        $vastaukset = is_array($kysymys['vastaukset'] ?? null) ? $kysymys['vastaukset'] : [];
                        $kysymykset[] = [
                            'teksti' => is_string($kysymys['teksti'] ?? null) ? trim($kysymys['teksti']) : '',
                            'vastaukset' => array_map(
                                static fn ($vastaus) => is_string($vastaus) ? trim($vastaus) : '',
                                array_replace([1 => '', 2 => '', 3 => '', 4 => ''], $vastaukset)
                            ),
                            'oikea' => filter_var($kysymys['oikea'] ?? null, FILTER_VALIDATE_INT),
                        ];
                    }
                }
                $sallitutKategoriat = array_map('intval', array_column($kategoriat, 'kategoriaID'));

                if (preg_match('/^.{1,50}$/us', $visaNimi) !== 1 || !$kategoriaID || !in_array($kategoriaID, $sallitutKategoriat, true)) {
                    $message = 'Anna visalle enintään 50 merkin nimi ja valitse kategoria.';
                } else {
                    $virheellinenKysymys = $kysymykset === [];
                    foreach ($kysymykset as $kysymys) {
                        if (preg_match('/^.{1,200}$/us', $kysymys['teksti']) !== 1
                            || count(array_filter($kysymys['vastaukset'], static fn ($vastaus) => preg_match('/^.{1,200}$/us', $vastaus) !== 1)) > 0
                            || !in_array($kysymys['oikea'], [1, 2, 3, 4], true)) {
                            $virheellinenKysymys = true;
                            break;
                        }
                    }

                    if ($virheellinenKysymys) {
                        $message = 'Jokainen kysymys tarvitsee neljä enintään 200 merkin vastausta ja yhden oikean vastauksen.';
                    } else {
                        $visaTunniste = bin2hex(random_bytes(16));
                        $stmt = $conn->prepare(
                            'INSERT INTO Tietovisat
                                (VisaNimi, Kysymys, kategoriaID, Vastaus1, Vastaus2, Vastaus3, Vastaus4, OikeaVastaus, OmistajaID, VisaTunniste)
                             VALUES
                                (:visa_nimi, :kysymys, :kategoria_id, :vastaus1, :vastaus2, :vastaus3, :vastaus4, :oikea_vastaus, :omistaja_id, :visa_tunniste)'
                        );
                        $conn->beginTransaction();
                        foreach ($kysymykset as $kysymys) {
                        $stmt->execute([
                            'visa_nimi' => $visaNimi,
                            'kysymys' => $kysymys['teksti'],
                            'kategoria_id' => $kategoriaID,
                            'vastaus1' => $kysymys['vastaukset'][1],
                            'vastaus2' => $kysymys['vastaukset'][2],
                            'vastaus3' => $kysymys['vastaukset'][3],
                            'vastaus4' => $kysymys['vastaukset'][4],
                            'oikea_vastaus' => $kysymys['oikea'],
                            'omistaja_id' => $omistajaID,
                            'visa_tunniste' => $visaTunniste,
                        ]);
                    }
                    $conn->commit();
                    $message = 'Visa tallennettiin (' . count($kysymykset) . ' kysymystä).';
                    $kysymykset = [];
                    }
                }
            } elseif ($action === 'delete') {
                $visaTunniste = $_POST['visa_tunniste'] ?? '';
                if (is_string($visaTunniste) && preg_match('/^[a-f0-9]{32}$/', $visaTunniste) === 1) {
                    $stmt = $conn->prepare('DELETE FROM Tietovisat WHERE VisaTunniste = :visa_tunniste AND OmistajaID = :omistaja_id');
                    $stmt->execute(['visa_tunniste' => $visaTunniste, 'omistaja_id' => $omistajaID]);
                    $message = $stmt->rowCount() > 0 ? 'Visa poistettiin.' : 'Visa löytyi jo poistettuna.';
                }
            }
        }
    }

    $visat = $conn->prepare(
        'SELECT t.VisaNimi, t.Kysymys, t.kategoriaID, t.VisaTunniste,
                t.Vastaus1, t.Vastaus2, t.Vastaus3, t.Vastaus4, t.OikeaVastaus,
                k.aihe AS kategoria
         FROM Tietovisat t
         JOIN Kategoriat k ON k.kategoriaID = t.kategoriaID
         WHERE t.OmistajaID = :omistaja_id AND t.VisaTunniste IS NOT NULL
         ORDER BY t.VisaNimi, t.visaID'
    );
    $visat->execute(['omistaja_id' => $omistajaID]);
    $visat = $visat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Admin-paneelin tietokantavirhe: ' . $e->getMessage());
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $message = 'Tietokantavirhe. Yritä uudelleen.';
    $visat = [];
} catch (RuntimeException $e) {
    $message = 'Kirjautunutta käyttäjää ei löytynyt tietokannasta.';
    $visat = [];
}

$visaryhmat = [];
foreach ($visat as $item) {
    $ryhmaAvain = $item['VisaTunniste'];
    if (!isset($visaryhmat[$ryhmaAvain])) {
        $visaryhmat[$ryhmaAvain] = [
            'nimi' => $item['VisaNimi'],
            'kategoria' => $item['kategoria'],
            'tunniste' => $item['VisaTunniste'],
            'kysymykset' => [],
        ];
    }
    $visaryhmat[$ryhmaAvain]['kysymykset'][] = $item;
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin-paneeli</title>
</head>
<body>
    <h1>Admin-paneeli</h1>
    <p>Kirjautuneena: <?= htmlspecialchars($_SESSION['kayttajanimi'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><a href="logout.php">Kirjaudu ulos</a></p>

    <?php if ($message !== ''): ?>
        <p><strong><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php endif; ?>

    <h2>Luo uusi visa</h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="create">
        <p>
            <label for="visa_nimi">Visan nimi</label><br>
            <input type="text" name="visa_nimi" id="visa_nimi" maxlength="50" required>
        </p>
        <p>
            <label for="kategoria_id">Kategoria</label><br>
            <select name="kategoria_id" id="kategoria_id" required>
                <option value="">Valitse kategoria</option>
                <?php foreach ($kategoriat as $kategoria): ?>
                    <option value="<?= (int) $kategoria['kategoriaID'] ?>"><?= htmlspecialchars($kategoria['aihe'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <div id="kysymykset">
            <?php foreach ($kysymykset ?: [['teksti' => '', 'vastaukset' => [1 => '', 2 => '', 3 => '', 4 => ''], 'oikea' => null]] as $index => $kysymys): ?>
                <fieldset class="kysymys">
                    <legend>Kysymys <?= $index + 1 ?></legend>
                    <p>
                        <label for="kysymys_<?= $index ?>">Kysymysteksti</label><br>
                        <input type="text" name="kysymykset[<?= $index ?>][teksti]" id="kysymys_<?= $index ?>" maxlength="200" value="<?= htmlspecialchars($kysymys['teksti'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </p>
                    <?php foreach ([1, 2, 3, 4] as $vastausNumero): ?>
                        <p>
                            <label for="vastaus_<?= $index ?>_<?= $vastausNumero ?>">Vastaus <?= $vastausNumero ?></label><br>
                            <input type="text" name="kysymykset[<?= $index ?>][vastaukset][<?= $vastausNumero ?>]" id="vastaus_<?= $index ?>_<?= $vastausNumero ?>" maxlength="200" value="<?= htmlspecialchars($kysymys['vastaukset'][$vastausNumero] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            <label>
                                <input type="radio" name="kysymykset[<?= $index ?>][oikea]" value="<?= $vastausNumero ?>" <?= (int) ($kysymys['oikea'] ?? 0) === $vastausNumero ? 'checked' : '' ?> required>
                                Oikea vastaus
                            </label>
                        </p>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" id="lisaa-kysymys">Lisää kysymys</button>
            <button type="button" id="poista-viimeinen-kysymys" disabled>Poista viimeinen kysymys</button>
        </p>
        <p><button type="submit">Tallenna visa</button></p>
    </form>

    <h2>Tallennetut visat</h2>
    <?php if ($visaryhmat === []): ?>
        <p>Visoja ei ole vielä tallennettu.</p>
    <?php else: ?>
        <?php foreach ($visaryhmat as $visa): ?>
            <section>
                <h3><?= htmlspecialchars($visa['nimi'], ENT_QUOTES, 'UTF-8') ?></h3>
                <p>Kategoria: <?= htmlspecialchars($visa['kategoria'], ENT_QUOTES, 'UTF-8') ?></p>
                <ol>
                    <?php foreach ($visa['kysymykset'] as $kysymys): ?>
                        <li>
                            <?= htmlspecialchars($kysymys['Kysymys'], ENT_QUOTES, 'UTF-8') ?>
                            <ul>
                                <?php foreach ([1, 2, 3, 4] as $vastausNumero): ?>
                                    <li>
                                        <?= htmlspecialchars($kysymys['Vastaus' . $vastausNumero], ENT_QUOTES, 'UTF-8') ?>
                                        <?= (int) $kysymys['OikeaVastaus'] === $vastausNumero ? '(oikea)' : '' ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <form method="post" onsubmit="return confirm('Poistetaanko tämä visa ja kaikki sen kysymykset?')">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="visa_tunniste" value="<?= htmlspecialchars($visa['tunniste'], ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit">Poista visa</button>
                </form>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
    <script>
        const kysymysContainer = document.getElementById('kysymykset');
        const poistaViimeinenPainike = document.getElementById('poista-viimeinen-kysymys');
        const paivitaPoistopainike = () => {
            poistaViimeinenPainike.disabled = kysymysContainer.querySelectorAll('fieldset').length <= 1;
        };

        document.getElementById('lisaa-kysymys').addEventListener('click', () => {
            const index = kysymysContainer.querySelectorAll('fieldset').length;
            const fieldset = document.createElement('fieldset');
            const legend = document.createElement('legend');
            const questionLabel = document.createElement('label');
            const questionInput = document.createElement('input');
            fieldset.className = 'kysymys';
            legend.textContent = `Kysymys ${index + 1}`;
            questionLabel.htmlFor = `kysymys_${index}`;
            questionLabel.textContent = 'Kysymysteksti';
            questionInput.type = 'text';
            questionInput.name = `kysymykset[${index}][teksti]`;
            questionInput.id = `kysymys_${index}`;
            questionInput.maxLength = 200;
            questionInput.required = true;
            fieldset.append(legend, questionLabel, document.createElement('br'), questionInput);

            for (let answerNumber = 1; answerNumber <= 4; answerNumber += 1) {
                const row = document.createElement('p');
                const answerLabel = document.createElement('label');
                const answerInput = document.createElement('input');
                const correctLabel = document.createElement('label');
                const correctInput = document.createElement('input');
                const inputId = `vastaus_${index}_${answerNumber}`;
                answerLabel.htmlFor = inputId;
                answerLabel.textContent = `Vastaus ${answerNumber}`;
                answerInput.type = 'text';
                answerInput.name = `kysymykset[${index}][vastaukset][${answerNumber}]`;
                answerInput.id = inputId;
                answerInput.maxLength = 200;
                answerInput.required = true;
                correctInput.type = 'radio';
                correctInput.name = `kysymykset[${index}][oikea]`;
                correctInput.value = answerNumber;
                correctInput.required = true;
                correctLabel.append(correctInput, ` Oikea vastaus`);
                row.append(answerLabel, document.createElement('br'), answerInput, correctLabel);
                fieldset.append(row);
            }
            kysymysContainer.append(fieldset);
            paivitaPoistopainike();
        });

        poistaViimeinenPainike.addEventListener('click', () => {
            const kysymysryhmat = kysymysContainer.querySelectorAll('fieldset');
            if (kysymysryhmat.length > 1) {
                kysymysryhmat[kysymysryhmat.length - 1].remove();
                paivitaPoistopainike();
            }
        });
    </script>
</body>
</html>
