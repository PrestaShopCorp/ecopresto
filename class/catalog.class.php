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

class Catalog
{

	public $fichierImport;
	public $fichierDistant;
	public $fichierDeref;
	public $fichierDerefDistant;
	public $limitMax;
	public $limitRowMax;
	public $tabInsert;
	public $supplier;
	public $tabAttributes;
	public $tabTVA;
	public $tabSelectProduct;

	public function __construct()
	{
		$this->tabSelectProduct = array();
		$this->tabAttributes = array();
		$this->tabTVA = array();
		$this->supplier = self::getInfoEco('ECO_SUPPLIER');

		$this->tabConfig = array(
			'ID_ECOPRESTO'=>0,
			'PMVC_TAX'=>0,
			'UPDATE_PRICE'=>0,
			'UPDATE_EAN'=>0,
			'UPDATE_NAME_DESCRIPTION'=>0,
			'UPDATE_IMAGE'=>0,
			'UPDATE_PRODUCT'=>0,
			'PARAM_LANG'=>0,
			'PARAM_INDEX'=>0,
			'PARAM_MULTILANG'=>0,
			'UPDATE_CATEGORY'=>0,
			'PA_TAX'=>0,
			'PARAM_SUPPLIER'=>0,
			'PARAM_NEWPRODUCT'=>0,
			'PARAM_MAJ_NEWPRODUCT'=>0,
			'DATE_STOCK'=>'-',
			'DATE_ORDER'=>'-',
			'DATE_UPDATE_SELECT_ECO'=>'-',
			'DATE_IMPORT_PS'=>'-',
			'DATE_IMPORT_ECO'=>'-',
			'IMPORT_AUTO'=>0,
			'NB_LIGNE_IMPORT'=> 0,
		);

		$this->tabInsert = array(
			'category_1' => 0,
			'category_2' => 1,
			'category_3' => 2,
			'category_4' => 3,
			'category_5' => 4,
			'ss_category_1' => 5,
			'ss_category_2' => 6,
			'ss_category_3' => 7,
			'ss_category_4' => 8,
			'ss_category_5' => 9,
			'name_1' => 18,
			'name_2' => 19,
			'name_3' => 20,
			'name_4' => 21,
			'name_5' => 22,
			'reference' => 10,
			'manufacturer' => 12,
			'description_short_1'=>23,
			'description_short_2'=>24,
			'description_short_3'=>25,
			'description_short_4'=>26,
			'description_short_5'=>27,
			'description_1'=>28,
			'description_2'=>29,
			'description_3'=>30,
			'description_4'=>31,
			'description_5'=>32,
			'price' => 33,
			'image_1'=>34,
			'image_2'=>35,
			'image_3'=>36,
			'image_4'=>37,
			'image_5'=>38,
			'image_6'=>39,
			'rate' => 40,
			'ean13'=>41,
			'weight'=>42,
			'pmvc' => 43,
		);

		$this->tabInsert_attribute = array(
			'reference_attribute' => 11,
			'price' => 33,
			'ean13'=>41,
			'weight'=>42,
			'pmvc' => 43,
			'attribute_1'=>13,
			'attribute_2'=>14,
			'attribute_3'=>15,
			'attribute_4'=>16,
			'attribute_5'=>17,
		);

		$this->getParam();

		$this->fichierImport = 'catalogue.csv';
		$this->fichierDistant = self::getInfoEco('ECO_URL_CATALOGUE').$this->tabConfig['ID_ECOPRESTO'];

		$this->fichierDeref = 'dereferencement.csv';
		$this->fichierDerefDistant = self::getInfoEco('ECO_URL_SORTIE').$this->tabConfig['ID_ECOPRESTO'];

		$this->limitMax = 2;
		$this->limitRowMax = 100;

	}

	public function getInfoEco($name)
	{
		return Db::getInstance()->getValue('SELECT `value` FROM `'._DB_PREFIX_.'ec_ecopresto_info` WHERE name="'.pSQL($name).'"');
	}

	public function updateInfoEco($name, $value)
	{
		return Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_info` SET `value` = "'.pSQL($value).'" WHERE `name`="'.pSQL($name).'"');
	}
	/**
	 * mettreajourInfoEco Met à jour une info dans la table info, si cette info n'existe pas (name) elle la créé
	 *
	 * @param string $name nom de la valeur
	 * @param string $value valeur
	 * @return boolean
	 */
	public function mettreajourInfoEco($name, $value)
	{
		if (Db::getInstance()->getValue('SELECT * FROM `'._DB_PREFIX_.'ec_ecopresto_info` WHERE name="'.pSQL($name).'"'))
			return Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_info` SET `value` = "'.pSQL($value).'" WHERE `name`="'.pSQL($name).'"');
		else
			return Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_info` (`name`, `value`) VALUES ("'.pSQL($name).'", "'.pSQL($value).'")');
	}
	/**
	 * mettreajouronfigEco Met à jour une info dans la table config pour le shop séelctionné
	 *
	 * @param string $name nom de la valeur
	 * @param string $value valeur
	 * @param string $shop shop en cours
	 * @return boolean
	 */
	public function mettreajourConfEco($name, $value, $shop)
	{
		
		return Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_configuration` SET `value` = "'.pSQL($value).'" WHERE `name`="'.pSQL($name).'" AND `id_shop`="'.pSQL($shop).'"');
	}

	public function synchroManuelOrder($idorder, $typ)
	{
		if ($typ == 0)
			Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_export_com` (`id_order`) VALUES ('.(int)$idorder.')');
		else
		{
			$idcS = $idorder;
			include 'gen_com.php';
		}
	}

	public function getSelectProduct()
	{
		$allProduct = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `reference`,`imported`
													FROM `'._DB_PREFIX_.'ec_ecopresto_product_shop`
													WHERE `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
		foreach ($allProduct as $product)
			$this->tabSelectProduct[$product['reference']] = 0;
	}

	public function setSelectProduct($ref, $action)
	{
		switch ($action)
		{
		case '0' :
			if (array_key_exists($ref, $this->tabSelectProduct))
				$this->tabSelectProduct[$ref] = 0;
			else
				$this->tabSelectProduct[$ref] = 1;
			break;
		case '1' :
			if (array_key_exists($ref, $this->tabSelectProduct))
				$this->tabSelectProduct[$ref] = 2;
			else
				unset($this->tabSelectProduct[$ref]);
			break;
		}
	}

