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

## Ohne Masha:Connect verwenden

Feedly installiert Masha:Connect nicht als Pflichtabhängigkeit. Meldungen, Kommentare, Anhänge, lokale E-Mails und die lokale Häkchen-Animation funktionieren ohne Connect und ohne Anbieterzugang.

Connect ist nur für zusätzliche signierte Anbieterinhalte wie zentrale Effekte, Profil-Icons und E-Mail-Standards erforderlich. Es kann separat per Git installiert werden:

```sh
git clone https://github.com/nomidi/masha-connect.git
```

Das geklonte Modul muss von der Silverstripe-Installation geladen werden; bei der ersten Einrichtung den regulären Modul-/Klassenmanifest-Aufbau einplanen. Für einen rein lokalen Feedly-Betrieb weder Connect noch `MASHA_API_KEY` einrichten. Ist ein gemeinsamer Schlüssel bereits gesetzt, aber Connect fehlt, bleiben Anbieterinhalte gesperrt; lokale Feedly-Funktionen und die Häkchen-Animation bleiben nutzbar. Es gibt dabei keinen Rückfall auf alte Anbieterzugänge.

## Dokumentation

- [Benutzung und Konfiguration auf Deutsch](docs/de/README.md)
- [Usage and configuration in English](docs/en/README.md)
- [Browserbasierte Ende-zu-Ende-Tests](tests/e2e/README.md)

Die Anleitungen erklären Bedienung und Einrichtung einschließlich geschützter E-Mail-Textvorlagen, Testmails mit Beispielinhalten, Footer, Fälligkeitserinnerungen und der API-Verbindung zu Masha:Suite Admin.

## Lizenz

MIT. Siehe [`composer.json`](composer.json).
