<?php

require_once( "OmegaWiki/WikiDataGlobals.php" );

class WikiLexicalDataHooks {

	public static function onBeforePageDisplay( $out, $skin ) {
		global $wgLang, $wgScriptPath, $wgRequest, $wgResourceModules;

		$out->addModules( 'ext.Wikidata.css' );
		$out->addModules( 'ext.Wikidata.ajax' );
		
		if ( $wgRequest->getText( 'action' )=='edit' ) {
			$out->addModules( 'ext.Wikidata.edit' );
			$out->addModules( 'ext.Wikidata.suggest' );
		}

		if ( $skin->getTitle()->isSpecialPage() ) {
			$out->addModules( 'ext.Wikidata.suggest' );
		}
		return true;
	}

	private static function isWikidataNs( $title ) {
		global $wdHandlerClasses;
		return array_key_exists( $title->getNamespace(), $wdHandlerClasses );
	}

	/**
	 * FIXME: This does not seem to do anything, need to check whether the
	 *        preferences are still being detected.
	 */
	public static function onGetPreferences( $user, &$preferences ) {
/*
		// preference to select between several available datasets
		$datasets = wdGetDatasets();
		foreach ( $datasets as $datasetid => $dataset ) {
			$datasetarray[$dataset->fetchName()] = $datasetid;
		}
		$preferences['ow_uipref_datasets'] = array(
			'type' => 'multiselect',
			'options' => $datasetarray,
			'section' => 'omegawiki',
			'label' => wfMsg( 'ow_shown_datasets' ),
		);
*/
		// allow the user to select the languages to display
		$preferences['ow_language_filter'] = array(
			'type' => 'check',
			'label' => '<b>' . wfMsg( 'ow_pref_lang_switch' ) . '</b>',
			'section' => 'omegawiki/ow-lang',
		);
		$preferences['ow_language_filter_list'] = array(
			'type' => 'multiselect',
			'label' => wfMsg( 'ow_pref_lang_select' ),
			'options' => array(), // to be filled later
			'section' => 'omegawiki/ow-lang',
		);

		$owLanguageNames = getOwLanguageNames();
		$col = new Collator('en_US.utf8');
		$col->asort( $owLanguageNames );
		foreach ( $owLanguageNames as $language_id => $language_name ) {
			$preferences['ow_language_filter_list']['options'][$language_name] = $language_id ;
		}

		return true;
	}

	public static function onArticleFromTitle( &$title, &$article ) {
		if ( self::isWikidataNs( $title ) ) {
			$article = new WikidataArticle( $title );
		}
		return true;
	}

	public static function onCustomEditor( $article, $user ) {
		if ( self::isWikidataNs( $article->getTitle() ) ) {
			$editor = new WikidataEditPage( $article );
			$editor->edit();
			return false;
		}
		return true;
	}

	public static function onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki ) {
		$action = $request->getVal( 'action' );
		if ( $action === 'history' && self::isWikidataNs( $title ) ) {
			$history = new WikidataPageHistory( $article );
			$history->history();
			return false;
		}
		return true;
	}

	public static function onAbortMove( $oldtitle, $newtitle, $user, &$error, $reason ) {
		if ( self::isWikidataNs( $oldtitle ) ) {
			$error = wfMsg( 'wikidata-handler-namespace-move-error' );
			return false;
		}
		return true;
	}

	/**
	* disable the "move" button for the Expression and DefinedMeaning namespaces
	* and prevent these pages to be moved like standard wiki pages
	* since they work differently
	*/
	public static function onNamespaceIsMovable( $index, $result ) {
		if ( ( $index == NS_EXPRESSION ) || ( $index == NS_DEFINEDMEANING ) ) {
			$result = false;
		}
		return true;
	}

	/**
	* Replaces the proposition to "create new page" by a custom,
	* allowing to create new expression as well
	*/
	public static function onNoGoMatchHook( &$title ) {
		global $wgOut,$wgDisableTextSearch;
		$wgOut->addWikiMsg( 'search-nonefound' );
		$wgOut->addWikiMsg( 'ow_searchnoresult', wfEscapeWikiText( $title ) );
	//	$wgOut->addWikiMsg( 'ow_searchnoresult', $title );

		$wgDisableTextSearch = true ;
		return true;
	}

	/**
	* The Go button should search (first) in the Expression namespace instead of Article namespace
	*/
	public static function onGoClicked( $allSearchTerms, &$title ) {
		$term = $allSearchTerms[0] ;
		$title = Title::newFromText( $term ) ;
		if ( is_null( $title ) ){
			return true;
		}

		// Replace normal namespace with expression namespace
		if ( $title->getNamespace() == NS_MAIN ) {
			$title = Title::newFromText( $term, NS_EXPRESSION ) ;
		}

		if ( $title->exists() ) {
			return false; // match!
		}
		return true; // no match
	}

	public static function onPageContentLanguage( $title, &$pageLang ) {
		if ( $title->getNamespace() === NS_EXPRESSION || $title->getNamespace() === NS_DEFINEDMEANING ) {
			global $wgLang;
			// in this wiki, we try to deliver content in the user language
			$pageLang = $wgLang;
		}
		return true;
	}

	public static function onSkinTemplateNavigation ( &$skin, &$links ) {

		// only for Expression and DefinedMeaning namespaces
		if ( ! self::isWikidataNs( $skin->getTitle() ) ) {
			return true;
		}

		// display an icon for enabling/disabling language filtering
		// only available in Vector.
		if ( $skin instanceof SkinVector ) {
			if ( $skin->getUser()->getOption( 'ow_language_filter' ) ) {
				// language filtering is on. The button is for disabling it
				$links['views']['switch_lang_filter'] = array (
					'class' => 'wld_lang_filter_on',
					'text' => '', // no text, just an image, see css
					'href' => $skin->getTitle()->getLocalUrl( "langfilter=off" ),
				);
			} else {
				// language filtering is off. The button is for enablingit
				$links['views']['switch_lang_filter'] = array (
					'class' => 'wld_lang_filter_off',
					'text' => '', // no text, just an image, see css
					'href' => $skin->getTitle()->getLocalUrl( "langfilter=on" ),
				);
			}
		}

		return true;
	}
}
