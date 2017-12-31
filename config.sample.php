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

// Pfade zu Konsolenprogramme:
$converter["video"] = "/usr/local/bin/ffmpeg";
$converter["gif"]   = "copy";

// Zu bearbeitende DWD-Radar-Daten:
$config = array();
$config[] = [
    "remoteURL"  => "https://www.dwd.de/DWD/wetter/radar/radfilm_baw_akt.gif",
    "posterURL"  => "https://www.dwd.de/DWD/wetter/radar/rad_baw_akt.jpg",
    "localFolder"=> "/srv/webspacepfad/tmp/radarDaten/bw",
    "output"     => [
        "webm"   => "/srv/webspacepfad/htdocs/img/regenradar_southwest.webm",
        "mp4"    => "/srv/webspacepfad/htdocs/img/regenradar_southwest.mp4",
        "gif"    => "/srv/webspacepfad/htdocs/img/regenradar_southwest.gif",
        "poster" => "/srv/webspacepfad/htdocs/img/regenradar_southwest.jpg",
    ],
    "forceRebuild"  => false
];

$config[] = [
    "remoteURL" => "https://www.dwd.de/DWD/wetter/radar/radfilm_brd_akt.gif",
    "posterURL" => "https://www.dwd.de/DWD/wetter/radar/rad_brd_akt.jpg",
    "localFolder"  => "/srv/webspacepfad/tmp/radarDaten/de",
    "output"       => [
        "webm" => "/srv/webspacepfad/htdocs/img/regenradar_de.webm",
        "mp4"  => "/srv/webspacepfad/htdocs/img/regenradar_de.mp4",
        "gif"  => "/srv/webspacepfad/htdocs/img/regenradar_de.gif",
        "poster" => "/srv/webspacepfad/htdocs/img/regenradar_southwest.de",
    ],
    "posterFrame"    => 1,
    "forceRebuild"  => false
];
