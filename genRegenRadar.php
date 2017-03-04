#!/usr/bin/env php
<?php
/*
 * DWD-Radar Video Konverter für neuthardwetter.de by Jens Dutzi
 * Version 1.5.1
 * 11.03.2016
 * (c) tf-network.de Jens Dutzi 2012-2016
 *
 * Lizenzinformationen (MIT License):
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this
 * software and associated documentation files (the "Software"), to deal in the Software
 * without restriction, including without limitation the rights to use, copy, modify,
 * merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies
 * or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
 * PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

// FTP Zugangsdaten:
$ftp["host"]        = "ftp-outgoing2.dwd.de";
$ftp["username"]    = "gds*****";
$ftp["password"]    = "********";

// Pfade zu Konsolenprogramme:
$converter["video"] = "/usr/bin/ffmpeg";
$converter["gif"]   = "/usr/bin/convert";

// Zu bearbeitende DWD-Radar-Daten:
$config = array();
$config[] = array(	"remoteFolder"  => "/gds/gds/specials/radar/southwest",
                  	"localFolder"   => "srv/webspacepfad/radarDaten/bw",
					"frames"        => "30",
					"output"        => array(	"webm" => "/srv/webspacepfad/htdocs/img/regenradar_southwest.webm",
                                           		"mp4"  => "/srv/webspacepfad/htdocs/img/regenradar_southwest.mp4",
                                           		"gif"  => "/srv/webspacepfad/htdocs/img/regenradar_southwest.gif"),
					"posterFile"    => "/srv/webspacepfad/htdocs/img/regenradar_southwest.jpg",
					"forceRebuild"  => false
                  );

$config[] = array(  "remoteFolder"  => "/gds/gds/specials/radar",
                    "localFolder"   => "/srv/webspacepfad/radarDaten/de",
                    "frames"        => "30",
                    "output"        => array(   "webm" => "/srv/webspacepfad/htdocs/img/regenradar_de.webm",
                                                "mp4"  => "/srv/webspacepfad/htdocs/img/regenradar_de.mp4",
                                                "gif"  => "/srv/webspacepfad/htdocs/img/regenradar_det.gif"),
                    "posterFile"    => "/srv/webspacepfad/htdocs/img/regenradar_de.jpg",
                    "forceRebuild"  => false
              );

/*
 * ================================================================================================
 * Start des Scripts
 * ================================================================================================
 */

// Prüfe vorraussetzungen
if(!extension_loaded("FTP")) {
	fwrite(STDERR, "PHP FTP Modul steht nicht zur Verfügung" . PHP_EOL);
	exit(1);
}
if($converter["video"] !== false) {
	if(!is_executable($converter["video"])) {
		fwrite(STDERR, "ffmpeg/libavtools Binary steht unter " . $converter["video"] . " nicht zur verfügung." . PHP_EOL);
		exit(1);
	}
}
if($converter["gif"] !== false) {
	if(!is_executable($converter["gif"])) {
		fwrite(STDERR, "convert Binary von Imagemagick steht unter " . $converter["gif"] . " nicht zur verfügung." . PHP_EOL);
		exit(1);
	}
}

// FTP-Verbindung aufbauen
$conn_id = ftp_connect($ftp["host"]);
if($conn_id === false) {
    fwrite(STDERR, "FTP Verbindungsaufbau zu " . $ftp["host"] . " ist fehlgeschlagen" . PHP_EOL);
    exit(1);
}

// Login mit Benutzername und Passwort
$login_result = @ftp_login($conn_id, $ftp["username"], $ftp["password"]);
ftp_pasv($conn_id, true);

// Verbindung überprüfen
if ((!$conn_id) || (!$login_result)) {
    fwrite(STDERR, "Verbindungsaufbau zu zu " . $ftp["host"] . " mit Benutzername " . $ftp["username"] . " fehlgeschlagen." . PHP_EOL);
    exit(2);
} else {
    echo "Verbunden zu " .$ftp["host"] . " mit Benutzername " .$ftp["username"] . PHP_EOL;
}

// Erzeuge Radar-Videos
foreach ($config as $value) {
    echo("Erzeuge Radar-Video aus dem Verzeichnis " . $value["remoteFolder"] . PHP_EOL);
    createRadarVideo($conn_id,  $value, $converter);
    echo(PHP_EOL . "... Auftrag ausgeführt!". PHP_EOL . PHP_EOL);
}

ftp_close($conn_id);

/*
 * Funktion zum herunterladen der Radar-Daten und kompilieren des Videos
 */
