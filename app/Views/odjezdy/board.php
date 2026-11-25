<?php
/**
 * @var array $stop
 * @var array $departures
 * @var array $week
 * @var \DateTimeImmutable $now
 * @var string $cas
 */
$apiUrl = base_url('api/odjezdy/' . rawurlencode($stop['stop_id']));
$tz     = config('Odjezdy')->timezone;
$mapUrl = ($stop['stop_lat'] !== null)
    ? base_url('mapa') . '?' . http_build_query(['lat' => $stop['stop_lat'], 'lon' => $stop['stop_lon'], 'open' => $stop['stop_id']])
    : null;
?>
<?= $this->extend('odjezdy/layout') ?>

<?= $this->section('content') ?>
<section class="board"
         data-api="<?= esc($apiUrl, 'attr') ?>"
         data-now="<?= $now->getTimestamp() ?>"
         data-tz="<?= esc($tz, 'attr') ?>"
         data-fixed="<?= $cas !== '' ? '1' : '0' ?>">
    <header class="board-head">
        <div>
            <p class="eyebrow">Odjezdy ze zastávky</p>
            <h1><?= esc($stop['stop_name']) ?></h1>
        </div>
        <div class="clock" id="clock" aria-label="Aktuální čas"><?= $now->format('H:i:s') ?></div>
    </header>

    <table class="dep">
        <thead>
        <tr><th>Odjezd</th><th>Linka</th><th>Směr</th><th class="r">Za</th></tr>
        </thead>
        <tbody id="dep-body">
        <?php if (! $departures): ?>
            <tr><td colspan="4" class="empty">V nejbližších dnech odtud nic nejede.</td></tr>
        <?php endif; ?>
        <?php foreach ($departures as $d): ?>
            <tr>
                <td class="t"><?= esc($d['time']) ?><?php if ($d['dayLabel'] !== ''): ?> <small><?= esc($d['dayLabel']) ?></small><?php endif; ?></td>
                <td><span class="badge badge-<?= esc($d['type'], 'attr') ?>"><?= esc($d['line']) ?></span></td>
                <td class="to"><?= esc($d['headsign']) ?></td>
                <td class="r eta"><?= esc($d['eta']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="board-foot">
        <span id="updated"></span>
        <?php if ($mapUrl): ?><a href="<?= esc($mapUrl, 'attr') ?>">Zobrazit na mapě</a><?php endif; ?>
    </p>
</section>

<section class="card">
    <h2>Počet odjezdů v tomto týdnu</h2>
    <p class="muted"><?= esc($week['weekStart']) ?> – <?= esc($week['weekEnd']) ?> · dnešek je zvýrazněn</p>
    <div class="chart-box"><canvas id="week-chart" role="img" aria-label="Sloupcový graf počtu odjezdů v jednotlivých dnech týdne"></canvas></div>
    <script type="application/json" id="week-data"><?= json_encode($week, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/odjezdy/lib/chartjs/chart.umd.js') ?>"></script>
<script src="<?= base_url('assets/odjezdy/week-chart.js') ?>"></script>
<script src="<?= base_url('assets/odjezdy/board.js') ?>"></script>
<?= $this->endSection() ?>
