/* Odjezdová tabule: hodiny + automatické obnovování odjezdů každých 30 s. */
(function () {
    'use strict';
    var board = document.querySelector('.board');
    if (!board) return;

    var api = board.dataset.api;
    var tz = board.dataset.tz || 'Europe/Prague';
    var fixed = board.dataset.fixed === '1';        // ?cas=… – čas je zafixovaný, neobnovujeme
    var offset = parseInt(board.dataset.now, 10) * 1000 - Date.now();
    var body = document.getElementById('dep-body');
    var clock = document.getElementById('clock');
    var updated = document.getElementById('updated');

    var fmt = new Intl.DateTimeFormat('cs-CZ', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: tz });

    function tick() {
        clock.textContent = fmt.format(new Date(Date.now() + offset));
    }
    if (!fixed) { tick(); setInterval(tick, 1000); }

    function cell(cls, text) {
        var td = document.createElement('td');
        if (cls) td.className = cls;
        if (text != null) td.textContent = text;
        return td;
    }

    function render(deps) {
        body.textContent = '';
        if (!deps.length) {
            var tr = document.createElement('tr');
            var td = cell('empty', 'V nejbližších dnech odtud nic nejede.');
            td.colSpan = 4;
            tr.appendChild(td);
            body.appendChild(tr);
            return;
        }
        deps.forEach(function (d) {
            var tr = document.createElement('tr');
            var t = cell('t', d.time);
            if (d.dayLabel) {
                t.appendChild(document.createTextNode(' '));
                var sm = document.createElement('small');
                sm.textContent = d.dayLabel;
                t.appendChild(sm);
            }
            var line = document.createElement('td');
            var b = document.createElement('span');
            b.className = 'badge badge-' + d.type;
            b.textContent = d.line;
            line.appendChild(b);
            tr.appendChild(t);
            tr.appendChild(line);
            tr.appendChild(cell('to', d.headsign));
            tr.appendChild(cell('r eta', d.eta));
            body.appendChild(tr);
        });
    }

    function refresh() {
        fetch(api, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                render(data.departures || []);
                offset = 0; // po obnovení se čas řídí hodinami zařízení
                updated.textContent = 'Aktualizováno ' + data.time.slice(0, 5);
            })
            .catch(function () { updated.textContent = 'Obnovení se nezdařilo, zkouším znovu…'; });
    }
    if (!fixed) setInterval(refresh, 30000);
})();
