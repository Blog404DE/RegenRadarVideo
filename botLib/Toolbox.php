<?php
/*
 * DWD-Radar Video Konverter für neuthardwetter.de by Jens Dutzi
 * Version 2.0.1
 * 09.07.2017
 * (c) tf-network.de Jens Dutzi 2012-2017
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

/**
 * Prüfe System-Vorraussetzungen
 *
 * @param $config
 * @param $converter
 * @throws Exception
 */
function checkSystem($config, $converter)
{
    // Prüfe Vorraussetzungen
    if (!extension_loaded("FTP")) {
        throw new Exception("PHP FTP Modul steht nicht zur Verfügung");
    }
    if ($converter["video"] !== false) {
        if (!is_executable($converter["video"])) {
            throw new Exception(
                "ffmpeg/libavtools Binary steht unter " . $converter["video"] . " nicht zur verfügung."
            );
        }
    }
    if ($converter["gif"] !== false) {
        if (!is_executable($converter["gif"])) {
            throw new Exception(
                "convert Binary von Imagemagick steht unter " . $converter["gif"] . " nicht zur verfügung."
            );
        }
    }

    // Prüfe Existenz der lokalen Verzeichnisse für die einzelnen Radar-Aufnahmen existieren
    foreach ($config as $currentConfig) {
        checkPosterFile($currentConfig);
        checkFolders($currentConfig);
        checkOutputFiles($currentConfig);
    }
}

/**
 * Prüfe Poster-Datei für das jeweilige Radar-Set
 *
 * @param $currentConfig
 * @throws Exception
 */
function checkPosterFile($currentConfig)
{
    // Poster-Datei prüfen
    if (!empty($currentConfig["posterFile"]) && $currentConfig["posterFile"] !== false) {
        if (!is_writeable(dirname($currentConfig["posterFile"]))) {
            throw new Exception(
                "In Ziel-Datei Ordner " .
                dirname($currentConfig["posterFile"]) .
                " kann keine Datei angelegt werden"
            );
        }

        if (file_exists($currentConfig["posterFile"])) {
            if (!is_writable($currentConfig["posterFile"])) {
                throw new Exception(
                    "Benötigte Ziel-Datei " . $currentConfig["posterFile"] . " ist nicht überschreibbar"
                );
            }
        }
    }
}

/**
 * Prüfe Ordner für das jeweilige Radar-Set
 * @param $currentConfig
 * @throws Exception
 */
function checkFolders($currentConfig)
{
    if (!is_writable($currentConfig["localFolder"])) {
        throw new Exception(
            "Benötigte Verzeichnisse " . $currentConfig["localFolder"] . " ist nicht beschreibbar"
        );
    }

    if (!file_exists($currentConfig["localFolder"] . "/frames")) {
        if (!mkdir($currentConfig["localFolder"] . "/frames")) {
            throw new Exception(
                "Konnte das fehlende Verzeichnis " . $currentConfig["localFolder"] . "/frames nicht anlegen"
            );
        }
    }

    // Filestats-Cache zurücksetzen um Änderungen zu übernehmen
    clearstatcache();
}

/**
 * Prüfe Zugriff auf die Output-Files des Radar-Set
 *
 * @param $currentConfig
 * @throws Exception
 */
function checkOutputFiles($currentConfig)
{
    foreach (array($currentConfig["output"]) as $testname) {
        if ($testname !== false && empty($testname)) {
            if (!is_writeable(dirname($testname))) {
                throw new Exception(
                    "In Ziel-Datei Ordner " . dirname($testname) . " kann keine Datei angelegt werden"
                );
            }
            if (file_exists($testname)) {
                if (!is_writable($testname)) {
                    throw new Exception("Benötigte Ziel-Datei " . $testname . " ist nicht überschreibbar");
                }
            }
        }
    }
}

/**
 * Verbindung aufbauen zum FTP Server
 *
 * @param $ftp
 * @return resource $ftpConnId
 * @throws Exception
 */
function connectToFtp($ftp)
{
    $ftpConnId = ftp_connect($ftp["host"]);
    if ($ftpConnId === false) {
        throw new Exception(
            "FTP Verbindungsaufbau zu " . $ftp["host"] . " ist fehlgeschlagen"
        );
    }

    // Login mit Benutzername und Passwort
    $loginResult = @ftp_login($ftpConnId, $ftp["username"], $ftp["password"]);
    ftp_pasv($ftpConnId, true);

    // Verbindung überprüfen
    if ((!$ftpConnId) || (!$loginResult)) {
        throw new Exception(
            "Verbindungsaufbau zu zu " . $ftp["host"] . " mit Benutzername " . $ftp["username"] . " fehlgeschlagen."
        );
    } else {
        echo "Verbunden zu " .$ftp["host"] . " mit Benutzername " .$ftp["username"] . PHP_EOL;
    }

    return $ftpConnId;
}

