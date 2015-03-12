<?php
/**
* NOTICE OF LICENSE
*
* This source file is subject to a commercial license from Adonie SAS - Ecopresto
* Use, copy, modification or distribution of this source file without written
* license agreement from Adonie SAS - Ecopresto is strictly forbidden.
* In order to obtain a license, please contact us: info@ecopresto.com
* ...........................................................................
* INFORMATION SUR LA LICENCE D'UTILISATION
*
* L'utilisation de ce fichier source est soumise a une licence commerciale
* concedee par la societe Adonie SAS - Ecopresto
* Toute utilisation, reproduction, modification ou distribution du present
* fichier source sans contrat de licence ecrit de la part de la SAS Adonie - Ecopresto est
* expressement interdite.
* Pour obtenir une licence, veuillez contacter Adonie SAS a l'adresse: info@ecopresto.com
* ...........................................................................
*
*  @package ec_ecopresto
*  @author    Adonie SAS - Ecopresto
*  @version    2.2.0
*  @copyright Copyright (c) Adonie SAS - Ecopresto
*  @license    Commercial license
*/

include('../../config/settings.inc.php');
include('../../config/config.inc.php');
include(dirname(__FILE__).'/class/download.class.php');
include(dirname(__FILE__).'/class/catalog.class.php');
include(dirname(__FILE__).'/class/reference.class.php');
$download = new DownloadBinaryFile();
$catalog = new Catalog();

if (Tools::getValue('ec_token') != $catalog->getInfoEco('ECO_TOKEN'))
{
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache');

	header('Location: ../');
	exit;
}
$html .= '<html><body style="font-family:arial"><h3>Cron - Tracking</h3><ul>';
$html .= '<li>Début du traitement '.date('m/d/Y - H:i').'</li>';

$trackingD = $catalog->getInfoEco('ECO_URL_TRACKING').$catalog->tabConfig['ID_ECOPRESTO'];
$trackingL = 'files/tracking.xml';

if ($download->load($trackingD) == true)
{
	$download->saveTo($trackingL);
	$html .= '<li>Téléchargement, OK</li>';
	$iteration = 0;
	if (($handle = fopen($trackingL, 'r')) !== false) {
		while (($data = fgetcsv($handle, 10000, ';')) !== false)
		{
			$iteration++;
			$ne = 0;
			$ne = Db::getInstance()->getValue('SELECT `id` FROM `'._DB_PREFIX_.'ec_ecopresto_tracking` WHERE `numero`="'.pSQL($data[3]).'"');

			if ($ne == 0)
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_tracking` (`id_order`,`transport`,`numero`,`date_exp`,`url_exp`)
								VALUES ('.(int)$data[0].',"'.pSQL($data[2]).'","'.pSQL($data[3]).'","'.pSQL($data[4]).'","'.pSQL($data[5]).'")');
		}
		$html .= '<li>Traitement tracking, OK. Itérations: '.$iteration.'</li>';
	}
}
$html .= '<li>Fin du traitement '.date('m/d/Y - H:i').'</li>';
if (Tools::getValue('debug'))
	echo $html;

