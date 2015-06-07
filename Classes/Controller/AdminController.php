<?php

/**
 * @license GPLv3, http://www.gnu.org/copyleft/gpl.html
 * @copyright Metaways Infosystems GmbH, 2012
 * @copyright Aimeos (aimeos.org), 2014
 * @package TYPO3_Aimeos
 */


namespace Aimeos\Aimeos\Controller;


use Aimeos\Aimeos\Base;


/**
 * Controller for adminisration interface.
 *
 * @package TYPO3_Aimeos
 */
class AdminController extends AbstractController
{
	private $_context;
	private $_controller;


	/**
	 * Generates the index file for the admin interface.
	 */
	public function indexAction()
	{
		$html = '';
		$abslen = strlen( PATH_site );
		$langid = $this->_getContext()->getLocale()->getLanguageId();
		$controller = $this->_getController();

		foreach( Base::getAimeos()->getCustomPaths( 'client/extjs' ) as $base => $paths )
		{
			$relJsbPath = '../' . substr( $base, $abslen );

			foreach( $paths as $path )
			{
				$jsbAbsPath = $base . '/' . $path;

				if( !is_file( $jsbAbsPath ) ) {
					throw new \Exception( sprintf( 'JSB2 file "%1$s" not found', $jsbAbsPath ) );
				}

				$jsb2 = new \MW_Jsb2_Default( $jsbAbsPath, $relJsbPath . '/' . dirname( $path ) );
				$html .= $jsb2->getHTML( 'css' );
				$html .= $jsb2->getHTML( 'js' );
			}
		}

		// rawurldecode() is necessary for ExtJS templates to replace "{site}" properly
		$urlTemplate = rawurldecode( \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleUrl( $this->request->getPluginName(), array( 'tx_aimeos_web_aimeostxaimeosadmin' => array( 'site' => '{site}', 'tab' => '{tab}' ) ) ) );
		$serviceUrl = \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleUrl( $this->request->getPluginName(), array( 'tx_aimeos_web_aimeostxaimeosadmin' => array( 'controller' => 'Admin', 'action' => 'do' ) ) );

		$this->view->assign( 'htmlHeader', $html );
		$this->view->assign( 'lang', $langid );
		$this->view->assign( 'i18nContent', $this->_getJsonClientI18n( $langid ) );
		$this->view->assign( 'config', $this->_getJsonClientConfig() );
		$this->view->assign( 'site', $this->_getSite( $this->request ) );
		$this->view->assign( 'smd', $controller->getJsonSmd( $serviceUrl ) );
		$this->view->assign( 'itemSchemas', $controller->getJsonItemSchemas() );
		$this->view->assign( 'searchSchemas', $controller->getJsonSearchSchemas() );
		$this->view->assign( 'activeTab', ( $this->request->hasArgument( 'tab' ) ? (int) $this->request->getArgument( 'tab' ) : 0 ) );
		$this->view->assign( 'urlTemplate', $urlTemplate );
	}


	/**
	 * Single entry point for all MShop admin requests.
	 *
	 * @return JSON 2.0 RPC message response
	 */
	public function doAction()
	{
		$param = \TYPO3\CMS\Core\Utility\GeneralUtility::_POST();
		$this->view->assign( 'response', $this->_getController()->process( $param, 'php://input' ) );
	}


	/**
	 * Returns the context item
	 *
	 * @return \MShop_Context_Item_Interface Context item
	 */
	protected function _getContext()
	{
		if( !isset( $this->_context ) )
		{
			$config = $this->_getConfig( $this->settings );
			$context = Base::getContext( $config );

			$localeItem = $this->_getLocale( $context );
			$context->setLocale( $localeItem );

			$localI18n = ( isset( $this->settings['i18n'] ) ? $this->settings['i18n'] : array() );
			$i18n = Base::getI18n( array( $localeItem->getLanguageId() ), $localI18n );

			$context->setI18n( $i18n );
			$context->setEditor( $GLOBALS['BE_USER']->user['username'] );

			$this->_context = $context;
		}

		return $this->_context;
	}


