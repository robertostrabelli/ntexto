<?php
require '2-check.php';
define('check', true);
?>

<!DOCTYPE html>
<html lang="en-US">

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

    div.login {
      padding: 10px;
      margin: auto;
      max-width: 400px;
      text-align: right;
    }

    div.login p.img {
      text-align: center;
      font-size:150%;
    }

    div.login p img {
      height: 48px;
    }

    p{line-height:1.8;}

    p a.refresh-captcha img {
      height: 28px;
    }

    #bad-login {
      color: red;
      font-size: 1.4em;
    }
    fieldset{
  border:1px solid darkred;
  padding: 5px 10px;
}
legend {
  background-color: darkred;
  padding: 5px 10px;
}
input {background-color: darkred;color: #0f0;}
  </style>
</head>

<body> 
  <div class="login">
    <p class="img">nTexto</p>
  <?php if (isset($failed)) { ?>
      <p id="bad-login">Invalid captcha, user or password.</p>
    <?php } ?>

    <form id="login-form" method="post" target="_self">
      <fieldset id="login">
        <legend>Login</legend>
        <p><label for="user">User <input type="text" id="user" name="user" required></label></p>
        <p><label for="password">Password <input type="password" id="password" name="password" required></label></p>
        <p><label for="captcha">Write the letters <input type="text" id="captcha" name="captcha_challenge" pattern="[A-Z]{6}"></label></p>
         <p><img src="captcha.php" alt="CAPTCHA" class="captcha-image"> <a href="#" class="refresh-captcha"><img src="reload.png" alt="refresh"></a></p>

          <input type="submit" value="Login">
      </fieldset>
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