<?php /** @var list<array> $stops */ ?>
<?= $this->extend('odjezdy/layout') ?>

<?= $this->section('content') ?>
<div class="no-print toolbar">
    <a href="<?= base_url('admin') ?>">← Zpět na seznam</a>
    <button class="btn" type="button" onclick="window.print()">Vytisknout</button>
    <span class="muted"><?= count($stops) ?> QR kódů · vytiskněte a vylepte na zastávku</span>
</div>

<div class="qr-sheet">
    <?php foreach ($stops as $s): ?>
        <figure class="qr-card">
            <figcaption class="qr-title"><?= esc($s['stop_name']) ?></figcaption>
            <canvas data-qr data-url="<?= esc($s['url'], 'attr') ?>" width="360" height="360"></canvas>
            <p class="qr-hint">Naskenujte pro aktuální odjezdy</p>
            <p class="qr-url"><?= esc($s['url']) ?></p>
        </figure>
    <?php endforeach; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/odjezdy/lib/qr-creator/qr-creator.min.js') ?>"></script>
<script src="<?= base_url('assets/odjezdy/qr.js') ?>"></script>
<?= $this->endSection() ?>
