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

- PHP 7.4 oder neuer
- Silverstripe CMS 4.13 oder neuer innerhalb der Silverstripe-4-Reihe

## Fälligkeitserinnerungen

Einträge können ein Fälligkeitsdatum erhalten. Damit Erinnerungen automatisch versendet werden, richte im Silverstripe-Projekt einen täglichen Cron-Aufruf ein:

```sh
vendor/bin/sake dev/tasks/MashaFeedlyDueDateReminderTask
```

Die Erinnerung wird einmalig an freigegebene zuständige Personen und die erstellende Person gesendet. In den Masha:Feedly-Einstellungen lässt sich wählen, ob die Prüfung per täglichem Cronjob oder beim ersten Websitebesuch des Tages startet. Im Besuchsmodus bleibt der Versand aus, solange niemand die Website aufruft. Jeder Empfänger kann Fälligkeitserinnerungen in den Masha:Feedly-Profileinstellungen deaktivieren.

## Lizenz

MIT. Siehe [`composer.json`](composer.json).
