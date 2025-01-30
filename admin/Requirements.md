# DeepBlogger Plugin Requirements

## 1. Übersicht

DeepBlogger ist ein WordPress-Plugin, das mithilfe von KI (OpenAI) automatisch Blogbeiträge generiert. In der ersten Version wird zudem **Deepseek** integriert, um erweiterte Recherche- und Analysefunktionen anzubieten.  
Das Plugin soll:

1. Neue Artikel basierend auf vorhandenen Kategorien erstellen  
2. Bestehende Artikel berücksichtigen, um Themen gezielt zu erweitern  
3. Automatisch Beitragsbilder mit Hilfe von KI generieren (z. B. via DALL·E)  
4. Mehrsprachigkeit bieten (Standard EN, Übersetzung DE)  
5. Manuelle Testgenerierung (Sofort-Funktion) sowie eine geplante Erzeugung (Scheduler/Queue) unterstützen  
6. Einen Debugbereich mit ausführlichem Logging für Fehlersuche und Nachvollziehbarkeit bereitstellen  
7. Die WordPress.org Plugin-Standards (Coding Standards, Sicherheit, Übersetzbarkeit) einhalten  

---

## 2. Funktionaler Umfang

### 2.1. KI-gestützte Beitragserstellung

- **Kategoriebezogene Generierung**  
  - Auswahl einer oder mehrerer Kategorien  
  - KI schlägt Themen vor, die in bestehenden Artikeln noch nicht ausreichend abgedeckt sind  
  - Verknüpfung mit vorhandenen Inhalten (z. B. Interne Links)

- **OpenAI Integration**
  - Dynamische Abfrage verfügbarer Modelle über die OpenAI API
  - Automatische Aktualisierung der Modell-Liste in den Einstellungen
  - Speicherung des zuletzt erfolgreichen API-Aufrufs im Cache
  - Fallback auf Standard-Modelle bei API-Fehlern

- **Inhaltliche Optimierung**  
  - Prompt-Generierung mit Fokus auf SEO-Kriterien (Keywords, Meta-Beschreibungen)  
  - Automatische Formatierung (Überschriften, Absätze, Listen)  
  - Einbindung relevanter interner Links

- **Mehrsprachigkeit**  
  - Standardmäßig Erstellung in Englisch  
  - Optionale Übersetzung ins Deutsche  
  - Spracherweiterungen für zukünftige Versionen vorgesehen  

### 2.2. Deepseek-Integration (Version 1.0)

- **Erweiterte Recherche**  
  - Deepseek liefert Themenvorschläge, Keyword-Analysen oder Trend-Scans  
  - Integration als separater Admin-Bereich oder Reiter im DeepBlogger-Interface

- **Benutzeroberfläche**  
  - Einfache Anzeige relevanter Suchdaten oder Keyword-Trends  
  - Nutzung der Ergebnisse zur KI-gestützten Beitragserstellung  

### 2.3. Automatische Bild-Generierung

- **KI-gesteuerte Bild-Erstellung**  
  - Verbindung zu OpenAI (z. B. DALL·E)  
  - Prompt basierend auf Beitragsinhalt/Thema  
  - Automatische Festlegung als „Featured Image“ und Bild-Optimierung (Größenanpassung, Komprimierung)

### 2.4. Test-Funktion (Sofort-Generierung)

- **Manuelle Auslösung**  
  - Admin/Redakteur kann sofort einzelne Artikel generieren lassen  
  - Konfiguration von: Kategorie, Keywords, gewünschter Stil/Länge, Sprache  

- **Direkte Vorschau**  
  - Nach wenigen Sekunden Anzeige des generierten Artikels  
  - Entscheidung: „Als Entwurf speichern“ oder „Veröffentlichen“  

### 2.5. Geplante Erzeugung (Scheduler & Queue)

- **Hintergrundprozess**  
  - Einrichtung von Cron-Jobs oder eigener Queue, um mehrere Artikel über einen festgelegten Zeitraum zu erstellen  
  - Vermeidung von API-Überlastung durch gestaffelte Anfragen  

- **Statusverfolgung**  
  - Jeder Erzeugungsprozess erhält einen Status: `Wartend`, `In Bearbeitung`, `Abgeschlossen` oder `Fehlgeschlagen`  
  - Admin-Ansicht zur Überwachung und ggf. Neu-Start/Abruf der Ergebnisse  

### 2.6. Debug-Bereich & Logging

- **Log-Level**  
  - Einstellbar: `Keine` | `Info` | `Debug (Verbose)`  
  - Speicherung von Prompt, Antwortzeit, Fehlermeldungen, etc.  

- **Detailansichten**  
  - Anzeige pro Job/Beitrag aller relevanten Daten (Prompt, Response, Zeitstempel)  
  - Download-/Export-Funktion (z. B. CSV oder Text)  

---

