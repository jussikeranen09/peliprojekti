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

try {
    $conn = connectDatabase();
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS kysymykset (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kysymys VARCHAR(500) NOT NULL,
            vastaus VARCHAR(500) NOT NULL,
            luotu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
            $message = 'Istunto vanhentui. Päivitä sivu ja yritä uudelleen.';
        } else {
            $action = $_POST['action'] ?? '';

            if ($action === 'create') {
                $kysymys = trim($_POST['kysymys'] ?? '');
                $vastaus = trim($_POST['vastaus'] ?? '');

                if ($kysymys === '' || $vastaus === '' || strlen($kysymys) > 500 || strlen($vastaus) > 500) {
                    $message = 'Kysymys ja vastaus ovat pakollisia, enintään 500 merkkiä.';
                } else {
                    $stmt = $conn->prepare('INSERT INTO kysymykset (kysymys, vastaus) VALUES (:kysymys, :vastaus)');
                    $stmt->execute(['kysymys' => $kysymys, 'vastaus' => $vastaus]);
                    $message = 'Kysymys lisättiin.';
                }
            } elseif ($action === 'update') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $kysymys = trim($_POST['kysymys'] ?? '');
                $vastaus = trim($_POST['vastaus'] ?? '');

                if (!$id || $kysymys === '' || $vastaus === '' || strlen($kysymys) > 500 || strlen($vastaus) > 500) {
                    $message = 'Muokattavasta kysymyksestä puuttuu tiedot tai ne ovat liian pitkiä.';
                } else {
                    $stmt = $conn->prepare('UPDATE kysymykset SET kysymys = :kysymys, vastaus = :vastaus WHERE id = :id');
                    $stmt->execute(['kysymys' => $kysymys, 'vastaus' => $vastaus, 'id' => $id]);
                    $message = 'Kysymys päivitettiin.';
                }
            } elseif ($action === 'delete') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if ($id) {
                    $stmt = $conn->prepare('DELETE FROM kysymykset WHERE id = :id');
                    $stmt->execute(['id' => $id]);
                    $message = 'Kysymys poistettiin.';
                }
            }
        }
    }

    $kysymykset = $conn->query('SELECT id, kysymys, vastaus, luotu FROM kysymykset ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Tietokantavirhe. Yritä uudelleen.';
    $kysymykset = [];
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

    <h2>Lisää uusi kysymys</h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="create">
        <p><label for="kysymys">Kysymys</label></p>
        <input type="text" name="kysymys" id="kysymys" maxlength="500" required>
        <p><label for="vastaus">Oikea vastaus</label></p>
        <input type="text" name="vastaus" id="vastaus" maxlength="500" required>
        <p><button type="submit">Lisää kysymys</button></p>
    </form>

    <h2>Olemassa olevat kysymykset</h2>
    <?php if ($kysymykset === []): ?>
        <p>Kysymyksiä ei ole vielä lisättynä.</p>
    <?php else: ?>
        <?php foreach ($kysymykset as $item): ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                <p>
                    <label>Kysymys</label><br>
                    <input type="text" name="kysymys" maxlength="500" value="<?= htmlspecialchars($item['kysymys'], ENT_QUOTES, 'UTF-8') ?>" required>
                </p>
                <p>
                    <label>Vastaus</label><br>
                    <input type="text" name="vastaus" maxlength="500" value="<?= htmlspecialchars($item['vastaus'], ENT_QUOTES, 'UTF-8') ?>" required>
                </p>
                <button type="submit" name="action" value="update">Tallenna</button>
                <button type="submit" name="action" value="delete" onclick="return confirm('Poistetaanko kysymys?')">Poista</button>
            </form>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
