<?php

declare(strict_types=1);

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

namespace blog404de\RegenRadar;

/**
 * Hauptklasse für das Erzeugen der Radar-Videos.
 */
class RegenRadar {
    /**
     * Netzwerk-Klasse.
     *
     * @var Network
     */
    public Network $network;

    /**
     * Prüfe System-Vorraussetzungen.
     *
     * @param array $config
     * @param array $converter
     *
     * @throws \Exception
     */
    public function __construct(array $config, array $converter) {
        try {
            // Prüfe ob libCurl vorhanden ist
            if (!\extension_loaded('curl')) {
                throw new \RuntimeException(
                    'libCurl bzw. die das libCurl-PHP Modul steht nicht zur Verfügung.'
                );
            }

            // Prüfe Vorraussetzungen
            if ((false !== $converter['video']) && !is_executable($converter['video'])) {
                throw new \RuntimeException(
                    'ffmpeg/libavtools Binary steht unter ' . $converter['video'] . ' nicht zur verfügung.'
                );
            }
            if ((false !== $converter['gif']) && 'copy' !== $converter['gif']) {
                throw new \RuntimeException(
                    'Für die GIF-Konvertieren steht ausschließlich der Weg  "Copy" zur Verfügung.'
                );
            }

            // Prüfe Existenz der lokalen Verzeichnisse für die einzelnen Radar-Aufnahmen existieren
            foreach ($config as $currentConfig) {
                $this->checkPosterFile($currentConfig);
                $this->checkFolders($currentConfig);
            }

            $this->network = new Network();
        } catch (\Exception $e) {
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
     * @throws \Exception
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

                $exitval = '';
                $output = -1;
                exec($cmd, $output, $exitval);
                if ('' === $output || 0 !== $exitval) {
                    throw new \RuntimeException('Fehler beim ausführen des Konvertierungs-Auftrags');
                }
            } elseif ('gif' === $filetype) {
                echo PHP_EOL . 'Starte kopieren des ' . $filetype . '-Video' . PHP_EOL;

                // Für das GIF-Format einfach kopieren
                copy($config['localFolder'] . '/' . basename($config['remoteURL']), $tmpRegenAnimation);
            }

            return $tmpRegenAnimation;
        } catch (\RuntimeException|\Exception $e) {
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
     * @throws \Exception
     */
    public function saveRadarVideo(string $tmpRegenAnimation, string $filename): void {
        try {
            if (!rename($tmpRegenAnimation, $filename)) {
                throw new \RuntimeException('Fehler beim verschieben des erzeugten Videos');
            }

            if (!chmod($filename, 0644)) {
                throw new \RuntimeException('Fehler beim setzen der Datei-Rechte für das erzeugten Videos');
            }

            echo '-> ' . $filename . ' wurde erzeugt.' . PHP_EOL;
        } catch (\RuntimeException|\Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Prüfe Poster-Datei für das jeweilige Radar-Set.
     *
     * @param array $currentConfig
     *
     * @throws \Exception
     */
    private function checkPosterFile(array $currentConfig): void {
        try {
            // Poster-Datei prüfen
            $posterFile = \array_key_exists('poster', $currentConfig) ? $currentConfig['output']['poster'] : false;
            if (!empty($posterFile) && false !== $posterFile) {
                $posterDirectory = \dirname($posterFile);
                if (!is_writable($posterDirectory)) {
                    throw new \RuntimeException(
                        'In Ziel-Datei Ordner ' .
                        $posterDirectory .
                        ' kann keine Datei angelegt werden'
                    );
                }

                if (file_exists($posterFile) && !is_writable($posterFile)) {
                    throw new \RuntimeException(
                        'Benötigte Ziel-Datei ' . $posterFile . ' ist nicht überschreibbar'
                    );
                }
            }
        } catch (\RuntimeException|\Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Prüfe Ordner für das jeweilige Radar-Set.
     *
     * @param array $currentConfig
     *
     * @throws \Exception
     */
    private function checkFolders(array $currentConfig): void {
        try {
            if (!is_writable($currentConfig['localFolder'])) {
                throw new \RuntimeException(
                    'Benötigte Verzeichnisse ' . $currentConfig['localFolder'] . ' ist nicht beschreibbar'
                );
            }

            foreach ($currentConfig['output'] as $outputFile) {
                if (false !== $outputFile) {
                    $outputFileDirectory = \dirname($outputFile);
                    if (!is_writable($outputFileDirectory)) {
                        throw new \RuntimeException(
                            "In Ziel-Datei Ordner {$outputFileDirectory} kann keine Datei angelegt werden"
                        );
                    }

                    if (file_exists($outputFile) && !is_writable($outputFile)) {
                        throw new \RuntimeException(
                            "Benötigte Ziel-Datei {$outputFile} ist nicht überschreibbar"
                        );
                    }
                }
            }

            // Filestats-Cache zurücksetzen, um Änderungen zu übernehmen
            clearstatcache();
        } catch (\RuntimeException|\Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}
