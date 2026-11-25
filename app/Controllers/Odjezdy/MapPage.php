<?php

namespace App\Controllers\Odjezdy;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class MapPage extends BaseController
{
    public function index(): string
    {
        $row = \Config\Database::connect()->table('gtfs_stops')
            ->select('MIN(stop_lat) AS s, MIN(stop_lon) AS w, MAX(stop_lat) AS n, MAX(stop_lon) AS e, COUNT(*) AS c', false)
            ->where('stop_lat IS NOT NULL', null, false)
            ->get()->getRowArray();

        $bounds = null;
        if ($row && (int) $row['c'] > 0) {
            $bounds = [[(float) $row['s'], (float) $row['w']], [(float) $row['n'], (float) $row['e']]];
        }

        return view('odjezdy/map', [
            'title'  => 'Mapa zastávek',
            'bounds' => $bounds,
            'popup'  => config('Odjezdy')->mapPopupLimit,
        ]);
    }

    /** Zastávky ve výřezu mapy: /api/mapa?south=&west=&north=&east= */
    public function stops(): ResponseInterface
    {
        $g = $this->request;
        foreach (['south', 'west', 'north', 'east'] as $k) {
            if (! is_numeric($g->getGet($k))) {
                return $this->response->setStatusCode(400)->setJSON(['error' => "Chybí parametr {$k}."]);
            }
        }
        $max = config('Odjezdy')->mapMaxStops;

        $rows = \Config\Database::connect()->table('gtfs_stops')
            ->select('stop_id, stop_name, stop_lat, stop_lon')
            ->where('stop_lat >=', (float) $g->getGet('south'))
            ->where('stop_lat <=', (float) $g->getGet('north'))
            ->where('stop_lon >=', (float) $g->getGet('west'))
            ->where('stop_lon <=', (float) $g->getGet('east'))
            ->orderBy('stop_name')
            ->limit($max + 1)
            ->get()->getResultArray();

        $truncated = count($rows) > $max;
        $rows      = array_slice($rows, 0, $max);

        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON([
            'truncated' => $truncated,
            'max'       => $max,
            'stops'     => array_map(static fn ($r) => [
                'id'   => $r['stop_id'],
                'name' => $r['stop_name'],
                'lat'  => (float) $r['stop_lat'],
                'lon'  => (float) $r['stop_lon'],
            ], $rows),
        ]);
    }
}
