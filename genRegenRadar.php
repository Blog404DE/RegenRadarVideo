#!/usr/bin/env php
<?php
/*
 * DWD-Radar Video Konverter für neuthardwetter.de by Jens Dutzi
 * Version 2.0.3
 * 2017-07-18
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

    // Erzeuge Radar-Videos
    foreach ($config as $value) {
        $header = "Beginne mit dem erzeugen des Radar-Video vom DWD Webserver: " . basename($value["remoteURL"]);
        echo(PHP_EOL . $header . PHP_EOL . str_repeat("=", strlen($header)) . PHP_EOL);

        // Lokaler Dateiname für das Download
        $localVideoFile = $value["localFolder"] . "/" . basename($value["remoteURL"]);

        // Prüfe DWD Video nach potentiellen Updates
        echo("Prüfe ob Update des Videos notwendig ist:" . PHP_EOL);
        if (file_exists($localVideoFile) && !$value["forceRebuild"]) {
            $needRebuild = checkDWDRadarVideoForUpdate($localVideoFile, $value["remoteURL"]);
        } elseif ($value["forceRebuild"]) {
            echo("-> Update des Videos ist wurde erzwungen durch die Konfigurationsdatei" . PHP_EOL);
            $needRebuild = true;
        } else {
            echo("-> Update des Videos ist notwendig, da noch keine lokale Datei existiert" . PHP_EOL);
            $needRebuild = true;
        }

        if (is_null($needRebuild)) {
            throw new Exception(
                "Ermitteln des Zeitpunkt des letzten Updates " .
                "für das Radar-Videos " . basename($localVideoFile) . " fehlgeschlagen"
            );
        } elseif ($needRebuild) {
            // Download des Radar-Videos
            downloadRadarFile($localVideoFile, $value["remoteURL"]);

            // Erzeuge die Videos
            foreach ($value["output"] as $filetype => $filename) {
                if ($filetype !== "poster") {
                    $header = "Erzeuge " . $filetype . " aus den Radar-Daten";
                    echo(PHP_EOL . $header . PHP_EOL . str_repeat("=", strlen($header)) . PHP_EOL);
                    if (empty($filename) || $filename === false) {
                        echo("-> Erzeugen des " . $filetype . "-Videos in der Konfiguration deaktiviert" . PHP_EOL);
                    } else {
                        $tmpRegenAnimation = createRadarVideo($filetype, $converter, $value);
                        saveRadarVideo($tmpRegenAnimation, $filename);
                    }
                } else {
                    $header = "Lade Poster-Datei für das Video vom DWD Webserver";
                    echo(PHP_EOL . $header . PHP_EOL . str_repeat("=", strlen($header)) . PHP_EOL);

                    if ($value["posterURL"] === false) {
                        echo(PHP_EOL . "-> Download der Poster-Datei wird nicht benötigt" . PHP_EOL);
                    } else {
                        downloadPosterFile($filename, $value["posterURL"]);
                    }
                }
            }
        }

        echo(PHP_EOL . "... Auftrag ausgeführt!". PHP_EOL . PHP_EOL);
    }
} catch (Exception $e) {
    // Fehler-Handling
    fwrite(STDERR, "Fataler Fehler: " . $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage());
    exit(-1);
}
