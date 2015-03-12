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

class DownloadBinaryFile
{

	private $UA = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:21.0) Gecko/20100101 Firefox/21.0';
	private $REFERER = 'http://www.google.fr';

	private $rawdata = '';

	/**
	 * Télecharger un fichier distant
	 * Les données sont stockées dans le tampon $rawdata
	 * @param string $url
	 * @return boolean
	 */
	public function load($url)
	{
		$file = $url;
		$file_headers = @get_headers($file);

		if ($file_headers[0] == 'HTTP/1.1 404 Not Found')
			return false;
		else
		{
			$this->rawdata = Tools::file_get_contents($url);
			return true;
		}
	}

	/**
	 * Enregister les données en local
	 * @param string $filename
	 */
	public function saveTo($filename)
	{
		if (file_exists($filename))
			@unlink($filename);

		$fhandle = fopen($filename, 'x');

		if ($fhandle)
		{
			fwrite($fhandle, $this->rawdata);
			fclose($fhandle);
		}
	}

	/**
	 * Fixer le user agent pour la requet
	 * @param string $ua
	 */
	public function setUseragent($ua)
	{
		$this->UA = $ua;
	}

	/**
	 * Fixer le referer pour la requet
	 * @param string $referer
	 */
	public function setReferer($referer)
	{
		$this->REFERER = $referer;
	}
}
