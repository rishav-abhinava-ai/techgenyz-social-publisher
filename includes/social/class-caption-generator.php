<?php
/** Platform-specific caption generation. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AISP_Caption_Generator {
	public static function generate( $platform, array $values ) {
		$platform = sanitize_key( $platform ); $template = (string) get_option( 'aisp_' . $platform . '_caption_template', '' );
		if ( '' === trim( $template ) ) { $template = self::default_template( $platform ); }
		$replacements = array(); foreach ( $values as $key => $value ) { $replacements[ '{' . $key . '}' ] = (string) $value; }
		$caption = trim( strtr( $template, $replacements ) );
		return 'x' === $platform ? self::prepare_x_caption( $caption, isset( $values['url'] ) ? $values['url'] : '' ) : $caption;
	}

	public static function default_template( $platform ) {
		$legacy = (string) get_option( 'aisp_message_format', '' ); if ( '' !== trim( $legacy ) ) { return $legacy; }
		$defaults = array( 'facebook' => "{title}\n\n{excerpt}\n\nRead more: {url}", 'linkedin' => "{title}\n\n{excerpt}\n\nRead the full article: {url}", 'x' => "{title}\n\n{url}" );
		return isset( $defaults[ $platform ] ) ? $defaults[ $platform ] : "{title}\n\n{url}";
	}

	/** Guarantees one complete server-derived canonical URL in X outbound text. */
	public static function prepare_x_caption( $caption, $url ) {
		$caption = trim( html_entity_decode( (string) $caption, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$url = esc_url_raw( html_entity_decode( trim( (string) $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $url ) { return self::limit_x_parts( $caption, '' ); }
		$canonical = self::normalized_url( $url );
		if ( preg_match_all( '~https?://[^\s<>"\']+~iu', $caption, $matches ) ) {
			foreach ( array_unique( $matches[0] ) as $found ) {
				$clean = rtrim( $found, '.,;:!?)]}' );
				if ( $canonical === self::normalized_url( $clean ) ) { $caption = str_replace( $clean, '', $caption ); }
			}
		}
		$caption = trim( preg_replace( "/\n{3,}/", "\n\n", $caption ) );
		return self::limit_x_parts( $caption, $url );
	}

	private static function limit_x_parts( $caption, $url ) {
		$suffix = '' !== $url ? "\n\n" . $url : '';
		if ( self::weighted_length( $caption . $suffix ) <= 280 ) { return $caption . $suffix; }
		$ellipsis = "\xE2\x80\xA6"; $available = 280 - self::weighted_length( $suffix ) - self::length( $ellipsis ); $parts = preg_split( '~(https?://[^\s<>"\']+)~iu', $caption, -1, PREG_SPLIT_DELIM_CAPTURE ); $shortened = ''; $used = 0;
		foreach ( $parts as $part ) {
			if ( preg_match( '~^https?://~i', $part ) ) {
				$separator = '' !== $shortened && ! preg_match( '/\s$/u', $shortened ) ? ' ' : '';
				$cost = self::length( $separator ) + 23;
				if ( $used + $cost <= $available ) { $shortened .= $separator . $part; $used += $cost; }
				continue;
			}
			$remaining = $available - $used;
			if ( $remaining > 0 ) { $take = min( $remaining, self::length( $part ) ); $shortened .= self::substring( $part, 0, $take ); $used += $take; }
		}
		return rtrim( $shortened ) . $ellipsis . $suffix;
	}

	/** Returns a practical X weighted length: every detected URL counts as 23. */
	public static function weighted_length( $text ) {
		$parts = preg_split( '~(https?://[^\s<>"\']+)~iu', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE ); $length = 0;
		foreach ( $parts as $part ) { $length += preg_match( '~^https?://~i', $part ) ? 23 : self::length( $part ); }
		return $length;
	}

	private static function normalized_url( $url ) { return untrailingslashit( esc_url_raw( html_entity_decode( trim( (string) $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ); }
	private static function length( $value ) { return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value ); }
	private static function substring( $value, $start, $length ) { return function_exists( 'mb_substr' ) ? mb_substr( $value, $start, $length ) : substr( $value, $start, $length ); }
}
