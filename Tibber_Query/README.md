# IP-Symcon Modul: Tibber Query
 
Die Nutzung des Moduls geschieht auf eigene Gefahr ohne Gewähr.

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang) 
2. [Systemanforderungen](#2-systemanforderungen)
3. [Installation](#3-installation)
4. [Einrichten der Instanz](#4-einrichten-der-instanz)
5. [ChangeLog](#5-changelog)

## 1. Funktionsumfang

Es handelt sich hierbei um einen frühen Entwicklungsstand.

Mit diesem Modul können die Informationen abgerufen werden welche von der Tibber Query API bereitgestellt werden. Folgende Informationen sind im Modul implementiert:

### 1.1 Day Ahead Preise

Das Modul fragt die Day Ahead Preise für den aktuellen und (wenn schon publiziert) für den Folgetag ab und speichert dies rückwirkend (da Symcon keine zukünftigen Werte im Archiv erlaubt) in der "Day Ahead Preis Hilfsvariable" Variablen ab. Dabei wird der aktuelle Tag mit T -2 und der morgige Tag mit T -1 ins Archiv gespeichert.
Zusätzlich wird automatisch ein Multi-Chart angelegt, welcher diese beiden Tage im stündlichen Vergleich über die beiden Tagen darstellt.

Die Abfrage erfolgt bei jedem ändern der Instanz und wird dann per Timer je nach aktueller Zeit auf 0:00 Uhr oder wenn vor 13 Uhr am Tag auf 13 Uhr getzt. Da nicht jeden Tag um 13 Uhr die Werte des Folgetages schon veröffentlicht sind, prüft das Modul ob schon Werte für den Folgetag vorhanden sind. Ist das nicht der Fall wird ein neuer Abruf 5 Minuten später erneut eingeplant. Die wird wiederholt bis die Werte geliefert wurden.

### 1.1.1 Aktueller Stundenpreis & Preislevel
Zu beginn jeder Stunde wird sowohl der aktuelle Preis und der von Tibber bereitgestellte Preislevel in die Variablen "Aktueller Preis" und "Aktueller Preis Level"

## 2. Systemanforderungen
- IP-Symcon ab Version 6.0
- Tibber Account
- Token aus dem eigenen Tibber-Account

## 3. Installation

Das Modul ist Bestandteil der Tibber Library und wird somit bei der Installation im Module Store mit installiert.

## 4. Einrichten der Instanz

Nach dem anlegen der Instanz muss die Instanz konfiguriert werden.
Dazu muss der Token (aus dem Tibber Account) eingetragen werden. Danach muss die Änderung der Instanz gespeichert werden.

Ist dies geschehen, wird automatisiert die Liste der im Tibber Account vorhanden Häuser abgerufen, welche im "Heim ID" Feld dann zur Auswahl bereit gestellt werden.
Hier dann das gewünschte Haus auswählen.

### 4.1 Preisdaten Einstellungen

#### 4.1.1 Preisdatenvariablen Loggen
Diese Checkbox muss aktiviert werden, wenn die Day Ahead Preise im Archiv gespeichert sowie der Multi-Chart erzeugt werden sollen.

#### 4.1.2 Preisdatenvariablen pro Stunde anlegen
Wird diese Checkbox aktivert, werden 48 Variablen ( 24 für den aktuellen Tag und 24 für den Folgetag) für jede Stunde angelegt, welche beim Abruf der Day Ahead Preise aktulaisert werden.

## 5. ChangeLog
Änderungshistorie

### Version 0.1 Test
* Initialer Commit
  