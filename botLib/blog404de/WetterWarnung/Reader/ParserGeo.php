<?php
/**
 * WarnParser für neuthardwetter.de by Jens Dutzi - ParserGeo.php
 *
 * @package    blog404de\WetterWarnung
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  Copyright (c) 2012-2018 Jens Dutzi (http://www.neuthardwetter.de)
 * @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 * @version    v3.0.1
 * @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Reader;

use Exception;

/**
 * Traint zum auslesen diverser Geo-Elemente aus der Roh-XML Datei
 *
 * @package blog404de\WetterWarnung\Reader
 */
trait ParserGeo
{
    /** @var array Array mit Bundesländer in Deutschland */
    private $regionames = [
        "BB" => "Brandenburg",
        "BL" => "Berlin",
        "BW" => "Baden-Württemberg",
        "BY" => "Bayern",
        "HB" => "Bremen",
        "HE" => "Hessen",
        "HH" => "Hamburg",
        "MV" => "Mecklenburg-Vorpommern",
        "NR" => "Nordrhein-Westfalen",
        "NS" => "Niedersachsen",
        "RP" => "Rheinland-Pfalz",
        "SA" => "Sachsen-Anhalt",
        "SH" => "Schleswig-Holstein",
        "SL" => "Saarland",
        "SN" => "Sachsen",
        "TH" => "Thüringen",
        "DE" => "Bundesrepublik Deutschland"
    ];

    /**
     * WarnCellID aus den Geo-Daten der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getRegioWarncellid(\SimpleXMLElement $currentWarnGeo): String
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnGeo, "warncellid")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt die WarnCellID in den Region-Angaben."
                );
            }

            return (string)$currentWarnGeo->{"warncellid"};
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * areaDesc aus den Geo-Daten der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getRegionArea(\SimpleXMLElement $currentWarnGeo): String
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnGeo, "areaDesc")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt der Ortsname in den Region-Angaben."
                );
            }

            return (string)$currentWarnGeo->{"areaDesc"};
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * State (Kurzform) aus den Geo-Daten der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getRegionStateShort(\SimpleXMLElement $currentWarnGeo): String
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnGeo, "state")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt 'State' in den Region-Angaben."
                );
            }

            return (string)$currentWarnGeo->{"state"};
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * State (Langform) aus den Geo-Daten der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getRegionStateLong(\SimpleXMLElement $currentWarnGeo): String
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnGeo, "state")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt 'State' in den Region-Angaben."
                );
            }

            // Ermittle Event-Typ
            if (!array_key_exists((string)$currentWarnGeo->{"state"}, $this->regionames)) {
                throw new Exception(
                    "Die Abkürzung des Bundeslandes " . $currentWarnGeo->{"state"} . " kann keinem " .
                    "deutschen Bundesland zugeordnet werden durch das Wetterwarnung-Script"
                );
            }

            return (string)$this->regionames[(string)$currentWarnGeo->{"state"}];
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * altitude aus den Geo-Daten der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     * @param boolean $metric Wert in metrischen Metern zurückgeben
     * @return float
     * @throws Exception
     */
    final protected function getRegionAltitude(\SimpleXMLElement $currentWarnGeo, bool $metric): float
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnGeo, "altitude")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt 'altitude' in den Region-Angaben."
                );
            }

            if ($metric) {
                $altitude = floor((float)$currentWarnGeo->{"altitude"} * 0.3048);
            } else {
                $altitude = floor((float)$currentWarnGeo->{"altitude"});
            }
            if ($altitude === false) {
                throw new Exception("Umwandlung des Altitude-Wertes von Feet in Meter fehlgeschlagen");
            }

            return $altitude;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * ceiling aus den Geo-Daten der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     * @param boolean $metric Wert in metrischen Metern zurückgeben
     * @return float
     * @throws Exception
     */
    final protected function getRegionCeiling(\SimpleXMLElement $currentWarnGeo, bool $metric): float
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnGeo, "ceiling")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt 'ceiling' in den Region-Angaben."
                );
            }

            if ($metric) {
                $ceiling = floor((float)$currentWarnGeo->{"ceiling"} * 0.3048);
            } else {
                $ceiling = floor((float)$currentWarnGeo->{"ceiling"});
            }

            if ($ceiling === false) {
                throw new Exception("Umwandlung des Ceiling-Wertes von Feet in Meter fehlgeschlagen");
            }

            return $ceiling;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Höehenangabe aus den Geo-Daten der Wetterwarnung ermitteln
     *
     * @param \SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     * @return string
     * @throws Exception
     */
    final protected function getRegionHoehenangabe(\SimpleXMLElement $currentWarnGeo): string
    {
        try {
            // Prüfe welche Höhenangabe verwendet werden muss für das Warngebiet (siehe Doku des DWD)
            if ($this->getRegionAltitude($currentWarnGeo, false) == 0 &&
                $this->getRegionCeiling($currentWarnGeo, false) != 9842.5197
            ) {
                // Warngebiet unter angegebener Mindest-Höhe
                $hoehenangabe = "Höhenlagen unter " . $this->getRegionCeiling($currentWarnGeo, true) . "m";
            } elseif ($this->getRegionAltitude($currentWarnGeo, false) != 0 &&
                      $this->getRegionCeiling($currentWarnGeo, false)  == 9842.5197
            ) {
                // Warngebiet über angegebener Höchstgebiet
                $hoehenangabe = "Höhenlagen über " . $this->getRegionAltitude($currentWarnGeo, true) . "m";
            } else {
                // Alle höhenlagen
                $hoehenangabe = "Alle Höhenlagen";
            }

            return (string)$hoehenangabe;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}