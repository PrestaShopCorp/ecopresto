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

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');

include(dirname(__FILE__).'/class/importProduct.class.php');
include(dirname(__FILE__).'/class/reference.class.php');
include(dirname(__FILE__).'/class/catalog.class.php');

$import = new importerProduct();
$catalog = new catalog();

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

$time = time();
$etp = (int)Tools::getValue('etp');
$total = (int)Tools::getValue('total');
$typ = (int)Tools::getValue('typ');
$id_shop = (int)Tools::getValue('ids');

$maxR = (($total - $etp) > 20) ? 20 : ($total - $etp);

$lstPdt = Db::getInstance()->ExecuteS('SELECT `reference`
	FROM `'._DB_PREFIX_.'ec_ecopresto_product_deleted`
	WHERE status=0
	LIMIT '.(int)$etp.', '.(int)$maxR);

foreach ($lstPdt as $pdt)
{
	if (time() - $time <= $catalog->limitMax)
	{
		$reference = new importerReference($pdt['reference']);
		$import->deleteProduct($reference->id_product, $pdt['reference']);
		$etp++;
	}
	else
		break;
}

echo $etp.','.$total.','.$typ.','.$pdt['reference'];