<?php
/**
 * @var array $stops
 * @var string $q
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var int $maxQr
 * @var array|null $lastImport
 */
$pageUrl = static fn (int $p): string => base_url('admin') . '?' . http_build_query(['q' => $q, 'strana' => $p]);
?>
<?= $this->extend('odjezdy/layout') ?>

<?= $this->section('content') ?>
<h1 class="page-title">Správa zastávek</h1>

<div class="stats">
    <div><strong><?= number_format($total, 0, ',', ' ') ?></strong><span><?= $q !== '' ? 'nalezených zastávek' : 'zastávek v databázi' ?></span></div>
    <div>
        <?php if ($lastImport): ?>
            <strong><?= esc(date('j. n. Y H:i', strtotime($lastImport['imported_at']))) ?></strong>
            <span>poslední import (<?= esc($lastImport['source']) ?>)</span>
        <?php else: ?>
            <strong>–</strong><span>zatím nebyl žádný import</span>
        <?php endif; ?>
    </div>
</div>

<form method="get" action="<?= base_url('admin') ?>" class="search inline-search" role="search">
    <label for="q" class="sr">Hledat zastávku</label>
    <input id="q" type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Hledat podle názvu nebo ID">
    <button class="btn secondary" type="submit">Hledat</button>
</form>

<form method="get" action="<?= base_url('admin/qr') ?>" id="qr-form">
    <div class="toolbar">
        <label class="check"><input type="checkbox" id="check-all"> Vybrat vše na stránce</label>
        <button class="btn" type="submit">Vytvořit QR kódy k tisku</button>
        <span class="muted">najednou nejvýše <?= (int) $maxQr ?> zastávek</span>
    </div>

    <table class="grid">
        <thead><tr><th class="c"></th><th>Zastávka</th><th>ID</th><th></th></tr></thead>
        <tbody>
        <?php if (! $stops): ?>
            <tr><td colspan="4" class="empty">Nic nenalezeno.</td></tr>
        <?php endif; ?>
        <?php foreach ($stops as $s): ?>
            <tr>
                <td class="c"><input type="checkbox" name="ids[]" value="<?= esc($s['stop_id'], 'attr') ?>" aria-label="Vybrat <?= esc($s['stop_name'], 'attr') ?>"></td>
                <td><?= esc($s['stop_name']) ?></td>
                <td class="mono"><?= esc($s['stop_id']) ?></td>
                <td class="r actions">
                    <a href="<?= base_url('admin/zastavka/' . rawurlencode($s['stop_id'])) ?>">QR a graf</a>
                    <a href="<?= base_url('tabule/' . rawurlencode($s['stop_id'])) ?>" target="_blank" rel="noopener">Tabule</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</form>

<?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Stránkování">
        <?php if ($page > 1): ?><a href="<?= esc($pageUrl($page - 1), 'attr') ?>">← Předchozí</a><?php endif; ?>
        <span>Strana <?= $page ?> z <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="<?= esc($pageUrl($page + 1), 'attr') ?>">Další →</a><?php endif; ?>
    </nav>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    document.getElementById('check-all')?.addEventListener('change', function () {
        document.querySelectorAll('#qr-form input[name="ids[]"]').forEach(function (c) { c.checked = this.checked; }, this);
    });
</script>
<?= $this->endSection() ?>
