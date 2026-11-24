/* Graf počtu odjezdů v jednotlivých dnech aktuálního týdne (Chart.js). */
(function () {
    'use strict';
    var canvas = document.getElementById('week-chart');
    var dataEl = document.getElementById('week-data');
    if (!canvas || !dataEl || typeof Chart === 'undefined') return;

    var week = JSON.parse(dataEl.textContent);
    var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    var css = getComputedStyle(document.documentElement);
    var accent = css.getPropertyValue('--accent').trim() || '#0b5fff';
    var text = css.getPropertyValue('--muted').trim() || '#5b6573';
    var grid = css.getPropertyValue('--line').trim() || '#dfe3e9';
    var dim = dark ? 'rgba(91,155,255,.45)' : 'rgba(11,95,255,.35)';

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: week.labels.map(function (l, i) { return l + ' ' + week.dates[i]; }),
            datasets: [{
                label: 'Počet odjezdů',
                data: week.counts,
                backgroundColor: week.counts.map(function (_, i) { return i === week.todayIndex ? accent : dim; }),
                borderRadius: 4,
                maxBarThickness: 56
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function (items) { return items[0].label + (items[0].dataIndex === week.todayIndex ? ' (dnes)' : ''); },
                        label: function (c) { return c.parsed.y + ' odjezdů'; }
                    }
                }
            },
            scales: {
                x: { ticks: { color: text }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: text, precision: 0 }, grid: { color: grid }, title: { display: true, text: 'odjezdů za den', color: text } }
            }
        }
    });
})();
