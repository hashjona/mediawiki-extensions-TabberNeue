<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\TabberNeue\Service;

use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\Sanitizer;

class TabIdGenerator {
	public function __construct(
		private readonly bool $parseTabName
	) {
	}

	/**
	 * Generates a sanitized ID from a label, suitable as a base for a unique ID.
	 */
	public function generateSanitizedId( string $label ): string {
		$labelText = trim( strip_tags( Sanitizer::decodeCharReferencesAndNormalize( $label ) ) );

		return Sanitizer::escapeIdForAttribute( $labelText );
	}

	/**
	 * Ensures the given ID is unique by appending a counter if necessary,
	 * using the provided ParserOutput to track existing IDs.
	 */
	public function ensureUniqueId( string $sanitizedId, ParserOutput $parserOutput ): string {
		$existingIds = $parserOutput->getExtensionData( 'tabber-ids' ) ?? [];
		$idCounter = ( $existingIds[ $sanitizedId ] ?? 0 ) + 1;
		$existingIds[ $sanitizedId ] = $idCounter;
		$parserOutput->setExtensionData( 'tabber-ids', $existingIds );

		return $idCounter > 1 ? $sanitizedId . '_' . $idCounter : $sanitizedId;
	}
}
