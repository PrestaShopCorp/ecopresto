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

require_once dirname(__FILE__).'/class/catalog.class.php';
require_once dirname(__FILE__).'/class/reference.class.php';

if (!defined('_CAN_LOAD_FILES_'))
	exit;
/**
 * Lecture du fichier ligne à ligne
 *
 *
 * @param aucun
 * @return boolean false si erreur
 */

class ecopresto extends Module{
	private $_html = '';
	private $_postErrors = array();
	const INSTALL_SQL_FILE = 'create.sql';
	const UNINSTALL_SQL_FILE = 'drop.sql';


	public function __construct()
	{
		$this->name = 'ecopresto';
		$this->tab = 'shipping_logistics';
		$this->version = '2.20.0';
		$this->need_instance = 0;
		$this->author = 'Ecopresto';
		$this->displayName = $this->l('Ecopresto - Dropshipping');
		$this->description = $this->l('Importer vos produits en Drop shipping avec Ecopresto');
		$this->confirmUninstall = $this->l('Etes vous sur de vouloir désinstaller le module ?');

		parent::__construct();

	}

	public function install()
	{
		if (!$this->executeSQLFile(self::INSTALL_SQL_FILE) || !parent::install())
			return false;

		$catalog = new Catalog();
		//Si on trouve un "supplier" ECOPRESTO, alors on réutilise son ID, sinon on en créé un
		if (!$catalog->SetSupplier($catalog->verifierSupplier()))
			return false;
		if (!$catalog->SetTax())
			return false;
		if (!$catalog->SetLang())
			return false;

		self::updateInfoEco('ECO_TOKEN', md5(time()._COOKIE_KEY_));
		Configuration::updateValue('ECOPRESTO_DEMO', 0);
		return true;
	}

	public function uninstall()
	{
		return $this->executeSQLFile(self::UNINSTALL_SQL_FILE) && parent::uninstall();
	}

	public function getInfoEco($name)
	{
		return Db::getInstance()->getValue('SELECT `value` FROM `'._DB_PREFIX_.'ec_ecopresto_info` WHERE name="'.pSQL($name).'"');
	}

