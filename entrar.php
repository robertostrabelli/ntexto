<?php
require '2-check.php';
define('check', true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta http-equiv="Content-Security-Policy" content="connect-src 'self'; font-src 'self'; frame-src 'self'; object-src 'none'; prefetch-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noimageindex,noarchive,nosnippet,nocache,nositelinkssearchbox,nopagereadaloud,notranslate,noodp,noydir,noyaca">
  <meta name="googlebot" content="none,noindex,nofollow,noimageindex,noarchive,nosnippet,nocache,nositelinkssearchbox,nopagereadaloud,notranslate,noodp,noydir,noyaca">
  <title>ntexto</title>
  <meta charset="utf-8">
  <meta name="apple-mobile-web-app-title" content="ntexto">
  <link rel="stylesheet" href="assets/css/style.css" type="text/css">
  <style>

    body{font-size:150%;}

    div.login {
      padding: 10px;
      margin: auto;
      max-width: 500px;
      text-align: right;
    }

    div.login p.img {
      text-align: center;   
    }

    div.login p img {
      height: 48px;
    }

    p a.refresh-captcha img {
      height: 28px;
    }

    #bad-login {
      color: red;
    }

input[type=text], input[type=password] {
  width: 100%;
  padding: 12px 20px;
  margin: 8px 0;
  display: inline-block;
  border: 1px solid #ccc;
  border-radius: 4px;
  box-sizing: border-box;
  font-size:20px;
}

input[type=submit] {  
  background-color: #4CAF50;
  color: white;
  padding: 14px 20px;
  margin: 8px 0;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size:25px;
}

input[type=submit]:hover {
  background-color: #45a049;
}
  </style>
</head>

<body> 
  <div class="login">
    <p class="img">nTexto</p>
  <?php if (isset($failed)) { ?>
      <p id="bad-login">Usuário, senha ou captcha errado.</p>
    <?php } ?>

    <form id="login-form" method="post" target="_self">

        <p><input placeholder="Usuário" type="text" id="user" name="user" required></p>
        <p><input placeholder="Senha" type="password" id="password" name="password" required></p>
        <p><input placeholder="Escreva as letras maiúsculas" type="text" id="captcha" name="captcha_challenge" pattern="[A-Z]{6}"></p>
         <p><img src="captcha.php" alt="CAPTCHA" class="captcha-image"> <a href="#" class="refresh-captcha"><img src="assets/img/reload.png" alt="refresh"></a></p>

         <p><input type="submit" value="Entrar"></p>
    </form>
  </div>

  <script>
    var refreshButton = document.querySelector(".refresh-captcha");
    refreshButton.onclick = function() {
      document.querySelector(".captcha-image").src = 'captcha.php?' + Date.now();
    }
  </script>

</body>

</html>