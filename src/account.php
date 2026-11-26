<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['kayttajanimi'], $_SESSION['rooli'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';
$account = null;

try {
    $conn = connectDatabase();
    $stmt = $conn->prepare('SELECT UserID, Nimi, Email, salasana FROM kayttajat WHERE Nimi = :nimi');
    $stmt->execute(['nimi' => $_SESSION['kayttajanimi']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = $_POST['csrf'] ?? '';
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
            $message = 'Istunto vanhentui. Päivitä sivu ja yritä uudelleen.';
        } elseif (!is_string($currentPassword) || !is_string($newPassword) || !is_string($confirmPassword)) {
            $message = 'Täytä kaikki salasanakentät.';
        } elseif (!password_verify($currentPassword, $account['salasana'])) {
            $message = 'Nykyinen salasana ei täsmää.';
        } elseif (strlen($newPassword) < 8) {
            $message = 'Uuden salasanan tulee olla vähintään 8 merkkiä pitkä.';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Uudet salasanat eivät täsmää.';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $conn->prepare('UPDATE kayttajat SET salasana = :salasana WHERE UserID = :id');
            $update->execute([
                'salasana' => $passwordHash,
                'id' => $account['UserID'],
            ]);
            $account['salasana'] = $passwordHash;
            $message = 'Salasana vaihdettiin.';
            $messageType = 'success';
        }
    }
} catch (PDOException $e) {
    error_log('Käyttäjätilin tietokantavirhe: ' . $e->getMessage());
    $message = 'Tilitietoja ei voitu ladata. Yritä myöhemmin uudelleen.';
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Oma tili | Taitaja TietoTesti</title>
    <link rel="stylesheet" href="site.css">
</head>
<body class="account-page">
    <header class="site-header">
        <a class="brand" href="index.php" aria-label="Taitaja TietoTesti, etusivu">
            <span class="brand-mark" aria-hidden="true">T</span>
            <span>Taitaja TietoTesti</span>
        </a>
        <nav class="site-nav" aria-label="Päänavigaatio">
            <a href="index.php">Etusivu</a>
            <a class="nav-current" href="account.php" aria-current="page">Oma tili</a>
            <?php if ($_SESSION['rooli'] === 'admin'): ?>
                <a href="admin.php">Admin-paneeli</a>
            <?php endif; ?>
            <a class="nav-action" href="logout.php">Kirjaudu ulos</a>
        </nav>
    </header>

    <main class="account-main">
        <p class="section-kicker">Käyttäjätili</p>
        <h1>Omat tiedot</h1>
        <p class="account-intro">Tarkista tilisi tiedot ja pidä salasanasi ajan tasalla.</p>

        <?php if ($message !== ''): ?>
            <p class="form-message <?= $messageType === 'success' ? 'form-message-success' : '' ?>" role="status">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if ($account): ?>
            <section class="account-details" aria-labelledby="details-title">
                <h2 id="details-title">Tilitiedot</h2>
                <dl>
                    <div><dt>Käyttäjänimi</dt><dd><?= htmlspecialchars($account['Nimi'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                    <div><dt>Sähköposti</dt><dd><?= htmlspecialchars($account['Email'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                    <div><dt>Käyttöoikeus</dt><dd><?= htmlspecialchars($_SESSION['rooli'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                </dl>
            </section>

            <section class="password-section" aria-labelledby="password-title">
                <h2 id="password-title">Vaihda salasana</h2>
                <form method="post" action="account.php" class="password-form">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <label for="current_password">Nykyinen salasana</label>
                    <input type="password" name="current_password" id="current_password" autocomplete="current-password" required>

                    <label for="new_password">Uusi salasana</label>
                    <input type="password" name="new_password" id="new_password" minlength="8" autocomplete="new-password" required>

                    <label for="confirm_password">Vahvista uusi salasana</label>
                    <input type="password" name="confirm_password" id="confirm_password" minlength="8" autocomplete="new-password" required>

                    <button class="button button-primary" type="submit">Päivitä salasana <span aria-hidden="true">↗</span></button>
                </form>
            </section>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <a class="brand brand-footer" href="index.php"><span class="brand-mark" aria-hidden="true">T</span><span>Taitaja TietoTesti</span></a>
        <p>Tietoa ja oivalluksia, yksi kysymys kerrallaan.</p>
        <a href="index.php">Etusivulle ↑</a>
    </footer>
</body>
</html>