/**
 * Lade Datei-Liste herunter
 *
 * @param $ftpConnId
 * @param $currentConfig
 * @return array
 * @throws
 */
function getFileList($ftpConnId, $currentConfig)
{
    // Versuche, in das benötigte Verzeichnis zu wechseln
    if (ftp_chdir($ftpConnId, $currentConfig["remoteFolder"])) {
        echo "Aktuelles Verzeichnis: " . ftp_pwd($ftpConnId)  . PHP_EOL;
    } else {
        throw new Exception("Verzeichniswechsel ist fehlgeschlagen");
    }

    // Verzeichnisliste auslesen und sortieren
    $arrFTPContent = ftp_nlist($ftpConnId, ".");
    if (count($arrFTPContent) == 0 || $arrFTPContent === false) {
        throw new Exception("Auslesen des Verezichnis " . $currentConfig["remoteFolder"] . " fehlgeschlagen");
    }

    // Zeit-Filter erzeugen nach GMT/UTC (-> damit nur die Grafiken der letzten x Stunden verarbeitet werden)
    $searchTime = new DateTime();
    $searchTime->setTimezone(new DateTimeZone('GMT'));
    $fileFilter = array($searchTime->format("Ymd_H"));
    for ($i = 1; $i < $currentConfig["runtimeHour"]; $i++) {
        $fileFilter[] = $searchTime->modify("-1 hour")->format("Ymd_H");
    }

    echo("Erzeuge Download-Liste:" . PHP_EOL);
    $arrDownloadList = array();
    foreach ($arrFTPContent as $filename) {
        // Laufe Filter durch
        foreach ($fileFilter as $filter) {
            if (strpos($filename, $filter) !== false) {
                // Übernehme Datei in zu-bearbeiten Liste
                $fileDate = ftp_mdtm($ftpConnId, $filename);
                echo("-> " . $filename . " => " . date("d.m.Y H:i", $fileDate) . PHP_EOL);
                $arrDownloadList[$filename] = $fileDate;
            }
        }
    }

    // Dateiliste absteigend sortieren
    arsort($arrDownloadList, SORT_NUMERIC);

    return $arrDownloadList;
}

/**
 * Dateien vom FTP Server herunternladen
 *
 * @param $ftpConnId
 * @param $currentConfig
 * @param $arrDownloadList
 * @return bool
 */
function downloadRadarImages($ftpConnId, $currentConfig, $arrDownloadList)
{
    // Beginne Download
    echo(PHP_EOL . "Starte den Download von " . count($arrDownloadList) . " Radar-Dateien" . PHP_EOL);

    $needRebuild = false;
    foreach ($arrDownloadList as $filename => $filetime) {
        $localFile = $currentConfig["localFolder"] . "/" . $filename;

        if (!file_exists($localFile)) {
            // Öffne lokale Datei
            $handle = fopen($localFile, 'w');

            if (ftp_fget($ftpConnId, $handle, $filename, FTP_BINARY, 0)) {
                echo "-> Datei " . $localFile . " (" .
                    date("d.m.Y H:i", $filetime) .
                    ") wurde erfolgreich heruntergeladen." . PHP_EOL;
                $needRebuild = true;
            } else {
                echo "-> Datei " . $filename . " (" .
                    date("d.m.Y H:i", $filetime) .
                    ") Download ist fehlgeschlagen. " . PHP_EOL;
            }

            // Schließe Datei-Handle
            fclose($handle);
        } else {
            echo "-> Datei " . $localFile . " existiert bereits und muss nicht neu geladen werden" . PHP_EOL;
        }
    }

    return $needRebuild;
}

/**
 * Download-Ordner bereinigen
 *
 * @param $currentConfig
 * @param $arrDownloadList
 * @throws Exception
 */
function cleanDownloadFolder($currentConfig, $arrDownloadList)
{
    // Symlink-Verzeichnis leeren
    echo(PHP_EOL . "Setze Symlink-Verzeichnis zurück" . PHP_EOL);
    array_map('unlink', glob($currentConfig["localFolder"] . "/frames/frame*.jpg"));

    // Entferne veraltete Dateien aus dem Download-Verzeichnis
    echo(PHP_EOL . "Führe Bereinigung durch:" . PHP_EOL);
    $arrLocalFiles = glob($currentConfig["localFolder"] . "/Webradar_*.jpg");
    arsort($arrLocalFiles);

    if ($arrLocalFiles === false) {
        throw new Exception("Fehler beim bereinigen der alten Dateien - generiere daher keine neue Regen-Animation");
    } else {
        // Ermittle Dateien die nicht mehr benötigt werden
        $arrOldFiles = array_diff(
            $arrLocalFiles,
            array_map(function ($item) use ($currentConfig) {
                return $currentConfig["localFolder"] . "/" . $item;
            }, array_keys($arrDownloadList))
        );

        // Lösche Dateien
        if (count($arrOldFiles) > 0) {
            foreach ($arrOldFiles as $delFile) {
                echo "Lösche veraltete Datei: " . $delFile . PHP_EOL;
                unlink($delFile);
            }
        } else {
            echo "Keine Dateien zum bereinigen gefunden" . PHP_EOL;
        }
    }
}

