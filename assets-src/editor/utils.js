/**
 * Pure helpers for the DumpSEO sidebar (no WordPress imports, unit-tested).
 */

/**
 * Robots meta value ("noindex,nofollow") to a list of tokens.
 *
 * @param {string} value Stored value.
 * @return {string[]} Tokens.
 */
export function parseRobots( value ) {
	return String( value || '' )
		.split( ',' )
		.map( ( token ) => token.trim().toLowerCase() )
		.filter( Boolean );
}

/**
 * The index choice: "index", "noindex" or "" (default from settings).
 *
 * @param {string} value Stored value.
 * @return {string} Choice.
 */
export function indexChoice( value ) {
	const tokens = parseRobots( value );
	if ( tokens.includes( 'noindex' ) ) {
		return 'noindex';
	}
	return tokens.includes( 'index' ) ? 'index' : '';
}

/**
 * New robots value with the index choice replaced.
 *
 * @param {string} value  Stored value.
 * @param {string} choice "index", "noindex" or "".
 * @return {string} New value.
 */
export function withIndexChoice( value, choice ) {
	const tokens = parseRobots( value ).filter(
		( token ) => token !== 'index' && token !== 'noindex'
	);
	return ( choice ? [ choice, ...tokens ] : tokens ).join( ',' );
}

/**
 * New robots value with one directive switched on or off.
 *
 * @param {string}  value     Stored value.
 * @param {string}  directive e.g. "nofollow".
 * @param {boolean} enabled   Whether it should be present.
 * @return {string} New value.
 */
export function withDirective( value, directive, enabled ) {
	const tokens = parseRobots( value ).filter(
		( token ) => token !== directive
	);
	if ( enabled ) {
		tokens.push( directive );
	}
	return tokens.join( ',' );
}

/**
 * How a text length compares with a recommended range.
 *
 * @param {number} length Characters.
 * @param {number} min    Recommended minimum.
 * @param {number} max    Recommended maximum.
 * @return {'empty'|'short'|'good'|'long'} Band.
 */
export function lengthBand( length, min, max ) {
	if ( length === 0 ) {
		return 'empty';
	}
	if ( length < min ) {
		return 'short';
	}
	return length > max ? 'long' : 'good';
}

/**
 * Worst status across reports, ignoring "info"; "pass" when all passed.
 *
 * @param {Array<{counts: Object<string, number>}>} reports Reports.
 * @return {'error'|'warning'|'pass'} Status.
 */
export function overallStatus( reports ) {
	for ( const status of [ 'error', 'warning' ] ) {
		if (
			reports.some(
				( report ) =>
					report && report.counts && report.counts[ status ] > 0
			)
		) {
			return status;
		}
	}
	return 'pass';
}

/**
 * Character length as users see it (code points, not UTF-16 units).
 *
 * @param {string} text Text.
 * @return {number} Length.
 */
export function charLength( text ) {
	return Array.from( String( text || '' ) ).length;
}

/**
 * Body of an analysis request. Every text field is sent as a string: the
 * endpoint validates types, and the editor can return other types (for a
 * new post without a title, getEditedPostSlug() returns the numeric post ID).
 *
 * @param {number}                  postId Post ID.
 * @param {Object<string, unknown>} values Editor values.
 * @return {Object<string, number|string>} Request body.
 */
export function analysisRequest( postId, values ) {
	const text = ( value ) =>
		value === null || value === undefined ? '' : String( value );
	return {
		post_id: postId,
		keyphrase: text( values.keyphrase ),
		seo_title: text( values.seoTitle ),
		seo_description: text( values.seoDescription ),
		title: text( values.title ),
		slug: text( values.slug ),
		excerpt: text( values.excerpt ),
		content: text( values.content ),
	};
}
