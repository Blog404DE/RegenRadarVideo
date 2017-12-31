<?php
/*
 * DWD-Radar Video Konverter für neuthardwetter.de by Jens Dutzi
 * Version 3.0.0
 * 2017-12-29
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
    // Prüfe ob libCurl vorhanden ist
    if (!extension_loaded('curl')) {
        throw new Exception(
            "libCurl bzw. die das libCurl-PHP Modul steht nicht zur Verfügung."
        );
    }

    // Prüfe Vorraussetzungen
    if ($converter["video"] !== false) {
        if (!is_executable($converter["video"])) {
            throw new Exception(
                "ffmpeg/libavtools Binary steht unter " . $converter["video"] . " nicht zur verfügung."
            );
        }
    }
    if ($converter["gif"] !== false) {
        if ($converter["gif"] !== "copy") {
            throw new Exception(
                "Für die GIF-Konvertieren steht ausschließlich der Weg  \"Copy\" zur Verfügung."
            );
        }
    }

    // Prüfe Existenz der lokalen Verzeichnisse für die einzelnen Radar-Aufnahmen existieren
    foreach ($config as $currentConfig) {
        checkPosterFile($currentConfig);
        checkFolders($currentConfig);
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

    foreach ($currentConfig["output"] as $format => $outputFile) {
        if ($outputFile !== false) {
            if (!is_writeable(dirname($outputFile))) {
                throw new Exception(
                    "In Ziel-Datei Ordner (Format: " . $format .") " .
                    dirname($outputFile) .
                    " kann keine Datei angelegt werden"
                );
            }

            if (file_exists($outputFile)) {
                if (!is_writable($outputFile)) {
                    throw new Exception(
                        "Benötigte Ziel-Datei (Format: " . $format .") " .
                        $outputFile . " ist nicht überschreibbar"
                    );
                }
            }
        }
    }

    // Filestats-Cache zurücksetzen um Änderungen zu übernehmen
    clearstatcache();
}

/**
 * Prüfe ob DWD VIdeo aktualisiert werden ,uss
 *
 * @param $localfile
 * @param $remotefile
 * @return boolean
 * @throws
 */
function checkDWDRadarVideoForUpdate($localfile, $remotefile)
{
    $updateVideo = null;

    // Beginne Prüfung über den Zeitstempel des letzten Updates
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $remotefile);
    curl_setopt($curl, CURLOPT_FILETIME, true);
    curl_setopt($curl, CURLOPT_NOBODY, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

    // Daten erfolgreich ermittelt?
    if (!curl_exec($curl)) {
        throw new Exception(
            "Verbindung zum DWD-Webserver für die Prüfung des letzten Updates ist fehlgeschlagen " .
            "(URL: " . basename($remotefile) . ")"
        );
    }

    // Zeitpunkt des letzten Updates ermitteln
    $info=curl_getinfo($curl);

    // Ermittle ob aktualisiert werden muss über den "Last-Modified"-Zeitstempel
    if (array_key_exists("filetime", $info)) {
        // Remote Filetime
        $remotefilemtime = $info["filetime"];

        // Erzwnge Update
        echo("  -> Upload-Zeitstempel: " . date("d.m.Y H:i:s", $remotefilemtime) . PHP_EOL);

        // Ermittle Zeitstempel der letzten Datei falls vorhanden
        if (file_exists($localfile)) {
            $localfilemtime = filemtime($localfile);
            echo("  -> Lokale Datei: " . date("d.m.Y H:i:s", $localfilemtime) . PHP_EOL);

            if ($localfilemtime < $remotefilemtime) {
                echo("-> Update des Radar-Videos erforderlich" . PHP_EOL);
                $updateVideo = true;
            } elseif ($localfilemtime >= $remotefilemtime) {
                echo("-> Kein Update Radar-Videos erforderlich" . PHP_EOL);
                $updateVideo = false;
            }
        }
    } elseif (array_key_exists("download_content_length", $info)) {
        // Wurde Update-Prüfung durchgeführt?
        echo(
            "\t** WARNUNG: Upload-Zeitstempel ist nicht vorhanden " .
            "(Prüfe auf Veränderung der Dateigröße) ** " . PHP_EOL
        );

        // Falle zurück auf Prüfung über den Dateinamen
        $remotefilesize = (int)$info["download_content_length"];
        $localfilesize = (int)filesize($localfile);

        echo("\t-> Entfernte Datei: " . round($remotefilesize / 1024) . " kBytes" . PHP_EOL);
        echo("\t-> Lokale Datei: " . round($localfilesize / 1024) . " kBytes " . PHP_EOL);

        if ($remotefilesize == $localfilesize) {
            echo("-> Kein Update Radar-Videos erforderlich" . PHP_EOL);
            $updateVideo = false;
        } elseif ($remotefilesize != $localfilesize) {
            echo("-> Update des Radar-Videos erforderlich" . PHP_EOL);
            $updateVideo = true;
        }
    }

    // Schließe Verbindung zum Webserver
    curl_close($curl);

    return $updateVideo;
}

/**
 * Rardar-Datei herunterladen
 *
 * @param $localfile
 * @param $remotefile
 * @throws Exception
 */
