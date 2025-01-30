# DeepBlogger - WordPress ChatGPT Auto Poster

Ein WordPress-Plugin zur automatischen Generierung und Veröffentlichung von Blog-Beiträgen mit ChatGPT.

## Features

- Automatische Generierung von Blog-Beiträgen mit ChatGPT
- Konfigurierbare Veröffentlichungsintervalle (täglich, wöchentlich, monatlich)
- SEO-Optimierung mit automatischer Metadaten-Generierung
- Kategoriezuweisung und Themenverwaltung
- Admin-Benachrichtigungen per E-Mail
- Fehlerprotokollierung und -behandlung

## Installation

1. Laden Sie das Plugin-Verzeichnis in das `/wp-content/plugins/` Verzeichnis hoch
2. Aktivieren Sie das Plugin über das 'Plugins' Menü in WordPress
3. Gehen Sie zu den Plugin-Einstellungen und konfigurieren Sie Ihren OpenAI API-Schlüssel

## Konfiguration

### OpenAI API-Schlüssel

1. Besuchen Sie [OpenAI](https://platform.openai.com/) und erstellen Sie einen Account
2. Generieren Sie einen API-Schlüssel
3. Fügen Sie den API-Schlüssel in den Plugin-Einstellungen ein

### Beitragsplanung

- Wählen Sie das gewünschte Veröffentlichungsintervall (täglich, wöchentlich, monatlich)
- Legen Sie die Themen fest, zu denen Beiträge generiert werden sollen
- Wählen Sie die Kategorien aus, denen die Beiträge zugeordnet werden sollen

## Entwicklung

### Voraussetzungen

- PHP 7.4 oder höher
- WordPress 5.0 oder höher
- Composer

### Setup

1. Klonen Sie das Repository:
```bash
git clone https://github.com/andreasostheimer/deepblogger.git
```

2. Installieren Sie die Abhängigkeiten:
```bash
composer install
```

### Tests ausführen

```bash
composer test
```

### Code-Standards prüfen

```bash
composer phpcs
```

### Code-Standards automatisch korrigieren

```bash
composer phpcbf
```

## Lizenz

Dieses Plugin ist unter der GPL v2 oder später lizenziert - siehe die [LICENSE](LICENSE) Datei für Details.

## Autor

Andreas Ostheimer
- GitHub: [@andreasostheimer](https://github.com/andreasostheimer)

## Changelog

### 1.0.0
- Erste Version mit grundlegenden Funktionen
- ChatGPT Integration
- Beitragsplanung
- SEO-Optimierung
- Admin-Interface

## Support

Bei Fragen oder Problemen erstellen Sie bitte ein [GitHub Issue](https://github.com/andreasostheimer/deepblogger/issues). 