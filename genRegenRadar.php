#!/usr/bin/env php
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

try {
    // Konfigurations-Arrays initialisieren
    $ftp = [];
    $config = [];
    $converter = [];

    // Root-Verzeichnis festlegen
    if (is_readable(dirname(__FILE__) . "/config.local.php")) {
        require_once dirname(__FILE__) . "/config.local.php";
    } else {
        throw new Exception(
            "Konfigurationsdatei 'config.local.php' existiert nicht. Zur Konfiguration lesen Sie README.md"
        );
    }

    // Toolbox laden
    require_once  dirname(__FILE__) . "/botLib/Toolbox.php";

    /*
     * ================================================================================================
     * Start des Scripts
     * ================================================================================================
     */

    // Prüfe System
    checkSystem($config, $converter);

    // FTP-Verbindung aufbauen
    $ftpConnId = connectToFtp($ftp);

    // Erzeuge Radar-Videos
    foreach ($config as $value) {
        $header = "Erzeuge Radar-Video aus dem Verzeichnis " . $value["remoteFolder"];
        echo(PHP_EOL . $header . PHP_EOL . str_repeat("=", strlen($header)) . PHP_EOL);

        // Dateiliste erzeugen und Dateien herunterladen
        $arrDownloadList = getFileList($ftpConnId, $value);
        $needRebuild = downloadRadarImages($ftpConnId, $value, $arrDownloadList);

        // Download-Ordner bereinigen
        cleanDownloadFolder($value, $arrDownloadList);

        // Erzeuge die Videos
        foreach ($value["output"] as $filetype => $filename) {
            if ($value["forceRebuild"]) {
                $needRebuild = true;
            } elseif (!empty($filename) || $filename !== false) {
                if (!file_exists($filename)) {
                    $needRebuild = true;
                }
            }


            if ($needRebuild) {
                $header = "Erzeuge " . $filetype . " aus den Radar-Daten";
                echo(PHP_EOL . $header . PHP_EOL . str_repeat("=", strlen($header)) . PHP_EOL);
                if (empty($filename) || $filename === false) {
                    echo("-> Erzeugen des " . $filetype . "-Videos in der Konfiguration deaktiviert" . PHP_EOL);
                } else {
                    prepaireRadarVideo($value, $arrDownloadList, $filetype);
                    createRadarVideo($filename, $filetype, $converter, $value);
                }
            }
        }

        // createRadarVideo($ftpConnId, $value, $converter);
        echo(PHP_EOL . "... Auftrag ausgeführt!". PHP_EOL . PHP_EOL);
    }

    // FTP Verbindung beenden
    ftp_close($ftpConnId);
} catch (Exception $e) {
    // Fehler-Handling
    fwrite(STDERR, "Fataler Fehler: " . $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage());
    exit(-1);
}