function downloadRadarFile($localfile, $remotefile)
{
    echo(PHP_EOL . "Starte Download des Radar-Videos:" . PHP_EOL);

    // File-Handler öffnen
    $filehandler = fopen($localfile, 'w+');
    if (!$filehandler) {
        throw new Exception(
            "Filehandler für " . $localfile . " zum speichern des Downloads konnte nicht geöffnet werden " .
            "(URL: " . basename($remotefile) . ")"
        );
    }

    // Download initialisieren
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $remotefile);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_PROGRESSFUNCTION, 'downloadProgress');
    curl_setopt($curl, CURLOPT_NOPROGRESS, false);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_FILE, $filehandler);

    // Datei herunterladen
    if (!curl_exec($curl)) {
        throw new Exception(
            "Verbindung zum DWD-Webserver für die Prüfung des letzten Updates ist fehlgeschlagen " .
            "(URL: " . basename($remotefile) . ")"
        );
    }

    echo(PHP_EOL . "-> Download abgeschlossen". PHP_EOL);
}

/**
 * Poster-Datei herunterladen
 *
 * @param $localfile
 * @param $remotefile
 * @throws Exception
 */
function downloadPosterFile($localfile, $remotefile)
{
    echo(PHP_EOL . "Starte Download der Poster-Grafik:" . PHP_EOL);

    // File-Handler öffnen
    $filehandler = fopen($localfile, 'w+');
    if (!$filehandler) {
        throw new Exception(
            "Filehandler für " . $localfile . " zum speichern der Poster-Grafik konnte nicht geöffnet werden " .
            "(URL: " . basename($remotefile) . ")"
        );
    }

    // Download initialisieren
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $remotefile);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_PROGRESSFUNCTION, 'downloadProgress');
    curl_setopt($curl, CURLOPT_NOPROGRESS, false);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_FILE, $filehandler);

    // Datei herunterladen
    if (!curl_exec($curl)) {
        throw new Exception(
            "Verbindung zum DWD-Webserver für die Prüfung des letzten Updates ist fehlgeschlagen " .
            "(URL: " . basename($remotefile) . ")"
        );
    }

    echo(PHP_EOL . "-> Download abgeschlossen". PHP_EOL);
}

/**
 * cURL Download Progress darstellen
 * (lubCurl Callback-Funktion)
 *
 * @param $resource
 * @param $downloadSize [optional]
 * @param $downloaded [optional]
 * @param $uploadSize [optional]
 * @param $uploaded [optional]
 * @throws
 */
function downloadProgress($resource, $downloadSize, $downloaded, $uploadSize, $uploaded)
{
    // Ressource vorhanden?
    if (!is_resource($resource)) {
        throw new Exception(
            "Interner Fehler: downloadProgress wurde direkt und nicht über libCurl aufgerufen"
        );
    }

    if ($downloadSize > 0) {
        echo("-> " . sprintf('%.2f', ($downloaded/$downloadSize)*100)  . "% abgeschlossen (" .
            round($downloaded/1024) . " kbyte von " . round($downloadSize / 1024) . " kbytes" .
            ")\r"
        );
    } elseif ($uploadSize > 0) {
        echo("-> " . sprintf('%.2f', ($uploaded/$uploadSize)*100)  . "% abgeschlossen (" .
            round($uploaded/1024) . " kbyte von " . round($uploadSize / 1024) . " kbytes" .
            ")\r"
        );
    }
    flush();
}

/**
 * Erzeuge Video aus Radar-Bilder
 *
 * @param $filetype
 * @param $converter
 * @param $config
 * @return string
 * @throws Exception
 */
function createRadarVideo($filetype, $converter, $config)
{
    // Animation neu erzeugen
    $tmpRegenAnimation = tempnam(sys_get_temp_dir(), 'RegenRadar');

    // Kommando auswählen anhand des Dateiformats
    if ($filetype == "webm" || $filetype == "mp4") {
        echo(PHP_EOL . "Starte kompilieren des " . $filetype . "-Video" . PHP_EOL);
        echo("-> Dieser Vorgang kann einige Zeit dauern ... " . PHP_EOL);

        // Standard-Format festlegen
        $exportFormat = "libx264";

        // Soll WebM erzeugt werden?
        if ($filetype == "webm") {
            $exportFormat = "libvpx";
        }

        $cmd = $converter["video"] .
            " -loglevel error -hide_banner -nostats -y " .
            "-i " . escapeshellarg($config["localFolder"] . "/" . basename($config["remoteURL"])) . " " .
            "-c:v " . escapeshellarg($exportFormat) . " -r 30 -an -b:v 600k -pix_fmt yuv420p " .
            "-f " . escapeshellarg($filetype) . " " .  escapeshellarg($tmpRegenAnimation);

        $exitval = exec($cmd, $output);
        if (is_null($output) || $exitval != 0) {
            throw new Exception("Fehler beim ausführen des Konvertierungs-Auftrags");
        }
    } elseif ($filetype == "gif") {
        echo(PHP_EOL . "Starte kopieren des " . $filetype . "-Video" . PHP_EOL);

        // Für das GIF-Format einfach kopieren
        copy($config["localFolder"] . "/" . basename($config["remoteURL"]), $tmpRegenAnimation);
    }

    return $tmpRegenAnimation;
}

/**
 * Kopiere erzeugte Video/Animation
 *
 * @param $tmpRegenAnimation
 * @param $filename
 * @throws Exception
 */
function saveRadarVideo($tmpRegenAnimation, $filename)
{
    if (!rename($tmpRegenAnimation, $filename)) {
        throw new Exception("Fehler beim verschieben des erzeugten Videos");
    }

    if (!chmod($filename, 0644)) {
        throw new Exception("Fehler beim setzen der Datei-Rechte für das erzeugten Videos");
    };

    echo("-> " . $filename . " wurde erzeugt." . PHP_EOL);
}