/**
 * Bereite Radar-Videos vor
 *
 * @param $currentConfig
 * @param $arrDownloadList
 * @param $filetype
 * @throws Exception
 */
function prepaireRadarVideo($currentConfig, $arrDownloadList, $filetype)
{
    // Symlinks neu erzeugen
    echo(PHP_EOL . "Erzeuge Symlinks für das erstellen des " . $filetype . "-Video" . PHP_EOL);
    $revDownloadList = array_reverse($arrDownloadList, true);
    $filenr = 0;
    foreach (array_keys($revDownloadList) as $filename) {
        $symlink = $currentConfig["localFolder"] . "/frames/frame" . str_pad($filenr, 3, "0", STR_PAD_LEFT) . ".jpg";
        echo("-> Symlink: " . $filename . " auf " . $symlink . PHP_EOL);

        if (!is_link($symlink)) {
            symlink("../" . $filename, $symlink);
        } else {
            unlink($symlink);
            symlink("../" . $filename, $symlink);
        }

        $filenr++;
    }

    // Poster-File kopieren
    if (!empty($currentConfig["posterFile"]) && $currentConfig["posterFile"] !== false) {
        reset($revDownloadList);
        $lastImage = $currentConfig["localFolder"] . "/" . key($revDownloadList);
        echo(PHP_EOL);
        echo( "-> Kopiere Poster-File " . key($revDownloadList) . " für " . $filetype . "-Videos in Ziel-Ordner");
        echo(PHP_EOL);
        if (!copy($lastImage, $currentConfig["posterFile"])) {
            throw new Exception("Kopieren des Poster-File " . key($revDownloadList) . " fehlgeschlagen");
        }
    }
}

/**
 * Erzeuge Video aus Radar-Bilder
 *
 * @param $filename
 * @param $filetype
 * @param $converter
 * @param $config
 * @throws Exception
 */
function createRadarVideo($filename, $filetype, $converter, $config)
{
    echo(PHP_EOL . "Starte kompilieren des des " . $filetype . "-Video" . PHP_EOL);
    echo("-> Dieser Vorgang kann einige Zeit dauern ... " . PHP_EOL);

    // Animation neu erzeugen
    $tmpRegenAnimation = tempnam(sys_get_temp_dir(), 'RegenRadar');

    // Kommando auswählen anhand des Dateiformats
    $cmd = "";
    switch ($filetype) {
        case "webm":
            if ($converter["video"] == false) {
                throw new Exception("Binary für Video-Erzeugung nicht konfiguriert");
            }
            $cmd = $converter["video"] .
                " -loglevel error -hide_banner -nostats -y -framerate 2/1 " .
                "-i \"" . $config["localFolder"] . "/frames/frame%03d.jpg\" " .
                "-c:v libvpx -r 30 -an -b:v 600k -pix_fmt yuv420p -f webm " .
                $tmpRegenAnimation;
            break;
        case "mp4":
            if ($converter["video"] == false) {
                throw new Exception("Binary für Video-Erzeugung nicht konfiguriert");
            }
            $cmd = $converter["video"] .
                " -loglevel error -hide_banner -nostats -y -framerate 2/1 " .
                "-i \"" . $config["localFolder"] . "/frames/frame%03d.jpg\" " .
                "-c:v libx264 -r 30 -an -b:v 600k -pix_fmt yuv420p -f mp4 " .
                $tmpRegenAnimation;
            break;
        case "gif":
            if ($converter["gif"] == false) {
                throw new Exception("Binary für Video-Erzeugung nicht konfiguriert");
            }
            $cmd = $converter["gif"] .
                " -delay 50 -loop 0 " .
                "\"" . $config["localFolder"] . "/frames/frame*.jpg\" gif:" . $tmpRegenAnimation;
            break;
    }

    // Konvertierung durchführen
    if (!empty($cmd)) {
        exec($cmd);
        rename($tmpRegenAnimation, $filename);
        @unlink($tmpRegenAnimation);
        chmod($filename, 0644);
        echo("-> " . $filename . " wurde erzeugt." . PHP_EOL);
    } else {
        throw new Exception("Fehler beim anlegen des Konvertierungs-Auftrags");
    }
}
