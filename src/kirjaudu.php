<html>
    <head>
        <meta charset="UTF-8">
        <title>Kirjautuminen</title>
    </head>
    <body>
        <?php
        require_once __DIR__ . '/db.php';

        $message = 'Kirjautuminen epäonnistui. Tarkista käyttäjänimi ja salasana.';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $kayttajanimi = trim($_POST['kayttajanimi'] ?? '');
            $salasana = $_POST['salasana'] ?? '';

            try {
                $conn = connectDatabase();
                $stmt = $conn->prepare('SELECT salasana FROM kayttajat WHERE Nimi = :nimi');
                $stmt->execute(['nimi' => $kayttajanimi]);
                $account = $stmt->fetch();

                if ($account && password_verify($salasana, $account['salasana'])) {
                    $message = 'Kirjautuminen onnistui';
                }
            } catch (PDOException $e) {
                $message = 'Tietokantayhteys epäonnistui.';
            }
        }
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        ?>
        <p><a href="login.php">Takaisin kirjautumiseen</a></p>
        <p><a href="signup.php">Luo uusi tili</a></p>
    </body>
</html>