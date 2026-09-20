<?php
/**
 * The 500 page.
 *
 * Rendered without the layout on purpose: whatever broke may be the database,
 * and the layout's header reads the market and the county list from it. A page
 * that needs a working database to say "something went wrong" cannot say it.
 */
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Something went wrong — Fix Listed</title>
<style>
  :root{color-scheme:light dark}
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#EDEFE9;color:#16201D;
       font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;padding:24px}
  @media (prefers-color-scheme:dark){body{background:#0D1412;color:#E4EAE5}}
  .box{max-width:460px;text-align:center}
  h1{font-family:Georgia,"Times New Roman",serif;font-weight:400;font-size:30px;margin:0 0 12px}
  p{margin:0 0 20px;opacity:.72;line-height:1.55}
  a{display:inline-block;background:#C79A3E;color:#0E1513;text-decoration:none;font-weight:600;
    padding:11px 20px;border-radius:3px}
</style>
</head>
<body>
  <div class="box">
    <h1>Something went wrong</h1>
    <p>This one is on us, not on you. It has been logged and we are looking at it. Try again in a
       moment.</p>
    <a href="/">Back to the home page</a>
  </div>
</body>
</html>
