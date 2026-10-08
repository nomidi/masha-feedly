# Masha:Feedly

Masha:Feedly ergänzt Silverstripe CMS um einen gemeinsamen Fehler- und Feedback-Tracker. Berechtigte Personen melden Probleme direkt auf der Website; das Team bearbeitet Meldungen im Frontend und CMS.

## Anforderungen

- PHP 8.3 oder neuer
- Silverstripe CMS 6.2 oder neuer

## Installation

Im Silverstripe-Projektverzeichnis:

```sh
composer require kooperativeweb/masha-feedly
vendor/bin/sake dev/build flush=1
```

Falls das Paket noch nicht auf Packagist verfügbar ist, kann es über GitHub eingebunden werden. Dafür ein VCS-Repository in der `composer.json` ergänzen:

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

Danach im Projektverzeichnis ausführen:

```sh
composer update kooperativeweb/masha-feedly
vendor/bin/sake dev/build flush=1
```

## Dokumentation

- [Benutzung und Konfiguration auf Deutsch](docs/de/README.md)
- [Usage and configuration in English](docs/en/README.md)
- [Browserbasierte Ende-zu-Ende-Tests](tests/e2e/README.md)

Die Anleitungen beschreiben mobile Bedienung und Einführung, Screenshots mit Ausschnittwahl, optionale Schritte zum Nachstellen, Zugriffsrechte, Profilvorschau und Tonwahl, E-Mail-Benachrichtigungen und Footer-Konfiguration, Fälligkeitserinnerungen sowie optionale Effekt-Anbieter und deren Vertrag.

## Lizenz

MIT. Siehe [`composer.json`](composer.json).