	public function updateInfoEco($name, $value)
	{
		return Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'ec_ecopresto_info` SET `value` = "'.pSQL($value).'" WHERE `name`="'.pSQL($name).'"');
	}

	public function executeSQLFile($file)
	{
		$path = realpath(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$this->name).DIRECTORY_SEPARATOR.'sql/';

		if (!file_exists($path.$file))
			return false;
		elseif (!$sql = Tools::file_get_contents($path.$file))
			return false;

		$sql = preg_split("/;\s*[\r\n]+/", str_replace('PREFIX_', _DB_PREFIX_, $sql));

		foreach ($sql as $query)
		{
			$query = trim($query);
			if ($query)
				if (!Db::getInstance()->Execute($query))
				{
					$this->_postErrors[] = Db::getInstance()->getMsgError().' '.$query;
					return false;
				}
		}
		return true;
	}
	/**
	 * verifierLicence Contrôle le numéro de licence
	 * Si le n° de licence renseigné ne semble pas bon, on place le module en mode demo avec la clef de demo
	 *
	 * @param string $id_eco la clef renseignée
	 * @return boolean true si licence OK
	 */
	private function verifierLicence($id_eco)
	{
		$catalog = new Catalog();
		if (!empty($id_eco)) {
			$res = Tools::file_get_contents(self::getInfoEco('ECO_URL_LIC').$id_eco);
			if (strpos($res, '#error') !== false) {
				if($id_eco != 'demo123456789demo123456789demo12')
					$catalog->mettreajourConfEco('ID_ECOPRESTO', 'demo123456789demo123456789demo12', '1');
				return false;
			}
			else
				return true;
		}
		else {
			if($id_eco != 'demo123456789demo123456789demo12')
				$catalog->mettreajourConfEco('ID_ECOPRESTO', 'demo123456789demo123456789demo12', '1');
			return false;
		}
			
	}
	 
	private function controleLicence($id_eco, $typ)
	{
		$res = Tools::file_get_contents(self::getInfoEco('ECO_URL_LIC').$id_eco);
		if (strpos($res, '#error') !== false)
			return $res;
		elseif ($typ == 1)
			return $res;
		else
		{
			if($id_eco != 'demo123456789demo123456789demo12')
				$catalog->mettreajourConfEco('ID_ECOPRESTO', 'demo123456789demo123456789demo12', '1');
			Configuration::updateValue('ECOPRESTO_CONFIGURATION_OK', true);
			return true;
		}
	}

	public function getContent()
	{
		$catalog = new Catalog();

		if (version_compare(_PS_VERSION_, '1.5', '>='))
		{
			if ('http://'.$_SERVER['HTTP_HOST'] != Tools::getShopDomain(true, true))
			{
				header('Location: '.Tools::getShopDomain(true, true).$_SERVER['REQUEST_URI']);
				exit();
			}
		}

		if (version_compare(_PS_VERSION_, '1.5', '>='))
		{
			self::updateInfoEco('ID_SHOP', $this->context->shop->id);
			self::updateInfoEco('ID_LANG', $this->context->language->id);
		}
		else
		{
			global $cookie;
			self::updateInfoEco('ID_SHOP', 1);
			self::updateInfoEco('ID_LANG', $cookie->id_lang);
		}

		$catalog->SetConfig();

		$output = '<div class="toolbarBox toolbarHead">
						<div class="pageTitle">
							<h3>
								<span id="current_obj" class="fontNa">
									<span class="breadcrumb item-0 ">Modules > </span>
									<span class="breadcrumb item-1 ">'.$this->displayName.'</span>
								</span>
							</h3>
						</div>
					</div>';

		if (Tools::isSubmit('maj_tax'))
		{
			$catalog->mettreajourInfoEco('isConfig', 1);
			$catalog->updateTax();
			$output .= $this->displayConfirmation($this->l('Paramètres de taxe correctement mis à jour.'));
		}
		if (Tools::isSubmit('maj_lang'))
		{
			$catalog->mettreajourInfoEco('isConfig', 1);
			$catalog->updateLang();
			$output .= $this->displayConfirmation($this->l('Paramètres de langue correctement mis à jour.'));
		}
		if (Tools::isSubmit('maj_config'))
		{
			$catalog->mettreajourInfoEco('isConfig', 1);
			$catalog->updateConfig();
			$output .= $this->displayConfirmation($this->l('Paramètres du module correctement mis à jour.'));
		}
		if (Tools::isSubmit('maj_attributes'))
		{
			$catalog->mettreajourInfoEco('isConfig', 1);
			$catalog->updateAttributes();
			$output .= $this->displayConfirmation($this->l('Paramètres des attributs correctement mis à jour.'));
		}
		
		if (Tools::isSubmit('reset_avertissement_ecopresto'))
		{
			$catalog->mettreajourInfoEco('isConfig', 0);
			$catalog->mettreajourInfoEco('isBienvenue', "0");
			$catalog->mettreajourInfoEco('isTableCatalogueBrut', "0");
			$catalog->mettreajourInfoEco('isImportCatalogue', "0");
			$catalog->mettreajourInfoEco('isProduitEnregistre', "0");
			$catalog->mettreajourInfoEco('isProduitImportPresta', "0");
			$output .= $this->displayConfirmation($this->l('Les messages d\'aide à la mise en service du module seront affichés.'));
		}
		if (Tools::isSubmit('ignore_tout_avertissement_ecopresto'))
		{
			$catalog->mettreajourInfoEco('isConfig', 1);
			$catalog->mettreajourInfoEco('isBienvenue', "1");
			$catalog->mettreajourInfoEco('isTableCatalogueBrut', "1");
			$catalog->mettreajourInfoEco('isImportCatalogue', "1");
			$catalog->mettreajourInfoEco('isProduitEnregistre', "1");
			$catalog->mettreajourInfoEco('isProduitImportPresta', "1");
			$output .= $this->displayConfirmation($this->l('Les messages d\'aide à la mise en service du module ne seront plus affichés.'));
		}
		if (Tools::isSubmit('masquer_message_bienvenue')) {
			$catalog->mettreajourInfoEco('isBienvenue', "1");
			$output .= $this->displayConfirmation($this->l('Le message de bienvenue ne sera plus affiché.'));
		}
		if (Tools::isSubmit('maj_import')) {
			$catalog->mettreajourInfoEco('nbligneatraitercsv', Tools::getValue('nbligneatraitercsv'));
			$output .= $this->displayConfirmation($this->l('Les paramètres d\'import sont correctement mis à jour.'));
		}
		// Méthode Parse CSV
		if (Tools::isSubmit('maj_catalogue_ecopresto'))
		{
			$catalog->mettreajourInfoEco('isTableCatalogueBrut', "0");
			$catalog->mettreajourInfoEco('isImportCatalogue', "0");
			$catalog->mettreajourInfoEco('isProduitEnregistre', "0");
			$catalog->mettreajourInfoEco('isProduitImportPresta', "0");
			$catalog->mettreajourInfoEco('pointeurcsv', 0);
			$catalog->deleteData();
			$catalog->deleteDataBrut();
			
			$var = $catalog->GetCatalogCSV();
	
			if (!$var)
				$output .= $this->displayError($this->l('Erreur lors du téléchargement du catalogue. Essayez à nouveau.'));
				
			else {
				$catalog->mettreajourInfoEco('isTableCatalogueBrut', "1");
				$output .= $this->displayConfirmation(sprintf($this->l('Catalogue téléchargé et tables intermédiaires vidées. Passez à l\'étape 2.')));
					 
			}
				
		}
		if (Tools::isSubmit('setCatalogBrutToEcopresto'))
		{
			$tabErreur = array();
			$var = $catalog->setCatalogCSVtoEcopresto_parsephp($tabErreur); 
			if (!$var)
				$output .= $this->displayError($this->l('Une erreur est survenue lors du traitement des produits. Essayez à nouveau.'));
			else {
				$traitement = $catalog->etatParseCatalogue();
				if ($traitement == 100) {
					$catalog->mettreajourInfoEco('isImportCatalogue', "1");
					$output .= $this->displayConfirmation($this->l('Cette étape est terminée. Vous pouvez sélectionner vos produits.'));
				}
				else
					$output .= $this->displayConfirmation($traitement.$this->l('% du fichier catalogue a été traité. Renouvelez cette étape.'));
			}
			//Affichage des erreurs SQL, le cas échéant:
			if (count($tabErreur) > 0 && _PS_MODE_DEV_) {
				$liste_erreur;
				foreach ($tabErreur as $erreur)
					$liste_erreur .='<li>'.$erreur.'</li>';
				$output .= $this->displayError('Erreurs rencontrées : <ul>'.$liste_erreur.'</ul>');
			}
		}
		return $output.$this->displayForm();
	}


	public function displayForm()
	{
		$html = '';
		$catalog = new Catalog();
		$licence = self::verifierLicence($catalog->tabConfig['ID_ECOPRESTO']);
		$onglet = "info";
		$isDemo = false;
		if (!$licence || $catalog->tabConfig['ID_ECOPRESTO'] == "demo123456789demo123456789demo12") {
			$isDemo = true;
			Configuration::updateValue('ID_ECOPRESTO', "demo123456789demo123456789demo12");
			$catalog->tabConfig['ID_ECOPRESTO'] = "demo123456789demo123456789demo12";
			$html .= $this->displayError($this->l('Vous utilisez une clef de licence de démonstration. Certaines fonctions ne seront peut-être pas disponibles. Si vous avez une clef de licence valide, utilisez le menu Réglages pour mettre à jour votre clef.'));
		}           
		$tabLic = explode(';', self::controleLicence($catalog->tabConfig['ID_ECOPRESTO'], 1));
		
		
		
		//Définition de l'onglet à afficher par défaut
		if (Tools::isSubmit('maj_tax') || Tools::isSubmit('maj_lang') || Tools::isSubmit('maj_config') || Tools::isSubmit('maj_attributes') || Tools::isSubmit('ignore_tout_avertissement_ecopresto') || Tools::isSubmit('reset_avertissement_ecopresto') || Tools::isSubmit('maj_import'))
			$onglet = "parametres";
        if (Tools::isSubmit('maj_catalogue_ecopresto') || Tools::isSubmit('setCatalogBrutToEcopresto') || Tools::isSubmit('enregistre_selection_produit'))
        	$onglet = "catalogue";
        if (Tools::isSubmit('creer_table_v220') || Tools::isSubmit('check_doublon_csv'))
        	$onglet = "aide";
		
		
		$nbTot = Db::getInstance()->getValue('SELECT count(distinct(`supplier_reference`)) FROM  `'._DB_PREFIX_.'product` p, `'._DB_PREFIX_.'ec_ecopresto_product_shop` ps WHERE p.`supplier_reference` = ps.`reference`');


		$html .= '<input type="hidden" name="idshop" value="'.(int)self::getInfoEco('ID_SHOP').'" id="idshop" />';
		$html .= '<input type="hidden" name="ec_token" value="'.self::getInfoEco('ECO_TOKEN').'" id="ec_token" />';
		$html .= '<script type="text/javascript">
			var textImportCatalogueEnCours = "'.$this->l('Import catalogue en cours...').'";
			var textImportCatalogueTermine = "'.$this->l('Import catalogue terminé avec succès').'";
			var textImportCatalogueErreur = "'.$this->l('Import catalogue non terminé : Erreur').'";
			var textMAJProduitsEnCours = "'.$this->l('Mise à jour des produits en cours...').'";
			var textMAJProduitsTermine = "'.$this->l('Mise à jour des produits terminé avec succès').'";
			var textSynchroEnCours = "'.$this->l('Synchronisation en cours...').'";
			var textSynchroTermine = "'.$this->l('Synchronisation terminée avec succès').'";
			var textSynchroErreur = "'.$this->l('Synchronisation non terminée : Erreur').'";
			var textDerefEnCours = "'.$this->l('Récupération des données articles déréférencés...').'";
			var textDerefTermine = "'.$this->l('Récupération des produits déréférencés terminée.').'";

		</script>';
		$html .= '<script type="text/javascript" src="../modules/ecopresto/js/tablefilter.js"></script>';
		$html .= '<script src="../modules/ecopresto/js/TFExt_ColsVisibility/TFExt_ColsVisibility.js" language="javascript" type="text/javascript"></script>';
		$html .= '<script src="../modules/ecopresto/js/XHRConnection.js"></script>';
		$html .= '<script src="../modules/ecopresto/js/function.js"></script>';
		$html .= '<link href="../modules/ecopresto/css/ec_ecopresto.css" rel="stylesheet">';
		$html .= '';

		$html .= '<div id="loading-div-background">
						<div id="loading-div" class="ui-corner-all" >
							<div class="progress progress-striped active well">

								<div class="bar barzeo"></div>

								<table class="barcent">
								<tr>
								<td>
								<div class="pull-right" id="pourcentage"><center>0%</center></div>
								</td>
								</tr>
								</table>
							</div>
							<h2 id="h2Modal" class="colgrfon">'.$this->l('Veuillez patienter').'</h2>
							<p id="titreModal">'.$this->l('Import catalogue en cours....').'</p>
							<p id="titreModalFin">'.$this->l('Import réalisé avec succès').'</p>
							<p id="titreModalErreur">'.$this->l('Erreur durant l\'import').'</p>
							<p id="closeModal"><a href="#" id="closeModalButton">'.$this->l('Fermer').'</a></p>
							<p id="closeModalWithoutReload"><a href="#" id="closeModalWithoutReloadButton">'.$this->l('Fermer').'</a></p>
						</div>
					</div>';
		
			
		//Barre de menu
		$html .= '<div class="eco_menubar">
					<ul>
						<li class="icone-accueil menuTabButton" id="menuTab12">'.$this->l('Accueil').'</li>
						<li class="icone-catalogue menuTabButton" id="menuTab2">'.$this->l('Catalogue').'</li>
						<li class="icone-commande menuTabButton" id="menuTab10">'.$this->l('Commandes').'</li>
						<li class="icone-suivi menuTabButton" id="menuTab11">'.$this->l('Suivi').'</li>
						<li class="icone-suppression menuTabButton" id="menuTab9">'.$this->l('Produits supprimés').'</li>
						<li class="icone-parametres menuTabButton" id="menuTab6">'.$this->l('Réglages').'</li>
						<li class="icone-aide menuTabButton" id="menuTab20">Support</li>
					</ul>
			</div>';
			
			
		
		if (!$catalog->getInfoEco('isBienvenue')) {
			
			$html .= '
				<div id="bienvenue">
					<div class="fond">
					<a href="https://www.youtube.com/watch?v=cjXictCZPos" title="'.$this->l('Comprendre comment fonctionne le dropshipping avec la vidéo').'" id="lien_video" target="_blank"></a>
					<div id="panneau_monsieur">'.$this->l('Cliquez sur l\'icône Catalogue pour importer une sélection de produits...').'</div>
					<div id="panneau_madame"><a href="http://www.ecopresto.com" target="_blank">'.$this->l('... et découvrez l\'intégralité du catalogue en ouvrant un compte Ecopresto!').'</a></div>
					</div>
					<div class="logo">
						<h3>'.$this->l('Ecopresto, Dropshipping Marketplace').'</h3>
						<p>'.$this->l('Rejoignez Ecopresto, place de marché européenne en dropshipping, créée pour aider les e-commerçants et les fournisseurs à développer leurs ventes.').'</p>
						<ul>
							<li>'.$this->l('Vous n\'avez pas de compte Ecopresto ? Vous pouvez tester notre module avec une sélection de produit. Découvrez l\'intégralité du catalogue en ouvrant un compte sur notre site Internet.').'</li>
							<li>'.$this->l('Vous êtes déjà client Ecopresto ? Utilisez le menu Réglages pour indiquer votre numéro de licence.').'</li>
							<li>'.$this->l('Vous avez besoin d\'aide ? Utilisez le menu Support pour lire la documentation et obtenir de l\'aide.').'</li>
						</ul>
						<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_attributes" method="post">
							<input type="submit" class="button" name="masquer_message_bienvenue" value="'.$this->l('Masquer ce message').'" />
						</form>
					</div>
				</div>
				';
		}
		
		$parametrescatalogue = '';
		if ($onglet == "catalogue")
			$parametrescatalogue = "selected";
		$html .= '<div id="tabList">';
		$html .= '<div id="menuTab2Sheet" class="tabItem '.$parametrescatalogue.'">';
		
		$html .= '<h3>'.$this->l('Catalogue Ecopresto').'</h3>';
		
		if ($catalog->getInfoEco('isConfig')) {
			
			if (!$catalog->getInfoEco('isTableCatalogueBrut')){
				$html .= '<div class="ec_inline step1ko"><form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_attributes" method="post">';
				$html .= '<h3>'.$this->l('Téléchargement du catalogue').'</h3>';
				$html .= '<p>'.$this->l('Récupérer les produits Ecopresto').'</p><input type="submit" class="button todo" name="maj_catalogue_ecopresto" value="'.$this->l('Faire cette étape').'" />';
				$html .= '</form></div>';
			}
			else 
			{
				$html .= '<div class="ec_inline step1ok"><form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_attributes" method="post">';
				$html .= '<h3>'.$this->l('Téléchargement du catalogue').'</h3>';
				$html .= '<p><strong>'.$this->l('Etape faite').'</strong> '.$this->l('Vous avez déjà récupéré le fichier du catalogue Ecopresto. En cliquant sur le bouton Mettre à jour, vous devrez refaire également l\'étape 2.').'</p><input type="submit" class="button" name="maj_catalogue_ecopresto" value="'.$this->l('Mettre à jour').'" />';
				$html .= '</form></div>';
			}
			
			if (!$catalog->getInfoEco('isTableCatalogueBrut') && !$catalog->getInfoEco('isImportCatalogue') ){
				$html .= '<div class="ec_inline step2ko"><form class="ec_inline step2" action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_attributes" method="post">';
				$html .= '<h3>'.$this->l('Import par lot').'</h3>';
				$html .= '<p>'.$this->l('Le traitement du catalogue ne peut avoir lieu que si le téléchargement est effectif.').'</p>';
				$html .= '<input type="submit" disabled class="button" name="noaction" value="'.$this->l('A faire').'" />';
				$html .= '</form></div>';
			}
			elseif ($catalog->getInfoEco('isTableCatalogueBrut') && !$catalog->getInfoEco('isImportCatalogue') ){
				
				if ($catalog->getInfoEco('pointeurcsv') == 0) {
					$html .= '<div class="ec_inline step2ko"><form class="ec_inline step2" action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="import_cataloguebrut_ecopresto" method="post">';
					$html .= '<h3>'.$this->l('Import par lot').'</h3>';
					$html .= '<p>'.
					$this->l('Traiter le fichier du catalogue. Cette étape se fait en plusieurs fois (choississez le nombre de lignes à traiter dans le menu Réglages)').'</p>';
					$html .= '<input type="submit" class="button todo" name="setCatalogBrutToEcopresto" value="'.$this->l('Commencer cette étape').'" />';
				}
				else {
					$traitement = $catalog->etatParseCatalogue();
					$html .= '<div class="ec_inline step2refresh"><form class="ec_inline step2" action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="import_cataloguebrut_ecopresto" method="post">';
					$html .= '<h3>'.$this->l('Import par lot').'</h3>';
					$html .= '<p>'.$this->l('Traiter le fichier du Catalogue. Cette étape est en cours. ').$traitement.$this->l('% du fichier traité.').'</p>';
					$html .= '<input type="submit" class="button retodo" name="setCatalogBrutToEcopresto" value="'.$this->l('Continuer cette étape').'" />';
				}
				
				$html .= '</form></div>';
			} else {
				$html .= '<div class="ec_inline step2ok">';
				$html .= '<h3>'.$this->l('Import par lot').'</h3>';
				$html .= '<p><strong>'.$this->l('Etape faite').'</strong> '.$this->l('Pour recommencer cette étape, vous devez refaire l\'étape 1. Il n\'y a aucun impact sur vos sélections précédentes.').'</p>';
				$html .= '</div>';
			}
			
				
			if (!$catalog->getInfoEco('isProduitEnregistre') && !($catalog->getInfoEco('isTableCatalogueBrut') && $catalog->getInfoEco('isImportCatalogue'))){
				$html .= '<div class="ec_inline step3ko">';
				$html .= '<h3>'.$this->l('Sélection des produits').'</h3>';
				$html .= '<p>'.$this->l('Vous devez avoir importé le catalogue pour choisir vos produits. Les sélections antérieures sont conservées.').'</p>';
				$html .= '</div>';
			} elseif (!$catalog->getInfoEco('isProduitEnregistre') && ($catalog->getInfoEco('isTableCatalogueBrut') && $catalog->getInfoEco('isImportCatalogue'))){
				$html .= '<div class="ec_inline step3ko">';
				$html .= '<h3>'.$this->l('Sélection des produits').'</h3>';
				$html .= '<p>'.$this->l('Utilisez le tableau ci-dessous pour sélectionner vos produits. Cliquez sur le bouton Enregistrer en bas pour enregistrer votre sélection.').'</p>';
				$html .= '</div>';
			} elseif ($catalog->getInfoEco('isProduitEnregistre')) {
				$html .= '<div class="ec_inline step3ok">';
				$html .= '<h3>'.$this->l('Sélection des produits').'</h3>';
				$html .= '<p><strong>'.$this->l('Etape faite').'</strong> '.$this->l('Vous pouvez modifier votre sélection de produit à tout moment. Utilisez le bouton Enregistrer en bas avant d\'importer les produits.').'</p>';
				$html .= '</div>';
			}
			if (!$catalog->getInfoEco('isProduitEnregistre') && !$catalog->getInfoEco('isProduitImportPresta')){
				$html .= '<div class="ec_inline step4ko">';
				$html .= '<h3>'.$this->l('Import des produits').'</h3>';
				$html .= '<p>'.$this->l('Vous devez avoir enregistré une sélection de produits pour les importer dans votre catalogue Prestashop.').'</p>';
				$html .= '</div>';
			} elseif ($catalog->getInfoEco('isProduitEnregistre') && !$catalog->getInfoEco('isProduitImportPresta')){
				$html .= '<div class="ec_inline step4ko">';
				$html .= '<h3>'.$this->l('Import des produits').'</h3>';
				$html .= '<p>'.$this->l('Cliquez sur le bouton Importer en bas pour importer les produits sélectionnés dans votre catalogue Prestashop.').'</p>';
				$html .= '</div>';
			} elseif ($catalog->getInfoEco('isProduitImportPresta')){
				$html .= '<div class="ec_inline step4ok">';
				$html .= '<h3>'.$this->l('Import des produits').'</h3>';
				$html .= '<p><strong>'.$this->l('Etape faite').'</strong> '.$this->l('Utilisez les boutons Enregistrer et Importer pour choisir vos produits et les importer dans Prestashop.').'</p>';
				$html .= '</div>';
			}
			$html .= '<div style="clear:both;">&nbsp;</div>';
			
		} else
			$html .= $this->displayError($this->l('Vous devez impérativement valider les paramètres de votre module avant de télécharger le catalogue. Cliquez sur l\'icône Réglages et validez les paramètres proposés.'));
		
		if ($catalog->getInfoEco('isTableCatalogueBrut') && $catalog->getInfoEco('isImportCatalogue')) {
			$cat = $sscat = '';
			$ncat = $sscat = -1;
	        $nsscat = 0;
	
			$all_catalog = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `category_1`, `ss_category_1`, `name_1`, `category_1`, `manufacturer`, `reference`, `price`, `pmvc`
																			FROM `'._DB_PREFIX_.'ec_ecopresto_catalog`
																		   ORDER BY `category_1`, `ss_category_1`,`name_1`');
	
			$all_selection = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `reference`, `id_shop`
																			FROM `'._DB_PREFIX_.'ec_ecopresto_product_shop`
																			WHERE `imported`=0 AND `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
			$pdt_sel = array();
			foreach ($all_selection as $selection)
				$pdt_sel[$selection['reference']] = ($selection['id_shop'] == self::getInfoEco('ID_SHOP')?1:'');
	
			$prestashopCategories = Category::getCategories((int)self::getInfoEco('ID_LANG'), false);
			$lstdercateg = $catalog->getCategory($prestashopCategories, $prestashopCategories[0][1], 1, 0);
		}
		if ($all_catalog)
		{
			$totalref = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT COUNT(distinct reference) FROM  `'._DB_PREFIX_.'ec_ecopresto_catalog`', true, 0);
			if ($totalref > 2000)
				$html .= '<div class="bootstrap">
							<div class="module_confirmation conf warning alert alert-warning">
								<button type="button" class="close" data-dismiss="alert">×</button>
								<strong>'.$this->l('Attention,').'</strong><br/>'.sprintf($this->l('Ecopresto vous propose de choisir parmi %1$d références différentes. Le tableau qui va vous permettre d\'afficher ces références peut-être long à afficher, et vos sélections peuvent être difficile à faire selon votre configuration locale (navigateur utilisé, puissance de votre ordinateur, etc.). Nous mettons tout en oeuvre pour réduire ces inconvénients, mais soyez patient si vous rencontrez des difficultés, elles sont le fait de traitement locaux, et non pas du serveur Ecopresto. Le cas échéant, nous vous invitons à tester votre module avec un autre navigateur, comme Google Chrome ou Opera.'), $totalref).'
							</div>
							</div>';
			
			
			$html .= '<span id="spnColMng"></span><div id="colsMng"></div>';

			$html .= '<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_catalogue" method="post">
						<table class="table" id="table1" cellspacing="0" cellpadding="0">
							<thead>
								<tr>
									<th><input type="checkbox" class="cbImporterAll" name="Importer" value="'.$this->l('Importer').'"></th>
									<th>'.$this->l('Catégorie Ecopresto').'</th>
									<th>'.$this->l('Sous catégorie Ecopresto').'</th>
									<th>'.$this->l('Catégorie locale').'</th>
									<th>'.$this->l('Référence').'</th>
									<th>'.$this->l('Produit').'</th>
									<th>'.$this->l('Marque').'</th>
									<th>'.$this->l('Prix HT').'</th>
									<th>'.$this->l('Prix de vente moyen HT').'</th>
									<th>'.$this->l('Marge').'</th>
								 </tr>
							</thead>
							<tbody>';

			foreach ($all_catalog as $resu)
			{
				$catSelected = $ssCatSelected = '';

				if ($resu['category_1'] != $cat)
				{
					$catSelected = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT `id_category` FROM `'._DB_PREFIX_.'ec_ecopresto_category_shop` WHERE `name`="'.pSQL(base64_encode($resu['category_1'])).'" AND `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
					$ncat++;
					$nsscat = -1;
					$html .= '<tr id='.$ncat.' class="row_hover">
									<td>
										<input type="checkbox" id="check'.$ncat.'" name="check'.$ncat.'" value="'.$ncat.'" class="checBB" />
									</td>
									<td class="cat cat'.$ncat.' curpoin"><span class="catdisplay">'.Tools::safeOutput($resu['category_1']).'</span></td>
									<td></td>
									<td>
										<span class="spancat '.($catSelected?' dnone ':'').'">
											'.$this->l('Créer automatiquement').'
											<img width="16" height="16" alt="edit" class="cuver" src="'._PS_ADMIN_IMG_.'edit.gif" class="imgcategorie" rel="'.base64_encode($resu['category_1']).'">
										</span>
										<select catSel="'.($catSelected > 0?$catSelected:0).'" name="catPS" class="selSpe '.(!$catSelected?' dnone ':'').'" rel="'.base64_encode($resu['category_1']).'">
												<option value="0">'.$this->l('Créer automatiquement').'</option>'.
						$lstdercateg
						.'</select>

									</td>
									<td></td>
									<td></td>
									<td></td>
									<td></td>
									<td></td>
									<td></td>
								 </tr>';
					$cat = $resu['category_1'];
				}

				if ($resu['ss_category_1'] != $sscat)
				{
					$ssCatSelected = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT `id_category` FROM `'._DB_PREFIX_.'ec_ecopresto_category_shop` WHERE `name`="'.pSQL(base64_encode($resu['ss_category_1'])).'" AND `id_shop`='.(int)self::getInfoEco('ID_SHOP'));
					$nsscat++;

					$html .= '<tr class="row_hover dnone" id='.$ncat.'___'.$nsscat.'>
									<td>
										<input type="checkbox" id="check'.$ncat.'___'.$nsscat.'" name="check'.$ncat.'___'.$nsscat.'" value="'.$ncat.'___'.$nsscat.'" class="checBB checBB'.$ncat.'" />
									</td>
									<td class="ssceza"><span class="catdisplay2">'.Tools::safeOutput($resu['category_1']).'</span></td>
									<td class="sscat sscat'.$ncat.' nsscat'.$nsscat.' curpoin">'.Tools::safeOutput($resu['ss_category_1']).'</td>
									<td>
										<span class="spancat '.($ssCatSelected?' dnone ':'').'">
											'.$this->l('Créer automatiquement').'
											<img width="16" height="16" alt="edit" class="cuver" src="'._PS_ADMIN_IMG_.'edit.gif" class="imgcategorie" rel="'.base64_encode($resu['ss_category_1']).'">
										</span>
										<select catSel="'.($ssCatSelected > 0?$ssCatSelected:0).'" name="catPS" class="selSpe '.(!$ssCatSelected?' dnone ':'').'" rel="'.base64_encode($resu['ss_category_1']).'">
												<option value="0">'.$this->l('Créer automatiquement').'</option>'.
						$lstdercateg
						.'</select>
									</td>
									<td></td>
									<td></td>
									<td></td>
									<td></td>
									<td></td>
									<td></td>
								</tr>';
					$sscat = $resu['ss_category_1'];
				}

				$html .= '<tr class="row_hover display_tr" id='.$ncat.'___'.$nsscat.'___'.Tools::safeOutput($resu['reference']).'>
									<td><input type="checkbox" id="check'.$ncat.'___'.$nsscat.'___'.Tools::safeOutput($resu['reference']).'" '.(isset($pdt_sel[$resu['reference']])?'checked="checked"':'').' rel="'.Tools::safeOutput($resu['reference']).'" name="check'.Tools::safeOutput($resu['reference']).'" value="'.$ncat.'___'.$nsscat.'___'.Tools::safeOutput($resu['reference']).'" class="checBB checBB'.$ncat.' checBB'.$ncat.'___'.$nsscat.' pdtI"></td>
									<td class="ssceza"><span class="catdisplay3">'.Tools::safeOutput($resu['category_1']).'</span></td>
									<td class="ssceza"><span class="sscatdisplay3">'.Tools::safeOutput($resu['ss_category_1']).'</span></td>
									<td><span class="catLoc'.Tools::safeOutput($resu['reference']).' dnone">'.base64_encode(Tools::safeOutput($resu['ss_category_1'])).'</span></td>
									<td>'.Tools::safeOutput($resu['reference']).'</td>
									<td class="pdt pdtcat'.$ncat.' pdtnsscat'.$nsscat.'sscat'.$ncat.'">'.Tools::safeOutput($resu['name_1']).'</td>
									<td>'.Tools::safeOutput($resu['manufacturer']).'</td>
									<td>'.Tools::safeOutput($resu['price']).'€</td>
									<td>'.Tools::safeOutput($resu['pmvc']).'€</td>
									<td>'.($resu['price'] > 0?round((($resu['pmvc'] - $resu['price']) / $resu['price'] * 100), 2):'?').'%</td>
							</tr>';
			}
			$html .= '</tbody>';
			$html .= '</table>';
			$html .= '</form>';
			$html .= '<script>
					   catSelSpeAfter();
					</script>';
						$html .= '<p class="spealer"><b>'.$this->l('Produits autorisés : ').'<span class="totAuth">'.$nbTot.'</span>/<span class="totAuthMax">'.$tabLic[2].'</span></b><p>';
		
		
			$html .= '<h3>'.$this->l('Etape 3').': '.$this->l('Sélection des produits').'</h3>';
			$html .= '<p>'.$this->l('Utilisez ce bouton pour enregistrer votre sélection de produits Ecopresto. Une fois l\'enregistrement effectué, vous pourrez importer cette sélection dans votre catalogue Prestashop (étape 4).').'
		 <br/><input type="submit" class="button" name="OK"  name="enregistre_selection_produit"  id="validSelect" value="'.$this->l('Enregistrer la sélection de produits').'" onclick="javascript:MAJProduct();" /></p>';
        
		 	if ($catalog->getInfoEco('isProduitEnregistre')) {
		 		$html .= '<h3>'.$this->l('Etape 4').': '.$this->l('Import des produits').'</h3>';
		 		$html .= '<p>'.$this->l('Utilisez ce bouton pour importer votre sélection de produits Ecopresto dans votre catalogue Prestashop. Une fois l\'import effectué, vous pouvez gérer ces produits depuis l\'interface de gestion des produits Prestashop.').'<br/><input type="button" onclick="javascript:recupInfoMajPS(1)" value="'.$this->l('Importer la selection dans ma boutique').'" class="button" /></p>';
		 	}
		 	
        }
		$html .= '</div>';


		//Onglet affiché si on revient vers les paramètres

		$parametres = '';
		if ($onglet == "parametres")
			$parametres = "selected";
		$html .= '<div id="menuTab6Sheet" class="tabItem '.$parametres.'">';
		
		$html .= '<div class="aide_locale"><h3>'.$this->l('A propos...').'</h3>'.$this->l('Chaque bloc de paramètres doit être validé individuellement en utilisant le bouton de validation correspondant.').'</div>';
		
		$html .= '<div class="aide_locale"><h3>'.$this->l('Tâches CRON').'</h3>'.$this->l('Pour le bon fonctionnement de votre module, vous devez ajouter 3 tâches cron qui devront appeler les URL suivantes :');
		$html .= '<ul><li>'.$this->l('Stock : ').'http://'.Tools::getShopDomainSsl().__PS_BASE_URI__.'modules/ecopresto/stock.php?ec_token='.Tools::safeOutput(self::getInfoEco('ECO_TOKEN')).'<blockquote><a href="http://'.Tools::getShopDomainSsl().__PS_BASE_URI__.'modules/ecopresto/stock.php?debug=1&ec_token='.Tools::safeOutput(self::getInfoEco('ECO_TOKEN')).'" target="_blank"><em>Lancer cette tâche manuellement</em></a></blockquote></li>';
				$html .= '<li>'.$this->l('Commande : ').'http://'.Tools::getShopDomainSsl().__PS_BASE_URI__.'modules/ecopresto/gen_com.php?ec_token='.Tools::safeOutput(self::getInfoEco('ECO_TOKEN')).'<blockquote><a href="http://'.Tools::getShopDomainSsl().__PS_BASE_URI__.'modules/ecopresto/gen_com.php?debug=1&ec_token='.Tools::safeOutput(self::getInfoEco('ECO_TOKEN')).'" target="_blank"><em>Lancer cette tâche manuellement</em></a></blockquote></li>';
				$html .= '<li>'.$this->l('Tracking : ').'http://'.Tools::getShopDomainSsl().__PS_BASE_URI__.'modules/ecopresto/tracking.php?ec_token='.Tools::safeOutput(self::getInfoEco('ECO_TOKEN')).'<blockquote><a href="http://'.Tools::getShopDomainSsl().__PS_BASE_URI__.'modules/ecopresto/tracking.php?debug=1&ec_token='.Tools::safeOutput(self::getInfoEco('ECO_TOKEN')).'" target="_blank"><em>Lancer cette tâche manuellement</em></a></blockquote></li></ul>';
		$html .= '</div>';
		
		$html .= '<fieldset><legend>'.$this->l('Votre licence Ecopresto').'</legend>';
		$html .= '<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_config" method="post">
					<p>'.$this->l('N° de licence').' : <input type="text" name="CONFIG_ECO[ID_ECOPRESTO]" value="'.$catalog->tabConfig['ID_ECOPRESTO'].'" class="longinput" /></p>
					<input type="submit" name="maj_config" value="'.$this->l('Activer').'" class="okpreac button"/>
				  </form>';
		$html .= '</fieldset>';
		
		$html .= '<fieldset><legend>'.$this->l('Paramètres du module Ecopresto').'</legend>';
		$html .= '<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_config" method="post">';

		
				

		$html .= '<h3>'.$this->l('Paramètres du module').'</h3>';
		$html .= '<p>'.$this->l('Importer les prix d\'achat :').'
					<input type="radio" name="CONFIG_ECO[PA_TAX]" value="1" '.(($catalog->tabConfig['PA_TAX'] == 1)?'checked=checked':'').' /> '.$this->l('HT').'
					<input type="radio" name="CONFIG_ECO[PA_TAX]" value="0" '.(($catalog->tabConfig['PA_TAX'] == 0)?'checked=checked':'').' /> '.$this->l('TTC').'
				</p>';
		$html .= '<p>'.$this->l('Prix de vente généralement constaté : ').'
					<input type="radio" name="CONFIG_ECO[PMVC_TAX]" value="1" '.(($catalog->tabConfig['PMVC_TAX'] == 1)?'checked=checked':'').' /> '.$this->l('HT').'
					<input type="radio" name="CONFIG_ECO[PMVC_TAX]" value="0" '.(($catalog->tabConfig['PMVC_TAX'] == 0)?'checked=checked':'').' /> '.$this->l('TTC').'
				</p>';

		//$html .= '<h3>'.$this->l('Paramètres autres').'</h3>';

		$html .= '<p>'.$this->l('Mettre à jour les prix de vente généralement constaté : ').'
					<input type="radio" name="CONFIG_ECO[UPDATE_PRICE]" value="1" '.(($catalog->tabConfig['UPDATE_PRICE'] == 1)?'checked=checked':'').' /> <img title="'.$this->l('Oui').'" alt="'.$this->l('Oui').'" src="../img/admin/enabled.gif">
					<input type="radio" name="CONFIG_ECO[UPDATE_PRICE]" value="0" '.(($catalog->tabConfig['UPDATE_PRICE'] == 0)?'checked=checked':'').' /> <img title="'.$this->l('Non').'" alt="'.$this->l('Non').'" src="../img/admin/disabled.gif">
				</p>';
		$html .= '<p>'.$this->l('Mettre à jour les EAN : ').'
					<input type="radio" name="CONFIG_ECO[UPDATE_EAN]" value="1" '.(($catalog->tabConfig['UPDATE_EAN'] == 1)?'checked=checked':'').' /> <img title="'.$this->l('Oui').'" alt="'.$this->l('Oui').'" src="../img/admin/enabled.gif">
					<input type="radio" name="CONFIG_ECO[UPDATE_EAN]" value="0" '.(($catalog->tabConfig['UPDATE_EAN'] == 0)?'checked=checked':'').' /> <img title="'.$this->l('Non').'" alt="'.$this->l('Non').'" src="../img/admin/disabled.gif">
				</p>';
		$html .= '<p>'.$this->l('Mettre à jour les noms et descriptions : ').'
					<input type="radio" name="CONFIG_ECO[UPDATE_NAME_DESCRIPTION]" value="0" '.(($catalog->tabConfig['UPDATE_NAME_DESCRIPTION'] == 0)?'checked=checked':'').' /> '.$this->l('Aucun').'
					<input type="radio" name="CONFIG_ECO[UPDATE_NAME_DESCRIPTION]" value="1" '.(($catalog->tabConfig['UPDATE_NAME_DESCRIPTION'] == 1)?'checked=checked':'').' /> '.$this->l('Juste les noms de produits').'
					<input type="radio" name="CONFIG_ECO[UPDATE_NAME_DESCRIPTION]" value="2" '.(($catalog->tabConfig['UPDATE_NAME_DESCRIPTION'] == 2)?'checked=checked':'').' /> '.$this->l('Juste les descrpitions de produits').'
					<input type="radio" name="CONFIG_ECO[UPDATE_NAME_DESCRIPTION]" value="3" '.(($catalog->tabConfig['UPDATE_NAME_DESCRIPTION'] == 3)?'checked=checked':'').' /> '.$this->l('Les deux').'
				</p>';
		$html .= '<p>'.$this->l('Mettre à jour les images : ').'
					<input type="radio" name="CONFIG_ECO[UPDATE_IMAGE]" value="1" '.(($catalog->tabConfig['UPDATE_IMAGE'] == 1)?'checked=checked':'').' /> <img title="'.$this->l('Oui').'" alt="'.$this->l('Oui').'" src="../img/admin/enabled.gif">
					<input type="radio" name="CONFIG_ECO[UPDATE_IMAGE]" value="0" '.(($catalog->tabConfig['UPDATE_IMAGE'] == 0)?'checked=checked':'').' /> <img title="'.$this->l('Non').'" alt="'.$this->l('Non').'" src="../img/admin/disabled.gif">
				</p>';
		$html .= '<p>'.$this->l('Supprimer les produits n’apparaissant plus dans le catalogue Ecopresto : ').'
					<input type="radio" name="CONFIG_ECO[UPDATE_PRODUCT]" value="1" '.(($catalog->tabConfig['UPDATE_PRODUCT'] == 1)?'checked=checked':'').' /> <img title="'.$this->l('Oui').'" alt="'.$this->l('Oui').'" src="../img/admin/enabled.gif">
					<input type="radio" name="CONFIG_ECO[UPDATE_PRODUCT]" value="0" '.(($catalog->tabConfig['UPDATE_PRODUCT'] == 0)?'checked=checked':'').' /> <img title="'.$this->l('Non').'" alt="'.$this->l('Non').'" src="../img/admin/disabled.gif">
				</p>';
		$html .= '<p>'.$this->l('Indexer les produits pour la recherche : ').'
					<input type="radio" name="CONFIG_ECO[PARAM_INDEX]" value="1" '.(($catalog->tabConfig['PARAM_INDEX'] == 1)?'checked=checked':'').' /> <img title="'.$this->l('Oui').'" alt="'.$this->l('Oui').'" src="../img/admin/enabled.gif">
					<input type="radio" name="CONFIG_ECO[PARAM_INDEX]" value="0" '.(($catalog->tabConfig['PARAM_INDEX'] == 0)?'checked=checked':'').' /> <img title="'.$this->l('Non').'" alt="'.$this->l('Non').'" src="../img/admin/disabled.gif">
				</p>';
		$html .= '<p>'.$this->l('Statut import de nouveaux produits : ').'
					<input type="radio" name="CONFIG_ECO[PARAM_NEWPRODUCT]" value="1" '.(($catalog->tabConfig['PARAM_NEWPRODUCT'] == 1)?'checked=checked':'').' /> '.$this->l('Actif').'
					<input type="radio" name="CONFIG_ECO[PARAM_NEWPRODUCT]" value="0" '.(($catalog->tabConfig['PARAM_NEWPRODUCT'] == 0)?'checked=checked':'').' /> '.$this->l('Désactivé').'
				</p>';
		$html .= '<p>'.$this->l('Mettre a jour seulement les nouveaux produits : ').'
					<input type="radio" name="CONFIG_ECO[PARAM_MAJ_NEWPRODUCT]" value="1" '.(($catalog->tabConfig['PARAM_MAJ_NEWPRODUCT'] == 1)?'checked=checked':'').' /> '.$this->l('Actif').'
					<input type="radio" name="CONFIG_ECO[PARAM_MAJ_NEWPRODUCT]" value="0" '.(($catalog->tabConfig['PARAM_MAJ_NEWPRODUCT'] == 0)?'checked=checked':'').' /> '.$this->l('Désactivé').'
				</p>';
		$html .= '<p>'.$this->l('Remontée de commande : ').'
					<input type="radio" name="CONFIG_ECO[IMPORT_AUTO]" value="1" '.(($catalog->tabConfig['IMPORT_AUTO'] == 1)?'checked=checked':'').' /> '.$this->l('Automatique').'
					<input type="radio" name="CONFIG_ECO[IMPORT_AUTO]" value="0" '.(($catalog->tabConfig['IMPORT_AUTO'] == 0)?'checked=checked':'').' /> '.$this->l('Manuelle').'
				</p>';

		$html .= '<p><input type="submit" class="button" name="maj_config" value="'.$this->l('Enregistrer').'" /></p>';
		$html .= '</form>';
		
		$html .= '</fieldset>';
		
		$html .= '<fieldset><legend>'.$this->l('Paramètres de langue').'</legend>';
		$html .= '<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_lang" method="post">';
		$html .= $catalog->getAllLang();
		$html .= '<p><input type="submit" class="button" name="maj_lang" value="'.$this->l('Mise à jour multilangue').'" /></p>';
		$html .= '</form>';
		$html .= '</fieldset>';

		$html .= '<fieldset><legend>'.$this->l('Paramètres de taxe').'</legend>';
		$html .= '<h3>'.$this->l('Parametrage Taxe').'</h3>';
		$html .= '<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_focus" method="post">';
		$html .= $catalog->getAllTax();
		$html .= '<p><input type="submit" class="button" name="maj_tax" value="'.$this->l('Mise à jour taxe').'" /></p>';
		$html .= '</form>';
		$html .= '</fieldset>';
		
		$html .= '<fieldset><legend>'.$this->l('Paramètres des attributs').'</legend>';
		$html .= '<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_attributes" method="post">';
		$html .= $catalog->getAllAttributes();
		$html .= '<p><input type="submit" class="button" name="maj_attributes" value="'.$this->l('Mise à jour attribut').'" /></p>';
		$html .= '</form>';
		$html .= '</fieldset>';
		
		
		$iteration_max = $this->getInfoEco('nbligneatraitercsv');
		if ($iteration_max < 100 || $iteration_max > 100000)
			$iteration_max = 5000;
		$html .= '<fieldset><legend>'.$this->l('Paramètres d\'import').'</legend>';
		$html .= '<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_attributes" method="post">';
		$html .= '<p>L\'import du catalogue est réalisé en plusieurs étapes. A chaque étape, le programme traite un certain nombre de ligne. La configuration de votre hébergement et les paramètres PHP associés peuvent limiter le volume de ces traitements.</p>';
		$html .= '<p>'.$this->l('Nombre de ligne a traiter par lot d\'import : ').'
					<input type="text" name="nbligneatraitercsv" value="'.$iteration_max.'" /></p>';
		$html .= '<p><input type="submit" class="button" name="maj_import" value="'.$this->l('Mise à jour import').'" /></p>';
		$html .= '</form>';
		$html .= '</fieldset>';
		
		$html .= '<fieldset><legend>'.$this->l('Afficher les messages d\'aide').'</legend>';
		$html .= '<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_attributes" method="post">';
		$html .= '<p>En cliquant sur ce bouton, vous déclencherez l\'affichage de tous les messages d\'aide à la mise en service du module. Il n\'y a pas d\'impact sur vos sélections de produit, ou toutes les autres actions du module.</p>';
		$html .= '<p><input type="submit" class="button" name="reset_avertissement_ecopresto" value="'.$this->l('Afficher les messages d\'aide').'" /></p>';
		$html .= '</form>';
		$html .= '</fieldset>';
		
		$html .= '<fieldset><legend>'.$this->l('Ignorer les messages d\'aide').'</legend>';
		$html .= '<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" name="form_attributes" method="post">';
		$html .= '<p>En cliquant sur ce bouton, vous supprimerez tous les messages d\'aide à la mise en service du module. Il n\'y a pas d\'impact sur vos sélections de produit, ou toutes les autres actions du module.</p>';
		$html .= '<p><input type="submit" class="button" name="ignore_tout_avertissement_ecopresto" value="'.$this->l('Ignorer les messages d\'aide').'" /></p>';
		$html .= '</form>';
		$html .= '</fieldset>';
		
		

		$html .= '</div>';
		
		//Affiché par défaut
		if ($onglet == "info")
			$info = "selected";
		$html .= '<div id="menuTab12Sheet" class="tabItem '.$info.'">';
		$html .= '<h3>'.$this->l('Bienvenue dans votre module Ecopresto').'</h3>';
		$html .= '<iframe border="0" frameborder="no" marginwidth="0" marginheight="0" height="800px;" src="'.Tools::safeOutput(self::getInfoEco('ECO_URL_ACTU')).Tools::safeOutput($catalog->tabConfig['ID_ECOPRESTO']).'" class="barcent" ></iframe>';
		$html .= '</div>';

		$html .= '<div id="menuTab10Sheet" class="tabItem">';
		$html .= '<h3>'.$this->l('Commandes Ecopresto').'</h3>';
		$commande = $catalog->getOrders(0);

		if (isset($commande) && count($commande) > 0)
		{
			$dossAdmin = explode('/index.php?', $_SERVER['REQUEST_URI']);
			$dossAdmin = explode('/', $dossAdmin[0]);
			$dossAdmin = $dossAdmin[count($dossAdmin) - 1];

			$html .= '<table id="list_order" class="table">';
			$html .= '<tr>';
			$html .= '<th>'.$this->l('ID commande').'</th>';
			$html .= '<th>'.$this->l('Date').'</th>';
			$html .= '<th>'.$this->l('Voir').'</th>';
			$html .= '<th>'.$this->l('Envoyer ecopresto').'</th>';
			$html .= '<th>'.$this->l('Ne pas envoyer').'</th>';
			$html .= '</tr>';

			foreach ($commande as $com)
			{
				$html .= '<tr id="orderMan'.$com['id_order'].'">';
				$html .= '<td>'.$com['id_order'].'</td>';
				$html .= '<td>'.$com['DatI'].'</td>';

				$html .= '<td><a target="_blank" href="'.__PS_BASE_URI__.$dossAdmin.'/index.php?'.(version_compare(_PS_VERSION_, '1.5', '<')?'tab':'controller').'=AdminOrders&id_order='.Tools::safeOutput($com['id_order']).'&vieworder&ec_token='.self::getInfoEco('ECO_TOKEN').'&token='.Tools::getAdminTokenLite('AdminOrders').'">Voir</a></td>';
				$html .= '<td><img src="'._PS_ADMIN_IMG_.'enabled.gif" class="sendCom" rel="'.$com['id_order'].'" /></td>';
				$html .= '<td><img src="'._PS_ADMIN_IMG_.'disabled.gif" class="NoSendCom" rel="'.$com['id_order'].'" /></td>';
				$html .= '</tr>';
			}
			$html .= '</table>';
		}
		else
			$html .= $this->l('Aucune commande en attente');

		$html .= '</div>';

		$html .= '<div id="menuTab11Sheet" class="tabItem">';
		$html .= '<h3>'.$this->l('Suivi des trackings commandes Ecopresto').'</h3>';
		$tracking = $catalog->getTracking();

		if (isset($tracking) && count($tracking) > 0)
		{
			$dossAdmin = explode('/index.php?', $_SERVER['REQUEST_URI']);
			$dossAdmin = explode('/', $dossAdmin[0]);
			$dossAdmin = $dossAdmin[count($dossAdmin) - 1];

			$html .= '<table id="list_order"  class="table">';
			$html .= '<tr>';
			$html .= '<th>'.$this->l('ID commande').'</th>';
			$html .= '<th>'.$this->l('Date exp').'</th>';
			$html .= '<th>'.$this->l('Numéro de tracking').'</th>';
			$html .= '<th>'.$this->l('Mode de tracking').'</th>';
			$html .= '<th>'.$this->l('Url de tracking').'</th>';
			$html .= '<th>'.$this->l('Voir la commande').'</th>';
			$html .= '</tr>';

			foreach ($tracking as $track)
			{
				$html .= '<tr id="orderTrack'.$track['id_order'].'">';
				$html .= '<td>'.$track['id_order'].'</td>';
				$html .= '<td>'.date('d/m/Y', $track['date_exp']).'</td>';
				$html .= '<td>'.Tools::safeOutput($track['numero']).'</td>';
				$html .= '<td>'.Tools::safeOutput($track['transport']).'</td>';
				$html .= '<td><a href="'.Tools::safeOutput($track['url_exp']).'" target="_blank">'.Tools::safeOutput($track['url_exp']).'</a></td>';
				$html .= '<td><a target="_blank" href="'.__PS_BASE_URI__.$dossAdmin.'/index.php?'.(version_compare(_PS_VERSION_, '1.5', '<')?'tab':'controller').'=AdminOrders&id_order='.Tools::safeOutput($track['id_order']).'&vieworder&token='.Tools::getAdminTokenLite('AdminOrders').'">Voir</a></td>';
				$html .= '</tr>';
			}
			$html .= '</table>';
		}
		else
			$html .= $this->l('Aucun tracking depuis 30 jours');

		$html .= '</div>';


		$html .= '<div id="menuTab9Sheet" class="tabItem">';
		$html .= '<h3>'.$this->l('Produits Ecopresto dérérencés').'</h3>';
		$html .= '<p><input type="submit" class="button" name="OK"  id="maj_dereferncement" value="'.$this->l('Importer les articles déréférencés').'" onclick="javascript:MAJDereferencement();" /></p>';

		$all_deref = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT `reference`, `dateDelete`
																		FROM `'._DB_PREFIX_.'ec_ecopresto_product_deleted`
																		WHERE `status`=0
																		ORDER BY `dateDelete`, `reference`');
		if (count($all_deref) != 0)
		{
			$html .= '<table class="table" id="table2">
							<thead>
								<tr>
									<th><input type="checkbox" name="Supprimer" value="'.$this->l('Supprimer').'" class="cbDerefAll" id="cbDerefAll"></th>
									<th>'.$this->l('Nom').'</th>
									<th>'.$this->l('Référence').'</th>
									<th>'.$this->l('Date').'</th>
								 </tr>
							</thead>
							<tbody>';

			foreach ($all_deref as $resu_deref)
			{
				$reference = new importerReference($resu_deref['reference']);
				$name = Db::getInstance()->getValue('SELECT `name` FROM `'._DB_PREFIX_.'product_lang` WHERE `id_product`='.(int)$reference->id_product);
				$html .= '<tr>';
				$html .= '<td><input type="checkbox" id="'.Tools::safeOutput($resu_deref['reference']).'" rel="" name="checkDeref" class="cbDeref"></td>';
				$html .= '<td>'.Tools::safeOutput($name).'</td>';
				$html .= '<td>'.Tools::safeOutput($resu_deref['reference']).'</td>';
				$html .= '<td>'.date('d/m/Y', $resu_deref['dateDelete']).'</td>';
				$html .= '</tr>';
			}
			$html .= '</tbody>';
			$html .= '</table>';
			$html .= '<p><input type="submit" class="button" name="'.$this->l('Supprimer les produits').'"  id="del_dereferncement" value="'.$this->l('Supprimer').'" onclick="javascript:DELDereferencement();" /></p>';
		}
		else
			$html .= $this->l('Aucun produit déréférencé');

		$html .= '</div>';
		
		//Affiché par défaut
		$aide = '';
		if ($onglet == "aide")
			$aide = "selected";
		$html .= '<div id="menuTab20Sheet" class="tabItem '.$aide.'">';
		$html .= '<div class="aide_locale"><h3>'.$this->l('Support technique').'</h3>'.$this->l('Vous pouvez faire une demande de support technique en utilisant le lien suivant : ').'<a href="http://addons.prestashop.com/contact-community.php?id_product=11052" target="_blank">'.$this->l('http://addons.prestashop.com/contact-community.php?id_product=11052').'</a></div>';
		$html .= '<div class="aide_locale"><h3>'.$this->l('Documentation technique').'</h3>'.$this->l('Vous pouvez télécharger notre documentation technique en utilisant le lien suivant : ').'<a href="http://www.ecopresto.com/images/pdf/ModulePrestashop_V220_'.$this->context->language->iso_code.'.pdf" target="_blank">http://www.ecopresto.com/images/pdf/ModulePrestashop_V220_'.$this->context->language->iso_code.'.pdf</a></div>';
		$html .= '<h3>'.$this->l('Informations de diagnostic').'</h3><em>'.$this->l('Pour accompagner votre demande de support, nous aurons probablement besoins des informations suivantes, ainsi que les informations situées en bas de page sous la rubrique "Tableau de bord / Informations techniques". Vous pouvez les copier/coller tel quel dans votre message :').'</em></em><ul>';
		$html .= '<li>'.$this->l('Version Prestashop').' : '._PS_VERSION_.'</li>';
		$html .= '<li>'.$this->l('Prestashop - cache activé ?').' : '._PS_CACHE_ENABLED_.'</li>';
		$html .= '<li>'.$this->l('Prestashop - SQL slave ?').' : '._PS_USE_SQL_SLAVE_.'</li>';
		$html .= '<li>'.$this->l('Prestashop - mode dev ?').' : '._PS_MODE_DEV_.'</li>';
		$html .= '<li>'.$this->l('Prestashop - mode demo ?').' : '._PS_MODE_DEMO_.'</li>';
		if (version_compare(_PS_VERSION_, '1.5', '>='))
		{
			$html .= '<li>'.$this->l('Prestashop - id shop ?').' : '.$this->context->shop->id.'</li>';
			$html .= '<li>'.$this->l('Prestashop - id lang ?').' : '.$this->context->language->id.'</li>';
		}
		$html .= '<hr/>';
		$html .= '<li>'.$this->l('Version PHP').' : '.phpversion().'</li>';
		$html .= '<li>'.$this->l('Max PHP Time').' : '.ini_get("max_execution_time").' sec.</li>';
		$html .= '<li>'.$this->l('Memory PHP').' : '.ini_get("memory_limit").' Mo</li>';
		$html .= '<li>'.$this->l('Début des tests sur les fonctions PHP : ').'</li>';
		if (!function_exists ('file_get_contents'))
			$html .= '<li>'.$this->l('PHP file_get_contents').' : <span style="color:orange">Fonction désactivée</span></li>';
		if (!function_exists ('file_get_contents'))
			$html .= '<li>'.$this->l('PHP fwrite').' : <span style="color:orange">Fonction désactivée</span></li>';
		if (!function_exists ('fopen'))
			$html .= '<li>'.$this->l('PHP fopen').' : <span style="color:orange">Fonction désactivée</span></li>';	
		$html .= '<li>'.$this->l('Fin des tests sur les fonction PHP - OK si aucun avertissement ci-dessus.').'</li>';	
			
		$html .= '<hr/>';
		if (is_writable(_PS_ROOT_DIR_.'/modules/ecopresto/files/'))
			$html .= '<li>'.$this->l('Droits d\'écriture sur le dossier ecopresto/files').' : Oui</li>';
		else
			$html .= '<li>'.$this->l('Droits d\'écriture sur le dossier ecopresto/files').' : <span style="color:orange">Non</span></li>';
		
		if (file_exists(_PS_ROOT_DIR_.'/modules/ecopresto/files/catalogue.csv')) {
			$html .= '<ul><li>'.$this->l('Le fichier catalogue.csv existe.').'</li>';
			if (is_writable(_PS_ROOT_DIR_.'/modules/ecopresto/files/catalogue.csv'))
				$html .= '<li>'.$this->l('Droits d\'écriture sur le fichier catalogue.csv').' : Oui</li>';
			else
				$html .= '<li>'.$this->l('Droits d\'écriture sur le fichier catalogue.csv').' : <span style="color:orange">Non</span></li>';
			$taille = (filesize(_PS_ROOT_DIR_.'/modules/ecopresto/files/catalogue.csv') / 1024) / 1024;
			$html .= '<li>'.$this->l('Taille du fichier catalogue.csv').' : '.round($taille, 2).' Mo</li>';
		} else 
			$html .= '<li><span style="color:orange">'.$this->l('Le fichier catalogue.csv n\'existe pas.').'</span></li>';
		$html .= '</ul><ul>';
		if (file_exists(_PS_ROOT_DIR_.'/modules/ecopresto/files/stock.xml')) {
			$html .= '<li>'.$this->l('Le fichier stock.xml existe.').'</li>';
			if (is_writable(_PS_ROOT_DIR_.'/modules/ecopresto/files/stock.xml'))
				$html .= '<li>'.$this->l('Droits d\'écriture sur le fichier stock.xml').' : Oui</li>';
			else
				$html .= '<li>'.$this->l('Droits d\'écriture sur le fichier stock.xml').' : <span style="color:orange">Non</span></li>';
			$taille = (filesize(_PS_ROOT_DIR_.'/modules/ecopresto/files/stock.xml') / 1024) / 1024;
			$html .= '<li>'.$this->l('Taille du fichier stock.xml').' : '.round($taille, 2).' Mo</li>';	
		} else 
			$html .= '<li><span style="color:orange">'.$this->l('Le fichier stock.xml n\'existe pas.').'</span></li>';
		$html .= '</ul><ul>';
		if (file_exists(_PS_ROOT_DIR_.'/modules/ecopresto/files/tracking.xml')) {
			$html .= '<li>'.$this->l('Le fichier tracking.xml existe.').'</li>';
			if (is_writable(_PS_ROOT_DIR_.'/modules/ecopresto/files/tracking.xml'))
				$html .= '<li>'.$this->l('Droits d\'écriture sur le fichier tracking.xml').' : Oui</li>';
			else
				$html .= '<li>'.$this->l('Droits d\'écriture sur le fichier tracking.xml').' : <span style="color:orange">Non</span></li>';
			$taille = (filesize(_PS_ROOT_DIR_.'/modules/ecopresto/files/tracking.xml') / 1024) / 1024;
			$html .= '<li>'.$this->l('Taille du fichier tracking.xml').' : '.round($taille, 2).' Mo</li>';	
		} else 
			$html .= '<li><span style="color:orange">'.$this->l('Le fichier tracking.xml n\'existe pas.').'</span></li>';
		$html .= '</ul></ul>';

		$html .= '</div>';
		
		$html .= '</div>';

		$html .= '<div class="footermodeco">
			<div id="infoSpe">
				<h3>Tableau de bord / Informations techniques</h3>
				
				<p>'.$this->l('Dernière remontée des commandes : ').Tools::safeOutput($catalog->tabConfig['DATE_ORDER']).'</p>
				<p>'.$this->l('Dernière remontée des stocks : ').Tools::safeOutput($catalog->tabConfig['DATE_STOCK']).'</p>
				<p>'.$this->l('Import catalogue Ecopresto : ').Tools::safeOutput($catalog->tabConfig['DATE_IMPORT_ECO']).'</p>
				<p>'.$this->l('Synchronisation de la sélection dans Prestashop : ').Tools::safeOutput($catalog->tabConfig['DATE_IMPORT_PS']).'</p>
				<p>'.$this->l('Mise à jour de la sélection dans le catalogue Ecopresto : ').Tools::safeOutput($catalog->tabConfig['DATE_UPDATE_SELECT_ECO']).'</p>';
		$html .= '<p>'.$this->l('N° de licence Ecopresto : ').Tools::safeOutput($catalog->tabConfig['ID_ECOPRESTO']).'</p>';
		if ($isDemo)
			$html .= '<p><strong>Vous utilisez une licence de démonstration. Utilisez le menu Réglages pour saisir votre numéro de licence.</strong></p>';
		else		
			$html .= '<p '.(isset($tabLic[1]) && $tabLic[1] < time()?' class="aleecoef" ':'').'>'.$this->l('Date de fin d\'adhésion : ').(isset($tabLic[1])?date('d/m/Y', $tabLic[1]):'').'</p>
				
				'.(isset($tabLic[3])?'<p '.('www.'.Configuration::get('PS_SHOP_DOMAIN') != 'www.'.$tabLic[3] && 'www.'.Configuration::get('PS_SHOP_DOMAIN') != 'http://'.$tabLic[3] && 'www.'.Configuration::get('PS_SHOP_DOMAIN') != $tabLic[3] && Configuration::get('PS_SHOP_DOMAIN') != 'www.'.$tabLic[3] && Configuration::get('PS_SHOP_DOMAIN') != $tabLic[3] && Tools::safeOutput($catalog->tabConfig['ID_ECOPRESTO']) != 'demo123456789demo123456789demo12'?' class="aleecoef" ':'').'>'.$this->l('URL du site enregistré : ').Tools::safeOutput($tabLic[3]):'').'</p>';
			
				
		$html .= '</div>';

		$html .='	
			<p class="fooCop">'.$this->l('Adonie SAS - Ecopresto | Tous droits réservés | L\'exploitation complète de ce module nécessite un abonnement auprès d\'Ecopresto.').'<br/><a href="http://www.ecopresto.com" target="_blank">http://www.ecopresto.com</a></p>
		</div>';

		return $html;
	}
}
