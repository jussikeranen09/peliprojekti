<html>
    <head>
        <meta charset="UTF-8">
        <title>Kirjautumissivu</title>
    </head>
    <body>
        <form action="kirjaudu.php" method="post">
            <p>Syötä nimi</p>
            <input type="text" name="kayttajanimi" id="kayttajanimi" autocomplete="username" required>
            <p>Syötä salasana</p>
            <input type="password" name="salasana" autocomplete="current-password" required>
            <input type="submit" name="lähetä">
        </form>
        <p>Ei vielä tiliä? <a href="signup.php">Luo tili</a></p>
    </body>
</html>