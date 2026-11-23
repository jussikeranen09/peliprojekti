<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <title>PeliProjekti</title>
</head>
<body>
    <h1>PeliProjekti</h1>
    <p>Tteksti</p>
    <p>teksti2</p>
    <ul class="menu">
    <?php if (isset($_SESSION['kayttajanimi'])): ?>
        <li>Kirjautuneena: <?= htmlspecialchars($_SESSION['kayttajanimi'], ENT_QUOTES, 'UTF-8') ?></li>
        <?php if ($_SESSION['rooli'] === 'admin'): ?>
            <li><a href="admin.php">Admin-paneeli</a></li>
        <?php endif; ?>
        <li><a href="logout.php">Kirjaudu ulos</a></li>
    <?php else: ?>
        <li><a href="login.php">Kirjaudu</a></li>
        <li><a href="signup.php">Luo tili</a></li>
    <?php endif; ?>
    </ul>
</body>
</html>