	/**
	 * Returns the ExtJS JSON RPC controller
	 *
	 * @return \Controller_ExtJS_JsonRpc ExtJS JSON RPC controller
	 */
	protected function _getController()
	{
		if( !isset( $this->_controller ) )
		{
			$cntlPaths = Base::getAimeos()->getCustomPaths( 'controller/extjs' );
			$this->_controller = new \Controller_ExtJS_JsonRpc( $this->_getContext(), $cntlPaths );
		}

		return $this->_controller;
	}


	/**
	 * Returns the JSON encoded configuration for the ExtJS client.
	 *
	 * @return string JSON encoded configuration object
	 */
	protected function _getJsonClientConfig()
	{
		$conf = $this->_getContext()->getConfig()->get( 'client/extjs', array() );
		return json_encode( array( 'client' => array( 'extjs' => $conf ) ), JSON_FORCE_OBJECT );
	}


	/**
	 * Returns the JSON encoded translations for the ExtJS client.
	 *
	 * @param string $lang ISO language code like "en" or "en_GB"
	 * @return string JSON encoded translation object
	 */
	protected function _getJsonClientI18n( $lang )
	{
		$i18nPaths = Base::getAimeos()->getI18nPaths();
		$i18n = new \MW_Translation_Zend2( $i18nPaths, 'gettext', $lang, array('disableNotices'=>true) );

		$content = array(
			'client/extjs' => $i18n->getAll( 'client/extjs' ),
			'client/extjs/ext' => $i18n->getAll( 'client/extjs/ext' ),
		);

		return json_encode( $content, JSON_FORCE_OBJECT );
	}


	/**
	 * Returns the locale object for the context
	 *
	 * @param \MShop_Context_Item_Interface $context Context object
	 * @return \MShop_Locale_Item_Interface Locale item object
	 */
	protected function _getLocale( \MShop_Context_Item_Interface $context )
	{
		$langid = 'en';
		if( isset( $GLOBALS['BE_USER']->uc['lang'] ) && $GLOBALS['BE_USER']->uc['lang'] != '' ) {
			$langid = $GLOBALS['BE_USER']->uc['lang'];
		}

		$localeManager = \MShop_Locale_Manager_Factory::createManager( $context );

		try {
			$sitecode = $context->getConfig()->get( 'mshop/locale/site', 'default' );
			$localeItem = $localeManager->bootstrap( $sitecode, $langid, '', false );
		} catch( \MShop_Locale_Exception $e ) {
			$localeItem = $localeManager->createItem();
		}

		$localeItem->setLanguageId( $langid );

		return $localeItem;
	}


	/**
	 * Returns the JSON encoded site item.
	 *
	 * @param \TYPO3\CMS\Extbase\Mvc\RequestInterface $request TYPO3 request object
	 * @return string JSON encoded site item object
	 * @throws Exception If no site item was found for the code
	 */
	protected function _getSite( \TYPO3\CMS\Extbase\Mvc\RequestInterface $request )
	{
		$localeManager = \MShop_Locale_Manager_Factory::createManager( $this->_getContext() );
		$manager = $localeManager->getSubManager( 'site' );

		$site = 'default';
		if( $request->hasArgument( 'site' ) ) {
			$site = $request->getArgument( 'site' );
		}

		$criteria = $manager->createSearch();
		$criteria->setConditions( $criteria->compare( '==', 'locale.site.code', $site ) );
		$items = $manager->searchItems( $criteria );

		if( ( $item = reset( $items ) ) === false ) {
			throw new Exception( sprintf( 'No site found for code "%1$s"', $site ) );
		}

		return json_encode( $item->toArray() );
	}


	/**
	 * Uses default view.
	 *
	 * return Tx_Extbase_MVC_View_ViewInterface View object
	 */
	protected function resolveView()
	{
		return \TYPO3\CMS\Extbase\Mvc\Controller\ActionController::resolveView();
	}
}
