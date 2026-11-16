# Prüfstand 16. November 2026

Lokal mit PHP 8.3, WordPress 6.9.11 und privater MariaDB:

- Vollständige PHP-Regressionssuite einschließlich nativer Einzel-/AJAX-/Sammelupdates,
  Single-Site und Multisite bestanden; jeweils 65 WordPress-Tests.
- PHP-Zeilenabdeckung **93,28 % (999/1071)** über alle produktiven PHP-Dateien;
  JavaScript **100 % (35/35)**, 33 Vitest-Tests. Strikte Grenzen > 80 % unverändert.
- Chromium-Prüfung bestanden, Update-Skript 100 % ausführbare Zeilen.
- Echtes Build-ZIP mit Wegwerfschlüssel signiert und vom Laufzeitprüfer akzeptiert;
  Paketvollständigkeit, isolierter Loader-Boot, manipuliertes Artefakt, fehlendes
  Signier-Secret, Schlüsselring-Erhalt und Key-ID-Konflikt geprüft.
- Drei Betreiberblöcke in Bash/Zsh mit Wegwerfpfaden geprüft; `gh` war ausschließlich
  eine lokale Attrappe. `make release` mit einem lokalen Bare-Remote geprüft.
- Composer-Konfigurationen, PHP-Code-Style (keine Fehler; Hinweise zu absichtlichen
  Kryptographie-/Dateisystem-/Lockoperationen sowie ein bestehender Hinweis),
  Shell-Syntax, Workflow-YAML und `git diff --check` bestanden.

Die Kollisionsprüfung verwendet eine zweite separat präfixierte SW-Providerkopie;
kein produktives Nachbarplugin wurde verändert oder durch Tests installiert.
CI definiert zusätzlich PHP 8.2/8.4 und WordPress 6.4; diese Matrix wurde lokal
nicht vollständig ausgeführt. Ein GitHub-Actions-Lauf bzw. SonarQube-Serverlauf
wurde hier nicht gestartet; Clover und LCOV liegen für CI bereit.

Es wurden keine Produktionsschlüssel, GitHub-Secrets, Tags oder Releases angelegt.
Der öffentliche Schlüsselring ist leer. Environment-Schutzregeln, eigener
Vertrauensanker und erster signierter Release fehlen weiterhin; die produktive
Updateversorgung ist deshalb gesperrt. Ein Store-Build bleibt eine spätere,
gesondert zu prüfende Umstellung gemäß [Betriebshandbuch](GITHUB-UPDATES-BETRIEB.md).
