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
                $kysymykset = is_array($annetutKysymykset)
                    ? array_values(array_filter(array_map(
                        static fn ($kysymys) => is_string($kysymys) ? trim($kysymys) : '',
                        $annetutKysymykset
                    ), static fn ($kysymys) => $kysymys !== ''))
                    : [];
                $sallitutKategoriat = array_map('intval', array_column($kategoriat, 'kategoriaID'));

                if (preg_match('/^.{1,50}$/us', $visaNimi) !== 1 || !$kategoriaID || !in_array($kategoriaID, $sallitutKategoriat, true)) {
                    $message = 'Anna visalle enintään 50 merkin nimi ja valitse kategoria.';
                } elseif ($kysymykset === [] || count(array_filter($kysymykset, static fn ($kysymys) => preg_match('/^.{1,200}$/us', $kysymys) !== 1)) > 0) {
                    $message = 'Lisää vähintään yksi kysymys. Kysymys voi olla enintään 200 merkkiä.';
                } else {
                    $stmt = $conn->prepare('INSERT INTO Tietovisat (VisaNimi, Kysymys, kategoriaID) VALUES (:visa_nimi, :kysymys, :kategoria_id)');
                    $conn->beginTransaction();
                    foreach ($kysymykset as $kysymys) {
                        $stmt->execute([
                            'visa_nimi' => $visaNimi,
                            'kysymys' => $kysymys,
                            'kategoria_id' => $kategoriaID,
                        ]);
                    }
                    $conn->commit();
                    $message = 'Visa tallennettiin (' . count($kysymykset) . ' kysymystä).';
                    $kysymykset = [];
                }
            }
        }
    }

    $visat = $conn->query(
        'SELECT t.VisaNimi, t.Kysymys, t.kategoriaID, k.aihe AS kategoria
         FROM Tietovisat t
         JOIN Kategoriat k ON k.kategoriaID = t.kategoriaID
         ORDER BY t.VisaNimi, t.visaID'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $message = 'Tietokantavirhe. Yritä uudelleen.';
    $visat = [];
}

$visaryhmat = [];
foreach ($visat as $item) {
    $ryhmaAvain = $item['VisaNimi'] . '\0' . $item['kategoriaID'];
    if (!isset($visaryhmat[$ryhmaAvain])) {
        $visaryhmat[$ryhmaAvain] = [
            'nimi' => $item['VisaNimi'],
            'kategoria' => $item['kategoria'],
            'kysymykset' => [],
        ];
    }
    $visaryhmat[$ryhmaAvain]['kysymykset'][] = $item['Kysymys'];
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
            <?php foreach ($kysymykset ?: [''] as $index => $kysymys): ?>
                <p>
                    <label for="kysymys_<?= $index + 1 ?>">Kysymys <?= $index + 1 ?></label><br>
                    <input type="text" name="kysymykset[]" id="kysymys_<?= $index + 1 ?>" maxlength="200" value="<?= htmlspecialchars($kysymys, ENT_QUOTES, 'UTF-8') ?>" required>
                </p>
            <?php endforeach; ?>
        </div>
        <p><button type="button" id="lisaa-kysymys">Lisää kysymys</button></p>
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
                        <li><?= htmlspecialchars($kysymys, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
    <script>
        document.getElementById('lisaa-kysymys').addEventListener('click', () => {
            const container = document.getElementById('kysymykset');
            const index = container.querySelectorAll('input').length + 1;
            const field = document.createElement('p');
            const label = document.createElement('label');
            const input = document.createElement('input');
            label.htmlFor = `kysymys_${index}`;
            label.textContent = `Kysymys ${index}`;
            input.type = 'text';
            input.name = 'kysymykset[]';
            input.id = `kysymys_${index}`;
            input.maxLength = 200;
            input.required = true;
            field.append(label, document.createElement('br'), input);
            container.append(field);
        });
    </script>
</body>
</html>
