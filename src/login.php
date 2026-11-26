<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kirjaudu | Taitaja TietoTesti</title>
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
            <h1>Tervetuloa takaisin.</h1>
            <p>Kirjaudu sisään ja jatka kouluaineiden kertaamista omalla tililläsi.</p>
        </section>
        <section class="auth-panel" aria-labelledby="login-title">
            <h2 id="login-title">Kirjaudu</h2>
            <form action="kirjaudu.php" method="post" class="auth-form">
                <label for="kayttajanimi">Käyttäjänimi</label>
                <input type="text" name="kayttajanimi" id="kayttajanimi" autocomplete="username" required>
                <label for="salasana">Salasana</label>
                <input type="password" name="salasana" id="salasana" autocomplete="current-password" required>
                <button type="submit">Kirjaudu sisään</button>
            </form>
            <p>Ei vielä tiliä? <a href="signup.php">Luo pelaajatili</a></p>
        </section>
    </main>
</body>
</html>