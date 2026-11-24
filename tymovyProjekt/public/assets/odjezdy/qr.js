/* QR kódy odjezdových tabulí (knihovna qr-creator, lokálně v assets/odjezdy/lib). */
(function () {
    'use strict';
    if (typeof QrCreator === 'undefined') return;

    function draw(canvas) {
        QrCreator.render({
            text: canvas.dataset.url,
            radius: 0,          // hranaté moduly – nejlepší čitelnost při tisku
            ecLevel: 'M',
            fill: '#000000',
            background: '#ffffff',
            size: canvas.width
        }, canvas);
    }

    document.querySelectorAll('canvas[data-url]').forEach(draw);

    var dl = document.getElementById('dl-png');
    var single = document.getElementById('qr');
    if (dl && single) {
        dl.addEventListener('click', function () {
            var a = document.createElement('a');
            var name = (single.dataset.name || 'zastavka').toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            a.download = 'qr-' + (name || 'zastavka') + '.png';
            a.href = single.toDataURL('image/png');
            a.click();
        });
    }
})();
