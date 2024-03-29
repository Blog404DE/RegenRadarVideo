#!/usr/bin/env php
<?php

/*
 *  RegenRadar Generator aus DWD-Radarbilder from neuthardwetter.de by Jens Dutzi
 *
 *  @package    blog404de\RegenRadar
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2020 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/RegenRadarVideo/blob/master/LICENSE.md
 *  @version    3.3.1
 *  @link       https://github.com/Blog404DE/RegenRadarVideo
 */

use blog404de\RegenRadar\RegenRadar;

try {
    // Konfigurations-Arrays initialisieren
    $config = [];
    $converter = [];

    // Root-Verzeichnis festlegen
    if (is_readable(__DIR__ . '/config.local.php')) {
        require_once __DIR__ . '/config.local.php';
    } else {
        throw new \RuntimeException(
            "Konfigurationsdatei 'config.local.php' existiert nicht. Zur Konfiguration lesen Sie README.md"
        );
    }

    // Autoloader initialisieren
    require_once __DIR__ . '/vendor/autoload.php';

    /*
     * ================================================================================================
     * Start des Scripts
     * ================================================================================================
     */

    $regenradarBot = new RegenRadar($config, $converter);

    // Erzeuge Radar-Videos
    foreach ($config as $value) {
        $header = 'Beginne mit dem erzeugen des Radar-Video vom DWD Webserver: ' . basename($value['remoteURL']);
        echo PHP_EOL . $header . PHP_EOL . str_repeat('=', mb_strlen($header)) . PHP_EOL;

        // Lokaler Dateiname für das Download
        $localVideoFile = $value['localFolder'] . '/' . basename($value['remoteURL']);

        // Prüfe DWD Video nach potenziellen Updates
        echo 'Prüfe ob Update des Videos notwendig ist:' . PHP_EOL;
        if (file_exists($localVideoFile)) {
            if ($value['forceRebuild']) {
                echo '-> Update des Videos ist wurde erzwungen durch die Konfigurationsdatei' . PHP_EOL;
                $needRebuild = true;
            } else {
                $needRebuild = $regenradarBot->network->checkDWDRadarVideoForUpdate($localVideoFile, $value['remoteURL']);
            }
        } else {
            echo '-> Update des Videos ist notwendig, da noch keine lokale Datei existiert' . PHP_EOL;
            $needRebuild = true;
        }

        if (null === $needRebuild) {
            throw new \RuntimeException(
                'Ermitteln des Zeitpunkt des letzten Updates ' .
                'für das Radar-Videos ' . basename($localVideoFile) . ' fehlgeschlagen'
            );
        }
        if ($needRebuild) {
            // Download des Radar-Videos
            $regenradarBot->network->downloadFile($localVideoFile, $value['remoteURL']);

            // Erzeuge die Videos
            foreach ($value['output'] as $filetype => $filename) {
                if ('poster' !== $filetype) {
                    $header = 'Erzeuge ' . $filetype . ' aus den Radar-Daten';
                    echo PHP_EOL . $header . PHP_EOL . str_repeat('=', mb_strlen($header)) . PHP_EOL;
                    if (empty($filename) || false === $filename) {
                        echo '-> Erzeugen des ' . $filetype . '-Videos in der Konfiguration deaktiviert' . PHP_EOL;
                    } else {
                        $tmpRegenAnimation = $regenradarBot->createRadarVideo($filetype, $converter, $value);
                        $regenradarBot->saveRadarVideo($tmpRegenAnimation, $filename);
                    }
                } else {
                    $header = 'Lade Poster-Datei für das Video vom DWD Webserver';
                    echo PHP_EOL . $header . PHP_EOL . str_repeat('=', mb_strlen($header)) . PHP_EOL;

                    if (false !== $value['posterURL']) {
                        $regenradarBot->network->downloadFile($filename, $value['posterURL']);
                    } else {
                        echo PHP_EOL . '-> Download der Poster-Datei wird nicht benötigt' . PHP_EOL;
                    }
                }
            }
        }

        echo PHP_EOL . '... Auftrag ausgeführt!' . PHP_EOL . PHP_EOL;
    }
} catch (RuntimeException|\Exception $e) {
    // Fehler-Handling
    fwrite(STDERR, 'Fataler Fehler: ' . $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getMessage());

    exit(-1);
}
