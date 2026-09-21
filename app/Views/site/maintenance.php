<?php
/**
 * The page a visitor gets while the site is down.
 *
 * Deliberately self-contained: inline styles, system-stack fallbacks, no
 * stylesheet, no script, no image, no database. This is the page that has to
 * render when nothing else will, so it depends on nothing that could be the
 * reason the site is down in the first place.
 *
 * @var string $message @var string $email
 */
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Back shortly — Fix Listed</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Marcellus&family=Hanken+Grotesk:wght@300..700&display=swap">
<style>
  :root{
    --paper:#EDEFE9; --paper-3:#FFFFFF; --ink:#0E1513;
    --text:#16201D; --text-2:#4E5C57; --line:#D6DACF;
    --brass:#8A6115; --brass-3:#DCB25C;
    --sans:"Hanken Grotesk",-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;
    --serif:"Marcellus",Georgia,"Times New Roman",serif;
  }
  *{box-sizing:border-box}
  body{
    margin:0; min-height:100vh; background:var(--paper); color:var(--text);
    font:16px/1.65 var(--sans); -webkit-font-smoothing:antialiased;
    display:grid; place-items:center; padding:24px;
  }
  .card{
    max-width:520px; width:100%; background:var(--paper-3);
    border:1px solid var(--line); border-radius:6px; padding:44px 40px;
    box-shadow:0 1px 2px rgba(14,21,19,.05), 0 24px 48px -20px rgba(14,21,19,.22);
  }
  .logo{
    font-family:var(--serif); font-size:26px; letter-spacing:.01em;
    color:var(--ink); margin-bottom:28px;
  }
  .logo i{font-style:normal; color:var(--brass)}
  h1{font-family:var(--serif); font-size:30px; font-weight:400; margin:0 0 14px; letter-spacing:-.01em}
  p{margin:0 0 14px; color:var(--text-2)}
  p:last-child{margin-bottom:0}
  a{color:var(--brass); text-decoration:underline; text-underline-offset:2px}
  .rule{height:1px; background:var(--line); margin:28px 0 22px}
  .tiny{font-size:13.5px; color:var(--text-2)}
  @media (max-width:520px){ .card{padding:32px 24px} h1{font-size:25px} }
</style>
</head>
<body>
  <main class="card">
    <div class="logo">Fix <i>Listed</i></div>
    <h1>Back shortly</h1>
    <p><?= e($message) ?></p>
    <div class="rule"></div>
    <p class="tiny">
      Nothing you have posted is affected — jobs, quotes and listings are all
      exactly where you left them.
      <?php if ($email !== ''): ?>
        If you need something urgently, email
        <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.
      <?php endif; ?>
    </p>
  </main>
</body>
</html>
