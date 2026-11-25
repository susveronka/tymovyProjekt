<?php

namespace App\Controllers\Odjezdy;

use App\Controllers\BaseController;
use App\Libraries\DepartureService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class Board extends BaseController
{
    /** Odjezdová tabule zastávky – otevírá se z QR kódu, vždy počítá s aktuálním časem. */
    public function show(string $stopId): string
    {
        $svc  = new DepartureService();
        $stop = $svc->findStop($stopId);
        if (! $stop) {
            throw PageNotFoundException::forPageNotFound('Zastávka nenalezena.');
        }

        $cfg = config('Odjezdy');
        $now = $svc->now($this->request->getGet('cas'));

        return view('odjezdy/board', [
            'title'      => $stop['stop_name'],
            'stop'       => $stop,
            'departures' => $svc->nextDepartures($stopId, $now, $cfg->boardLimit),
            'week'       => $svc->weekCounts($stopId, $now),
            'now'        => $now,
            'cas'        => (string) $this->request->getGet('cas'),
        ]);
    }

    /** JSON s nejbližšími odjezdy (pro automatické obnovování tabule a bubliny na mapě). */
    public function departures(string $stopId): ResponseInterface
    {
        $svc  = new DepartureService();
        $stop = $svc->findStop($stopId);
        if (! $stop) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Zastávka nenalezena.']);
        }

        $cfg   = config('Odjezdy');
        $limit = (int) ($this->request->getGet('limit') ?: $cfg->boardLimit);
        $limit = max(1, min($limit, 30));
        $now   = $svc->now($this->request->getGet('cas'));

        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON([
                'stop'       => ['id' => $stop['stop_id'], 'name' => $stop['stop_name']],
                'time'       => $now->format('H:i:s'),
                'departures' => $svc->nextDepartures($stopId, $now, $limit),
            ]);
    }
}
