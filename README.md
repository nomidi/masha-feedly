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

## Fälligkeitserinnerungen

Einträge können ein Fälligkeitsdatum erhalten. Damit Erinnerungen automatisch versendet werden, richte im Silverstripe-Projekt einen täglichen Cron-Aufruf ein:

```sh
vendor/bin/sake dev/tasks/MashaFeedlyDueDateReminderTask
```

Die Erinnerung wird einmalig an freigegebene zuständige Personen und die erstellende Person gesendet. In den Masha:Feedly-Einstellungen lässt sich wählen, ob die Prüfung per täglichem Cronjob oder beim ersten Websitebesuch des Tages startet. Im Besuchsmodus bleibt der Versand aus, solange niemand die Website aufruft. Jeder Empfänger kann Fälligkeitserinnerungen in den Masha:Feedly-Profileinstellungen deaktivieren.

## Abschluss-Effekte

Die Animationen liegen im separaten Silverstripe-Modul **Masha:Effects** neben diesem Modul. In Feedly bleibt der universelle Loader. Name, Dateien, Theme, Aktivierung und saisonale Regeln werden im Anbieter-CMS gepflegt. `MASHA_FEEDLY_EFFECTS_BASE_URL` konfiguriert dessen Website-Adresse; ohne Konfiguration wird derselbe Server verwendet. Für lokale Einrichtung und Dateiformat siehe [Masha:Effects](../masha-effects/README.md).

Der Anbieter benötigt einen API-Schlüssel. `MASHA_FEEDLY_EFFECTS_API_KEY` wird nur serverseitig verwendet; Browser bekommen lokale Proxy-URLs, die Login und Feedly-Freigabe prüfen. Die Anbieter-Dateien liegen unter `masha-effects/private/resources/` außerhalb der öffentlichen Ressourcen.

## Lizenz

MIT. Siehe [`composer.json`](composer.json).
