<?php
/** @var string $title */
$loggedIn = (bool) session()->get('odj_admin');
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Odjezdy') ?> · Odjezdové tabule</title>
    <link rel="stylesheet" href="<?= base_url('assets/odjezdy/app.css') ?>">
    <?= $this->renderSection('head') ?>
</head>
<body data-base="<?= esc(base_url(), 'attr') ?>">
<header class="top">
    <a class="brand" href="<?= base_url() ?>"><span class="brand-mark" aria-hidden="true"></span>Odjezdové tabule</a>
    <nav>
        <a href="<?= base_url('mapa') ?>">Mapa</a>
        <a href="<?= base_url('admin') ?>">Správa</a>
        <?php if ($loggedIn): ?>
            <form method="post" action="<?= base_url('admin/logout') ?>" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="link">Odhlásit</button>
            </form>
        <?php endif; ?>
    </nav>
</header>

<main class="wrap">
    <?= $this->renderSection('content') ?>
</main>

<footer class="foot">
    Jízdní řády: <a href="https://data.jr.ggu.cz/" rel="noopener">JrUtil / data.jr.ggu.cz</a> (zdroj CIS JŘ, Ministerstvo dopravy).
    Zobrazen je plánovaný jízdní řád bez zpoždění v reálném čase.
</footer>
<?= $this->renderSection('scripts') ?>
</body>
</html>
