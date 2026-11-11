<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Odjezdové tabule</title>
  <style>
    body { margin: 0; font-family: Arial, sans-serif; background: #f0f0f0; }
    header { background: #0b2545; padding: 12px 20px; }
    header a { color: white; text-decoration: none; margin-right: 20px; }
    main { max-width: 900px; margin: 20px auto; padding: 0 16px; }
    .tabule { background: #0b2545; color: white; padding: 16px; border-radius: 8px; }
    .tabule table { width: 100%; font-size: 1.3em; border-collapse: collapse; }
    .tabule td { padding: 8px; border-bottom: 1px solid #355; }
    .cas { color: #ffd166; font-weight: bold; }
    .karta { background: white; padding: 16px; margin-top: 16px; border-radius: 8px; }
    #mapa { height: 70vh; }
    .qr-mrizka { display: flex; flex-wrap: wrap; gap: 16px; }
    .qr-polozka { background: white; border: 1px solid #ccc; padding: 12px; text-align: center; width: 240px; }
    @media print { header, .bez-tisku { display: none; } }
  </style>
  <?= $this->renderSection('hlavicka') ?>
</head>
<body>
  <header>
    <a href="<?= base_url('admin') ?>">Administrace</a>
    <a href="<?= base_url('mapa') ?>">Mapa</a>
  </header>
  <main>
    <?= $this->renderSection('obsah') ?>
  </main>
  <?= $this->renderSection('skripty') ?>
</body>
</html>
