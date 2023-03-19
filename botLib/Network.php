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
 * Class Network.
 */
class Network {
    /**
     * Datei herunterladen.
     *
     * @param string $localfile
     * @param string $remotefile
     *
     * @throws \Exception
     */
    public function downloadFile(string $localfile, string $remotefile): void {
        try {
            echo PHP_EOL . 'Starte Download der Datei ' . basename($localfile) . ':' . PHP_EOL;

            // File-Handler öffnen
            $filehandler = fopen($localfile, 'wb+');
            if (!\is_resource($filehandler)) {
                throw new \RuntimeException(
                    'Filehandler für ' . $localfile . ' zum speichern des Download konnte nicht geöffnet werden ' .
                    '(URL: ' . basename($remotefile) . ')'
                );
            }

            // Download initialisieren
            $curl = curl_init();
            if (!$curl instanceof \CurlHandle) {
                throw new \RuntimeException('Initialisierung von libCurl fehlgeschlagen');
            }

            curl_setopt_array(
                $curl,
                [
                    CURLOPT_URL => $remotefile,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => false,
                    CURLOPT_PROGRESSFUNCTION => [$this, 'downloadProgress'],
                    CURLOPT_NOPROGRESS => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_FILE => $filehandler,
                ]
            );

            // Datei herunterladen
            if (!curl_exec($curl)) {
                throw new \RuntimeException(
                    'Verbindung zum DWD-Webserver für die Prüfung des Download ist fehlgeschlagen ' .
                    '(URL: ' . basename($remotefile) . ')'
                );
            }

            echo PHP_EOL . '-> Download abgeschlossen' . PHP_EOL;
        } catch (\RuntimeException|\Exception $e) {
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
     * @throws \Exception
     *
     * @return bool
     */
    public function checkDWDRadarVideoForUpdate(string $localfile, string $remotefile): bool {
        try {
            // Beginne Prüfung über den Zeitstempel des letzten Updates
            $curl = curl_init();
            if (!$curl instanceof \CurlHandle) {
                throw new \RuntimeException('Initialisierung von libCurl fehlgeschlagen');
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
                throw new \RuntimeException(
                    'Verbindung zum DWD-Webserver für die Prüfung des letzten Updates ist fehlgeschlagen ' .
                    '(URL: ' . basename($remotefile) . ')'
                );
            }

            // Zeitpunkt des letzten Updates ermitteln
            $info = curl_getinfo($curl);

            // Ermittle, ob aktualisiert werden muss über den "Last-Modified"-Zeitstempel
            $updateVideo = $this->updateExists($localfile, $info);

            // Schließe Verbindung zum Webserver
            curl_close($curl);

            return $updateVideo;
        } catch (\RuntimeException|\Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Prüfe, ob Update für eine bereits heruntergeladene Datei existiert.
     *
     * @param string $localfile
     * @param array  $info
     *
     * @return bool
     */
    private function updateExists(string $localfile, array $info): bool {
        $updateVideo = true;

        if (\array_key_exists('filetime', $info)) {
            // Remote Filetime
            $remotefilemtime = $info['filetime'];

            // Erzwnge Update
            echo '  -> Upload-Zeitstempel: ' . date('d.m.Y H:i:s', $remotefilemtime) . PHP_EOL;

            // Ermittle Zeitstempel der letzten Datei, falls vorhanden
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
            echo "\t** WARNUNG: Upload-Zeitstempel ist nicht vorhanden " .
                '(Prüfe auf Veränderung der Dateigröße) ** ' . PHP_EOL
            ;

            // Falle zurück auf Prüfung über den Dateinamen
            $remotefilesize = (int)$info['download_content_length'];
            $localfilesize = (int)filesize($localfile);

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

    /**
     * cURL Download Progress darstellen
     * (lubCurl Callback-Funktion).
     *
     * @param \CurlHandle $resource
     * @param int         $downloadSize [optional]
     * @param int         $downloaded   [optional]
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     *
     * @throws
     */
    private function downloadProgress(\CurlHandle $resource, int $downloadSize, int $downloaded): void {
        try {
            if (!$resource instanceof \CurlHandle) {
                throw new \RuntimeException('downloadProgress wurde nicht über libCurl aufgerufen');
            }

            if ($downloadSize > 0) {
                echo '-> ' . sprintf('%.2f', ($downloaded / $downloadSize) * 100) . '% abgeschlossen (' .
                    round($downloaded / 1024) . ' kbyte von ' . round($downloadSize / 1024) . ' kbytes' .
                    ")\r"
                ;
            }
            flush();
        } catch (\RuntimeException|\Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}
