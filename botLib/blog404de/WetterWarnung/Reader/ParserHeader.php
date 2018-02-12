<?php
/**
 * WarnParser für neuthardwetter.de by Jens Dutzi - ParserHeader.php
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
 * Traint zum auslesen diverser Header-Elemente aus der Roh-XML Datei
 *
 * @package blog404de\WetterWarnung\Reader
 */
trait ParserHeader
{
    /**
     * Info-Node der Wetterwarnungen des DWD nach einer bestimmten WarnCellID durchsuchen
     *
     * @param \SimpleXMLElement $xml komplette XML-Wetterwarnung
     * @param int $warnCellId WarnCellID nach der gesucht werden soll
     * @param $stateCode string Die Abkürzung des Bundesland in dem sich die WarnCellID befindet
     * @return \SimpleXMLElement|bool
     * @throws
     */
    final protected function searchForWarnArea(
        \SimpleXMLElement $xml,
        int $warnCellId,
        string $stateCode
    ): \SimpleXMLElement {
        // Prüfen ob die entsprechenden Felder in der XML Datei existieren
        if (!property_exists($xml, "info")) {
            throw new Exception(
                "Fehler beim parsen der Wetterwarnung: Die XML Datei beinhaltet kein 'info'-Node."
            );
        }
        if (!property_exists($xml->{"info"}, "area")) {
            throw new Exception(
                "Fehler beim parsen der Wetterwarnung: Die XML Datei beinhaltet kein 'info->area' Node."
            );
        }

        // Durchlaufe den gesamten Info->Area Node
        foreach ($xml->{"info"}->{"area"} as $area) {
            // Prüfe ob es sich um kein Node mit Polygon-Informationen ist, sondern mit Orts-Informationen
            if (!property_exists($area, "polygon")) {
                // Speichere areaDesc
                if (!property_exists($area, "areaDesc")) {
                    throw new Exception(
                        "Fehler beim durchsuchen der Geo-Informationen: XML-GeoInfo-Node fehlt 'areaDesc'."
                    );
                }

                // Alle GeoInfo-Felder auslesen bei denen eine WarnCellID vorkommt
                $cellinformation = $this->extractInfosFromGeoField($area);

                // Prüfe ob mindestens ein gültiger Eintrag existiert in dem geprüften Area-Node
                if (count($cellinformation) == 0) {
                    throw new Exception(
                        "Fehler beim durchsuchen der Geo-Informationen: " .
                        "Der 'GeoCode'-Node beinhaltete keine WarncellID-Informationen innerhalb der WetterWarnung"
                    );
                }

                if (array_key_exists($warnCellId, $cellinformation)) {
                    $result = new \SimpleXMLElement("<geoInfo></geoInfo>");
                    $result->addChild("warncellid", $warnCellId);
                    $result->addChild("areaDesc", $cellinformation[$warnCellId]["areaDesc"]);
                    $result->addChild("altitude", $cellinformation[$warnCellId]["altitude"]);
                    $result->addChild("ceiling", $cellinformation[$warnCellId]["ceiling"]);
                    $result->addChild("state", $stateCode);

                    return $result;
                }
            }
        }

        // Ergebnis übergeben
        return new \SimpleXMLElement("<geoInfo></geoInfo>");
    }

    /**
     * Durchsuche GeoInfo-Feld nach allen vorhandenen WarnCellIDs und stelle diese in einem Array zusammen
     *
     * @param \SimpleXMLElement $area Area-Node der XML-Wetterwarnung
     * @return array
     * @throws Exception
     */
    private function extractInfosFromGeoField(\SimpleXMLElement $area): array
    {
        $result = [];
        $geocodes = $area->{"geocode"};

        // Durchsuche den gesamten GeoCode-Block nach dem Eintrag mit den WarnCellIDs
        foreach ($geocodes as $currentGeoCode) {
            // Prüfe ob Nodes vorhanden sind
            if (!property_exists($currentGeoCode, "valueName") || !property_exists($currentGeoCode, "value")) {
                throw new Exception(
                    "Fehler beim durchsuchen der Geo-Informationen: XML-Nodes " .
                    "'area'->'geocode'->'valueName' oder 'value' fehlen."
                );
            }

            if (($currentGeoCode->{"valueName"} == "WARNCELLID")) {
                // Prüfe nach Höhenangaben-Nodes
                if (!property_exists($area, "altitude")) {
                    throw new Exception(
                        "Fehler beim durchsuchen der Geo-Informationen: XML-Nodes 'altitude' fehlt"
                    );
                }
                if (!property_exists($area, "ceiling")) {
                    throw new Exception(
                        "Fehler beim durchsuchen der Geo-Informationen: XML-Nodes 'ceiling' fehlt."
                    );
                }

                // Ergebnis zusammenstellen
                $result[(string)$currentGeoCode->{"value"}] =
                    [
                        "altitude" => (float)$area->{"altitude"},
                        "ceiling"  => (float)$area->{"ceiling"},
                        "areaDesc" => (string)$area->{"areaDesc"}
                    ];
            }
        }

        return $result;
    }

    /**
     *  Art der Meldung auslesen
     *
     * @param \SimpleXMLElement $xml komplette XML-Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getHeaderIdentifier(\SimpleXMLElement $xml): String
    {
        try {
            if (!property_exists($xml, "identifier")) {
                throw new Exception(
                    "Fehler beim parsen der Wetterwarnung: Die XML Datei beinhaltet kein 'identifier'-Node."
                );
            }

            return (string)$xml->{"identifier"};
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Art der Meldung auslesen (Update, Cancel, Alert etc.)
     *
     * @param \SimpleXMLElement $xml komplette XML-Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getHeaderMsgType(\SimpleXMLElement $xml): String
    {
        try {
            if (!property_exists($xml, "msgType")) {
                throw new Exception(
                    "Fehler beim parsen der Wetterwarnung: Die XML Datei beinhaltet kein 'identifier'-Node."
                );
            }

            return strtolower((string)$xml->{"msgType"});
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Reference-Feld bei Bedarf auslesen (sofern es sich um eine Update-Meldung handelt)
     *
     * @param \SimpleXMLElement $xml komplette XML-Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getHeaderReference(\SimpleXMLElement $xml): String
    {
        try {
            if ($this->getHeaderMsgType($xml) == "update") {
                if (!property_exists($xml, "references")) {
                    throw new Exception(
                        "Fehler beim parsen der Wetterwarnung: Die XML Datei mit Typ 'update' " .
                        "beinhaltet kein 'references'-Node."
                    );
                } else {
                    $reference = (string)$xml->{"references"};
                }
            } else {
                $reference = "";
            }

            return $reference;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}