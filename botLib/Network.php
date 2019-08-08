<?php

declare(strict_types=1);

/*
 *  RegenRadar Generator aus DWD-Radarbilder from neuthardwetter.de by Jens Dutzi
 *
 *  @package    blog404de\RegenRadar
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2019 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/RegenRadarVideo/blob/master/LICENSE.md
 *  @version    3.2.1-stable
 *  @link       https://github.com/Blog404DE/RegenRadarVideo
 */

namespace blog404de\RegenRadar;

use Exception;

/**
 * Class Network.
 */
class Network
{
    /**
     * Rardar-Datei herunterladen.
     *
     * @param string $localfile
     * @param string $remotefile
     *
     * @throws Exception
     */
    public function downloadRadarFile(string $localfile, string $remotefile)
    {
        try {
            echo PHP_EOL . 'Starte Download des Radar-Videos:' . PHP_EOL;

            // File-Handler öffnen
            $filehandler = fopen($localfile, 'w+');
            if (!\is_resource($filehandler)) {
                throw new Exception(
                    'Filehandler für ' . $localfile . ' zum speichern des Downloads konnte nicht geöffnet werden ' .
                    '(URL: ' . basename($remotefile) . ')'
                );
            }

            // Download initialisieren
            $curl = curl_init();
            if (!\is_resource($curl)) {
                throw new Exception('Initialisierung von libCurl fehlgeschlagen');
            }

            curl_setopt_array(
                $curl,
                [
                    CURLOPT_URL => $remotefile,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => false,
                    CURLOPT_PROGRESSFUNCTION => [$this, 'downloadProgress'],
                    CURLOPT_NOPROGRESS => false,
                    CURLOPT_BINARYTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_FILE => $filehandler,
                ]
            );

            // Datei herunterladen
            if (!curl_exec($curl)) {
                throw new Exception(
                    'Verbindung zum DWD-Webserver für die Prüfung des letzten Updates ist fehlgeschlagen ' .
                    '(URL: ' . basename($remotefile) . ')'
                );
            }

            echo PHP_EOL . '-> Download abgeschlossen' . PHP_EOL;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Poster-Datei herunterladen.
     *
     * @param string $localfile
     * @param string $remotefile
     *
     * @throws Exception
     */
    public function downloadPosterFile(string $localfile, string $remotefile)
    {
        try {
            echo PHP_EOL . 'Starte Download der Poster-Grafik:' . PHP_EOL;

            // File-Handler öffnen
            $filehandler = fopen($localfile, 'w+');
            if (!$filehandler) {
                throw new Exception(
                    'Filehandler für ' . $localfile . ' zum speichern der Poster-Grafik konnte nicht geöffnet werden ' .
                    '(URL: ' . basename($remotefile) . ')'
                );
            }

            // Download initialisieren
            $curl = curl_init();
            if (!\is_resource($curl)) {
                throw new Exception('Initialisierung von libCurl fehlgeschlagen');
            }

            curl_setopt_array(
                $curl,
                [
                    CURLOPT_URL => $remotefile,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => false,
                    CURLOPT_PROGRESSFUNCTION => [$this, 'downloadProgress'],
                    CURLOPT_NOPROGRESS => false,
                    CURLOPT_BINARYTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_FILE => $filehandler,
                ]
            );

            // Datei herunterladen
            if (!curl_exec($curl)) {
                throw new Exception(
                    'Verbindung zum DWD-Webserver für die Prüfung des letzten Updates ist fehlgeschlagen ' .
                    '(URL: ' . basename($remotefile) . ')'
                );
            }

            echo PHP_EOL . '-> Download abgeschlossen' . PHP_EOL;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Prüfe ob DWD VIdeo aktualisiert werden ,uss.
     *
     * @param string $localfile
     * @param string $remotefile
     *
     * @throws
     *
     * @return bool
     */
    public function checkDWDRadarVideoForUpdate(string $localfile, string $remotefile): bool
    {
        try {
            // Beginne Prüfung über den Zeitstempel des letzten Updates
            $curl = curl_init();
            if (!\is_resource($curl)) {
                throw new Exception('Initialisierung von libCurl fehlgeschlagen');
            }

            curl_setopt_array(
                $curl,
                [
                    CURLOPT_URL => $remotefile,
                    CURLOPT_FILETIME => true,
                    CURLOPT_NOBODY => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                ]
            );

            // Daten erfolgreich ermittelt?
            if (!curl_exec($curl)) {
                throw new Exception(
                    'Verbindung zum DWD-Webserver für die Prüfung des letzten Updates ist fehlgeschlagen ' .
                    '(URL: ' . basename($remotefile) . ')'
                );
            }

            // Zeitpunkt des letzten Updates ermitteln
            $info = curl_getinfo($curl);

            // Ermittle ob aktualisiert werden muss über den "Last-Modified"-Zeitstempel
            $updateVideo = $this->updateExists($localfile, $info);

            // Schließe Verbindung zum Webserver
            curl_close($curl);

            return $updateVideo;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * @param string $localfile
     * @param array  $info
     *
     * @return bool
     */
    private function updateExists(string $localfile, array $info): bool
    {
        $updateVideo = true;

        if (\array_key_exists('filetime', $info)) {
            // Remote Filetime
            $remotefilemtime = $info['filetime'];

            // Erzwnge Update
            echo '  -> Upload-Zeitstempel: ' . date('d.m.Y H:i:s', $remotefilemtime) . PHP_EOL;

            // Ermittle Zeitstempel der letzten Datei falls vorhanden
            if (file_exists($localfile)) {
                $localfilemtime = filemtime($localfile);
                echo '  -> Lokale Datei: ' . date('d.m.Y H:i:s', $localfilemtime) . PHP_EOL;

                if ($localfilemtime < $remotefilemtime) {
                    echo '-> Update des Radar-Videos erforderlich' . PHP_EOL;
                    $updateVideo = true;
                } elseif ($localfilemtime >= $remotefilemtime) {
                    echo '-> Kein Update Radar-Videos erforderlich' . PHP_EOL;
                    $updateVideo = false;
                }
            }
        } elseif (\array_key_exists('download_content_length', $info)) {
            // Wurde Update-Prüfung durchgeführt?
            echo
                "\t** WARNUNG: Upload-Zeitstempel ist nicht vorhanden " .
                '(Prüfe auf Veränderung der Dateigröße) ** ' . PHP_EOL
            ;

            // Falle zurück auf Prüfung über den Dateinamen
            $remotefilesize = (int) $info['download_content_length'];
            $localfilesize = (int) filesize($localfile);

            echo "\t-> Entfernte Datei: " . round($remotefilesize / 1024) . ' kBytes' . PHP_EOL;
            echo "\t-> Lokale Datei: " . round($localfilesize / 1024) . ' kBytes ' . PHP_EOL;

            if ($remotefilesize === $localfilesize) {
                echo '-> Kein Update Radar-Videos erforderlich' . PHP_EOL;
                $updateVideo = false;
            } elseif ($remotefilesize !== $localfilesize) {
                echo '-> Update des Radar-Videos erforderlich' . PHP_EOL;
                $updateVideo = true;
            }
        }

        return $updateVideo;
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */

    /**
     * cURL Download Progress darstellen
     * (lubCurl Callback-Funktion).
     *
     * @param resource $resource
     * @param int      $downloadSize [optional]
     * @param int      $downloaded   [optional]
     * @param int      $uploadSize   [optional]
     * @param int      $uploaded     [optional]
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @throws
     */
    private function downloadProgress($resource, int $downloadSize, int $downloaded, int $uploadSize, int $uploaded)
    {
        try {
            // Ressource vorhanden?
            if (!\is_resource($resource)) {
                throw new Exception(
                    'Interner Fehler: downloadProgress wurde direkt und nicht über libCurl aufgerufen'
                );
            }

            if ($downloadSize > 0) {
                echo '-> ' . sprintf('%.2f', ($downloaded / $downloadSize) * 100) . '% abgeschlossen (' .
                    round($downloaded / 1024) . ' kbyte von ' . round($downloadSize / 1024) . ' kbytes' .
                    ")\r"
                ;
            } elseif ($uploadSize > 0) {
                echo '-> ' . sprintf('%.2f', ($uploaded / $uploadSize) * 100) . '% abgeschlossen (' .
                    round($uploaded / 1024) . ' kbyte von ' . round($uploadSize / 1024) . ' kbytes' .
                    ")\r"
                ;
            }
            flush();
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}
