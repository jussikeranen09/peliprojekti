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
<html>
    <head>
        <meta charset="UTF-8">
        <title>Luo tili</title>
    </head>
    <body>
        <h1>Luo tili</h1>
        <?php if ($message !== ''): ?>
            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form action="signup.php" method="post">
            <p><label for="kayttajanimi">Käyttäjänimi</label></p>
            <input type="text" name="kayttajanimi" id="kayttajanimi" maxlength="50" autocomplete="username" required>
            <p><label for="email">Sähköposti</label></p>
            <input type="email" name="email" id="email" maxlength="254" autocomplete="email" required>
            <p><label for="salasana">Salasana (vähintään 8 merkkiä)</label></p>
            <input type="password" name="salasana" id="salasana" minlength="8" autocomplete="new-password" required>
            <p><button type="submit">Luo tili</button></p>
        </form>
        <p><a href="login.php">Takaisin kirjautumiseen</a></p>
    </body>
</html>