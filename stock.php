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

include '../../config/settings.inc.php';
include '../../config/config.inc.php';

include dirname(__FILE__).'/class/download.class.php';
include dirname(__FILE__).'/class/catalog.class.php';
include dirname(__FILE__).'/class/reference.class.php';

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
$htmldebug = '<html><body style="font-family:arial"><h3>Cron - Stock</h3><ul>';
$htmldebug .= '<li>Début du traitement '.date('m/d/Y - H:i').'</li>';
$id_shop = $catalog->getInfoEco('ID_SHOP');
$stockD = $catalog->getInfoEco('ECO_URL_STOCK').$catalog->tabConfig['ID_ECOPRESTO'];
$stockL = 'files/stock.xml';

$lstTax = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `rate`, `id_tax_rules_group`
													FROM `'._DB_PREFIX_.'ec_ecopresto_tax_shop` ts, `'._DB_PREFIX_.'ec_ecopresto_tax` t
													WHERE ts.id_tax_eco = t.id_tax_eco
													AND `id_shop`='.(int)$id_shop);

$tabTax = array();
foreach ($lstTax as $tax)
{
	$tabTax['id_tax'][$tax['rate']] = $tax['id_tax_rules_group'];
	$tabTax['rate'][$tax['rate']] = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT `rate` 
											FROM `'._DB_PREFIX_.'tax` t, `'._DB_PREFIX_.'tax_rule` tr
											WHERE `id_tax_rules_group` ='.(int)$tax['id_tax_rules_group'].'
											AND `id_country` = '.(int)Configuration::get('PS_COUNTRY_DEFAULT').'
											AND t.`id_tax` = tr.`id_tax`');
}
$htmldebug .= '<li>Taxe, OK</li>';
if ($download->load($stockD) == true)
{
	$htmldebug .= '<li>Téléchargement stock, OK</li>';
	$download->saveTo($stockL);
	if (($handle = fopen($stockL, 'r')) !== false)
	{
		$etat = $catalog->updateMAJStock();
		$htmldebug .= '<li>MaJ stock, OK</li>';
		$iteration = 0;
		while (($data = fgetcsv($handle, 10000, ';')) !== false)
		{
			$iteration ++;
			$ref = $data[0];
			$qty = $data[1];
			$pri = $data[2];
			$tva = $data[3];
			$reference = new importerReference($ref);

			if (isset($reference->id_product) && $reference->id_product > 0)
				if (version_compare(_PS_VERSION_, '1.5', '>='))
				{
					if ($reference->id_product_attribute)
					{
						$allAT = importerReference::getAllProductIdByReference($ref);
						if (count($allAT) < 1)
							$allAT[] = 0;
						foreach ($allAT as $att)
						{
							StockAvailable::setQuantity((int)$reference->id_product, (int)$att, (int)$qty, (int)getShopForRef($att, 1));
							updatePrice((int)$reference->id_product, (int)$att, (float)$pri, (float)$tva, (int)getShopForRef($att, 1), $tabTax);
						}
					}
					else
					{
						StockAvailable::setQuantity((int)$reference->id_product, 0, (int)$qty, (int)getShopForRef($ref, 2));
						updatePrice((int)$reference->id_product, 0, (float)$pri, (float)$tva, (int)getShopForRef($reference->id_product, 0), $tabTax);
					}
				}
				else
				{
					updateProductQuantity($reference->id_product, $reference->id_product_attribute, $qty);
					updatePrice((int)$reference->id_product, $reference->id_product_attribute, (float)$pri, (float)$tva, 1, $tabTax);
				}
		}
		$htmldebug .= '<li>Traitement stock, OK. Itérations: '.$iteration.'</li>';
		$catalog->updateMAJStock($etat);
	}
}
$htmldebug .= '<li>Fin du traitement '.date('m/d/Y - H:i').'</li>';
if (Tools::getValue('debug'))
	echo $htmldebug;
function updatePrice($idP, $att = 0, $pri, $tva, $idS, $tabTax)
{
	$catalog = new Catalog();
	$tempRate = $tva / 1;
	$id_tax = (isset($tabTax['id_tax'][$idS][(string)$tempRate])?(int)$tabTax['id_tax'][$idS][(string)$tempRate]:0);

	$wholesale_price = (float)round(($catalog->tabConfig['PMVC_TAX'] == 0 && $id_tax != 0?$pri * (1 + ($tabTax['rate'][$idS][(string)$tempRate] / 100)):$pri), 6);
	if ($att != 0)
		Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'product_attribute` SET wholesale_price = '.(float)$wholesale_price.' WHERE `id_product_attribute` = '.(int)$att);
	else
	{
		Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'product` SET wholesale_price = '.(float)$wholesale_price.' WHERE `id_product` = '.(int)$idP);
		if (version_compare(_PS_VERSION_, '1.5', '>='))
            Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'product_shop` SET wholesale_price = '.(float)$wholesale_price.' WHERE `id_product` = '.(int)$idP);
	}
}

function getShopForRef($id, $typ)
{
	$shop = Db::getInstance()->getValue('SELECT `id_shop` FROM `'._DB_PREFIX_.'stock_available` WHERE id_product'.($typ = 1?'_attribute':'').'='.(int)$id);
	if (!isset($shop) || $shop == 0)
		return 1;
	else
		return $shop;
}

function updateProductQuantity($id_product, $id_product_attribute, $quantity)
{
	if ($id_product_attribute)
		Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'product_attribute`
				SET `quantity` = '.(int)$quantity.'
				WHERE `id_product`='.(int)$id_product.'
				AND `id_product_attribute` = '.(int)$id_product_attribute);

	Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'product`
			SET `quantity`='.(int)$quantity.'
			WHERE `id_product`='.(int)$id_product);

	Module::hookExec('updateQuantity',
		array(
			'id_product' => $id_product,
			'id_product_attribute' => $id_product_attribute,
			'quantity' => $quantity
		)
	);
}

$catalog->UpdateUpdateDate('DATE_STOCK');