function createRadarVideo($conn_id, $config, $converter) {
    // Prüfe Existenz der lokalen Verzeichnisse
    if(!is_writable($config["localFolder"])) {
        echo("Benötigte Verzeichnisse " . $config["localFolder"] . " ist nicht beschreibbar" . PHP_EOL);
        exit(1);
    } else {
        if(!file_exists($config["localFolder"] . "/frames")) {
            if(!mkdir($config["localFolder"] . "/frames")) {
                fwrite(STDERR, "Konnte das fehlende Verzeichnis " . $config["localFolder"] . "/frames nicht anlegen");
                exit(1);
            }
        }
    }
    foreach(array($config["output"]) as $testname) {
        if($testname !== false && empty($testname)) {
            if(file_exists($testname)) {
                if(!is_writable($testname)) {
                    fwrite(STDERR, "Benötigte Ziel-Datei " . $testname . " ist nicht überschreibbar" . PHP_EOL);
                    exit(1);
                }
            } else {
                if(!is_writeable(dirname($testname))) {
                    fwrite(STDERR, "In Ziel-Datei Ordner " . dirname($testname) . " kann keine Datei angelegt werden");
                    exit(1);
                }
            }
        }
    }
    if(!empty($config["posterFile"]) && $config["posterFile"] !== false) {
        if(file_exists($config["posterFile"])) {
            if(!is_writable($config["posterFile"])) {
                fwrite(STDERR, "Benötigte Ziel-Datei " . $config["posterFile"] . " ist nicht überschreibbar" . PHP_EOL);
                exit(1);
            }
        } else {
            if(!is_writeable(dirname($config["posterFile"]))) {
                fwrite(STDERR, "In Ziel-Datei Ordner " . dirname($config["posterFile"]) . " kann keine Datei angelegt werden" . PHP_EOL);
                exit(1);
            }
        }
    }

    // Versuche, in das benötigte Verzeichnis zu wechseln
    if (ftp_chdir($conn_id, $config["remoteFolder"])) {
        echo "Aktuelles Verzeichnis: " . ftp_pwd($conn_id)  . PHP_EOL;
    } else {
        fwrite(STDERR, "Verzeichniswechsel ist fehlgeschlagen." . PHP_EOL);
        exit(2);
    }

    // Verzeichnisliste auslesen und sortieren
    $arrFTPContent = ftp_nlist($conn_id, ".");
    if(count($arrFTPContent)==0 || $arrFTPContent === false) {
        fwrite(STDERR, "Auslesen des Verezichnis " . $config["remoteFolder"] . " fehlgeschlagen." . PHP_EOL);
        exit(3);
    }

    // Zeit-Filter erzeugen (-> damit nur die Grafiken der letzten 3h verarbeitet werden)
    $searchTime = new DateTime();
    $searchTime->setTimezone(new DateTimeZone('GMT'));
    $fileFilter = array($searchTime->format("Ymd_H"),
                        $searchTime->modify("-1 hour")->format("Ymd_H"),
                        $searchTime->modify("-1 hour")->format("Ymd_H"));

    echo("Erzeuge Download-Liste:" . PHP_EOL);
    $arrDownloadList = array();
    foreach ($arrFTPContent as $filename) {
       // Laufe Filter durch
        foreach ($fileFilter as $filter) {
            if(strpos($filename, $filter) !== false) {
                // Übernehme Datei in zu-bearbeiten Liste
                $fileDate = ftp_mdtm($conn_id, $filename);
                echo ($filename . " => " . date("d.m.Y H:i", $fileDate) . PHP_EOL);
                $arrDownloadList[$filename] = $fileDate;
            }
        }
    }

    // Dateiliste sortieren
    arsort($arrDownloadList, SORT_NUMERIC);
    array_splice($arrDownloadList, 20);

    // Symlink-Verzeichnis leeren
    echo(PHP_EOL . "Setze Symlink-Verzeichnis zurück" . PHP_EOL);
    array_map('unlink', glob($config["localFolder"] . "/frames/frame*.jpg"));
    clearstatcache();

    // Entferne veraltete Dateien aus dem Download-Verzeichnis
    echo(PHP_EOL . "Führe Bereinigung durch:" . PHP_EOL);
    $arrLocalFiles = glob($config["localFolder"] . "/Webradar_Suedwest_*.jpg");
    if($arrLocalFiles === false) {
        fwrite(STDERR, "Fehler beim bereinigen der alten Dateien - generiere daher keine neue Regen-Animation" . PHP_EOL);
        exit(3);
    } else {
        arsort($arrLocalFiles);
        $arrOldFiles = array_splice($arrLocalFiles, 20);
        if(count($arrOldFiles) > 0) {
            foreach ($arrOldFiles as $delFile) {
                echo "Lösche veraltete Datei: " . $delFile . PHP_EOL;
                unlink($delFile);
            }
            $needRebuild = true;
        } else {
            echo "Keine Dateien zum bereinigen gefunden" . PHP_EOL;
        }
    }

    // Beginne Download
    echo(PHP_EOL . "Starte den Download von " . count($arrDownloadList) . " Radar-Dateien" . PHP_EOL);

    $needRebuild = false;
    foreach ($arrDownloadList as $filename => $filetime) {
        $localFile = $config["localFolder"] . "/" . $filename;

        if(!file_exists($localFile)) {
            // Öffne lokale Datei
            $handle = fopen($localFile, 'w');

            if (ftp_fget($conn_id, $handle, $filename, FTP_BINARY, 0)) {
                echo "Datei " . $localFile . " wurde erfolgreich heruntergeladen." . PHP_EOL;
                $needRebuild = true;
            } else {
                echo "Datei " . $filename . " Download ist fehlgeschlagen. " . PHP_EOL;
            }

            // Schließe Datei-Handle
            fclose($handle);
        } else {
            echo "Datei " . $localFile . " existiert bereits und muss nicht neu geladen werden" . PHP_EOL;
        }
    }

    // Symlinks neu erzeugen
    echo (PHP_EOL . "Erzeuge Symlinks für das erstellen der Animation" . PHP_EOL);
    $reverseArrDownloadList = array_reverse($arrDownloadList, true);
    $filenr = 0;
    foreach ($reverseArrDownloadList as $filename => $filetime) {
        $symlink = $config["localFolder"] . "/frames/frame" . str_pad($filenr, 3, "0", STR_PAD_LEFT) . ".jpg";
        echo("Erzeuge Symlink für " . $filename . " auf " . $symlink . PHP_EOL);

        if (!is_link($symlink)) {
            symlink("../" . $filename, $symlink);
        } else {
            fwrite(STDERR, "Abbruch: Symlink existiert bereits" . PHP_EOL);
            exit(4);
        }

        $filenr++;
    }

    // Poster-File kopieren
    if(!empty($config["posterFile"]) && $config["posterFile"] !== false) {
        reset($reverseArrDownloadList);
        $lastImage = $config["localFolder"] . "/" . key($reverseArrDownloadList);
        echo(PHP_EOL . "Kopiere Poster-File " . key($reverseArrDownloadList) . " für Videos in Webspace-Image Ordner" . PHP_EOL);
        if(!copy($lastImage, $config["posterFile"])) {
            fwrite(STDERR, "Abbruch: Kopieren des Poster-File " . key($reverseArrDownloadList) . " fehlgeschlagen" . PHP_EOL);
            exit(5);
        } else {

        }
    }

    // Radar-Animationen bei Bedarf erzeugen
    foreach ($config["output"] as $filetype => $filename) {
        if(!empty($filename) || $filename !== false) {
            if(!file_exists($filename)) $needRebuild = true;
        }

        // Leerzeile ausgeben
        echo PHP_EOL;

        if($filetype == "gif" && $converter["gif"] == false) {
        	fwrite(STDERR, "Fehler beim anlegen des Konvertierungs-Auftrags - Binary für " . $converter["gif"] . " wurde nicht konfiguriert." . PHP_EOL);
        	exit(6);
        } else if(($filetype == "webm" || $filetype == "mp4") && $converter["video"] == false) {
        	fwrite(STDERR, "Fehler beim anlegen des Konvertierungs-Auftrags - Binary für " . $converter["video"] . " wurde nicht konfiguriert." . PHP_EOL);
        	exit(6);
        } else {
	        // Animation neu erzeugen
	        if($needRebuild || $config["forceRebuild"]) {
	            echo("Erzeuge " . $filetype . "-Video aus den Radar-Daten" . PHP_EOL);
	            if(empty($filename) || $filename === false) {
	                echo("Erzeugen des " . $filetype . "-Videos in der Konfiguration deaktiviert" . PHP_EOL);
	            } else {
	                $tmpRegenAnimation = tempnam(sys_get_temp_dir(), 'RegenRadar');

	                // Kommando auswählen anhand des Dateiformats
	                $cmd ="";
	                switch ($filetype) {
	                    case "webm":
	                        $cmd = $converter["video"] . " -loglevel error -hide_banner -nostats -y -framerate 2/1 -i " . $config["localFolder"] . "/frames/frame%03d.jpg -c:v libvpx -r 30 -an -b:v 600k -pix_fmt yuv420p -f webm " . $tmpRegenAnimation;
	                        break;
	                    case "mp4":
	                        $cmd = $converter["video"] . " -loglevel error -hide_banner -nostats -y -framerate 2/1 -i " . $config["localFolder"] . "/frames/frame%03d.jpg -c:v libx264 -r 30 -an -b:v 600k -pix_fmt yuv420p -f mp4 " . $tmpRegenAnimation;
	                        break;
	                    case "gif":
	                        $cmd = $converter["gif"] . " -delay 50 -loop 0 " . $config["localFolder"] . "/frames/frame*.jpg gif:" . $tmpRegenAnimation;
	                        break;
	                }

	                // Konvertierung durchführen
	                if(!empty($cmd)) {
	                    exec($cmd);
	                    rename($tmpRegenAnimation, $filename);
	                    @unlink($tmpRegenAnimation);
	                    chmod($filename, 0644);
	                    echo("..." . $filename . " wurde erzeugt." . PHP_EOL);
	                } else {
	                    fwrite(STDERR, "Fehler beim anlegen des Konvertierungs-Auftrags." . PHP_EOL);
	                    exit(6);
	                }
	            }
	        }
        }
    }
}
?>