	public function getOrders($idc = 0)
	{
		$lstC = Db::getInstance()->ExecuteS('SELECT o.`id_order`, `id_address_delivery`, DATE_FORMAT(`invoice_date`, "%d/%m/%Y") AS DatI
									FROM `'._DB_PREFIX_.'orders` o
									LEFT JOIN `'._DB_PREFIX_.'ec_ecopresto_export_com` ec ON (o.`id_order` = ec.`id_order`)
									WHERE `valid`=1
									'.($idc != 0?' AND o.`id_order`='.(int)$idc:'').'
									AND ec.`id_order` IS NULL');
		$i = 0;

		foreach ($lstC as $com)
		{
			$ok = Db::getInstance()->getValue('SELECT count(`id_order_detail`)
											FROM `'._DB_PREFIX_.'order_detail` od, `'._DB_PREFIX_.'ec_ecopresto_catalog` c
											WHERE od.`product_supplier_reference` = c.`reference`
											AND `id_order`='.(int)$com['id_order'].'
											GROUP BY od.`product_supplier_reference`');

			$ok2 = Db::getInstance()->getValue('SELECT count(`id_order_detail`)
											FROM `'._DB_PREFIX_.'order_detail` od, `'._DB_PREFIX_.'ec_ecopresto_catalog_attribute` ca 
											WHERE od.`product_supplier_reference` = ca.`reference_attribute`
											AND `id_order`='.(int)$com['id_order'].'
											GROUP BY od.`product_supplier_reference`');
			if (($ok == 0 || $ok == '') && ($ok2 == 0 || $ok2 == ''))
			{
				unset($lstC[$i]);
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_export_com` (`id`,`id_order`) VALUES ("",'.(int)$com['id_order'].')');
			}

			$i++;
		}
		return array_values($lstC);
	}

	public function getTracking()
	{
		return Db::getInstance()->ExecuteS('SELECT *
									FROM `'._DB_PREFIX_.'ec_ecopresto_tracking`
									WHERE ((UNIX_TIMESTAMP(NOW()) - `date_exp`)/86400)<30
									ORDER BY `date_exp`');
	}

	public function updateSelectProduct()
	{
		foreach ($this->tabSelectProduct as $key => $value)
		{
			switch ($value)
			{
				case '0' :
					break;
				case '1' :
					Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_product_shop` (`reference`,`id_shop`,`imported`) VALUES ("'.pSQL($key).'",'.(int)self::getInfoEco('ID_SHOP').',0)');
					break;
				case '2' :
					Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_product_shop` SET `imported` = 1 WHERE `id_shop`='.(int)self::getInfoEco('ID_SHOP').' AND `reference`="'.pSQL($key).'"');
					break;
			}
		}
	}

	public function cutFile()
	{
		$dossierTempo = 'files/csv/';
		$handledir = opendir($dossierTempo);
		while (false !== ($fichier = readdir($handledir)))
			if (($fichier != '.') && ($fichier != '..') && ($fichier != 'index.php'))
				unlink($dossierTempo.$fichier);

			$file = $this->fichierImport;
		$cutsize = 3000;

		$handle = fopen('files/'.$file, 'rb')
			or die ('Lecture impossible !');
		$i = 0;
		$nbre = 0;
		while (!feof($handle))
		{
			$istring = $i;
			$partstring = 'category_1;category_2;category_3;category_4;category_5;ss_category_1;ss_category_2;ss_category_3;ss_category_4;ss_category_5;reference;reference_attribute;manufacturer;attribute_1;attribute_2;attribute_3;attribute_4;attribute_5;name_1;name_2;name_3;name_4;name_5;description_short_1;description_short_2;description_short_3;description_short_4;description_short_5;description_1;description_2;description_3;description_4;description_5;price;image_1;image_2;image_3;image_4;image_5;image_6;rate;ean13;weight;pmvc';
			$retempol = Tools::strlen($istring);
			while (Tools::strlen($istring) < 3)
				$istring = '0'.$istring;

			while ($nbre < $cutsize && !feof($handle))
			{
				$partstring .= fgets($handle);
				$nbre++;
			}

			$partfile = fopen('files/csv/'.$file.'.'.$istring, 'w+')
				or die('Erreur fatale: impossible d\'ouvrir $file.$istring');

			fwrite($partfile, $partstring)
				or die('Erreur fatale: impossible d\'écrire $file.$istring');
			fclose($partfile);
			$i++;
			$nbre = 0;
		}
		fclose($handle);

		$nbFichier = 0;

		$handledir = opendir($dossierTempo);
		while (false !== ($fichier = readdir($handledir)))
			if (($fichier != '.') && ($fichier != '..') && ($fichier != 'index.php'))
				$nbFichier++;

			return $nbFichier;
	}

	public function insertData($requete)
	{
		$requete = Tools::substr($requete, 0, -1).';';

		try
		{
			Db::getInstance()->Execute($requete);
			return true;
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	public function updateCategory($rel, $cat)
	{
		if ($cat != 0)
		{
			$id_category = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT `id_category` FROM `'._DB_PREFIX_.'ec_ecopresto_category_shop` WHERE `name`="'.pSQL($rel).'" AND `id_shop`='.(int)self::getInfoEco('ID_SHOP'));

			if ($id_category)
				Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_category_shop` SET id_category='.(int)$cat.' WHERE `name`="'.pSQL($rel).'" AND `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
			else
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_category_shop` (`name`,`id_category`,`id_shop`) VALUES ("'.pSQL($rel).'",'.(int)$cat.','.(int)self::getInfoEco('ID_SHOP').')');
		}
			else
				Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'ec_ecopresto_category_shop` WHERE `name`="'.pSQL($rel).'"');
	}


	public function getProdDelete()
	{
		$lstPdt = Db::getInstance()->ExecuteS('SELECT ps.`reference`, ps.`imported`
											FROM `'._DB_PREFIX_.'ec_ecopresto_product_shop` ps
											LEFT JOIN `'._DB_PREFIX_.'ec_ecopresto_catalog` c ON (c.`reference` = ps.`reference`)
											WHERE `id_shop`='.(int)self::getInfoEco('ID_SHOP').'
											AND c.`reference` IS NULL');
		$tabDelete = array();
		foreach ($lstPdt as $pdt)
			$tabDelete[$pdt['reference']] = $pdt['imported'];

		Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_product_shop` SET imported=1 WHERE `id_shop`='.(int)self::getInfoEco('ID_SHOP'));

		$lstPdtTemp = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `reference` FROM `'._DB_PREFIX_.'ec_ecopresto_product_shop_temp` WHERE `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
		foreach ($lstPdtTemp as $pdtTemp)
		{
			$ref = Db::getInstance()->getValue('SELECT `reference` FROM `'._DB_PREFIX_.'ec_ecopresto_product_shop` WHERE `id_shop`='.(int)self::getInfoEco('ID_SHOP').' AND `reference`="'.pSQL($pdtTemp['reference']).'"');
			if (isset($ref) && $ref != '')
				Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_product_shop` SET imported=0 WHERE `reference`="'.pSQL($pdtTemp['reference']).'" AND `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
			else
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_product_shop` (`reference`,`id_shop`,`imported`) VALUES ("'.pSQL($pdtTemp['reference']).'",'.(int)self::getInfoEco('ID_SHOP').',0)');
		}

		foreach ($tabDelete as $delete => $val)
			Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_product_shop` SET imported='.(int)$val.' WHERE `reference`="'.pSQL($delete).'" AND `id_shop`='.(int)self::getInfoEco('ID_SHOP'));

		Db::getInstance()->execute('TRUNCATE TABLE `'._DB_PREFIX_.'ec_ecopresto_product_shop_temp`');

		$this->UpdateUpdateDate('DATE_UPDATE_SELECT_ECO');
	}

	public function updateCatalogAll()
	{
		Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_product_shop` SET imported=1 WHERE `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
	}

	public function updateMAJStock($info = 1)
	{
		if (!is_array($info))
		{
			$info = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'module` WHERE `name` = "mailalerts"');
			Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'module` WHERE `name` = "mailalerts"');
			return $info;
		}
		else
		{
			$v = array();

			foreach ($info as $val)
				$v[] = '"'.pSQL($val).'"';

			Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'module` VALUES ('.implode(',', $v).')');
		}
	}

	public function updateCatalog($reference)
	{
		Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_product_shop_temp` (`reference`,`id_shop`) VALUES ("'.pSQL($reference).'",'.(int)self::getInfoEco('ID_SHOP').')');
	}

	public function getAllTax()
	{
		$all_tax = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `id_tax_eco`, `rate`
																	FROM  `'._DB_PREFIX_.'ec_ecopresto_tax`');
		$response = '';
		$tax_PS = TaxRulesGroup::getTaxRulesGroups();
		foreach ($all_tax as $tax)
		{
			$tax_eco = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT id_tax_rules_group FROM `'._DB_PREFIX_.'ec_ecopresto_tax_shop` WHERE `id_tax_eco`='.(int)$tax['id_tax_eco'].' AND `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
			$response .= '<p>Taxe EcoPresto : '.$tax['rate'].' =>';
			$response .= ' <select name="tax_ps[]">
							<option value="'.$tax['id_tax_eco'].'_0"> 0 </option>';
			foreach ($tax_PS as $taxPS)
				$response .= '<option value="'.$tax['id_tax_eco'].'_'.$taxPS['id_tax_rules_group'].'" '.($tax_eco == $taxPS['id_tax_rules_group']?'selected="selected"':'').'>'.$taxPS['name'].'</option>';
			$response .= '</select>';
		}
		return $response;
	}

	public function getAllAttributes()
	{
		$all_Attribut = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `id_attribute_eco`, `value`
																	FROM  `'._DB_PREFIX_.'ec_ecopresto_attribute`');
		$response = '';
		$Attribut_PS = AttributeGroup::getAttributesGroups(self::getInfoEco('ID_LANG'));
		foreach ($all_Attribut as $attribut)
		{
			$attribut_eco = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT id_attribute FROM `'._DB_PREFIX_.'ec_ecopresto_attribute_shop` WHERE `id_attribute_eco`='.(int)$attribut['id_attribute_eco'].' AND `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
			$response .= '<p>Attribut EcoPresto : '.$attribut['value'].' =>';
			$response .= ' <select name="attribut_ps[]">
							<option value="'.$attribut['id_attribute_eco'].'_0"> Créer automatiquement </option>';
			foreach ($Attribut_PS as $attributPS)
				$response .= '<option value="'.$attribut['id_attribute_eco'].'_'.$attributPS['id_attribute_group'].'" '.($attribut_eco == $attributPS['id_attribute_group']?'selected="selected"':'').'>'.$attributPS['name'].'</option>';
			$response .= '</select>';
		}
		return $response;
	}

	public function getAllLang()
	{
		$all_lang = Language::getLanguages(true);

		$response = '';
		$lang_ECOPRESTO = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `id_lang_eco`, `lang`
																	FROM  `'._DB_PREFIX_.'ec_ecopresto_lang`');
		foreach ($all_lang as $lang)
		{
			$lang_eco = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT id_lang_eco FROM `'._DB_PREFIX_.'ec_ecopresto_lang_shop` WHERE `id_lang`='.(int)$lang['id_lang'].' AND `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
			$response .= '<p>Langue Prestashop : '.$lang['name'].' =>';
			$response .= ' <select name="langECO[]">';
			foreach ($lang_ECOPRESTO as $language)
				$response .= '<option value="'.$lang['id_lang'].'_'.$language['id_lang_eco'].'" '.($lang_eco == $language['id_lang_eco']?'selected="selected"':'').'>'.$language['lang'].'</option>';
			$response .= '</select>';
		}
		return $response;
	}

	public function updateTax()
	{
		if (Tools::getValue('tax_ps') != '')
		{
			foreach (Tools::getValue('tax_ps') as $key => $val)
			{
				$value = explode('_', $val);
				$id_tax = Db::getInstance()->getValue('SELECT count(`id_tax_rules_group`) FROM `'._DB_PREFIX_.'ec_ecopresto_tax_shop` WHERE `id_shop`='.(int)self::getInfoEco('ID_SHOP').' AND `id_tax_eco`='.(int)$value[0]);

				if ($id_tax > 0)
					Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_tax_shop`
												SET `id_tax_rules_group` = '.(int)$value[1].'
												WHERE `id_tax_eco` = '.(int)$value[0].'
												AND `id_shop` = '.(int)self::getInfoEco('ID_SHOP'));
				else
					Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_tax_shop` (`id_tax_eco`,`id_tax_rules_group`,`id_shop`) VALUES ('.(int)$value[0].','.(int)$value[1].','.(int)self::getInfoEco('ID_SHOP').')');

			}
		}
	}

	public function updateAttributes()
	{
		if (Tools::getValue('attribut_ps') != '')
		{
			foreach (Tools::getValue('attribut_ps') as $key => $val)
			{
				$value = explode('_', $val);

				$id_attribute = Db::getInstance()->getValue('SELECT count(`id_attribute_eco`) FROM `'._DB_PREFIX_.'ec_ecopresto_attribute_shop` WHERE `id_shop`='.(int)self::getInfoEco('ID_SHOP').' AND `id_attribute_eco`='.(int)$value[0]);

				if ($id_attribute > 0)
					Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_attribute_shop`
												SET `id_attribute` = '.(int)$value[1].'
												WHERE `id_attribute_eco` = '.(int)$value[0].'
												AND `id_shop` = '.(int)self::getInfoEco('ID_SHOP'));
				else
					Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_attribute_shop` (`id_attribute_eco`,`id_attribute`,`id_shop`) VALUES ('.(int)$value[0].','.(int)$value[1].','.(int)self::getInfoEco('ID_SHOP').')');

			}
		}
	}

	public function updateLang()
	{
		foreach (Tools::getValue('langECO') as $key => $val)
		{
			$value = explode('_', $val);
			$id_lang = Db::getInstance()->getValue('SELECT `id_lang` FROM `'._DB_PREFIX_.'ec_ecopresto_lang_shop` WHERE `id_shop`='.(int)self::getInfoEco('ID_SHOP').' AND `id_lang`='.(int)$value[0]);
			if ($id_lang)
				Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_lang_shop`
				   SET `id_lang_eco` = '.(int)$value[1].'
				   WHERE `id_lang` = '.(int)$id_lang.'
				   AND `id_shop` = '.(int)self::getInfoEco('ID_SHOP'));
			else
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_lang_shop` (`id_lang`,`id_lang_eco`,`id_shop`) VALUES ('.(int)$value[0].','.(int)$value[1].','.(int)self::getInfoEco('ID_SHOP').')');
		}
	}

	public function updateConfig()
	{
		foreach (Tools::getValue('CONFIG_ECO') as $key => $val)
		{
			Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_configuration`
										SET `value`="'.pSQL($val).'"
										WHERE `name`="'.pSQL($key).'"
										'.($key != 'ID_ECOPRESTO'?'AND `id_shop` = '.(int)self::getInfoEco('ID_SHOP'):''));

			if ($key == 'ID_ECOPRESTO')
				Configuration::updateValue('ECOPRESTO_DEMO', 2);
		}
	}


	public function getCategory($categories, $current, $id_category = 1, $id_selected = 1)
	{
		$output = '<option value="'.$id_category.'"'.(($id_selected == $id_category) ? ' selected="selected"' : '').'>';
		$output .= str_repeat('&nbsp;', $current['infos']['level_depth'] * 5).Tools::stripslashes($current['infos']['name']).'</option>';

		if (isset($categories[$id_category]))
			foreach ($categories[$id_category] as $key => $row)
				$output .= self::getCategory($categories, $categories[$id_category][$key], $key, $id_selected);

		return $output;
	}



	private function getParam()
	{
		if ($results = Db::getInstance()->ExecuteS('SELECT `name`,`value` FROM `'._DB_PREFIX_.'ec_ecopresto_configuration` WHERE `id_shop`='.(int)self::getInfoEco('ID_SHOP')))
			foreach ($results as $f)
				$this->tabConfig[$f['name']] = $f['value'];
	}

	public function getInfoPdt()
	{
		if (Db::getInstance()->getValue('SELECT `value` FROM `'._DB_PREFIX_.'ec_ecopresto_info` WHERE `id`=12 AND((UNIX_TIMESTAMP(NOW()) - `value`)/86400)>7'))
		{
			self::updateInfoEco('ECO_INFO', time());
			$resu = '<export_info>';
			$resu .= '<password>'.$this->tabConfig['ID_ECOPRESTO'].'</password>';
			$resu .= '<info_pdt>';

			$lst_pdt = Db::getInstance()->executeS('SELECT `price`, `date_add`, `date_upd`, `active`, `supplier_reference`
				FROM  `'._DB_PREFIX_.'product` p, `'._DB_PREFIX_.'ec_ecopresto_product_shop` ps
				WHERE p.`supplier_reference` = ps.`reference`'
			);

			foreach ($lst_pdt as $pdt)
			{
				$resu .= '<sku>'.(isset($pdt['supplier_reference'])?$pdt['supplier_reference']:'').'</sku>';
				$resu .= '<price>'.(isset($pdt['price'])?$pdt['price']:'').'</price>';
				$resu .= '<date_add>'.(isset($pdt['date_add'])?$pdt['date_add']:'').'</date_add>';
				$resu .= '<date_upd>'.(isset($pdt['date_upd'])?$pdt['date_upd']:'').'</date_upd>';
				$resu .= '<active>'.(isset($pdt['active'])?$pdt['active']:'').'</active>';
			}
			$resu .= '</info_pdt>';
			$resu .= '</export_info>';
			include_once 'class/send.class.php';
			$send = new sendEco();
			$send->sendInfo(self::getInfoEco('ECO_URL_STAT'), $resu);
		}
	}

	public function deleteData()
	{
		Db::getInstance()->execute('TRUNCATE TABLE `'._DB_PREFIX_.'ec_ecopresto_catalog`');
		Db::getInstance()->execute('TRUNCATE TABLE `'._DB_PREFIX_.'ec_ecopresto_catalog_attribute`');
	}
	/**
	 * Supprime les données des tables produit avant de recharger l'ensemble de la base produit
	 *
	 *
	 * @param aucun
	 * @return boolean false si erreur
	 */
	public function deleteDataBrut()
	{
		Db::getInstance()->execute('TRUNCATE TABLE `'._DB_PREFIX_.'ec_ecopresto_catalog`');
		Db::getInstance()->execute('TRUNCATE TABLE `'._DB_PREFIX_.'ec_ecopresto_catalog_attribute`');
		Db::getInstance()->execute('TRUNCATE TABLE `'._DB_PREFIX_.'ec_ecopresto_attribute_shop`');
		Db::getInstance()->execute('TRUNCATE TABLE `'._DB_PREFIX_.'ec_ecopresto_attribute`');
	}

	public function SetSupplier()
	{
		Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'supplier` (`name`,`date_add`,`date_upd`,`active`) VALUES ("'.pSQL($this->supplier).'","'.pSQL(date('Y-m-d H:i:s')).'","'.pSQL(date('Y-m-d H:i:s')).'",1)');

		$idSupplier = Db::getInstance()->Insert_ID();

		if (($idSupplier) && ($idSupplier != 0))
		{
			$all_lang = Language::getLanguages(true);

			foreach ($all_lang as $lang)
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'supplier_lang` (`id_supplier`,`id_lang`) VALUES ('.(int)$idSupplier.','.(int)$lang['id_lang'].')');

			if (version_compare(_PS_VERSION_, '1.5', '>='))
			{
				$all_shop = Shop::getShops(false);

				foreach ($all_shop as $shop)
					Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'supplier_shop` (`id_supplier`,`id_shop`) VALUES ('.(int)$idSupplier.','.(int)$shop['id_shop'].')');

				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_configuration` (`value`,`name`,`id_shop`) VALUES ('.(int)$idSupplier.',"PARAM_SUPPLIER",'.(int)$shop['id_shop'].')');
			}
			else
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_configuration` (`value`,`name`,`id_shop`) VALUES ('.(int)$idSupplier.',"PARAM_SUPPLIER",1)');
		}
		else
			return false;
		return true;
	}

	public function SetTax()
	{
		$default_tax = 0;
		$all_tax = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `id_tax_eco` FROM  `'._DB_PREFIX_.'ec_ecopresto_tax`');

		if (version_compare(_PS_VERSION_, '1.5', '>='))
			$all_shop = Shop::getShops(false);

		foreach ($all_tax as $tax)
		{
			if (version_compare(_PS_VERSION_, '1.5', '>='))
				foreach ($all_shop as $shop)
					Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_tax_shop` (`id_tax_eco`,`id_tax_rules_group`,`id_shop`) VALUES ('.(int)$tax['id_tax_eco'].','.(int)$default_tax.','.(int)$shop['id_shop'].')');
			else
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_tax_shop` (`id_tax_eco`,`id_tax_rules_group`,`id_shop`) VALUES ('.(int)$tax['id_tax_eco'].','.(int)$default_tax.',1)');
		}

		return true;
	}

	public function SetLang()
	{
		$default_lang = 1;
		$all_lang = Language::getLanguages(true);

		if (version_compare(_PS_VERSION_, '1.5', '>='))
			$all_shop = Shop::getShops(false);

		foreach ($all_lang as $lang)
		{
			if (version_compare(_PS_VERSION_, '1.5', '>='))
				foreach ($all_shop as $shop)
					Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_lang_shop` (`id_lang_eco`,`id_lang`,`id_shop`) VALUES ('.(int)$default_lang.','.(int)$lang['id_lang'].','.(int)$shop['id_shop'].')');
			else
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_lang_shop` (`id_lang_eco`,`id_lang`,`id_shop`) VALUES ('.(int)$default_lang.','.(int)$lang['id_lang'].',1)');
		}

		return true;
	}

	public function deleteAttributes()
	{
		Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'ec_ecopresto_attribute`');
	}

