# Odjezdové tabule z GTFS (data.jr.ggu.cz)

Aplikace v CodeIgniteru 4 (PHP + MySQL). Knihovny (Leaflet, Chart.js, qr-creator) jsou uložené lokálně v `public/assets/odjezdy/lib/`, nic se nenačítá z CDN. Jedinou výjimkou jsou dlaždice podkladové mapy z OpenStreetMap, ty se stahují z internetu.

## Co aplikace umí

| Adresa | Funkce |
|---|---|
| `/` | Hledání zastávky podle názvu |
| `/tabule/{stop_id}` | Odjezdová tabule: 10 nejbližších odjezdů, hodiny, automatické obnovování, graf počtu odjezdů v aktuálním týdnu (Po–Ne, dnešek zvýrazněn) |
| `/mapa` | Mapa zastávek, po kliknutí na zastávku se ukáží její nejbližší odjezdy |
| `/admin` | Správa (přihlášení): seznam zastávek, detail s QR kódem, stažení PNG, hromadný tisk QR kódů pro vylepení na zastávky |

QR kód míří na `/tabule/{stop_id}`, tedy na tabuli s aktuálním časem v okamžiku naskenování.

## Instalace do projektu v Laragonu

1. Stáhněte si tento branch (`git pull`, `git checkout odjezdove-tabule`). Routy už jsou zapojené v `app/Config/Routes.php`.
2. Do `.env` doplňte nastavení z `env.odjezdy.example` (databáze, `baseURL`).
3. Založení databáze a tabulek:
   ```
   php spark db:create odjezdy
   php spark migrate
   ```
4. Administrátor: `php spark odjezdy:admin jmeno heslo`

> **Pozor, změna oproti původní verzi projektu:** tato verze nahrazuje dřívější kontroler `Odjezdy`, pohledy `tabule`, `mapa`, `rozlozeni` a `admin/index`. Původní migrace `VytvorTabulky` zůstala v repozitáři, ale nové tabulky `gtfs_*` mají jiné sloupce, a proto je nová migrace ze starých tabulek `gtfs_*` při spuštění zahodí a založí znovu (data se dají naimportovat znovu). Tabulka `vybrane_zastavky` se nemaže, jen se už nepoužívá. Původní verzi najdete ve větvi `main`.

## Data

Rychlé vyzkoušení bez stahování (vymyšlené zastávky): `php spark gtfs:sample` a `php spark gtfs:import writable/gtfs/ukazka_GTFS.zip`

Skutečná data (JDF_merged_GTFS.zip pro autobusy ≈ 125 MB, CZPTT_GTFS.zip pro vlaky ≈ 75 MB):

```
php spark gtfs:download
php spark gtfs:import --bbox "49.00,17.30,49.15,17.60"            # okolí Uherského Hradiště
php spark gtfs:import --append --bbox "49.75,18.20,49.95,18.55"   # okolí Orlové a Ostravy
```

- `--bbox minLat,minLon,maxLat,maxLon` omezí import na výřez, celá ČR jsou desítky milionů řádků a trvala by hodiny.
- `--names "Hradiště,Orlová"` doplní zastávky podle části názvu (spojuje se s `--bbox` logickým NEBO).
- Bez `--append` se stará data před importem smažou.
- Data se aktualizují každou noc, import stačí opakovat podle potřeby. ID zastávek (a tím i vytištěné QR kódy) zůstávají stejná, pokud se nemění název souboru.

## Co je dobré vědět

- Zobrazuje se plánovaný jízdní řád, bez zpoždění v reálném čase. Na konečné zastávce spoje se odjezdy nezobrazují.
- Aplikaci otevírejte na adrese z `app.baseURL` (jinak selže přihlášení kvůli CSRF).
- Při tisku QR kódů lze v administraci nastavit veřejnou adresu serveru (nebo `odjezdy.publicBaseUrl` v `.env`), aby kódy nemířily na `localhost`.
- V ladicím režimu (`CI_ENVIRONMENT = development`) se dole zobrazuje lišta CodeIgniteru, na ostrém serveru nastavte `production`.
