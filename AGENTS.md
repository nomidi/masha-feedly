# masha-feedly – Projektanweisungen

Tests nur schreiben oder anpassen, niemals ausführen. Statische Prüfungen ohne Anwendungsstart sind erlaubt.

## Persönliche Installationsdaten

- Niemals persönliche Rechnerpfade, Benutzernamen, Gerätenamen, lokale Entwicklungsdomains, konkrete Server-/Deploymentpfade oder Serverkonten in versionierte Moduldateien aufnehmen. Das gilt auch für README, AGENTS.md, Beispiele, Testdaten, Fixtures, Kommentare, generierte Dateien und Source Maps.
- Solche projektspezifischen Betreiberangaben ausschließlich in der privaten Hostdatei `readme.local.md` außerhalb der Modul-Repositories dokumentieren. Kein Exemplar dieser Datei im Modul anlegen oder committen.
- Allgemeine Anleitungen verwenden neutrale Platzhalter wie `/path/to/project` und reservierte Beispieldomains. Tests verwenden synthetische Adressen; wichtige Eigenschaften wie Port, Protokoll und lokale Hostnamenform erhalten.
- Öffentliche Hersteller-, Support-, Impressums-, Lizenz- und Quellenverweise bleiben zulässig. Konkrete Installationsadressen sind keine neutralen Konfigurationsbeispiele. Bei Änderungen den Diff und eingecheckte generierte Dateien vor dem Commit auf persönliche Angaben prüfen. Die Bereinigung selbst erzeugt keinen News-Eintrag.
