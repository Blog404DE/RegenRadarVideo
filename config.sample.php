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

// Pfade zu Konsolenprogramme:
$converter['video'] = '/usr/local/bin/ffmpeg';
$converter['gif'] = 'copy';

// Zu bearbeitende DWD-Radar-Daten:
$config = [];
$config[] = [
    'remoteURL' => 'https://www.dwd.de/DWD/wetter/radar/radfilm_baw_akt.gif',
    'posterURL' => 'https://www.dwd.de/DWD/wetter/radar/rad_baw_akt.jpg',
    'localFolder' => '/srv/webspacepfad/tmp/radarDaten/bw',
    'output' => [
        'webm' => '/srv/webspacepfad/htdocs/img/regenradar_southwest.webm',
        'mp4' => '/srv/webspacepfad/htdocs/img/regenradar_southwest.mp4',
        'gif' => '/srv/webspacepfad/htdocs/img/regenradar_southwest.gif',
        'poster' => '/srv/webspacepfad/htdocs/img/regenradar_southwest.jpg',
    ],
    'forceRebuild' => false,
];

$config[] = [
    'remoteURL' => 'https://www.dwd.de/DWD/wetter/radar/radfilm_brd_akt.gif',
    'posterURL' => 'https://www.dwd.de/DWD/wetter/radar/rad_brd_akt.jpg',
    'localFolder' => '/srv/webspacepfad/tmp/radarDaten/de',
    'output' => [
        'webm' => '/srv/webspacepfad/htdocs/img/regenradar_de.webm',
        'mp4' => '/srv/webspacepfad/htdocs/img/regenradar_de.mp4',
        'gif' => '/srv/webspacepfad/htdocs/img/regenradar_de.gif',
        'poster' => '/srv/webspacepfad/htdocs/img/regenradar_southwest.de',
    ],
    'forceRebuild' => false,
];
