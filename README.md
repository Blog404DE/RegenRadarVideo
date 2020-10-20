[![Build Status](https://travis-ci.org/Blog404DE/RegenRadarVideo.svg?branch=master)](https://travis-ci.org/Blog404DE/RegenRadarVideo)

# Regen-Radar Script

## Wichtiger Hinweis

**Mit der Einstellung des Grundversorgungs-Zugang des DWD und der Umstellung auf OpenData wird die Version bis einschließlich 2.x nicht mehr unterstützt. Bitte laden Sie daher unter "Releases" die Version 3.0.0 oder neuer bzw. das "Develop"-Branch um die OpenData-Unterstützung zu erhalten.**

## Einleitung

Das Regenradar-Script dient zum erstellen von Videos bzw. animierten GIF-Dateien anhand der vom Deutschen Wetterdienst im Rahmen der Grundversorgung angebotenen Regenradar-Bilder für ganz Deutschland bzw. einzelnen Regionen innerhalb von Deutschland. Details zur Grundversorgung finden sich auf der [NeuthardWetterScripts Hauptseite](https://github.com/Blog404DE/NeuthardWetterScripts).

## Anleitung für Regen Radar

### Vorraussetzungen:

- Linux (unter Debian getestet)
- PHP 7.1 (oder neuer) mit aktiviertem curl-Modul
- ffmpeg oder libav-tools installiert (für mp4/webm-Videos)
- Shell-Zugriff zum einrichten eines Cronjob

### Vorbereitung:

1. Installation der zusätzlich zu php benötigten Pakete:

	Debian/Ubuntu/Mint:

	```bash
	apt-get update
	apt-get install ffmpeg
	```
	Sollten Sie eine Meldung bekommen, dass ffmpeg nicht verfügbar ist (bei Debian Jessie / old-stable) können Sie stattdessen ffmpeg-Fork *libav-tools* verwenden.

	RHEL/CentOS/Fedora

	```bash
	yum install ffmpeg
	```
2. Notwendige Librarys über Composer/Packagist in der Stable-Version laden

	```bash
	composer create-project --no-dev blog404de/regenradarvideo
	```

### Konfiguration *(neu)*:

Bei dem eigentlichen Script zum abrufen der Wetter-Warnungen handelt es sich um die Datei ```genRegenRadar.php```. Das Script selber wird gesteuert über die ```config.local.php``` Datei. Um diese Datei anzulegen, kopieren Sie bitte ```config.sample.php``` und nennen die neue Datei ```config.local.php```.

Die anzupassenden Konfigurationsparameter in der *config.local.php* lauten wie folgt:

1. Pfade zu den für das erstellen der Videos benötigten Konsolen-Programme

	```php
	// Pfade zu Konsolenprogramme:
	$converter["video"] = "/usr/bin/ffmpeg";
	$converter["gif"]   = "copy";
	```

	Für ```$converter["video"]``` benötigt man den Pfad zur libav-tool oder ffmpeg Binary. Dies wird benötigt zum erstellen der webm/mp4-Videos.

	```$converter["gif"]``` unterstützt zur Zeit nur die "copy"-Methode, daher darf für die gif-Unterstützung der Konfigurationsparameter nicht verändert werden, es sei denn, man möchte keine GIF Datei erzeugen. Hierfür muss der Parameter auf folgenden Wert verändert werden:
	```$converter["gif"] = false;```

2. Konfiguration der zu erstellenden Video-Dateien (Array):

    Das Array beinhaltet beinhaltet die Konfiguration für die einzelnen Regenradar-Videos die erzeugt werden sollen (z.B. für Deutschland und/oder einzelne Bundesländer).

	```php
	$config[] = [
		"remoteURL" => "https://www.dwd.de/DWD/wetter/radar/radfilm_brd_akt.gif",
		"posterURL" => "https://www.dwd.de/DWD/wetter/radar/rad_brd_akt.jpg",
		"localFolder"  => "/srv/webspacepfad/tmp/radarDaten/de",
		"output"       => [
			"webm" => "/srv/webspacepfad/htdocs/img/regenradar_de.webm",
			"mp4"  => "/srv/webspacepfad/htdocs/img/regenradar_de.mp4",
			"gif"  => "/srv/webspacepfad/htdocs/img/regenradar_de.gif",
			"poster" => "/srv/webspacepfad/htdocs/img/regenradar_southwest.de",
		],
		"forceRebuild"  => false
	];
	```

	Der Array-Wert ```"remoteURL"``` beinhaltet die URL der entsprechenden Video-Datei des DWD. Diese kann über die DWD-Homepage unter https://www.dwd.de/DE/leistungen/radarbild_film/radarbild_film.html ermittelt werden. Hierzu wählen Sie auf der genannten Seite Deutschland oder das benötigte Bundesland aus und klicken danach unterhalt der Grafik auf "Radarfilm". Nachdem der Radarfilm geladen ist, klicken Sie diesen mit der rechten Maustaste an und gehen auf *Bildadresse kopieren* (Safari/Chrome) bzw. *Grafikadresse kopieren* (Firefox).

	Der zweite Array-Wert ```"posterURL"``` beinhaltet wiederum die URL des letzten Radarbilds, welches ebenfalls auf der DWD-Homepage angeboten wird. Die Schritte sind dabei ähnlich wie bei dem ermitteln der Video-URL. Einzig anstatt das Tab "Radarfilm" muss "Radarbild" ausgewählt werden. Das bestimmen der URL zur Grafik erfolgt analog zum vorherigen Schritt.

	Als Gegenstück zum Pfad auf dem FTP Server dient ```"localFolder"```. Dieser Array-Wert beinhaltet ein lokaler Ordner, in dem das heruntergeladene Radarvideo zwischengespeichert wird.

	```"output"``` ist der Dreh- und Angelpunkt für das erstellen der Videos und beinhaltet ein Array welches einerseits beinhaltet für welches Format (webm, mp4, gif) die Videos erzeugt werden sollen und den Ziel-Pfad in dem die Datei jeweils gespeichert werden soll. Im Beispiel werden die Videos in allen 3 verfügbaren Formate erstellt. Falls Sie z.B. die animierte GIF Datei nicht benötigen hinterlegen Sie anstatt des Zielpfad einfach *false*.

	Innerhalb des gleichen Array findet sich neben webm/mp4 und gif auch noch das Ausgabe-Format *"poster"*. Hierbei handelt es sich um den Ausgabe-Pfad der Poster-Grafik für das verwendete Radar-Bild. Sollte eine solche Poster-Datei nicht benötigt werden, verwenden Sie auch hier *false* als Parameter.

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

Copyright Jens Dutzi 2015-2020 / Stand: 20.10.2020 / Dieses Werk ist lizenziert unter einer [MIT Lizenz]
(http://opensource.org/licenses/mit-license.php)

