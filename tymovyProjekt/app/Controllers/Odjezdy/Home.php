<?php

namespace App\Controllers\Odjezdy;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class Home extends BaseController
{
    public function index(): string
    {
        $db = \Config\Database::connect();

        return view('odjezdy/home', [
            'title'     => 'Odjezdové tabule',
            'stopCount' => (int) $db->table('gtfs_stops')->countAllResults(),
        ]);
    }

    /** Hledání zastávek podle názvu (našeptávač na úvodní stránce). */
    public function search(): ResponseInterface
    {
        $q = trim((string) $this->request->getGet('q'));
        if (mb_strlen($q) < 2) {
            return $this->response->setJSON(['stops' => []]);
        }

        $rows = \Config\Database::connect()->table('gtfs_stops')
            ->select('stop_id, stop_name')
            ->like('stop_name', $q)
            ->orderBy('stop_name')
            ->limit(20)
            ->get()->getResultArray();

        return $this->response->setJSON(['stops' => $rows]);
    }
}
