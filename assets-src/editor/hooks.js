/**
 * Data hooks for the DumpSEO sidebar.
 */
import apiFetch from '@wordpress/api-fetch';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';

import { analysisRequest } from './utils';

export const KEYS = {
	title: '_dumpseo_title',
	description: '_dumpseo_description',
	keyphrase: '_dumpseo_focus_keyphrase',
	canonical: '_dumpseo_canonical',
	robots: '_dumpseo_robots',
	socialTitle: '_dumpseo_social_title',
	socialDescription: '_dumpseo_social_description',
	socialImage: '_dumpseo_social_image',
};

/**
 * The post's DumpSEO meta and a setter. Values are saved with the post.
 *
 * @return {[Object<string, string>, function(string, string): void]} Meta and setter.
 */
export function useSeoMeta() {
	const meta = useSelect(
		( s ) => s( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
		[]
	);
	const { editPost } = useDispatch( 'core/editor' );
	const setMeta = ( key, value ) => editPost( { meta: { [ key ]: value } } );
	return [ meta, setMeta ];
}

/**
 * Runs the analysis on the unsaved post, one second after the last change.
 * Older requests are aborted so a slow response never overwrites a newer one.
 *
 * @param {Object<string, string>} meta Current DumpSEO meta.
 * @return {{data: Object|null, loading: boolean, error: string}} State.
 */
export function useAnalysis( meta ) {
	const { postId, title, slug, excerpt, blocks } = useSelect( ( s ) => {
		const editor = s( 'core/editor' );
		return {
			postId: editor.getCurrentPostId(),
			title: editor.getEditedPostAttribute( 'title' ),
			slug: editor.getEditedPostSlug(),
			excerpt: editor.getEditedPostAttribute( 'excerpt' ),
			// Only used to notice edits; serialising content happens when the timer fires.
			blocks: s( 'core/block-editor' ).getBlocks(),
		};
	}, [] );
	const [ state, setState ] = useState( {
		data: null,
		loading: false,
		error: '',
	} );

	const keyphrase = meta[ KEYS.keyphrase ] || '';
	const seoTitle = meta[ KEYS.title ] || '';
	const seoDescription = meta[ KEYS.description ] || '';

	useEffect( () => {
		if ( ! postId ) {
			return undefined;
		}
		const controller =
			typeof window.AbortController === 'function'
				? new window.AbortController()
				: null;
		const timer = window.setTimeout( () => {
			setState( ( previous ) => ( { ...previous, loading: true } ) );
			apiFetch( {
				path: '/dumpseo/v1/analysis',
				method: 'POST',
				signal: controller ? controller.signal : undefined,
				data: analysisRequest( postId, {
					keyphrase,
					seoTitle,
					seoDescription,
					title,
					slug,
					excerpt,
					content: select( 'core/editor' ).getEditedPostContent(),
				} ),
			} )
				.then( ( data ) =>
					setState( { data, loading: false, error: '' } )
				)
				.catch( ( error ) => {
					if ( error && error.name === 'AbortError' ) {
						return;
					}
					setState( ( previous ) => ( {
						...previous,
						loading: false,
						error: ( error && error.message ) || 'error',
					} ) );
				} );
		}, 1000 );

		return () => {
			window.clearTimeout( timer );
			if ( controller ) {
				controller.abort();
			}
		};
	}, [
		postId,
		title,
		slug,
		excerpt,
		blocks,
		keyphrase,
		seoTitle,
		seoDescription,
	] );

	return state;
}
