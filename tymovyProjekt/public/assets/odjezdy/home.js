(function () {
    'use strict';
    var input = document.getElementById('q');
    var list = document.getElementById('results');
    if (!input || !list) return;

    var base = document.body.dataset.base;
    var timer = null;
    var seq = 0;

    function render(stops, query) {
        list.textContent = '';
        if (!stops.length) {
            var li = document.createElement('li');
            var span = document.createElement('span');
            span.style.cssText = 'display:block;padding:.7rem 1rem;color:var(--muted)';
            span.textContent = 'Pro „' + query + '“ nebylo nic nalezeno.';
            li.appendChild(span);
            list.appendChild(li);
            return;
        }
        stops.forEach(function (s) {
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.href = base + 'tabule/' + encodeURIComponent(s.stop_id);
            var name = document.createElement('span');
            name.textContent = s.stop_name;
            var id = document.createElement('small');
            id.textContent = s.stop_id;
            a.appendChild(name);
            a.appendChild(id);
            li.appendChild(a);
            list.appendChild(li);
        });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { list.textContent = ''; return; }
        timer = setTimeout(function () {
            var my = ++seq;
            fetch(base + 'api/zastavky?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) { if (my === seq) render(data.stops || [], q); })
                .catch(function () { /* síťová chyba – seznam necháme */ });
        }, 200);
    });
})();
