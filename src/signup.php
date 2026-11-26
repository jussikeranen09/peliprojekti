<?php
require_once __DIR__ . '/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kayttajanimi = trim($_POST['kayttajanimi'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $salasana = $_POST['salasana'] ?? '';

    if ($kayttajanimi === '' || strlen($kayttajanimi) > 50) {
        $message = 'Käyttäjänimen tulee olla 1–50 merkkiä pitkä.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $message = 'Anna kelvollinen sähköpostiosoite.';
    } elseif (strlen($salasana) < 8) {
        $message = 'Salasanan tulee olla vähintään 8 merkkiä pitkä.';
    } else {
        try {
            $conn = connectDatabase();
            $stmt = $conn->prepare(
                'INSERT INTO kayttajat (Nimi, Email, salasana, rooli) VALUES (:nimi, :email, :salasana, :rooli)'
            );
            $stmt->execute([
                'nimi' => $kayttajanimi,
                'email' => $email,
                'salasana' => password_hash($salasana, PASSWORD_DEFAULT),
                'rooli' => 'pelaaja',
            ]);
            $message = 'Tili luotu. Voit nyt kirjautua sisään.';
        } catch (PDOException $e) {
            $message = $e->getCode() === '23000'
                ? 'Käyttäjänimi on jo käytössä.'
                : 'Tilin luominen epäonnistui tietokantavirheen vuoksi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Luo tili | Taitaja TietoTesti</title>
    <link rel="stylesheet" href="site.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">T</span><span>Taitaja TietoTesti</span></a>
        <nav class="site-nav" aria-label="Päänavigaatio">
            <a href="index.php">Etusivu</a>
            <a href="login.php">Kirjaudu</a>
        </nav>
    </header>
    <main class="auth-main">
        <section class="auth-intro">
            <p class="section-kicker">Opiskele, kertaa, oivalla</p>
            <h1>Tee kertaamisesta omaa.</h1>
            <p>Luo pelaajatili ja pääset mukaan tietovisojen opiskelumaailmaan.</p>
        </section>
        <section class="auth-panel" aria-labelledby="signup-title">
            <h2 id="signup-title">Luo pelaajatili</h2>
            <?php if ($message !== ''): ?>
                <p class="form-message" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <form action="signup.php" method="post" class="auth-form">
                <label for="kayttajanimi">Käyttäjänimi</label>
                <input type="text" name="kayttajanimi" id="kayttajanimi" maxlength="50" autocomplete="username" required>
                <label for="email">Sähköposti</label>
                <input type="email" name="email" id="email" maxlength="254" autocomplete="email" required>
                <label for="salasana">Salasana (vähintään 8 merkkiä)</label>
                <input type="password" name="salasana" id="salasana" minlength="8" autocomplete="new-password" required>
                <button type="submit">Luo tili</button>
            </form>
            <p>Onko sinulla jo tili? <a href="login.php">Kirjaudu sisään</a></p>
        </section>
    </main>
</body>
</html>