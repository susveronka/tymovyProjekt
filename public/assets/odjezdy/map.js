/* Mapa zastávek (Leaflet + OpenStreetMap) s přehledem nejbližších odjezdů v bublině. */
(function () {
    'use strict';
    var el = document.getElementById('map');
    if (!el || typeof L === 'undefined') return;

    var base = document.body.dataset.base;
    var popupLimit = parseInt(el.dataset.popup, 10) || 5;
    var bounds = JSON.parse(el.dataset.bounds || 'null');
    var params = new URLSearchParams(location.search);

    L.Icon.Default.imagePath = base + 'assets/odjezdy/lib/leaflet/images/';

    var map = L.map(el, { zoomControl: true });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    if (params.get('lat') && params.get('lon')) {
        map.setView([parseFloat(params.get('lat')), parseFloat(params.get('lon'))], 16);
    } else if (bounds) {
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });
    } else {
        map.setView([49.8, 15.5], 8);   // střed ČR, když v databázi nic není
    }

    var layer = L.layerGroup().addTo(map);
    var hint = document.getElementById('map-hint');
    var markers = {};          // stop_id -> marker (nepřekreslujeme už zobrazené)
    var openId = params.get('open');
    var reqSeq = 0;

    function text(tag, str, cls) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (str != null) e.textContent = str;
        return e;
    }

    function popupContent(stop) {
        var box = text('div', null, 'pop');
        box.appendChild(text('h3', stop.name));
        var body = text('div', 'Načítám odjezdy…');
        box.appendChild(body);
        var link = text('a', 'Celá odjezdová tabule →');
        link.href = base + 'tabule/' + encodeURIComponent(stop.id);
        box.appendChild(link);
        return { box: box, body: body };
    }

    function loadDepartures(stop, body) {
        fetch(base + 'api/odjezdy/' + encodeURIComponent(stop.id) + '?limit=' + popupLimit, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                body.textContent = '';
                var deps = data.departures || [];
                if (!deps.length) { body.textContent = 'V nejbližších dnech nic nejede.'; return; }
                var table = document.createElement('table');
                deps.forEach(function (d) {
                    var tr = document.createElement('tr');
                    var t = text('td', d.time + (d.dayLabel ? ' (' + d.dayLabel + ')' : ''), 't');
                    var l = document.createElement('td');
                    l.appendChild(text('span', d.line, 'badge badge-' + d.type));
                    tr.appendChild(t);
                    tr.appendChild(l);
                    tr.appendChild(text('td', d.headsign));
                    table.appendChild(tr);
                });
                body.appendChild(table);
            })
            .catch(function () { body.textContent = 'Odjezdy se nepodařilo načíst.'; });
    }

    function addMarker(stop) {
        if (markers[stop.id]) return;
        var m = L.marker([stop.lat, stop.lon], { title: stop.name }).addTo(layer);
        m.on('click', function () {
            var p = popupContent(stop);
            m.bindPopup(p.box, { maxWidth: 340 }).openPopup();
            loadDepartures(stop, p.body);
        });
        markers[stop.id] = m;
        if (openId && stop.id === openId) {
            openId = null;
            m.fire('click');
        }
    }

    function load() {
        var b = map.getBounds();
        var my = ++reqSeq;
        var q = new URLSearchParams({ south: b.getSouth(), west: b.getWest(), north: b.getNorth(), east: b.getEast() });
        fetch(base + 'api/mapa?' + q.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (my !== reqSeq) return;
                hint.hidden = !data.truncated;
                (data.stops || []).forEach(addMarker);
            })
            .catch(function () { /* mapa zůstane s tím, co už má */ });
    }

    var t = null;
    map.on('moveend', function () { clearTimeout(t); t = setTimeout(load, 250); });
    load();
})();
