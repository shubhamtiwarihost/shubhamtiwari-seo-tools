/**
 * The DumpSEO sidebar.
 */
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import {
	Button,
	CheckboxControl,
	Notice,
	PanelBody,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import * as editPost from '@wordpress/edit-post';
import * as editor from '@wordpress/editor';
import { createElement, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
	LengthHint,
	ResultList,
	SearchPreview,
	StatusMarker,
} from './components';
import { KEYS, useAnalysis, useSeoMeta } from './hooks';
import {
	indexChoice,
	overallStatus,
	parseRobots,
	withDirective,
	withIndexChoice,
} from './utils';

// WordPress 6.6 moved these to @wordpress/editor; older versions only have them in @wordpress/edit-post.
const PluginSidebar = editor.PluginSidebar || editPost.PluginSidebar;
const PluginSidebarMoreMenuItem =
	editor.PluginSidebarMoreMenuItem || editPost.PluginSidebarMoreMenuItem;

const DIRECTIVES = {
	nofollow: __( 'Do not follow links (nofollow)', 'dumpseo' ),
	noarchive: __( 'Do not show a cached copy (noarchive)', 'dumpseo' ),
	nosnippet: __( 'Do not show a text snippet (nosnippet)', 'dumpseo' ),
	noimageindex: __( 'Do not index images (noimageindex)', 'dumpseo' ),
};

/**
 * Analysis panel body: loading state, error, or results.
 *
 * @param {Object}  props         Props.
 * @param {Object}  props.report  Report.
 * @param {boolean} props.loading Whether a request is running.
 * @param {string}  props.error   Error message.
 */
function AnalysisBody( { report, loading, error } ) {
	return (
		<Fragment>
			{ loading && (
				<p className="dumpseo-loading">
					<Spinner /> { __( 'Checking…', 'dumpseo' ) }
				</p>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ __( 'The check could not run:', 'dumpseo' ) } { error }
				</Notice>
			) }
			{ report && report.results.length === 0 && (
				<p>
					{ __(
						'There is not enough text to check yet.',
						'dumpseo'
					) }
				</p>
			) }
			<ResultList report={ report } />
		</Fragment>
	);
}

/**
 * Panel title with the report's overall status (none until something was checked).
 *
 * @param {Object}      props        Props.
 * @param {string}      props.label  Panel name.
 * @param {Object|null} props.report Report.
 */
function PanelTitle( { label, report } ) {
	return (
		<span className="dumpseo-panel-title">
			{ label }
			{ report && report.results.length > 0 && (
				<StatusMarker status={ overallStatus( [ report ] ) } />
			) }
		</span>
	);
}

