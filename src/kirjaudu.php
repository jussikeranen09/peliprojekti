<html>
    <body>
<?php
$salasana;
$kayttajanimi;
if (isset($_POST["kayttajanimi"])){
    $kayttajanimi = $_POST["kayttajanimi"];
}

if (isset($_POST['salasana'])){
    $salasana = $_POST["salasana"];
}

if  (($kayttajanimi = "opiskelija") && ($salasana = "salasana123")) {
    echo "Kirjautuminen onnistui";
}
else {
    echo "Kirjautuminen epäonnistui";
}
?>
<input type="button" value="Takaisin" onclick="window.location.href='kirjaudu.html'"/>

    </body>
</html>