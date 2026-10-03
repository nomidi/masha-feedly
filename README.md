# Masha:Feedly-Modul

Masha:Feedly ergänzt SilverStripe CMS um einen gemeinsamen Fehler- und Feedback-Tracker. Nutzer melden Probleme direkt auf der Website, während das Team Einträge im Frontend und CMS bearbeitet.

## Dokumentation

- [Benutzung auf Deutsch](docs/de/README.md)
- [Usage in English](docs/en/README.md)

## Installation

Im Silverstripe-Projektverzeichnis ausführen:

```sh
composer require kooperativeweb/masha-feedly
sake dev/build flush=1
```

## Installation aus GitHub

Bis die Version auf Packagist verfügbar ist, kann das Paket direkt aus GitHub installiert werden. Dafür in der `composer.json` des Silverstripe-Projekts ein VCS-Repository ergänzen und die Entwicklungsversion anfordern:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/nomidi/masha-feedly" }
  ],
  "require": {
    "kooperativeweb/masha-feedly": "dev-main"
  }
}
```

Anschließend ausführen:

```sh
composer update kooperativeweb/masha-feedly
sake dev/build flush=1
```

## Anforderungen

- PHP 8.3 oder neuer
- Silverstripe Framework 6.2 oder neuer

## Lizenz

MIT. Siehe [`composer.json`](composer.json).