export default function Sidebar() {
	const [ meta, setMeta ] = useSeoMeta();
	const { data, loading, error } = useAnalysis( meta );
	const permalink = useSelect(
		( s ) => s( 'core/editor' ).getPermalink(),
		[]
	);

	const robots = meta[ KEYS.robots ] || '';
	const status = data
		? overallStatus( [ data.seo, data.readability ] )
		: null;
	const hidden =
		indexChoice( robots ) === 'noindex' ||
		( window.dumpseoEditor && ! window.dumpseoEditor.blogPublic );

	return (
		<Fragment>
			<PluginSidebarMoreMenuItem target="dumpseo-sidebar">
				{ __( 'DumpSEO', 'dumpseo' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				className="dumpseo-sidebar"
				name="dumpseo-sidebar"
				title={ __( 'DumpSEO', 'dumpseo' ) }
				icon="search"
			>
				<PanelBody title={ __( 'Search appearance', 'dumpseo' ) }>
					{ hidden && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'This page is hidden from search engines.',
								'dumpseo'
							) }
						</Notice>
					) }
					<SearchPreview
						title={ data ? data.preview.title : '' }
						url={ permalink || '' }
						description={ data ? data.preview.description : '' }
					/>
					<TextControl
						label={ __( 'Focus keyphrase', 'dumpseo' ) }
						help={ __(
							'The words people would search for to find this page.',
							'dumpseo'
						) }
						value={ meta[ KEYS.keyphrase ] || '' }
						onChange={ ( value ) =>
							setMeta( KEYS.keyphrase, value )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'SEO title', 'dumpseo' ) }
						help={
							<Fragment>
								{ __(
									'Leave empty to use the title template. Variables such as %%site_name%% are allowed.',
									'dumpseo'
								) }{ ' ' }
								{ data && (
									<LengthHint
										text={ data.preview.title }
										min={ 30 }
										max={ 60 }
									/>
								) }
							</Fragment>
						}
						value={ meta[ KEYS.title ] || '' }
						onChange={ ( value ) => setMeta( KEYS.title, value ) }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __( 'Meta description', 'dumpseo' ) }
						help={
							<Fragment>
								{ __(
									'Leave empty to use the description template.',
									'dumpseo'
								) }{ ' ' }
								{ data && (
									<LengthHint
										text={ data.preview.description }
										min={ 120 }
										max={ 160 }
									/>
								) }
							</Fragment>
						}
						value={ meta[ KEYS.description ] || '' }
						onChange={ ( value ) =>
							setMeta( KEYS.description, value )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={
						<PanelTitle
							label={ __( 'SEO analysis', 'dumpseo' ) }
							report={ data && data.seo }
						/>
					}
				>
					<AnalysisBody
						report={ data && data.seo }
						loading={ loading }
						error={ error }
					/>
				</PanelBody>

				<PanelBody
					title={
						<PanelTitle
							label={ __( 'Readability', 'dumpseo' ) }
							report={ data && data.readability }
						/>
					}
					initialOpen={ false }
				>
					<AnalysisBody
						report={ data && data.readability }
						loading={ loading }
						error={ error }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Social sharing', 'dumpseo' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'Social sharing title', 'dumpseo' ) }
						help={ __(
							'Leave empty to use the SEO title.',
							'dumpseo'
						) }
						value={ meta[ KEYS.socialTitle ] || '' }
						onChange={ ( value ) =>
							setMeta( KEYS.socialTitle, value )
						}
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __( 'Social sharing description', 'dumpseo' ) }
						help={ __(
							'Leave empty to use the meta description.',
							'dumpseo'
						) }
						value={ meta[ KEYS.socialDescription ] || '' }
						onChange={ ( value ) =>
							setMeta( KEYS.socialDescription, value )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						type="url"
						label={ __( 'Social sharing image URL', 'dumpseo' ) }
						help={ __(
							'Leave empty to use the featured image, then the default sharing image.',
							'dumpseo'
						) }
						value={ meta[ KEYS.socialImage ] || '' }
						onChange={ ( value ) =>
							setMeta( KEYS.socialImage, value )
						}
						__nextHasNoMarginBottom
					/>
					<MediaUploadCheck>
						<MediaUpload
							allowedTypes={ [ 'image' ] }
							onSelect={ ( media ) =>
								setMeta( KEYS.socialImage, media.url )
							}
							render={ ( { open } ) => (
								<Button variant="secondary" onClick={ open }>
									{ __(
										'Choose from media library',
										'dumpseo'
									) }
								</Button>
							) }
						/>
					</MediaUploadCheck>
				</PanelBody>

				<PanelBody
					title={ __( 'Advanced', 'dumpseo' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Search engines', 'dumpseo' ) }
						value={ indexChoice( robots ) }
						options={ [
							{
								value: '',
								label: __(
									'Default (from DumpSEO settings)',
									'dumpseo'
								),
							},
							{
								value: 'index',
								label: __(
									'Show in search results (index)',
									'dumpseo'
								),
							},
							{
								value: 'noindex',
								label: __(
									'Hide from search results (noindex)',
									'dumpseo'
								),
							},
						] }
						onChange={ ( value ) =>
							setMeta(
								KEYS.robots,
								withIndexChoice( robots, value )
							)
						}
						__nextHasNoMarginBottom
					/>
					{ Object.keys( DIRECTIVES ).map( ( directive ) => (
						<CheckboxControl
							key={ directive }
							label={ DIRECTIVES[ directive ] }
							checked={ parseRobots( robots ).includes(
								directive
							) }
							onChange={ ( checked ) =>
								setMeta(
									KEYS.robots,
									withDirective( robots, directive, checked )
								)
							}
							__nextHasNoMarginBottom
						/>
					) ) }
					<TextControl
						type="url"
						label={ __( 'Canonical URL', 'dumpseo' ) }
						help={ __(
							'Only if this page duplicates another one. Leave empty for the page’s own address.',
							'dumpseo'
						) }
						value={ meta[ KEYS.canonical ] || '' }
						onChange={ ( value ) =>
							setMeta( KEYS.canonical, value )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				{ status && (
					<p className="screen-reader-text" aria-live="polite">
						{ STATUS_SUMMARY[ status ] }
					</p>
				) }
			</PluginSidebar>
		</Fragment>
	);
}

const STATUS_SUMMARY = {
	error: __( 'DumpSEO found problems.', 'dumpseo' ),
	warning: __( 'DumpSEO suggests improvements.', 'dumpseo' ),
	pass: __( 'DumpSEO checks passed.', 'dumpseo' ),
};