## 3. Nicht-funktionale Anforderungen

1. **WordPress.org-Standards**  
   - Einhaltung der WP-Coding-Standards  
   - Übersetzungsfunktionen (`__()`, `_e()`, etc.)  
   - sichere Nutzung von WordPress-Hooks und Filtern  

2. **Sicherheit**  
   - Sichere Speicherung des OpenAI-/Deepseek-API-Schlüssels  
   - Validierung aller Eingaben (XSS, CSRF, SQL-Injection)  

3. **Performance**  
   - Minimale Frontend-Belastung  
   - Caching von Zwischenergebnissen (z. B. generierten Inhalten)  
   - Asynchrone Queue/Batch-Verarbeitung  

4. **Kompatibilität**  
   - WordPress >= 5.x  
   - PHP >= 7.4  
   - Unterstützung gängiger Themes und Page-Builder (Gutenberg, Elementor, Divi)  

---

## 4. Datenstruktur

- **Posts**  
  - Keine eigene Datenbankstruktur, Nutzung der WP-Core-Struktur (`wp_posts`)  
  - Ablage generierter Inhalte als Beitrag mit Status `draft` oder `publish`  

- **Post Meta**  
  - Speicherung relevanter Metadaten (Prompt, genutztes Modell, Deepseek-Infos, Zeitstempel)  

- **Options / Settings**  
  - Option für API-Schlüssel (OpenAI, Deepseek)  
  - Auswahl des KI-Modells, Log-Level, Sprachoptionen  

---

## 5. Implementierungsschritte (Checkliste)

1. **Grundgerüst und Plugin-Struktur aufsetzen**  
   - `deepblogger.php` als Hauptdatei  
   - Ordnerstruktur: `/admin/`, `/includes/ai/`, `/includes/deepseek/`, `/languages/`, `/assets/`  

2. **Admin-Oberfläche erstellen**  
   - Menüeinträge für:  
     - **Einstellungen** (API Keys, Modelle, Sprachoptionen, Log-Level)  
     - **KI-Generierung** (Kategorien wählen, Sofortgenerierung, Queue-Jobs)  
     - **Deepseek** (separates Dashboard/Reiter)  
     - **Log/Debug** (Auflistung von Protokollen)  

3. **OpenAI-Anbindung**  
   - Einrichtung der API-Kommunikation (z. B. via OpenAI-PHP-SDK oder cURL)  
   - Prompt-Generierung (Einfügen von Kategorien, Keywords, Stilvorgaben)  
   - Verarbeitung der Antwort (Textaufbereitung, HTML, Formatierung)  

4. **Deepseek-Integration**  
   - Eigene API-Kommunikation zu Deepseek-Services (oder direkter Zugriff auf OpenAI, falls integriert)  
   - Ergebnisdarstellung (Themen/Keyword-Empfehlungen, Trends)  

5. **Bild-Generierung (DALL·E oder andere)**  
   - Prompt basierend auf Beitragstitel/Thema  
   - Bild-Daten empfangen und als WP-Attachment anlegen  
   - Setzen als Featured Image  

6. **Test-Funktion (Sofort-Generierung)**  
   - Admin-Seite: „Jetzt Beitrag generieren“  
   - Direkter API-Call, Darstellung in einer Vorschau (Modal oder eigenständige Seite)  

7. **Scheduler und Queue**  
   - WP-Cron oder eigene Queue-Lösung implementieren  
   - Verwaltung mehrerer Generierungsjobs (Statuseinstellung, Fortschrittsanzeige)  

8. **Debug & Logging**  
   - Implementierung einer Log-Klasse oder Nutzung der WP-Logging-Funktionalität  
   - Speichern von Zeitstempeln, Prompt, Response, Fehlercodes in einer Log-Tabelle oder in `wp_options` / `.log` Dateien  
   - Admin-Interface zur Einsicht und Filterung von Logs  

9. **Qualitätssicherung & Veröffentlichung**  
   - Code Review und Testläufe (lokale/Staging-Umgebung)  
   - Einhaltung von WordPress.org-Richtlinien (Sicherheit, Internationalisierung)  
   - Hochladen/Veröffentlichen im Plugin-Repository  

---

## 6. Zusammenfassung

Das DeepBlogger-Plugin vereint KI-basierte Inhalts- und Bildgenerierung sowie eine Deepseek-Integration für erweiterte Recherche- und Analysefunktionen. Durch eine Kombination aus Sofort- und geplanter Generierung (Queue) deckt es sowohl schnelle Tests als auch automatisierte Workflows ab. Detaillierte Logs und eine Debug-Funktion unterstützen bei der Fehlersuche und Optimierung. Mit dem oben skizzierten Funktionsumfang und den Implementierungsschritten steht einer erfolgreichen Veröffentlichung im WordPress.org-Plugin-Repository nichts im Wege.
