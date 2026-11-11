<?= $this->extend('rozlozeni') ?>

<?= $this->section('obsah') ?>
<div class="karta bez-tisku">
  <h3>Přidat zastávku</h3>
  <form method="get">
    <input name="q" value="<?= esc($hledani) ?>" placeholder="Název zastávky">
    <button>Hledat</button>
  </form>

  <?php foreach ($nalezene as $z): ?>
    <form method="post" action="<?= base_url('admin/pridat') ?>">
      <input type="hidden" name="stop_id" value="<?= esc($z['stop_id']) ?>">
      <?= esc($z['stop_name']) ?> (<?= esc($z['stop_id']) ?>)
      <button>Přidat</button>
    </form>
  <?php endforeach ?>
</div>

<p class="bez-tisku"><button onclick="window.print()">Vytisknout QR kódy</button></p>

<div class="qr-mrizka">
  <?php foreach ($vybrane as $v): ?>
    <div class="qr-polozka" data-url="<?= esc($v['url']) ?>">
      <strong><?= esc($v['stop_name']) ?></strong><br>
      <canvas></canvas><br>
      <a class="bez-tisku stahnout" href="#" download="qr-<?= esc($v['stop_id']) ?>.png">Stáhnout PNG</a>
      <a class="bez-tisku" href="<?= base_url('admin/smazat/' . $v['id']) ?>">Odebrat</a>
    </div>
  <?php endforeach ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('skripty') ?>
<script src="<?= base_url('assets/js/admin.js') ?>"></script>
<?= $this->endSection() ?>
