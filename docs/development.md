# Entwicklung, Installation aus dem Quellcode und Release-Build

Kurzüberblick und Befehle: [README](../README.md#development). Tests, Coverage und SonarQube: [tests/README.md](../tests/README.md).

## Installation aus dem Quellcode

Bei Installation aus dem Quellcode ohne mitgelieferte Abhängigkeiten im Plugin-Verzeichnis Composer ausführen. Für eine produktive Installation:

```sh
composer install --no-dev --optimize-autoloader
```

Anschließend das Plugin in WordPress aktivieren. Der [Plugin-Bootstrap](../sw-newspaper2.php#L46) lädt bevorzugt `vendor-prefixed/autoload.php` und fällt sonst auf `vendor/autoload.php` zurück. Fehlen beide, startet das Plugin nicht und zeigt einen Hinweis im Backend.

## Release-Build und isolierte Composer-Abhängigkeiten

Der [GitHub-Actions-Workflow](../.github/workflows/main.yml) baut bei einem Versions-Tag das installierbare ZIP. Die Pipeline richtet PHP 8.2 und Composer 2 ein, validiert die [Composer-Konfiguration](../composer.json), installiert ausschließlich Produktionsabhängigkeiten aus dem Lockfile und führt Mozart **1.2.3** in einem separaten Docker-Container aus. Mozart selbst wird nicht als Plugin-Abhängigkeit installiert oder ausgeliefert.

Die Mozart-Konfiguration (`extra.mozart` in der [composer.json](../composer.json)) isoliert Laufzeitbibliotheken unter dem Namespace **SWNewspaper2\\Dependencies**. Namenlose beziehungsweise globale Klassen, Konstanten und Funktionen erhalten ebenfalls Plugin-spezifische Präfixe. Ohne explizite Paketliste verarbeitet Mozart alle Laufzeitabhängigkeiten samt deren Abhängigkeiten, nicht die Entwicklungswerkzeuge.

Das generierte Verzeichnis **vendor-prefixed** enthält die isolierten Bibliotheken und deren eigenen Autoloader. Der [Plugin-Bootstrap](../sw-newspaper2.php#L46) bevorzugt diesen Autoloader und registriert daran zusätzlich den Plugin-Namespace. Im Release ist deshalb das ursprüngliche Vendor-Verzeichnis nicht erforderlich und wird vollständig ausgeschlossen. Entwicklungswerkzeuge, Tests, Git-Metadaten und Composer-Builddateien werden ebenfalls nicht ins ZIP übernommen. Der Installationsordner im Archiv bleibt unabhängig vom Repository-Namen **sw-newspaper2**.

Für einen lokalen Release-Build mit Docker im Plugin-Verzeichnis:

```sh
composer validate --strict
composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD:/project" coenjacobs/mozart:1.2.3 /mozart/bin/mozart compose
php tests/bootstrap.php
```

Mozart verändert den Build-Arbeitsbaum und entfernt verarbeitete Originalbibliotheken. Den Release-Build deshalb in einem frischen Checkout oder einer separaten Kopie ausführen, nicht in der laufenden WordPress-Installation. Nach Änderungen an den Abhängigkeiten das generierte Ausgabe-Verzeichnis neu aufbauen; es wird nicht eingecheckt. Beim Einbinden neuer Bibliotheken sicherstellen, dass die gebaute Plugin-Version die isolierten Klassen verwendet. Der aktuelle Stand benötigt noch keine externen Laufzeitbibliotheken.

Der Workflow führt die [Importer-Regressionstests](../tests/legacy-import.php) aus, prüft den generierten Autoloader und startet den [Bootstrap-Smoke-Test](../tests/bootstrap.php) gegen das fertig zusammengestellte Paket **ohne** ursprüngliches Vendor-Verzeichnis. Anschließend werden ZIP-Integrität und erforderliche beziehungsweise unerwünschte Archivpfade geprüft. Erst danach wird das ZIP an das GitHub-Release angehängt.
