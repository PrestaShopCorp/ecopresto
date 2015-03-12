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

if (!defined('_PS_VERSION_'))
	exit;

class importerReference
{
	public $id_product;
	public $id_product_attribute = 0;

	public function __construct($reference)
	{
		if (empty($reference))
			return;

		$res = self::getProductIdByReference($reference);

		if ($res === false)
			$this->id_product = 0;

		$this->id_product = $res['id_product'];
		$this->id_product_attribute = $res['id_product_attribute'];
	}

	public static function getProductIdByReference($reference)
	{
		$id = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT `id_product` FROM `'._DB_PREFIX_.'product` WHERE `supplier_reference` = "'.pSQL($reference).'"');

		if ($id)
			return array('id_product' => $id, 'id_product_attribute' => 0);

		$res = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('SELECT `id_product`, `id_product_attribute` FROM `'._DB_PREFIX_.'product_attribute` WHERE `supplier_reference` = "'.pSQL($reference).'"');

		if (isset($res['id_product_attribute']))
			return array('id_product' => $res['id_product'], 'id_product_attribute' => $res['id_product_attribute']);

		return false;
	}

	public static function getAllProductIdByReference($reference)
	{
		$res = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `id_product_attribute` FROM `'._DB_PREFIX_.'product_attribute` WHERE `supplier_reference` = "'.pSQL($reference).'"');

		if (count($res) > 0)
		{
			$all = array();
			foreach ($res as $det)
				$all[] = $det['id_product_attribute'];
			return $all;
		}
		return false;
	}

	public static function getShopProduct($id_shop, $id_product)
	{
		if (version_compare(_PS_VERSION_, '1.5', '<'))
			return true;
		if (Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT 1 FROM `'._DB_PREFIX_.'product_shop` WHERE `id_product`='.(int)$id_product.' AND `id_shop`='.(int)$id_shop))
			return true;
		else
			return false;
	}
}
