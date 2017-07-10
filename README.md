[![Build Status](https://travis-ci.org/Blog404DE/RegenRadarVideo.svg?branch=develop)](https://travis-ci.org/Blog404DE/RegenRadarVideo)

# Regen-Radar Script

> **Wichtige Änderung:** Die Konfigurations-Datei der aktuellste Version des Wetterwarnung Downloader ist nicht kompatibel zur Konfigurations-Datei der bisherigen Version 1.x. Weitere Informationen hierzu finden Sie hier in der README.md. 


## Einleitung

Das Regenradar-Script dient zum erstellen von Videos bzw. animierten GIF-Dateien anhand der vom Deutschen Wetterdienst im Rahmen der Grundversorgung angebotenen Regenradar-Bilder für ganz Deutschland bzw. einzelnen Regionen innerhalb von Deutschland. Details zur Grundversorgung finden sich auf der [NeuthardWetterScripts Hauptseite](https://github.com/Blog404DE/NeuthardWetterScripts).

## Anleitung für Regen Radar

### Vorraussetzungen:

- Linux (unter Debian getestet)
- PHP 5.6 (oder neuer) mit aktiviertem FTP-Modul
- ffmpeg oder libav-tools installiert (für mp4/webm-Videos)
- Imagemagick (für animierte gif-datei)
- Shell-Zugriff zum einrichten eines Cronjob

### Vorbereitung:

1. Installation der zusätzlich zu php benötigten Pakete:

	Debian/Ubuntu/Mint:
	
	```bash
	apt-get update
	apt-get install imagemagick ffmpeg
	```
	Sollten Sie eine Meldung bekommen, dass ffmpeg nicht verfügbar ist (bei Debian Jessie / old-stable) können Sie stattdessen ffmpeg-Fork *libav-tools* verwenden.

	RHEL/CentOS/Fedora
	
	```bash
	yum install ImageMagick ffmpeg
	```

### Konfiguration *(neu)*:

Bei dem eigentlichen Script zum abrufen der Wetter-Warnungen handelt es sich um die Datei ```genRegenRadar.php```. Das Script selber wird gesteuert über die ```config.local.php``` Datei. Um diese Datei anzulegen, kopieren Sie bitte ```config.sample.php``` und nennen die neue Datei ```config.local.php```.

Die anzupassenden Konfigurationsparameter in der *config.local.php* lauten wie folgt:

1. FTP Zugangsdaten für den Zugriff auf den DWD FTP Server.

	```php
	// FTP Zugangsdaten:
	$ftp["host"]        = "ftp-outgoing2.dwd.de";
	$ftp["username"]    = "gds******";
	$ftp["password"]    = "*********";
	```

	Die benötigten Zugangsdaten und den Hostnamen wird vom DWD per E-Mail nach der Registrierung (siehe Vorbereitung) mitgeteilt. Bei ```$ftp["username"]``` handelt es sich um den Benutzername und bei ```$ftp["password"]``` um das zugeteilte Passwort. Der Hostname ```$ftp["hostname"]``` muss in der Regel nicht angepasst werden.
	
2. Pfade zu den für das erstellen der Videos benötigten Konsolen-Programme 

	```php
	// Pfade zu Konsolenprogramme:
	$converter["video"] = "/usr/bin/ffmpeg";
	$converter["gif"]   = "/usr/bin/convert";
	```	

	Für ```$converter["video"]``` benötigt man den Pfad zur libav-tool oder ffmpeg Binary. Dies wird benötigt zum erstellen der webm/mp4-Videos.
	
	```$converter["gif"]``` benötigt den Pfad zum convert-Tool aus dem Imagemagick-Paket. Diese Binary dient zum erstellen der animierten GIF-Datei.
	
	Möchte man z.B. keine animierte GIF Datei erzeugen, so empfiehlt sich anstatt des Pfad *false* als Wert zu hinterlegen: 
	```$converter["gif"] = false;```
	
3. Konfiguration der zu erstellenden Video-Dateien (Array):

	```php
$config[] = array(	"remoteFolder"  => "/gds/gds/specials/radar",
                  		"localFolder"   => "/srv/webspacepfad/radarDaten/de",
						"runtimeHour"   => 4,
						"output"        => array(	"webm" => "/srv/webspacepfad/htdocs/img/regenradar_de.webm",
                                           			"mp4"  => "/srv/webspacepfad/htdocs/img/regenradar_de.mp4",
													"gif"  => "/srv/webspacepfad/htdocs/img/regenradar_det.gif"),
						"posterFile"    => "/srv/webspacepfad/htdocs/img/regenradar_de.jpg",
						"forceRebuild"  => false
                  );
	```	
	Der Array-Wert ```"remoteFolder"``` für beinhaltet der Pfad auf dem DWD FTP Server welches die Radar-Daten beinhaltet. Der beispielhaft hinterlegte Pfad beinhaltet der Regenradar-Bilder für Deutschland. Es existieren in diesem Pfad auch Unterordner für einzelne Regionen innerhalb von Deutschland wie z.B. */gds/gds/specials/radar/southwest* für Süd/Westen von Deutschland (z.B. Baden-Würrtemberg).
	
	Als Gegenstück zum Pfad auf dem FTP Server dient ```"localFolder"```. Dieser Array-Wert beinhaltet ein lokaler Ordner, in dem die benötigten einzelnen Radar-Bilder durch das Script gespeichert werden. 
	
	Mit dem Array-Wert ```"runtimeHour"``` wird hinterlegt, wieviele Stunden das Radar-Video zurück gehen soll. Erzeugt man zum Beispiel das Radar-Video um 18:15 und gibt als ```runtimeHour``` *4* Stunden an, dann werden für das Video die Radar-Bilder von 14:00 Uhr bis 18:15 verwendet. Der Start-Zeitpunkt ist dabei aktuell immer die jeweilige volle Stunde (noch).
	
	```"output"``` ist der Dreh- und Angelpunkt für das erstellen der Videos und beinhaltet ein Array welches einerseits beinhaltet für welches Format (webm, mp4, gif) die Videos erzeugt werden sollen und den Ziel-Pfad in dem die Datei jeweils gespeichert werden soll. Im Beispiel werden die Videos in allen 3 verfügbaren Formate erstellt. Falls Sie z.B. die animierte GIF Datei nicht benötigen hinterlegen Sie anstatt des Zielpfad einfach *false*.
	
	Der vorletzte Konfigurationsparameter ```"posterFile"``` dient zum getrennten speichern des ersten im Video verwendeten Radar-Bild. Einige HTML5 Video-Player wie z.b. <http://www.videojs.com> verwenden eine Grafik für die schnelle Darstellung einer Grafik im Videoplayer noch bevor das Video heruntergeladen wurde. Sollte eine solche Poster-Datei nicht benötigt werden, verwenden Sie auch hier *false* als Parameter.
	
	```"forceRebuild"``` dient ausschließlich zu Test-Zwecken und dient dazu das Script anzuweisen auf jeden Fall alle Videos neu zu erstellen unabhängig davon, ob neue Radar-Bilder hinzugekommen sind. Standardmäßig sollte dieser Parameter auf *false* stehen.
	
	**Hinweis:** Um Videos für mehrere Bereiche in Deutschland zu erstellen, können Sie das ```$config[]``` Array entsprechend um weitere Einträge erweitern. Beispielhaft sind in der Beispiel-Konfiguration zwei Array-Elemente enthalten - jeweils für Süd/West-Deutschland und Gesamt-Deutschland.
	

### Das PHP-Script ausführbar machen und als Cronjob hinterlegen

1. Das konfigurierte Scripte startfähig machen

	```sh
	chmod +x genRegenRadar.php
	```
	
2. Shell-Script für den Aufruf als Cronjob. Ein direkter Aufruf bietet sich nicht an, da es ansonsten zu parallelen Aufruf des Scripts kommen kann. Dies kann dabei zu unerwünschten Effekten führen bis zum kompletten hängen des Systems. 

	Um dies zu verhindern bietet sich die Verwendung einer Lock-Datei an, wie in folgendem Beispiel exemplarisch gezeigt:
	
	```bash
	#!/bin/bash
	LOCKFILE=/tmp/$(whoami)_$(basename $0).lock
	[ -f ${LOCKFILE} ] && { echo "$(basename $0) läuft schon"; exit 1; }

	lock_file_loeschen() {
    	    rm -f ${LOCKFILE}
	}

	trap "lock_file_loeschen ; exit 1" 2 9 15

	# Lock-Datei anlegen
	echo $$ > ${LOCKFILE}


	# Starte Script
	/pfad/zum/script/genRegenRadar.php

	# Lösche Lockfile
	lock_file_loeschen
	exit 0
	```

	In diesem Script müssen Sie selbstverständlich den Pfad zum Regenrader-Script entsprechend anpassen.

	
	Als Update-Frequenz für die Videos hat sich alle 15 Minuten herausgestellt, auch wenn der DWD alle 5 Minuten neue Bilder hinterlegt. Bei der gewünschten Update-Frequenz sollte beachtet werden, dass das erzeugen der Videos je nach System einige Zeit beansprucht (insbesondere die animierte GIF Datei). Für ein ausführen des Cronjob alle 15 Minuten würde die Cronjob-Zeile wie folgt aussehen:
	```*/15 * * * * /pfad/zum/script/cron.genRegenRadar.sh```, wobei hier der Pfad zum Shell-Script aus Schritt 2 angepasst werden muss.
	

--
##### Lizenz-Information:

Copyright Jens Dutzi 2015-2017 / Stand: 10.07.2017 / Dieses Werk ist lizenziert unter einer [MIT Lizenz](http://opensource.org/licenses/mit-license.php)

