<?= $this->extend('odjezdy/layout') ?>

<?= $this->section('content') ?>
<section class="card narrow">
    <h1>Přihlášení do správy</h1>
    <?php if (! empty($error)): ?><div class="notice error" role="alert"><?= esc($error) ?></div><?php endif; ?>
    <form method="post" action="<?= base_url('admin/login') ?>" class="form">
        <?= csrf_field() ?>
        <label>Uživatelské jméno
            <input type="text" name="username" required autocomplete="username" autofocus>
        </label>
        <label>Heslo
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn">Přihlásit</button>
    </form>
    <p class="muted">Účet vytvoříte příkazem <code>php spark odjezdy:admin jméno</code>.</p>
</section>
<?= $this->endSection() ?>
