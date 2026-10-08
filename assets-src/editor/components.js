/**
 * Presentational components for the DumpSEO sidebar.
 */
import { createElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { charLength, lengthBand } from './utils';

export const STATUS = {
	error: { symbol: '✕', label: __( 'Problem', 'dumpseo' ) },
	warning: {
		symbol: '!',
		label: __( 'Improvement', 'dumpseo' ),
	},
	info: { symbol: 'i', label: __( 'Note', 'dumpseo' ) },
	pass: { symbol: '✓', label: __( 'Good', 'dumpseo' ) },
};

/**
 * Status marker: symbol for sighted users, word for everyone (never color alone).
 *
 * @param {Object} props        Props.
 * @param {string} props.status Status.
 */
export function StatusMarker( { status } ) {
	const info = STATUS[ status ] || STATUS.info;
	return (
		<span className={ `dumpseo-status dumpseo-status--${ status }` }>
			<span aria-hidden="true" className="dumpseo-status__symbol">
				{ info.symbol }
			</span>
			<span className="dumpseo-status__label">{ info.label }</span>
		</span>
	);
}

/**
 * Character counter shown under a field.
 *
 * @param {Object} props      Props.
 * @param {string} props.text Rendered text.
 * @param {number} props.min  Recommended minimum.
 * @param {number} props.max  Recommended maximum.
 */
export function LengthHint( { text, min, max } ) {
	const length = charLength( text );
	const band = lengthBand( length, min, max );
	let message;
	switch ( band ) {
		case 'empty':
			message = __( 'Empty.', 'dumpseo' );
			break;
		case 'short':
			message = sprintf(
				/* translators: 1: characters, 2: recommended minimum, 3: recommended maximum. */
				__(
					'%1$d characters — a little short (aim for %2$d–%3$d).',
					'dumpseo'
				),
				length,
				min,
				max
			);
			break;
		case 'long':
			message = sprintf(
				/* translators: 1: characters, 2: recommended minimum, 3: recommended maximum. */
				__(
					'%1$d characters — may be cut off (aim for %2$d–%3$d).',
					'dumpseo'
				),
				length,
				min,
				max
			);
			break;
		default:
			message = sprintf(
				/* translators: 1: characters, 2: recommended minimum, 3: recommended maximum. */
				__( '%1$d characters — good length (%2$d–%3$d).', 'dumpseo' ),
				length,
				min,
				max
			);
	}
	return (
		<span className={ `dumpseo-length dumpseo-length--${ band }` }>
			{ message }
		</span>
	);
}

/**
 * Search result preview.
 *
 * @param {Object} props             Props.
 * @param {string} props.title       Rendered title.
 * @param {string} props.url         Page URL.
 * @param {string} props.description Rendered description.
 */
export function SearchPreview( { title, url, description } ) {
	return (
		<div
			className="dumpseo-preview"
			aria-label={ __( 'Search result preview', 'dumpseo' ) }
			role="group"
		>
			<p className="dumpseo-preview__url">{ url }</p>
			<p className="dumpseo-preview__title">
				{ title || __( '(no title)', 'dumpseo' ) }
			</p>
			<p className="dumpseo-preview__description">
				{ description ||
					__(
						'No description: search engines will pick text from the page.',
						'dumpseo'
					) }
			</p>
		</div>
	);
}

/**
 * List of analysis results.
 *
 * @param {Object} props        Props.
 * @param {Object} props.report Report {results: [...]}.
 */
export function ResultList( { report } ) {
	if ( ! report ) {
		return null;
	}
	return (
		<ul className="dumpseo-results">
			{ report.results.map( ( result ) => (
				<li
					key={ result.id }
					className={ `dumpseo-result dumpseo-result--${ result.status }` }
				>
					<StatusMarker status={ result.status } />
					<span className="dumpseo-result__message">
						{ result.message }
					</span>
					{ result.recommendation && (
						<span className="dumpseo-result__advice">
							{ result.recommendation }
						</span>
					) }
				</li>
			) ) }
		</ul>
	);
}
