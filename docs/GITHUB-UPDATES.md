# Verifizierte manuelle Updates

Adaptiert aus ChurchTools Suite [`49dc66f`](https://github.com/sw-wordpress/churchtools-suite/tree/49dc66f), ohne Laufzeitabhängigkeit.
Quelle: `sw-wordpress/sw-newspaper2`, GitHub-ID `1432343893` (API geprüft),
Basename `sw-newspaper2/sw-newspaper2.php`, Version `SWNEWSPAPER2_VERSION`.
Nur dieser Slug wird verwaltet; alle Vertrauens-, Hook- und Speichernamen sind pluginspezifisch.

Der Backend-Footer lädt einen kleinen Client. Erst nach `load` kann er eine
Nonce-geschützte Prüfung auslösen. Der persistente Netzwerkzustand begrenzt
Versuche einschließlich Fehlern auf 24 Stunden; ausdrücklich manuelle Prüfungen
auf 60 Sekunden. Anzeige-Hooks lesen ausschließlich lokalen Cache. Frontend,
Cron, REST und WP-CLI lösen keine GitHub-Abfragen aus.

Installation erfolgt ausschließlich durch WordPress nach einem nativen manuellen
Einzel-, AJAX- oder Sammelauftrag. Der Download-Guard prüft erneut Signatur,
Repository/Release/Asset-Bindung, Versionsschutz, sieben Tage Cachealter sowie
ZIP-Hash, Größe, Struktur, Pfade, Dateitypen, Entpacklimits und Plugin-Header.
Automatische Installationen bleiben auch mit alten Angeboten gesperrt.
Eine zweite Providerkopie teilt weder Schlüssel noch Optionen oder Locks.
Multisite serialisiert die Lock-Erzeugung zusätzlich per Datenbankmutex, weil
`sitemeta` keinen eindeutigen Schlüssel besitzt; Eigentümerprüfungen lesen direkt
aus der Datenbank. Ein abgelaufener Besitzer darf keinen Nachfolger freigeben.

Es gab keinen alten GitHub-Updater, Installer, Cronjob oder Token im Zielplugin.
Migration und Deaktivierung bereinigen nur eigene Angebote und Auto-Update-Auswahl.
Der Release-Build nutzt eine feste Runtime-Positivliste; Composer erzeugt mangels
produktiver Drittanbieterabhängigkeiten ausschließlich den eigenen Loader unter
`vendor-prefixed/`. Neue Runtime-Abhängigkeiten erfordern eine Isolationserweiterung.

[Einrichtung, kopierbare Schlüsselblöcke, Rotation, Grenzen und Store-Wechsel](GITHUB-UPDATES-BETRIEB.md).
[Tests und Coverage](../tests/README.md#verifizierte-updates).
Der öffentliche Schlüsselring bleibt bis zur Betreiberkonfiguration leer.
