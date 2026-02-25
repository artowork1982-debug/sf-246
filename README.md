# SafetyFlash – Työturvallisuustiedotteiden hallintajärjestelmä

SafetyFlash on työpaikkojen turvallisuustiedotteiden hallintaan tarkoitettu sovellus. Sen avulla voidaan luoda, käsitellä, hyväksyä ja julkaista turvallisuustiedotteita infonäytöille ja sähköpostijakeluun.

---

## Ominaisuudet

### Tiedotteiden hallinta

- Kolme tiedotetyyppiä: 🔴 Ensitiedote (red), 🟡 Vaaratilanne (yellow), 🟢 Tutkintatiedote (green)
- Tiedotteiden luonti, muokkaus ja poisto
- Monivaiheinen lomake (stepper-UI) tiedotteen luontiin
- Esikatselukuvan automaattinen generointi (1920×1080 SafetyFlash-kortti)
- Kuvaeditori: rajaus, kierto, zoomaus ja annotaatiot (nuolet, tekstit, ympyrät)
- Tuki 1–3 pääkuvalle + rajaton määrä lisäkuvia (extra images)
- Kuvatekstit jokaiselle kuvalle
- Grid-layout valinta (kuva-asettelu kortissa)
- Fonttikoon säätö tiedotekorttiin

### Tutkintatiedote (Investigation Report)

- Luodaan olemassa olevan ensitiedotteen/vaaratilanteen pohjalta TAI itsenäisenä
- Juurisyyanalyysi ja korjaavat toimenpiteet -kentät
- Alkuperäisen tiedotteen versiohistoria säilyy
- PDF-raportin generointi (A4, Dompdf) sisältäen kansilehti, sisältö, kuvat ja alkuperäinen SafetyFlash-kortti

### Monikielisyys

- Käyttöliittymä: suomi (fi), ruotsi (sv), englanti (en), italia (it), kreikka (el)
- Tiedotteiden käännösversiot (translation children)
- Kieliversioiden linkitys `translation_group_id`:llä

### Työnkulku ja roolit

- Roolit: Admin (1), Esimies (2), Turvatiimi (3), Viestintä (4), Peruskäyttäjä (5)
- Tilat: Luonnos → Tarkistettavana → Lisätietoa pyydetty → Tarkastettu → Viestinnälle → Julkaistu
- Esimieshyväksyntä ennen julkaisua
- Sähköposti-ilmoitukset tilan muutoksista (PHPMailer + SMTP)

### Infonäytöt (Digital Signage / Xibo)

- Xibo-integraatio: julkaistut tiedotteet näytetään infonäytöillä
- Näyttökohtaiset targetit (valitse mihin näyttöihin tiedote lähetetään)
- API-avainautentikointi näyttökohtaisesti
- HTML-slideshow ja JSON-rajapinta Xibo-widgeteille
- Näytön kesto (duration) ja näyttöaika (TTL) per tiedote
- Rate limiting (60 req/min per IP)

### Listanäkymä

- Kolme näkymää: Grid, Lista, Kompakti
- Suodattimet: tyyppi, tila, työmaa, päivämääräväli, hakusana
- Lajittelu: luotu, tapahtunut, päivitetty
- Massa-poisto (admin)
- Käännösversioiden lippuikonit korteissa

### Tietoturva

- CSRF-suojaus kaikissa lomakkeissa ja API-kutsuissa
- Roolipohjainen pääsynhallinta
- Istunnonhallinta ja automaattinen uloskirjaus
- Kuvatiedostojen validointi ja turvallinen tallennus (`basename`, `realpath`)
- Audit log kaikista toiminnoista (`sf_audit_log`)

### Tekniset ominaisuudet

- PHP 8.x + MySQL/MariaDB
- Vanilla JavaScript (ei frameworkia) – modulaarinen ES-moduulirakenne
- Imagick + GD kuvantuottamiseen
- Dompdf PDF-raporttien generointiin
- PWA-tuki (Service Worker, manifest, offline-sivu)
- Responsiivinen UI (mobiili + desktop)
- Taustaprosessointi esikatselukuvien generointiin (cron tai inline)

---

## Hakemistorakenne

```
sf-246/
├── app/
│   ├── api/           # API-endpointit (save, process, display, report)
│   ├── actions/       # Toiminnot (publish, save_edit, delete)
│   ├── config/        # Asetukset ja käännöstermit
│   ├── includes/      # Suojaus, CSRF, apufunktiot
│   ├── services/      # PreviewImageGenerator, ReportImageGenerator
│   └── views/         # PDF-template
├── assets/
│   ├── css/           # Tyylitiedostot (form, list, view, modal)
│   ├── js/            # JavaScript-moduulit
│   ├── pages/         # PHP-sivut (form, list, view)
│   ├── lib/           # Database, sf_terms
│   ├── img/           # Kuvat, ikonit, templatepohjat
│   └── fonts/         # Open Sans fontit
├── docs/              # Dokumentaatio
├── migrations/        # Tietokantamigraatiot
├── config.php         # Pääkonfiguraatio
├── index.php          # Reititys ja sivunlataus
└── upload.php         # Kuvien upload-käsittely
```

---

## Vaatimukset

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Imagick PHP-laajennus
- GD PHP-laajennus
- Composer (Dompdf)
- SMTP-palvelin sähköposteja varten

---

## Asennus

1. Kloonaa repo
2. Kopioi `env.example` → `.env` ja täytä asetukset
3. Suorita `composer install`
4. Aja tietokantamigraatiot (`migrations/`-kansiosta)
5. Aseta `uploads/`-kansion kirjoitusoikeudet
6. Konfiguroi web-palvelin osoittamaan juurihakemistoon

---

## Lisenssi

Yksityinen / sisäinen käyttö (Private / internal use)
