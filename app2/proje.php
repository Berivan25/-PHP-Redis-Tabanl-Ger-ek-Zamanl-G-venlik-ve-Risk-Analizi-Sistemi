Kod Örneği: PHP ile Formdan Veri Alma ve Güvenli Gösterim
index.html

<form action="form.php" method="POST">
  <label for="username">Kullanıcı Adı:</label>
  <input type="text" name="username" id="username" required>
  <button type="submit">Gönder</button>
</form>

php

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = htmlspecialchars($_POST['username']); 
    echo "Hoş geldin, " . $username . "!";
}
?>

