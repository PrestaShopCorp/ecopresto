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
*  @version    2.20.0
*  @copyright Copyright (c) Adonie SAS - Ecopresto
*  @license    Commercial license
*/

require dirname(__FILE__).'/../../config/config.inc.php';
require dirname(__FILE__).'/../../init.php';

require 'class/catalog.class.php';
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

switch ((int)Tools::getValue('majsel'))
{
	case 1:
		$catalog->setSelectProduct(Tools::getValue('ref'), Tools::getValue('etp'));
		echo Tools::safeOutput(Tools::getValue('tot')).','.Tools::safeOutput(Tools::getValue('actu')).','.Tools::safeOutput(count($catalog->tabSelectProduct));
		break;

	case 2:
		$catalog->updateCategory(Tools::getValue('rel'), Tools::getValue('cat'));
		break;

	case 3:
		echo 1;
		break;

	case 4:
		$catalog->UpdateUpdateDate('DATE_IMPORT_PS');
		echo Tools::safeOutput(Tools::getValue('nb')).','.Tools::safeOutput($catalog->getTotalMAJ());
		break;

	case 5:
		if (Tools::getValue('actu') + 1 == Tools::safeOutput(Tools::getValue('tot')))
			$catalog->mettreajourInfoEco('isProduitEnregistre', "1");
		$catalog->updateCatalog(Tools::getValue('ref'));
		echo Tools::safeOutput(Tools::getValue('actu')).','.Tools::safeOutput(Tools::getValue('tot'));
		break;

	case 6:
		$catalog->getProdDelete();
		echo Tools::safeOutput(Tools::getValue('actu')).','.Tools::safeOutput(Tools::getValue('tot'));
		break;

	case 7 :
		$catalog->updateCatalogAll();
		echo '1,1';
		break;

	case 8 :
		//Suppression de l'étape de téléchargement
		//$return = $catalog->GetFilecsv();
		//On renvoi directement 1,1000,1 (ok, nombre de ligne, nombre de fichier
		$return = "1,1000,1";
		echo $return;
		break;

	case 9:
		$return = $catalog->GetDereferencement();
		echo $return;
		break;

	case 10:
		$catalog->synchroManuelOrder(Tools::getValue('idc'), Tools::getValue('typ'));
		break;

	case 11:
		echo $catalog->SetDerefencement();
		break;

	case 12:
		include dirname(__FILE__).'/class/importProduct.class.php';
		include dirname(__FILE__).'/class/reference.class.php';
		$import = new importerProduct();
		$catalog->UpdateDereferencement(Tools::getValue('ref'));
		$reference = new importerReference(Tools::getValue('ref'));
		$import->deleteProduct($reference->id_product, Tools::getValue('ref'));
		$import->deleteProductShop();
		echo Tools::safeOutput(Tools::getValue('actu')).','.Tools::safeOutput(Tools::getValue('tot'));
		break;

	case 13:
		include dirname(__FILE__).'/class/importProduct.class.php';
		$import = new importerProduct();
		$import->deleteProductShop();
		break;

	case 14:
		/*
		Configuration::updateValue('ECOPRESTO_DEMO', Tools::getValue('lic'));
		if (Tools::getValue('lic') == 1)
			Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_configuration`
										SET `value` = "demo123456789demo123456789demo12"
										WHERE `name` = "ID_ECOPRESTO"');
										*/
		break;
		
	default:
		break;
}
?>