	public function insertAttributes()
	{
		foreach ($this->tabAttributes as $value)
			if (!Db::getInstance()->getValue('SELECT `id_attribute_eco` FROM `'._DB_PREFIX_.'ec_ecopresto_attribute`  WHERE `value`="'.pSQL($value).'"'))
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_attribute` (`value`,`id_lang`) VALUES ("'.pSQL($value).'",'.(int)self::getInfoEco('ID_LANG').')');

	}

	public function matchAttributes()
	{
		$all_attributs = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `id_attribute_eco` FROM `'._DB_PREFIX_.'ec_ecopresto_attribute`');

		foreach ($all_attributs as $attribut)
		{
			$id_attribut = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT `id_attribute` FROM `'._DB_PREFIX_.'ec_ecopresto_attribute_shop` WHERE `id_attribute_eco`='.(int)$attribut.' AND `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
			if (!$id_attribut)
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_attribute_shop` (`id_attribute_eco`,`id_attribute`,`id_shop`) VALUES ('.(int)$attribut['id_attribute_eco'].',0,'.(int)self::getInfoEco('ID_SHOP').')');
		}
	}

	public function getTotalMAJ()
	{
		if ($this->tabConfig['UPDATE_PRODUCT'] == 1)
		{
			$lst_Sup = Db::getInstance()->execute('SELECT `reference`
				FROM `'._DB_PREFIX_.'ec_ecopresto_product_deleted`
				WHERE status=0'
			);

			if (isset($lst_Sup) && $lst_Sup[0])
			{
				$supp = array();

				foreach ($lst_Sup as $tab_Sup)
					$supp[] = '"'.pSQL($tab_Sup).'"';

				$supp = implode(',', $supp);
			}
			else
				$supp = '99999999999999999999999999';

		}
		$totalPdt = Db::getInstance()->getValue('SELECT count(ps.`reference`)
											FROM `'._DB_PREFIX_.'ec_ecopresto_catalog` c, `'._DB_PREFIX_.'ec_ecopresto_product_shop` ps
											WHERE c.`reference` = ps.`reference`
											'.($this->tabConfig['UPDATE_PRODUCT'] == 1?' AND ps.`reference` NOT IN ('.$supp.') ':'').'
											AND `id_shop`='.(int)self::getInfoEco('ID_SHOP'));

		$totalPdtSup = Db::getInstance()->getValue('SELECT count(`reference`)
											FROM `'._DB_PREFIX_.'ec_ecopresto_product_deleted`
											WHERE `status`=0');

		$pdtnotsup = $totalPdt - $totalPdtSup;

		if ($this->tabConfig['UPDATE_PRODUCT'] == 0)
			return $pdtnotsup.','.$totalPdtSup;
		else
		{
			$sup = $totalPdt - $pdtnotsup;
			return $pdtnotsup.','.$sup;
		}
	}

	public function matchTax()
	{
		foreach ($this->tabTVA as $tax)
		{
			$id_tax_eco = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT `id_tax_eco` FROM `'._DB_PREFIX_.'ec_ecopresto_tax` WHERE `rate`="'.(float)$tax.'"');

			if (!$id_tax_eco)
			{
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_tax` (`rate`) VALUES ("'.(float)$tax.'")');
				$idtaxeco = Db::getInstance()->Insert_ID();

				$idTaxR = Db::getInstance()->getValue('SELECT `id_tax_rules_group` 
											FROM `'._DB_PREFIX_.'tax` t, `'._DB_PREFIX_.'tax_rule` tr
											WHERE `rate` ='.(float)round($tax, 3).'
											AND `id_country` = '.(int)Configuration::get('PS_COUNTRY_DEFAULT').'
											AND t.`id_tax` = tr.`id_tax`');

				if (!isset($idTaxR) || $idTaxR == '')
					$idTaxR = 0;

				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_tax_shop` (`id_tax_eco`,`id_tax_rules_group`,`id_shop`) VALUES ('.(int)$idtaxeco.','.(int)$idTaxR.','.(int)self::getInfoEco('ID_SHOP').')');
			}
		}
	}

	public function getUpdateDate()
	{
		$all_date = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `action`, `dateupdate` FROM `'._DB_PREFIX_.'ec_ecopresto_update_date` WHERE `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
		$response = '';

		foreach ($all_date as $date_update)
			$response .= '<p>'.$date_update['action'].' => '.$date_update['dateupdate'].'</p>';

		return $response;
	}

	public function UpdateUpdateDate($action)
	{
		$today = date('Y-m-d H:i:s');

		if ($action == 'DATE_UPDATE_SELECT_ECO' || $action == 'DATE_IMPORT_PS')
			Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_configuration` SET `value`="'.pSQL($today).'" WHERE `name`="'.pSQL($action).'" AND `id_shop` = '.(int)self::getInfoEco('ID_SHOP'));
		else
		{
			if (version_compare(_PS_VERSION_, '1.5', '>='))
			{
				$all_shop = Shop::getShops(false);
				foreach ($all_shop as $shop)
					Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_configuration` SET `value`="'.pSQL($today).'" WHERE `name`="'.pSQL($action).'" AND `id_shop` = '.(int)$shop['id_shop']);
			}
			else
				Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_configuration` SET `value`="'.pSQL($today).'" WHERE `name`="'.pSQL($action).'" AND `id_shop` = 1');
		}
	}

	public function SetConfig()
	{
		if (version_compare(_PS_VERSION_, '1.5', '>='))
		{
			$all_shop_ps = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT S.`id_shop` FROM `'._DB_PREFIX_.'shop` AS S WHERE NOT EXISTS (SELECT DISTINCT(C.`id_shop`) FROM `'._DB_PREFIX_.'ec_ecopresto_configuration` C WHERE C.`id_shop` = S.`id_Shop`)');

			foreach ($all_shop_ps as $shop_ps)
			{
				foreach ($this->tabConfig as $key => $value)
				{
					if (Tools::substr($key, 0, 5) == 'DATE_')
						Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_configuration` (`name`,`value`,`id_shop`) VALUES ("'.pSQL($key).'","-",'.(int)$shop_ps['id_shop'].')');
					else
					{
						$result = Db::getInstance()->getValue('SELECT `value` FROM `'._DB_PREFIX_.'ec_ecopresto_configuration` WHERE `name`="'.pSQL($key).'" AND `id_shop`=1');
						Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_configuration` (`name`,`value`,`id_shop`) VALUES ("'.pSQL($key).'","'.pSQL($result).'",'.(int)$shop_ps['id_shop'].')');
					}
				}

				$idS = Db::getInstance()->getValue('SELECT `value` FROM `'._DB_PREFIX_.'ec_ecopresto_configuration` WHERE `name` = "PARAM_SUPPLIER" AND `id_shop` = 1');
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'supplier_shop` (`id_supplier`,`id_shop`) VALUES ('.(int)$idS.','.(int)$shop_ps['id_shop'].')');
				$result_att = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `id_attribute_eco`, `id_attribute` FROM `'._DB_PREFIX_.'ec_ecopresto_attribute_shop` WHERE `id_shop`=1');

				foreach ($result_att as $result_att1)
					Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_attribute_shop` (`id_attribute_eco`,`id_attribute`,`id_shop`) VALUES ('.(int)$result_att1['id_attribute_eco'].','.(int)$result_att1['id_attribute'].','.(int)$shop_ps['id_shop'].')');

				$result_lang = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `id_lang_eco`, `id_lang` FROM `'._DB_PREFIX_.'ec_ecopresto_lang_shop` WHERE `id_shop`=1');

				foreach ($result_lang as $result_lang1)
					Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_lang_shop` (`id_lang_eco`,`id_lang`,`id_shop`) VALUES ('.(int)$result_lang1['id_lang_eco'].','.(int)$result_lang1['id_lang'].','.(int)$shop_ps['id_shop'].')');

				$result_tax = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `id_tax_eco`, `id_tax_rules_group` FROM `'._DB_PREFIX_.'ec_ecopresto_tax_shop` WHERE `id_shop`=1');

				foreach ($result_tax as $result_tax1)
					Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_tax_shop` (`id_tax_eco`,`id_tax_rules_group`,`id_shop`) VALUES ('.(int)$result_tax1['id_tax_eco'].','.(int)$result_tax1['id_tax_rules_group'].','.(int)$shop_ps['id_shop'].')');
			}
		}
	}

	public function GetFilecsv()
	{
		$domain = Configuration::get('PS_SHOP_DOMAIN');
		
		$cle = $this->tabConfig['ID_ECOPRESTO'];

		$filename = $this->fichierDistant;
		include_once 'class/download.class.php';
		$download = new DownloadBinaryFile();
		$response = '';

		if ($download->load($filename) == true)
		{
			$download->saveTo('files/'.$this->fichierImport);
			$contenu_fichier = Tools::file_get_contents('files/'.$this->fichierImport);
			$lignesTot = substr_count($contenu_fichier, "\n");

			if ($lignesTot > 1)
			{
				$nbFichier = $this->cutFile();
				$response = '1,'.$lignesTot.','.$nbFichier;
			}
			else
				$response = '0,<p>Aucune mise à jour catalogue Ecopresto disponible.</p>';
		}
		else
			$response = '0,<p>Aucune mise à jour catalogue Ecopresto disponible.</p>';
		return $response;
	}
	/**
	 * Enregistre le catalogue CSV en provennance d'Ecopresto
	 *
	 *
	 * @param aucun
	 * @return false si erreur, nb ligne du fichier sinon
	 */
	public function GetCatalogCSV()
	{
		$domain = Configuration::get('PS_SHOP_DOMAIN');
		
		$cle = $this->tabConfig['ID_ECOPRESTO'];

		$filename = $this->fichierDistant;
		include_once _PS_ROOT_DIR_.'/modules/ecopresto/class/download.class.php';
		$download = new DownloadBinaryFile();
		$response = '';
		
		
		if ($download->load($filename) == true)
		{
			$download->saveTo(_PS_ROOT_DIR_.'/modules/ecopresto/files/'.$this->fichierImport);
			//Lire le nombre de ligne
			$linecount = 0;
			$handle = fopen(_PS_ROOT_DIR_.'/modules/ecopresto/files/'.$this->fichierImport, "r");
			while(!feof($handle)){
			  $line = fgets($handle, 4096);
			  $linecount = $linecount + substr_count($line, PHP_EOL);
			}
			fclose($handle);
			$nbproduit = $linecount -1;
			if ($linecount == 0)
				$response = false;
			else 
				$response = $nbproduit;
		}
		else
			$response = false;
			
		return $response;
	}
	
	/**
	 * Enregistre le fichier CSV dans une table temporaire
	 *
	 *
	 * @param aucun
	 * @return boolean false si erreur
	 */
	public function setCatalogCSVMySQL()
	{
		$sql = "LOAD DATA LOCAL INFILE '"._PS_ROOT_DIR_."/modules/ecopresto/files/".$this->fichierImport."' 
				INTO TABLE "._DB_PREFIX_."ec_ecopresto_cataloguebrut 
				CHARACTER SET utf8
				FIELDS TERMINATED BY ';' 
				ENCLOSED BY '\"' 
				LINES TERMINATED BY '\n'
				IGNORE 1 LINES;";
		if (!Db::getInstance()->execute($sql))
			return false;
		else 
			return true;
	}
	/**
	 * Enregistre le fichier CSV dans une table temporaire, via un parse PHP
	 *
	 *
	 * @param aucun
	 * @return boolean false si erreur
	 */
	public function setCatalogCSVMySQL_Parsephp(&$tabErreur)
	{
		//Lire la dernière position du pointeur, ou prendre au début si aucune ligne traitée
		$tabEtat = $this->etatCatalogBrutToEcopresto();
		if ($tabEtat[0] == $tabEtat[2])
			$pointeur = 0;
		else
			$pointeur = $this->getInfoEco('etape1_pointeur');
		
		//$pointeur = 0;
		$iteration_max = $this->getInfoEco('etape1_ligneatraiter');
		if ($iteration_max < 100 || $iteration_max > 100000)
			$iteration_max = 5000;
		
		$handle = fopen(_PS_ROOT_DIR_."/modules/ecopresto/files/".$this->fichierImport, 'r');
		fseek($handle, $pointeur);
		$res = "resultat : ";
		$i = 0;
		while ((($data = fgetcsv($handle, 10000, ';')) !== false) && ($i < $iteration_max))
		{
			$i++;
			
			$sql_catalogbrut = "INSERT INTO `"._DB_PREFIX_."ec_ecopresto_cataloguebrut` (`category_1`, `category_2`, `category_3`, `category_4`, `category_5`, `ss_category_1`, `ss_category_2`, `ss_category_3`, `ss_category_4`, `ss_category_5`, `reference`, `reference_attribute`, `manufacturer`, `attribute_1`, `attribute_2`, `attribute_3`, `attribute_4`, `attribute_5`, `name_1`, `name_2`, `name_3`, `name_4`, `name_5`, `description_short_1`, `description_short_2`, `description_short_3`, `description_short_4`, `description_short_5`, `description_1`, `description_2`, `description_3`, `description_4`, `description_5`, `price`, `image_1`, `image_2`, `image_3`, `image_4`, `image_5`, `image_6`, `rate`, `ean13`, `weight`, `pmvc`, `importbrut`) VALUES (
			'".pSQL($data[0])."', 
			'".pSQL($data[1])."', 
			'".pSQL($data[2])."', 
			'".pSQL($data[3])."', 
			'".pSQL($data[4])."', 
			'".pSQL($data[5])."', 
			'".pSQL($data[6])."', 
			'".pSQL($data[7])."', 
			'".pSQL($data[8])."', 
			'".pSQL($data[9])."', 
			'".pSQL($data[10])."', 
			'".pSQL($data[11])."', 
			'".pSQL($data[12])."', 
			'".pSQL($data[13])."', 
			'".pSQL($data[14])."', 
			'".pSQL($data[15])."', 
			'".pSQL($data[16])."', 
			'".pSQL($data[17])."', 
			'".pSQL($data[18])."', 
			'".pSQL($data[19])."', 
			'".pSQL($data[20])."', 
			'".pSQL($data[21])."', 
			'".pSQL($data[22])."', 
			'".pSQL($data[23])."', 
			'".pSQL($data[24])."', 
			'".pSQL($data[25])."', 
			'".pSQL($data[26])."', 
			'".pSQL($data[27])."', 
			'".pSQL($data[28])."', 
			'".pSQL($data[29])."', 
			'".pSQL($data[30])."', 
			'".pSQL($data[31])."', 
			'".pSQL($data[32])."', 
			'".pSQL($data[33])."', 
			'".pSQL($data[34])."', 
			'".pSQL($data[35])."', 
			'".pSQL($data[36])."', 
			'".pSQL($data[37])."', 
			'".pSQL($data[38])."', 
			'".pSQL($data[39])."', 
			'".pSQL($data[40])."', 
			'".pSQL($data[41])."', 
			'".pSQL($data[42])."', 
			'".pSQL($data[43])."', 
			'0');";
			if (!Db::getInstance(_PS_USE_SQL_SLAVE_)->execute($sql_catalogbrut))
				$tabErreur[] = 'Erreur SQL CatalogBrut: '.$sql_catalogbrut.' - Itération : '.$i;
			//$res .= $data[10].' - ';
			//Mettre à jour le pointeur
			$this->mettreajourInfoEco('etape1_pointeur', ftell($handle));
		}
		return true;
	}
	/**
	 * Enregistre le fichier CSV dans les tables catalogue et attributs, via un parse PHP
	 *
	 *
	 * @param aucun
	 * @return boolean false si erreur
	 */
	public function setCatalogCSVtoEcopresto_parsephp(&$tabErreur)
	{
		//Lire la dernière position du pointeur, ou prendre au début si aucune ligne traitée
		$tabEtat = $this->etatCatalogBrutToEcopresto();
		
		$pointeur = $this->getInfoEco('pointeurcsv');
		//Si premier passage, supprimer les données présentes dans le catalogue actuel
		if ($pointeur == 0) {
			$this->deleteData();
			//Supprimer les tables et les attributs ?
			$this->deleteDataBrut();
		}
		
		$tab_Attributes = array();
		//$pointeur = 0;
		$iteration_max = $this->getInfoEco('nbligneatraitercsv');
		if ($iteration_max < 100 || $iteration_max > 100000)
			$iteration_max = 5000;
		
		
		$handle = fopen(_PS_ROOT_DIR_."/modules/ecopresto/files/".$this->fichierImport, 'r');
		fseek($handle, $pointeur);
		$i = 0;
		while ((($data = fgetcsv($handle, 10000, ';')) !== false) && ($i < $iteration_max))
		{
			
			$i++;
			if ($i > 1) {
				//Requête pour insertion dans la table produit :
				$sql_catalog = "INSERT INTO  "._DB_PREFIX_."ec_ecopresto_catalog (`category_1` ,`category_2` ,`category_3` ,`category_4` ,`category_5` ,`ss_category_1` ,`ss_category_2` ,`ss_category_3` ,`ss_category_4` ,`ss_category_5` ,`name_1` ,`name_2` ,`name_3` ,`name_4` ,`name_5` ,`reference` ,`manufacturer` ,`description_short_1` ,`description_short_2` ,`description_short_3` ,`description_short_4` ,`description_short_5` ,`description_1` ,`description_2` ,`description_3` ,`description_4` ,`description_5` ,`image_1` ,`image_2` ,`image_3` ,`image_4` ,`image_5` ,`image_6` ,`rate` ,`price` ,`ean13` ,`weight` ,`pmvc`) VALUES (
				'".pSQL($data[0])."' ,
				'".pSQL($data[1])."' ,
				'".pSQL($data[2])."' ,
				'".pSQL($data[3])."' ,
				'".pSQL($data[4])."' ,
				'".pSQL($data[5])."' ,
				'".pSQL($data[6])."' ,
				'".pSQL($data[7])."' ,
				'".pSQL($data[8])."' ,
				'".pSQL($data[9])."' ,
				'".pSQL($data[18])."' ,
				'".pSQL($data[19])."' ,
				'".pSQL($data[20])."' ,
				'".pSQL($data[21])."' ,
				'".pSQL($data[22])."' ,
				'".pSQL($data[10])."' ,
				'".pSQL($data[12])."' ,
				'".pSQL($data[23])."' ,
				'".pSQL($data[24])."' ,
				'".pSQL($data[25])."' ,
				'".pSQL($data[26])."' ,
				'".pSQL($data[27])."' ,
				'".pSQL($data[28])."' ,
				'".pSQL($data[29])."' ,
				'".pSQL($data[30])."' ,
				'".pSQL($data[31])."' ,
				'".pSQL($data[32])."' ,
				'".pSQL($data[34])."' ,
				'".pSQL($data[35])."' ,
				'".pSQL($data[36])."' ,
				'".pSQL($data[37])."' ,
				'".pSQL($data[38])."' ,
				'".pSQL($data[39])."' ,
				'".pSQL($data[40])."' ,
				'".pSQL($data[33])."' ,
				'".pSQL($data[41])."' ,
				'".pSQL($data[42])."' ,
				'".pSQL($data[43])."');";
				if (!Db::getInstance(_PS_USE_SQL_SLAVE_)->execute($sql_catalog))
					$tabErreur[] = 'Erreur SQL Catalog: '.$sql_catalog.'<br/>Itération CSV: '.$i;
				
				if ($data[11] != '' && $data[10] != $data[11]) {
					//Requête pour insertion dans la table attributs :
					$sql_attribute = "INSERT INTO  "._DB_PREFIX_."ec_ecopresto_catalog_attribute (`reference_attribute` ,`reference` ,`price` ,`pmvc` ,`ean13` ,`weight` ,`attribute_1` ,`attribute_2` ,`attribute_3` ,`attribute_4` ,`attribute_5`) VALUES (
					'".pSQL($data[11])."',  
					'".pSQL($data[10])."',  
					'".pSQL($data[33])."',  
					'".pSQL($data[43])."',  
					'".pSQL($data[41])."',  
					'".pSQL($data[42])."',  
					'".pSQL($data[13])."',  
					'".pSQL($data[14])."',  
					'".pSQL($data[15])."',  
					'".pSQL($data[16])."',  
					'".pSQL($data[17])."');";
					if (!Db::getInstance(_PS_USE_SQL_SLAVE_)->execute($sql_attribute))
						$tabErreur[] = 'Erreur SQL Attribute: '.$sql_attribute.'<br/>Itération CSV: '.$i;
				}
				
				//Construire la liste des attributs : 
				if ($data[13] != '')
				{
					$att = $data[13];
					$explode_Att = explode('|', $att);
					foreach ($explode_Att as $lst_Att)
					{
						list($name_, $val_) = explode(':', $lst_Att);
						$name = ltrim(trim($name_));
						//On inscrit dans le tableau l'attribut sous sa propre clef, on évite les doublons ainsi
						$tab_Attributes[$name] = $name;
					}
				}
				if ($data[40] != '')
					array_push($this->tabTVA, $data[40]);
				//Mettre à jour le pointeur
				$this->mettreajourInfoEco('pointeurcsv', ftell($handle));
			}
			//insérer la liste des attributs
			$this->tabAttributes = $tab_Attributes;
			$this->insertAttributes();
			$this->matchAttributes();
			//insérer la liste des taux de TVA
			$tabtva = $this->tabTVA;
			$this->tabTVA = array_unique($tabtva);
			$this->matchTax();
		}
		return ftell($handle);
	}
	/*
	 * setCatalogBrutToEcopresto enregistre les données de la table cataloguebrut vers les tables permettant
	 * de sélectionner les produits Ecopresto. Le 
	 * @param aucun
	 * @return selon résultat, erreur ou nombre de ligne restant à traiter
	 */
	public function setCatalogBrutToEcopresto() {
		//Récupérer le nombre de ligne à traiter, remplacer par une valeur par défaut si trop grand ou texte
		$nbligne = $this->tabConfig['NB_LIGNE_IMPORT'];
		if ((int)$nbligne > 100000 || (int)$nbligne == 0) {
			$nbligne = 2000;
		}
		//chercher le nombre de ligne restant à traiter
		$tabEtat = $this->etatCatalogBrutToEcopresto();
		
		//Si premier passage, supprimer les données présentes dans le catalogue actuel
		if ($tabEtat[0] == $tabEtat[2]) {
			$this->deleteData();
			//Supprimer les tables et les attributs ?
			$this->deleteDataBrut();
		}

		$tab_Attributes = array();
		//Requête sur la table temporaire
		$produit_data = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT * FROM  `'._DB_PREFIX_.'ec_ecopresto_cataloguebrut` WHERE `importbrut` = 0 LIMIT '.pSQL($nbligne).'', true, 0);
		foreach ($produit_data as $data)
		{
			//Requête pour insertion dans la table produit :
			$sql_catalog = 
			"INSERT INTO  "._DB_PREFIX_."ec_ecopresto_catalog (`category_1` ,`category_2` ,`category_3` ,`category_4` ,`category_5` ,`ss_category_1` ,`ss_category_2` ,`ss_category_3` ,`ss_category_4` ,`ss_category_5` ,`name_1` ,`name_2` ,`name_3` ,`name_4` ,`name_5` ,`reference` ,`manufacturer` ,`description_short_1` ,`description_short_2` ,`description_short_3` ,`description_short_4` ,`description_short_5` ,`description_1` ,`description_2` ,`description_3` ,`description_4` ,`description_5` ,`image_1` ,`image_2` ,`image_3` ,`image_4` ,`image_5` ,`image_6` ,`rate` ,`price` ,`ean13` ,`weight` ,`pmvc`) VALUES (
			'".pSQL($data['category_1'])."' ,
			'".pSQL($data['category_2'])."' ,
			'".pSQL($data['category_3'])."' ,
			'".pSQL($data['category_4'])."' ,
			'".pSQL($data['category_5'])."' ,
			'".pSQL($data['ss_category_1'])."' ,
			'".pSQL($data['ss_category_2'])."' ,
			'".pSQL($data['ss_category_3'])."' ,
			'".pSQL($data['ss_category_4'])."' ,
			'".pSQL($data['ss_category_5'])."' ,
			'".pSQL($data['name_1'])."' ,
			'".pSQL($data['name_2'])."' ,
			'".pSQL($data['name_3'])."' ,
			'".pSQL($data['name_4'])."' ,
			'".pSQL($data['name_5'])."' ,
			'".pSQL($data['reference'])."' ,
			'".pSQL($data['manufacturer'])."' ,
			'".pSQL($data['description_short_1'])."' ,
			'".pSQL($data['description_short_2'])."' ,
			'".pSQL($data['description_short_3'])."' ,
			'".pSQL($data['description_short_4'])."' ,
			'".pSQL($data['description_short_5'])."' ,
			'".pSQL($data['description_1'])."' ,
			'".pSQL($data['description_2'])."' ,
			'".pSQL($data['description_3'])."' ,
			'".pSQL($data['description_4'])."' ,
			'".pSQL($data['description_5'])."' ,
			'".pSQL($data['image_1'])."' ,
			'".pSQL($data['image_2'])."' ,
			'".pSQL($data['image_3'])."' ,
			'".pSQL($data['image_4'])."' ,
			'".pSQL($data['image_5'])."' ,
			'".pSQL($data['image_6'])."' ,
			'".pSQL($data['rate'])."' ,
			'".pSQL($data['price'])."' ,
			'".pSQL($data['ean13'])."' ,
			'".pSQL($data['weight'])."' ,
			'".pSQL($data['pmvc'])."');";
			if (!Db::getInstance(_PS_USE_SQL_SLAVE_)->execute($sql_catalog))
				return false;
				
			if ($data['reference_attribute'] != '' && $data['reference'] != $data['reference_attribute']) {
				//Requête pour insertion dans la table attributs :
				$sql_attribute = "INSERT INTO  "._DB_PREFIX_."ec_ecopresto_catalog_attribute (`reference_attribute` ,`reference` ,`price` ,`pmvc` ,`ean13` ,`weight` ,`attribute_1` ,`attribute_2` ,`attribute_3` ,`attribute_4` ,`attribute_5`) VALUES (
				'".pSQL($data['reference_attribute'])."',  
				'".pSQL($data['reference'])."',  
				'".pSQL($data['price'])."',  
				'".pSQL($data['pmvc'])."',  
				'".pSQL($data['ean13'])."',  
				'".pSQL($data['weight'])."',  
				'".pSQL($data['attribute_1'])."',  
				'".pSQL($data['attribute_2'])."',  
				'".pSQL($data['attribute_3'])."',  
				'".pSQL($data['attribute_4'])."',  
				'".pSQL($data['attribute_5'])."');";
				if (!Db::getInstance(_PS_USE_SQL_SLAVE_)->execute($sql_attribute))
					return false;
			}
			
			$sql_updatecataloguebrut = 'UPDATE `'._DB_PREFIX_.'ec_ecopresto_cataloguebrut` SET importbrut=1 WHERE `reference_attribute`="'.pSQL($data['reference_attribute']).'"';
			if (!Db::getInstance(_PS_USE_SQL_SLAVE_)->execute($sql_updatecataloguebrut))
					return false;
			
			//Construire la liste des attributs : 
			if ($data['attribute_1'] != '')
			{
				$att = $data['attribute_1'];
				$explode_Att = explode('|', $att);
				foreach ($explode_Att as $lst_Att)
				{
					list($name_, $val_) = explode(':', $lst_Att);
					$name = ltrim(trim($name_));
					//On inscrit dans le tableau l'attribut sous sa propre clef, on évite les doublons ainsi
					$tab_Attributes[$name] = $name;
				}
			}
			if ($data['rate'] != '')
				array_push($this->tabTVA, $data['rate']);				
		//Fin du foreach $produit_data
		}
		//insérer la liste des attributs
		$this->tabAttributes = $tab_Attributes;
		$this->insertAttributes();
		$this->matchAttributes();
		//insérer la liste des taux de TVA
		$tabtva = $this->tabTVA;
		$this->tabTVA = array_unique($tabtva);
		$this->matchTax();
		
		//chercher le nombre de ligne restant à traiter après ce traitement
		$tabEtat = $this->etatCatalogBrutToEcopresto();
		if ($tabEtat[0] == 0) {
			if ($tabEtat[1] == $tabEtat[2]) {
				//mettre à jour l'info sur la date de dernier import
				$this->UpdateUpdateDate('DATE_IMPORT_ECO');
				return "termine";
			}
			else
				return "erreur";
		}
		else
			return $tabEtat[0];
	}
	/*
	 * etatCatalogBrutToEcopresto renvoi l'etat du traitement du catalogue brut : lignes totales, lignes traitées, lignes restantes, nombre d'étapes vu la configuration actuelle
	 * @param aucun
	 * @return array
	 */
	public function etatCatalogBrutToEcopresto() {
		//Récupérer le nombre de ligne à traiter, remplacer par une valeur par défaut si trop grand ou texte
		$nbligne = $this->tabConfig['NB_LIGNE_IMPORT'];
		if ((int)$nbligne > 100000 || (int)$nbligne == 0) {
			$nbligne = 2000;
		}
		$tabEtat = array();
		//Lignes restantes
		$tabEtat[0] = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT COUNT(*) FROM  `'._DB_PREFIX_.'ec_ecopresto_cataloguebrut` WHERE `importbrut` = 0', true, 0);
		//Lignes traitées
		$tabEtat[1] = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT COUNT(*) FROM  `'._DB_PREFIX_.'ec_ecopresto_cataloguebrut` WHERE `importbrut` = 1', true, 0);
		//Lignes totales
		$tabEtat[2] = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT COUNT(*) FROM  `'._DB_PREFIX_.'ec_ecopresto_cataloguebrut`', true, 0);
		//Calculer le nombre d'etape restante
		$tabEtat[3] = (int)($tabEtat[0] / $nbligne + 1);
		return $tabEtat;
	}
	/*
	 * etatParseCatalogue renvoi le pourcentage de traitement du fichier catalogue.csv, en fonction de sa taille et de la dernière position du pointeur
	 * @param aucun
	 * @return int
	 */
	public function etatParseCatalogue()
	{
		return round($this->getInfoEco('pointeurcsv') * 100 / filesize(_PS_ROOT_DIR_.'/modules/ecopresto/files/catalogue.csv'));
	}
				
	public function GetDereferencement()
	{
		$domain = Configuration::get('PS_SHOP_DOMAIN');
		$cle = $this->tabConfig['ID_ECOPRESTO'];

		$filename = $this->fichierDerefDistant;
		include 'class/download.class.php';
		$download = new DownloadBinaryFile();
		$response = '';

		if ($download->load($filename) == true)
		{
			$download->saveTo('files/'.$this->fichierDeref);
			$contenu_fichier = Tools::file_get_contents('files/'.$this->fichierDeref);
			$lignesTot = substr_count($contenu_fichier, "\n");
			if ($lignesTot > 1)
				$response = '1,'.$lignesTot;
			else
				$response = '0,<p>Aucun nouveau produit déréférencé.</p>';
		}
		else
			$response = '0,<p>Aucun nouveau produit déréférencé.</p>';

		return $response;
	}

	public function SetDerefencement()
	{
		include 'class/reference.class.php';

		if (($handle = fopen('files/'.$this->fichierDeref, 'r')) !== false)
		{
			while (($data = fgetcsv($handle, 10000, ';')) !== false)
			{
				$ref = $data[0];
				$dateDelete = $data[1];
				$refPre = '';
				$refPre = Db::getInstance()->getValue('SELECT `reference` FROM `'._DB_PREFIX_.'ec_ecopresto_product_deleted` WHERE `reference`="'.pSQL($ref).'"');

				$reference = new importerReference($ref);

				if ($refPre)
				{
					if ($reference->id_product)
						Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_product_deleted` SET `status`=0 WHERE `reference`="'.pSQL($ref).'"');
					else
						Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_product_deleted` SET `status`=1 WHERE `reference`="'.pSQL($ref).'"');
				}
				elseif ($reference->id_product)
					Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_product_deleted` (`reference`,`dateDelete`,`status`) VALUES ("'.pSQL($ref).'","'.pSQL($dateDelete).'",0)');
				else
					Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ec_ecopresto_product_deleted` (`reference`,`dateDelete`,`status`) VALUES ("'.pSQL($ref).'","'.pSQL($dateDelete).'",1)');
			}
		}
	}

	public function UpdateDereferencement($ref)
	{
		Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_product_deleted` SET `status`= 1 WHERE `reference`="'.pSQL($ref).'"');
	}
}
