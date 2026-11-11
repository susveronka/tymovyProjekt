<?= $this->extend('rozlozeni') ?>

<?= $this->section('hlavicka') ?>
<link rel="stylesheet" href="<?= base_url('assets/js/mapa.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('obsah') ?>
<div id="mapa" data-api="<?= base_url('api/mapa') ?>"></div>
<?= $this->endSection() ?>

<?= $this->section('skripty') ?>
<script src="<?= base_url('assets/js/mapa.js') ?>"></script>
<?= $this->endSection() ?>
