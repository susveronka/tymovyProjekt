<?php
/**
 * @var array|null $bounds
 * @var int $popup
 */
?>
<?= $this->extend('odjezdy/layout') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/odjezdy/lib/leaflet/leaflet.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<h1 class="page-title">Mapa zastávek</h1>
<p class="muted">Klikněte na zastávku – zobrazí se její nejbližší odjezdy.</p>
<div class="map-wrap">
    <div id="map"
         data-bounds="<?= esc(json_encode($bounds), 'attr') ?>"
         data-popup="<?= (int) $popup ?>"></div>
    <div id="map-hint" class="map-hint" hidden>Zastávek je v tomto výřezu příliš mnoho – přibližte mapu.</div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/odjezdy/lib/leaflet/leaflet.js') ?>"></script>
<script src="<?= base_url('assets/odjezdy/map.js') ?>"></script>
<?= $this->endSection() ?>
