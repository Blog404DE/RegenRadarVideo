<?php
/**
 * WarnParser für neuthardwetter.de by Jens Dutzi - SendToInterface.php
 *
 * @package    blog404de\WetterWarnung
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  Copyright (c) 2012-2018 Jens Dutzi (http://www.neuthardwetter.de)
 * @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 * @version    v3.0.1
 * @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Action;

use Exception;

/**
 * Definition der zwingend benötigten Methoden für eine Action-Klasse
 *
 * @package blog404de\WetterWarnung\Action
 */
interface SendToInterface
{
    /**
     * Action Ausführung starten (Tweet versenden)
     *
     * @param array $parsedWarnInfo
     * @param bool $warnExists Wetterwarnung existiert bereits
     * @return int
     * @throws Exception
     */
    public function startAction(array $parsedWarnInfo, bool $warnExists): int;

    /**
     * Setter für Twitter OAuth Zugangsschlüssel
     *
     * @param array $config
     * @throws Exception
     */
    public function setConfig(array $config);

    /**
     * Getter-Methode für das Konfigurations-Array
     * @return array
     */
    public function getConfig(): array;
}