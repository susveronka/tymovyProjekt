<?php
/**
 * @var array $stop
 * @var string $url
 * @var array $week
 */
$local = (bool) preg_match('~//(localhost|127\.0\.0\.1|\[::1\]|[^/]+\.(test|local))(:|/|$)~i', $url);
?>
<?= $this->extend('odjezdy/layout') ?>

<?= $this->section('content') ?>
<p class="crumb"><a href="<?= base_url('admin') ?>">← Zpět na seznam</a></p>
<h1 class="page-title"><?= esc($stop['stop_name']) ?></h1>
<p class="muted mono">ID zastávky: <?= esc($stop['stop_id']) ?></p>

<div class="two">
    <section class="card">
        <h2>QR kód odjezdové tabule</h2>
        <div class="qr-box"><canvas id="qr" data-url="<?= esc($url, 'attr') ?>" data-name="<?= esc($stop['stop_name'], 'attr') ?>" width="320" height="320"></canvas></div>
        <p class="url"><a href="<?= esc($url, 'attr') ?>" target="_blank" rel="noopener"><?= esc($url) ?></a></p>
        <?php if ($local): ?>
            <div class="notice">Adresa míří na lokální server. Pro QR kódy k vylepení nastavte v <code>.env</code>
                <code>odjezdy.publicBaseUrl</code> na veřejnou adresu webu.</div>
        <?php endif; ?>
        <div class="btn-row">
            <button class="btn" type="button" id="dl-png">Stáhnout PNG</button>
            <a class="btn secondary" href="<?= base_url('admin/qr') . '?' . http_build_query(['ids' => [$stop['stop_id']]]) ?>" target="_blank" rel="noopener">Verze k tisku</a>
        </div>
        <p class="muted">QR kód vede na adresu tabule, která při každém otevření spočítá odjezdy pro aktuální čas.</p>
    </section>

    <section class="card">
        <h2>Odjezdy v týdnu <?= esc($week['weekStart']) ?> – <?= esc($week['weekEnd']) ?></h2>
        <div class="chart-box"><canvas id="week-chart" role="img" aria-label="Sloupcový graf počtu odjezdů v jednotlivých dnech týdne"></canvas></div>
        <script type="application/json" id="week-data"><?= json_encode($week, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    </section>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/odjezdy/lib/qr-creator/qr-creator.min.js') ?>"></script>
<script src="<?= base_url('assets/odjezdy/lib/chartjs/chart.umd.js') ?>"></script>
<script src="<?= base_url('assets/odjezdy/week-chart.js') ?>"></script>
<script src="<?= base_url('assets/odjezdy/qr.js') ?>"></script>
<?= $this->endSection() ?>
