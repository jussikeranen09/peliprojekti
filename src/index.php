<?php
session_start();

$kategoriat = [];
$visaMaara = 0;
try {
    require_once __DIR__ . '/db.php';
    $conn = connectDatabase();
    $kategoriat = $conn->query(
        'SELECT k.kategoriaID, k.aihe, COUNT(DISTINCT t.VisaTunniste) AS visaMaara,
                COUNT(t.visaID) AS kysymysMaara
         FROM Kategoriat k
         LEFT JOIN Tietovisat t ON t.kategoriaID = k.kategoriaID
         GROUP BY k.kategoriaID, k.aihe
         ORDER BY k.aihe'
    )->fetchAll(PDO::FETCH_ASSOC);
    $visaMaara = array_sum(array_column($kategoriat, 'visaMaara'));
} catch (PDOException $e) {
    error_log('Etusivun tietokantavirhe: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Taitaja TietoTesti auttaa kertaamaan kouluaineita tietovisojen avulla ja huomaamaan, mitä kannattaa harjoitella lisää.">
    <title>Taitaja TietoTesti | Opiskele ja kertaa</title>
    <link rel="stylesheet" href="site.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="index.php" aria-label="Taitaja TietoTesti, etusivu">
            <span class="brand-mark" aria-hidden="true">T</span>
            <span>Taitaja TietoTesti</span>
        </a>
        <nav class="site-nav" aria-label="Päänavigaatio">
            <a href="#kategoriat">Kategoriat</a>
            <?php if (isset($_SESSION['kayttajanimi'], $_SESSION['rooli'])): ?>
                <a href="account.php">Oma tili</a>
                <?php if ($_SESSION['rooli'] === 'admin'): ?>
                    <a href="admin.php">Admin-paneeli</a>
                <?php endif; ?>
                <a class="nav-action" href="logout.php">Kirjaudu ulos</a>
            <?php else: ?>
                <a href="login.php">Kirjaudu</a>
                <a class="nav-action" href="signup.php">Luo tili</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-copy">
                <p class="eyebrow"><span class="eyebrow-dot"></span> Opiskele, kertaa, oivalla</p>
                <h1 id="hero-title">Taitaja TietoTesti</h1>
                <p class="hero-lead">Kertaa kouluaineita kysymys kerrallaan.</p>
                <p class="hero-description">Testaa, mitä jo osaat, ja huomaa, mihin kannattaa vielä palata. Taitaja TietoTesti tekee opiskelusta aktiivista lyhyillä tietovisoilla, joita voit tehdä omaan tahtiin.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="#kategoriat">Tutustu kategorioihin <span aria-hidden="true">↓</span></a>
                    <?php if (!isset($_SESSION['kayttajanimi'])): ?>
                        <a class="button button-quiet" href="signup.php">Luo pelaajatili <span aria-hidden="true">↗</span></a>
                    <?php else: ?>
                        <span class="welcome-note">Tervetuloa, <?= htmlspecialchars($_SESSION['kayttajanimi'], ENT_QUOTES, 'UTF-8') ?>.</span>
                    <?php endif; ?>
                </div>
            </div>
            <aside class="hero-board" aria-label="Pelin pikatiedot">
                <div class="board-topline"><span>PELIN TIEDOT</span><span class="live-dot"></span></div>
                <div class="board-count"><?= (int) $visaMaara ?><span> <?= (int) $visaMaara === 1 ? 'visa' : 'visaa' ?></span></div>
                <p>aiheissa, jotka pitävät uteliaisuuden hereillä.</p>
                <div class="board-rule"></div>
                <div class="board-bottom"><span>Valitse aihe.</span><span>Haasta tietosi.</span></div>
                <span class="board-stamp" aria-hidden="true">?</span>
            </aside>
            <div class="hero-index" aria-hidden="true">01 / TIEDONHALU</div>
        </section>

        <section class="intro" aria-labelledby="intro-title">
                <p class="section-kicker">Kertaa omaan tahtiin</p>
            <div class="intro-grid">
                <h2 id="intro-title">Testaa, mitä osaat.<br>Huomaa, mitä kerrata.</h2>
                <p>Jokainen visa kokoaa samaan aiheeseen liittyviä kysymyksiä. Neljästä vastausvaihtoehdosta yksi on oikea. Kysymykset auttavat huomaamaan, mitkä asiat ovat hallussa ja mitä kannattaa vielä harjoitella.</p>
            </div>
        </section>

        <section class="categories" id="kategoriat" aria-labelledby="categories-title">
            <div class="section-heading">
                <div>
                    <p class="section-kicker">Valitse kerrattava aine</p>
                    <h2 id="categories-title">Kategoriat</h2>
                </div>
                <p><?= count($kategoriat) ?> aihetta <span aria-hidden="true">↘</span></p>
            </div>
            <?php if ($kategoriat === []): ?>
                <p class="empty-state">Kategorioita ei saatu ladattua juuri nyt.</p>
            <?php else: ?>
                <div class="category-grid">
                    <?php foreach ($kategoriat as $index => $kategoria): ?>
                        <article class="category-item category-tone-<?= $index % 4 ?>">
                            <span class="category-number">0<?= $index + 1 ?></span>
                            <h3><?= htmlspecialchars($kategoria['aihe'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= (int) $kategoria['visaMaara'] ?> <?= (int) $kategoria['visaMaara'] === 1 ? 'visa' : 'visaa' ?> <span aria-hidden="true">·</span> <?= (int) $kategoria['kysymysMaara'] ?> <?= (int) $kategoria['kysymysMaara'] === 1 ? 'kysymys' : 'kysymystä' ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="closing-callout">
            <div>
                <p class="section-kicker">Kertaus alkaa tästä</p>
                <h2>Mikä aihe kaipaa<br>kertausta tänään?</h2>
            </div>
            <a class="button button-light" href="<?= isset($_SESSION['kayttajanimi']) ? 'account.php' : 'login.php' ?>">
                <?= isset($_SESSION['kayttajanimi']) ? 'Oma tili' : 'Kirjaudu sisään' ?> <span aria-hidden="true">↗</span>
            </a>
        </section>
    </main>

    <footer class="site-footer">
        <a class="brand brand-footer" href="index.php"><span class="brand-mark" aria-hidden="true">T</span><span>Taitaja TietoTesti</span></a>
        <p>Tietoa ja oivalluksia, yksi kysymys kerrallaan.</p>
        <a href="#hero-title">Takaisin ylös ↑</a>
    </footer>
</body>
</html>
