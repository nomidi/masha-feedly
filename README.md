# Masha:Feedly-Modul

Masha:Feedly ergänzt SilverStripe CMS um einen gemeinsamen Fehler- und Feedback-Tracker. Nutzer melden Probleme direkt auf der Website, während das Team Einträge im Frontend und CMS bearbeitet.

## Dokumentation

- [Benutzung auf Deutsch](docs/de/README.md)
- [Usage in English](docs/en/README.md)

## Composer-Installation

Das Paket ist als Silverstripe-Vendormodul angelegt. In einem Silverstripe-Projekt kann es direkt aus diesem GitHub-Repository installiert werden:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/nomidi/masha-feedly"
    }
  ],
  "require": {
    "kooperativeweb/masha-feedly": "dev-main"
  }
}
```

Danach im Projektverzeichnis ausführen:

```sh
composer update kooperativeweb/masha-feedly
vendor/bin/sake dev/build flush=1
```

## Anforderungen

- PHP 8.3 oder neuer
- Silverstripe Framework 6.2 oder neuer

## Lizenz

MIT. Siehe [`composer.json`](composer.json).
