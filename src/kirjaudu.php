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
        <title>Kirjautuminen</title>
    </head>
    <body>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <p><a href="login.php">Takaisin kirjautumiseen</a></p>
        <p><a href="signup.php">Luo uusi tili</a></p>
    </body>
</html>