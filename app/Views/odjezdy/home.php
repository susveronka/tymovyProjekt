<?= $this->extend('odjezdy/layout') ?>

<?= $this->section('content') ?>
<section class="hero">
    <h1>Odjezdy autobusů a vlaků</h1>
    <p class="lead">Najděte zastávku a zobrazte deset nejbližších odjezdů, nebo je vyhledejte na mapě.</p>

    <?php if ($stopCount === 0): ?>
        <div class="notice">V databázi zatím nejsou žádné zastávky. Naimportujte GTFS příkazem <code>php spark gtfs:import</code>
            (nebo nejdřív <code>php spark gtfs:sample</code> pro ukázková data).</div>
    <?php else: ?>
        <form class="search" role="search" autocomplete="off" onsubmit="return false">
            <label for="q" class="sr">Název zastávky</label>
            <input id="q" type="search" placeholder="Název zastávky, např. náměstí" minlength="2" autofocus>
        </form>
        <ul id="results" class="results" aria-live="polite"></ul>
        <p class="muted"><?= number_format($stopCount, 0, ',', ' ') ?> zastávek v databázi · <a href="<?= base_url('mapa') ?>">Otevřít mapu</a></p>
    <?php endif; ?>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/odjezdy/home.js') ?>"></script>
<?= $this->endSection() ?>
