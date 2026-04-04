<?php
/**
 * TabberNeue
 * TabberTransclude Class
 * Implement <tabbertransclude> tag
 *
 * @package TabberNeue
 * @author  alistair3149, Eric Fortin, Alexia E. Smith, Ciencia Al Poder
 * @license GPL-3.0-or-later
 * @link    https://www.mediawiki.org/wiki/Extension:TabberNeue
 */

declare( strict_types=1 );

namespace MediaWiki\Extension\TabberNeue;

use Exception;
use MediaWiki\Config\Config;
use MediaWiki\Extension\TabberNeue\DataModel\TabModel;
use MediaWiki\Extension\TabberNeue\Parsing\TabberTranscludeWikitextProcessor;
use MediaWiki\Extension\TabberNeue\Service\TabberRenderer;
use MediaWiki\Extension\TabberNeue\Service\TabIdGenerator;
use MediaWiki\Extension\TabberNeue\Service\TabParser;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Html\Html;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;

class TabberTransclude {

	public function __construct(
		private readonly TabberRenderer $renderer,
		private readonly TabParser $tabParser,
		private readonly TabIdGenerator $tabIdGenerator,
		private readonly HookContainer $hookContainer,
		private readonly Config $config,
		private readonly PermissionManager $permissionManager,
		private readonly UserFactory $userFactory,
	) {
	}

	/**
	 * Parser callback for <tabbertransclude> tag
	 */
	public function parserHook( ?string $input, array $args, Parser $parser, PPFrame $frame ): string {
		if ( $input === null ) {
			return '';
		}

		return $this->render( $input, $args, $parser, $frame );
	}

	/**
	 * Renders the necessary HTML for a <tabbertransclude> tag.
	 */
	public function render( string $input, array $args, Parser $parser, PPFrame $frame ): string {
		$processor = new TabberTranscludeWikitextProcessor(
			$parser,
			$this->tabParser,
			$this->tabIdGenerator,
			$this->permissionManager,
			$this->userFactory
		);
		$tabModels = $processor->process( $input );

		$resolvedModels = [];
		foreach ( $tabModels as $index => $tabModel ) {
			$tabContent = '';
			try {
				$tabContent = $this->prepareTransclusionPanel(
					$tabModel->content,
					$parser,
					$frame,
					$this->shouldInlineTab( $index )
				);
			} catch ( Exception ) {
				$tabContent = Html::errorBox(
					$parser->msg( 'tabberneue-error-transclusion-processing' )->escaped()
				);
			}

			$resolvedModels[] = new TabModel(
				$tabModel->name, $tabModel->label, $tabContent, $tabModel->pageUrl
			);
		}

		return $this->renderer->render(
			$resolvedModels, $args, $parser, 'tabberneue-tabbertransclude-category'
		);
	}

	private function shouldInlineTab( int $index ): bool {
		return $index === 0 && !$this->config->get( 'TabberNeueTranscludeFirstTabOnDemand' );
	}

	/**
	 * Build individual tab content HTML string.
	 *
	 * @throws Exception
	 */
	private function prepareTransclusionPanel(
		string $pageName,
		Parser $parser,
		PPFrame $frame,
		bool $shouldInlineTab,
	): string {
		$title = Title::newFromText( trim( $pageName ) );
		if ( !$title ) {
			// The error state is already handled in TabberTranscludeWikitextProcessor::parseTabContent()
			// TODO: This is not the best way to handle this.
			return $pageName;
		}

		if ( !$title->exists() || !$this->canReadTitle( $title, $parser ) ) {
			return Html::errorBox(
				$parser->msg( 'tabberneue-error-transclusion-unavailable' )->escaped()
			);
		}

		$titleText = $title->getPrefixedText();
		$wikitext = sprintf( '{{:%s}}', $titleText );

		if ( $shouldInlineTab ) {
			$html = $parser->recursiveTagParseFully(
				$wikitext,
				$frame
			);
		} else {
			$innerContentHtml = $parser->getLinkRenderer()->makeLink( $title, null );

			// TODO: Should probably refactor this hook, not sure if it's used anywhere else.
			$originalinnerContentHtml = $innerContentHtml;

			$this->hookContainer->run(
				'TabberNeueRenderLazyLoadedTab',
				[ &$innerContentHtml, $parser, $frame ]
			);
			if ( $originalinnerContentHtml !== $innerContentHtml ) {
				$parser->getOutput()->recordOption( 'tabberneuelazyupdated' );
			}

			$html = Html::rawElement(
				'div',
				[
					'class' => 'tabber__transclusion',
					'data-mw-tabber-page' => $titleText,
					'data-mw-tabber-revision' => $title->getLatestRevID()
				],
				$innerContentHtml
			);
		}

		// TODO: There might be a cleaner way to do this.
		$revRecord = $parser->fetchCurrentRevisionRecordOfTitle( $title );
		$parser->getOutput()->addTemplate(
			$title,
			$title->getArticleId(),
			$revRecord?->getId() ?? 0
		);

		return $html;
	}

	private function canReadTitle( Title $title, Parser $parser ): bool {
		$user = $this->userFactory->newFromUserIdentity( $parser->getUserIdentity() );

		return $this->permissionManager->userCan( 'read', $user, $title );
	}
}
