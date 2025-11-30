<?php
date_default_timezone_set('Europe/Istanbul');

$host = "localhost";      
$kullanici = "root";      
$sifre = "";              
$veritabani = "proje_risk";  

$conn = new mysqli($host, $kullanici, $sifre, $veritabani);


if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}

?>
