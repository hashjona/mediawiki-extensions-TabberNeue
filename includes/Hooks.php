<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\TabberNeue;

use MediaWiki\Config\Config;
use MediaWiki\Extension\TabberNeue\Scribunto\LuaLibrary;
use MediaWiki\Extension\TabberNeue\Service\TabberRenderer;
use MediaWiki\Extension\TabberNeue\Service\TabIdGenerator;
use MediaWiki\Extension\TabberNeue\Service\TabParser;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserFactory;

class Hooks implements ParserFirstCallInitHook {

	public function __construct(
		private readonly TabParser $tabParser,
		private readonly TabIdGenerator $tabIdGenerator,
		private readonly HookContainer $hookContainer,
		private readonly TabberRenderer $tabberRenderer,
		private readonly Config $config,
		private readonly PermissionManager $permissionManager,
		private readonly UserFactory $userFactory,
	) {
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 *
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ): void {
		$parser->setHook( 'tabber', [ new Tabber(
			$this->tabberRenderer,
			$this->tabParser,
			$this->tabIdGenerator,
		), 'parserHook' ] );
		$parser->setHook( 'tabbertransclude', [ new TabberTransclude(
			$this->tabberRenderer,
			$this->tabParser,
			$this->tabIdGenerator,
			$this->hookContainer,
			$this->config,
			$this->permissionManager,
			$this->userFactory,
		), 'parserHook' ] );
	}

	/**
	 * Register Lua libraries for Scribunto.
	 * We cannot use hook handlers because it does not support conditional registration.
	 *
	 * @return bool|void
	 */
	public static function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ) {
		if ( $engine !== 'lua' ) {
			return;
		}

		$extraLibraries['mw.ext.tabber'] = LuaLibrary::class;
	}
}
