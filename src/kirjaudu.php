<?php
session_start();
require_once __DIR__ . '/db.php';

$message = 'Kirjautuminen epäonnistui. Tarkista käyttäjänimi ja salasana.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kayttajanimi = trim($_POST['kayttajanimi'] ?? '');
    $salasana = $_POST['salasana'] ?? '';

    if ($kayttajanimi === '' || $salasana === '') {
        $message = 'Täytä käyttäjänimi ja salasana.';
    } else {
        try {
            $conn = connectDatabase();
            $stmt = $conn->prepare('SELECT Nimi, salasana, rooli FROM kayttajat WHERE Nimi = :nimi');
            $stmt->execute(['nimi' => $kayttajanimi]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($account && password_verify($salasana, $account['salasana'])) {
                session_regenerate_id(true);
                $_SESSION['kayttajanimi'] = $account['Nimi'];
                $_SESSION['rooli'] = $account['rooli'];

                if ($account['rooli'] === 'admin') {
                    header('Location: admin.php');
                    exit;
                }

                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            $message = 'Tietokantayhteys epäonnistui.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kirjautuminen | Taitaja TietoTesti</title>
    <link rel="stylesheet" href="site.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">T</span><span>Taitaja TietoTesti</span></a>
        <nav class="site-nav" aria-label="Päänavigaatio">
            <a href="index.php">Etusivu</a>
            <a href="signup.php">Luo tili</a>
        </nav>
    </header>
    <main class="auth-main">
        <section class="auth-intro">
            <p class="section-kicker">Opiskele, kertaa, oivalla</p>
            <h1>Kirjautuminen</h1>
            <p>Tarkista tunnuksesi ja yritä uudelleen.</p>
        </section>
        <section class="auth-panel">
            <p class="form-message" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
            <p><a class="button button-primary" href="login.php">Takaisin kirjautumiseen</a></p>
            <p><a href="signup.php">Luo uusi pelaajatili</a></p>
        </section>
    </main>
</body>
</html>