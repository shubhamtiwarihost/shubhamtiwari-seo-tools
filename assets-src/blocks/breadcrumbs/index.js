/**
 * "Breadcrumbs" block (editor side). The block is dynamic: PHP renders the
 * real trail on the frontend, so the editor shows a sample with the same markup.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const SAMPLE = [
	__( 'Home', 'dumpseo' ),
	__( 'Category', 'dumpseo' ),
	__( 'Current page', 'dumpseo' ),
];

function Edit() {
	const blockProps = useBlockProps( { className: 'dumpseo-breadcrumbs' } );
	return (
		<nav { ...blockProps } aria-label={ __( 'Breadcrumbs', 'dumpseo' ) }>
			<ol className="dumpseo-breadcrumbs__list">
				{ SAMPLE.map( ( name, index ) => (
					<li key={ name } className="dumpseo-breadcrumbs__item">
						{ index < SAMPLE.length - 1 ? (
							// Not a real link in the editor; the frontend links to the real pages.
							<a
								href="#dumpseo-breadcrumbs"
								onClick={ ( event ) => event.preventDefault() }
							>
								{ name }
							</a>
						) : (
							<span aria-current="page">{ name }</span>
						) }
						{ index < SAMPLE.length - 1 && (
							<span
								className="dumpseo-breadcrumbs__separator"
								aria-hidden="true"
							>
								›
							</span>
						) }
					</li>
				) ) }
			</ol>
		</nav>
	);
}

registerBlockType( 'dumpseo/breadcrumbs', {
	apiVersion: 3,
	title: __( 'Breadcrumbs', 'dumpseo' ),
	description: __(
		'Shows the path from the homepage to the current page. The trail is built for each page when it is displayed.',
		'dumpseo'
	),
	category: 'theme',
	icon: 'arrow-right-alt2',
	keywords: [ __( 'navigation', 'dumpseo' ), __( 'path', 'dumpseo' ) ],
	edit: Edit,
	save: () => null,
} );
