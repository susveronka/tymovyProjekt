<?= $this->extend('rozlozeni') ?>

<?= $this->section('obsah') ?>
<div class="tabule" id="tabule" data-api="<?= base_url('api') ?>" data-zastavka="<?= esc(rawurlencode($zastavka['stop_id'])) ?>">
  <h2><?= esc($zastavka['stop_name']) ?></h2>
  <table>
    <tbody id="radky"><tr><td>Načítám…</td></tr></tbody>
  </table>
</div>

<div class="karta">
  <h3>Počet odjezdů v tomto týdnu</h3>
  <canvas id="graf" height="110"></canvas>
</div>
<?= $this->endSection() ?>

<?= $this->section('skripty') ?>
<script src="<?= base_url('assets/js/tabule.js') ?>"></script>
<?= $this->endSection() ?>
