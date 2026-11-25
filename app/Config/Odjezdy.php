<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Nastavení aplikace odjezdových tabulí.
 * Hodnoty lze přepsat v souboru .env, např.:
 *   odjezdy.publicBaseUrl = https://odjezdy.example.cz/
 */
class Odjezdy extends BaseConfig
{
    /** Časová zóna, ve které se počítají odjezdy. */
    public string $timezone = 'Europe/Prague';

    /** Kolik nejbližších odjezdů se zobrazí na tabuli. */
    public int $boardLimit = 10;

    /** Kolik odjezdů se ukáže v bublině zastávky na mapě. */
    public int $mapPopupLimit = 5;

    /** Maximum zastávek, které API vrátí pro jeden výřez mapy. */
    public int $mapMaxStops = 150;

    /**
     * Veřejná adresa webu, na kterou budou mířit QR kódy.
     * Když je prázdná, použije se base_url(). Hodí se, když administraci
     * otevíráte na localhostu, ale QR kódy mají mířit na ostrý server.
     */
    public string $publicBaseUrl = '';

    /** Odkazy ke stažení GTFS (příkaz php spark gtfs:download). */
    public array $downloadUrls = [
        'jdf'   => 'https://data.jr.ggu.cz/results/latest/JDF_merged_GTFS.zip',
        'vlaky' => 'https://data.jr.ggu.cz/results/latest/CZPTT_GTFS.zip',
    ];
}
