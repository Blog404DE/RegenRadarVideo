<?php

declare(strict_types=1);

/*
 *  RegenRadar Generator aus DWD-Radarbilder from neuthardwetter.de by Jens Dutzi
 *
 *  @package    blog404de\RegenRadar
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2019 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/RegenRadarVideo/blob/master/LICENSE.md
 *  @version    3.2.2-unstable
 *  @link       https://github.com/Blog404DE/RegenRadarVideo
 */

namespace blog404de\RegenRadar;

use Exception;

/**
 * Hauptklasse für das erzeugen der Radar-Videos.
 */
class RegenRadar {
    /**
     * Netzwerk-Klasse.
     *
     * @var Network
     */
    public $network;

    /**
     * Prüfe System-Vorraussetzungen.
     *
     * @param array $config
     * @param array $converter
     *
     * @throws Exception
     */
    public function __construct(array $config, array $converter) {
        try {
            // Prüfe ob libCurl vorhanden ist
            if (!\extension_loaded('curl')) {
                throw new Exception(
                    'libCurl bzw. die das libCurl-PHP Modul steht nicht zur Verfügung.'
                );
            }

            // Prüfe Vorraussetzungen
            if (false !== $converter['video']) {
                if (!is_executable($converter['video'])) {
                    throw new Exception(
                        'ffmpeg/libavtools Binary steht unter ' . $converter['video'] . ' nicht zur verfügung.'
                    );
                }
            }
            if (false !== $converter['gif']) {
                if ('copy' !== $converter['gif']) {
                    throw new Exception(
                        'Für die GIF-Konvertieren steht ausschließlich der Weg  "Copy" zur Verfügung.'
                    );
                }
            }

            // Prüfe Existenz der lokalen Verzeichnisse für die einzelnen Radar-Aufnahmen existieren
            foreach ($config as $currentConfig) {
                $this->checkPosterFile($currentConfig);
                $this->checkFolders($currentConfig);
            }

            $this->network = new Network();
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /** @noinspection PhpUnused */

    /**
     * Erzeuge Video aus Radar-Bilder.
     *
     * @param string $filetype
     * @param array  $converter
     * @param array  $config
     *
     * @throws Exception
     *
     * @return string
     */
    public function createRadarVideo(string $filetype, array $converter, array $config): string {
        try {
            // Animation neu erzeugen
            $tmpRegenAnimation = tempnam(sys_get_temp_dir(), 'RegenRadar');

            // Kommando auswählen anhand des Dateiformats
            if ('webm' === $filetype || 'mp4' === $filetype) {
                echo PHP_EOL . 'Starte kompilieren des ' . $filetype . '-Video' . PHP_EOL;
                echo '-> Dieser Vorgang kann einige Zeit dauern ... ' . PHP_EOL;

                // Standard-Format festlegen
                $exportFormat = 'libx264';

                // Soll WebM erzeugt werden?
                if ('webm' === $filetype) {
                    $exportFormat = 'libvpx';
                }

                $cmd = $converter['video'] .
                    ' -loglevel error -hide_banner -nostats -y ' .
                    '-i ' . escapeshellarg($config['localFolder'] . '/' . basename($config['remoteURL'])) . ' ' .
                    '-c:v ' . escapeshellarg($exportFormat) . ' -r 30 -an -b:v 600k -pix_fmt yuv420p ' .
                    '-f ' . escapeshellarg($filetype) . ' ' . escapeshellarg($tmpRegenAnimation);

                exec($cmd, $output, $exitval);
                if ('' === $output || 0 !== $exitval) {
                    throw new Exception('Fehler beim ausführen des Konvertierungs-Auftrags');
                }
            } elseif ('gif' === $filetype) {
                echo PHP_EOL . 'Starte kopieren des ' . $filetype . '-Video' . PHP_EOL;

                // Für das GIF-Format einfach kopieren
                copy($config['localFolder'] . '/' . basename($config['remoteURL']), $tmpRegenAnimation);
            }

            return $tmpRegenAnimation;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Kopiere erzeugte Video/Animation.
     *
     * @param string $tmpRegenAnimation
     * @param string $filename
     *
     * @throws Exception
     */
    public function saveRadarVideo(string $tmpRegenAnimation, string $filename) {
        try {
            if (!rename($tmpRegenAnimation, $filename)) {
                throw new Exception('Fehler beim verschieben des erzeugten Videos');
            }

            if (!chmod($filename, 0644)) {
                throw new Exception('Fehler beim setzen der Datei-Rechte für das erzeugten Videos');
            }

            echo '-> ' . $filename . ' wurde erzeugt.' . PHP_EOL;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Prüfe Poster-Datei für das jeweilige Radar-Set.
     *
     * @param array $currentConfig
     *
     * @throws Exception
     */
    private function checkPosterFile(array $currentConfig) {
        try {
            // Poster-Datei prüfen
            $posterFile = \array_key_exists('poster', $currentConfig) ? $currentConfig['output']['poster'] : false;
            if (!empty($posterFile) && false !== $posterFile) {
                $posterDirectory = \dirname($posterFile);
                if (!is_writable($posterDirectory)) {
                    throw new Exception(
                        'In Ziel-Datei Ordner ' .
                        $posterDirectory .
                        ' kann keine Datei angelegt werden'
                    );
                }

                if (file_exists($posterFile)) {
                    if (!is_writable($posterFile)) {
                        throw new Exception(
                            'Benötigte Ziel-Datei ' . $posterFile . ' ist nicht überschreibbar'
                        );
                    }
                }
            }
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Prüfe Ordner für das jeweilige Radar-Set.
     *
     * @param array $currentConfig
     *
     * @throws Exception
     */
    private function checkFolders(array $currentConfig) {
        try {
            if (!is_writable($currentConfig['localFolder'])) {
                throw new Exception(
                    'Benötigte Verzeichnisse ' . $currentConfig['localFolder'] . ' ist nicht beschreibbar'
                );
            }

            foreach ($currentConfig['output'] as $outputFile) {
                $outputFileDirectory = \dirname($outputFile);
                if (false !== $outputFile) {
                    if (!is_writable($outputFileDirectory)) {
                        throw new Exception(
                            "In Ziel-Datei Ordner {$outputFileDirectory} kann keine Datei angelegt werden"
                        );
                    }

                    if (file_exists($outputFile)) {
                        if (!is_writable($outputFile)) {
                            throw new Exception(
                                "Benötigte Ziel-Datei {$outputFile} ist nicht überschreibbar"
                            );
                        }
                    }
                }
            }

            // Filestats-Cache zurücksetzen um Änderungen zu übernehmen
            clearstatcache();
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}
