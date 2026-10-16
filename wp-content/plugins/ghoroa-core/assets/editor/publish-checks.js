/**
 * Block editor side of the §7 publish checks (ghoroa-core). Plain script, no build step.
 *
 * 1. Sends the current SCF field values (dek, isHealth, healthClaimsReviewed) with every save as
 *    `gn_acf`, because the field meta boxes save only after the post itself.
 * 2. Pre-publish panel: lists what blocks publishing (red) and the warnings (amber), from
 *    POST /gn/v1/publish-check. The server enforces the same rules when the post is published.
 * 3. Tells medical reviewers that only their review fields and Notes are saved.
 */
( function ( wp, config ) {
	if ( ! wp || ! wp.plugins || ! config ) {
		return;
	}
	const el = wp.element.createElement;
	const { useState, useEffect } = wp.element;
	const { useSelect, dispatch } = wp.data;
	const PrePublishPanel = ( wp.editor && wp.editor.PluginPrePublishPanel ) || ( wp.editPost && wp.editPost.PluginPrePublishPanel );

	/** Current values of the article fields in the SCF meta box (absent fields are left out). */
	function fieldValues() {
		const values = {};
		Object.entries( config.fields ).forEach( ( [ key, field ] ) => {
			const box = document.querySelector( `input[type="checkbox"][name="acf[${ field }]"]` );
			const text = document.querySelector( `textarea[name="acf[${ field }]"], input[type="text"][name="acf[${ field }]"]` );
			if ( box ) {
				values[ key ] = box.checked;
			} else if ( text ) {
				values[ key ] = text.value;
			}
		} );
		return values;
	}

	// 1. Keep `gn_acf` in step with the meta box.
	const sync = ( event ) => {
		const name = event.target && event.target.name;
		if ( name && Object.values( config.fields ).some( ( field ) => name === `acf[${ field }]` ) ) {
			dispatch( 'core/editor' ).editPost( { gn_acf: fieldValues() } );
		}
	};
	document.addEventListener( 'change', sync, true );
	document.addEventListener( 'input', sync, true );

	// 2. Pre-publish panel.
	function ChecksPanel() {
		const state = useSelect( ( s ) => {
			const editor = s( 'core/editor' );
			return {
				id: editor.getCurrentPostId(),
				title: editor.getEditedPostAttribute( 'title' ),
				slug: editor.getEditedPostAttribute( 'slug' ),
				excerpt: editor.getEditedPostAttribute( 'excerpt' ),
				content: editor.getEditedPostContent(),
				categories: editor.getEditedPostAttribute( 'categories' ),
				featured_media: editor.getEditedPostAttribute( 'featured_media' ),
			};
		}, [] );
		const [ result, setResult ] = useState( null );
		const key = JSON.stringify( state );

		useEffect( () => {
			let live = true;
			wp.apiFetch( { path: '/gn/v1/publish-check', method: 'POST', data: { ...state, acf: fieldValues() } } )
				.then( ( r ) => live && setResult( r ) )
				.catch( () => live && setResult( { errors: [], warnings: [ { code: 'x', message: 'যাচাই করা যায়নি; প্রকাশের সময় আবার যাচাই হবে।' } ] } ) );
			return () => {
				live = false;
			};
		}, [ key ] );

		const list = ( items, color ) =>
			el( 'ul', { style: { margin: '0 0 8px', paddingLeft: '18px' } },
				items.map( ( p, i ) => el( 'li', { key: i, style: { color } }, p.message ) ) );

		let body;
		if ( ! result ) {
			body = el( 'p', null, 'যাচাই হচ্ছে…' );
		} else if ( result.goesToReview ) {
			body = el( 'p', null, 'এটি স্বাস্থ্য-বিষয়ক লেখা: প্রকাশ নয়, এডিটরের পর্যালোচনার জন্য জমা (Pending) হবে।' );
		} else if ( ! result.errors.length && ! result.warnings.length ) {
			body = el( 'p', { style: { color: '#2D6A4F' } }, 'সব ঠিক আছে।' );
		} else {
			body = el( 'div', null,
				result.errors.length ? el( 'p', { style: { fontWeight: 600 } }, 'প্রকাশের আগে ঠিক করুন:' ) : null,
				result.errors.length ? list( result.errors, '#b32d2e' ) : null,
				result.warnings.length ? el( 'p', { style: { fontWeight: 600 } }, 'একবার দেখে নিন:' ) : null,
				result.warnings.length ? list( result.warnings, '#6A4A0F' ) : null );
		}
		return el( PrePublishPanel, { title: 'প্রকাশের আগে যাচাই', initialOpen: true }, body );
	}

	if ( PrePublishPanel ) {
		wp.plugins.registerPlugin( 'gn-publish-checks', { render: ChecksPanel } );
	}

	// 3. Medical reviewers: say what is saved.
	if ( config.isReviewer ) {
		wp.domReady( () =>
			dispatch( 'core/notices' ).createNotice(
				'info',
				'আপনি মেডিক্যাল রিভিউয়ার: শুধু “চিকিৎসক দ্বারা পর্যালোচিত” ঘর আর নোট (Notes) সংরক্ষণ হবে; লেখার অন্য পরিবর্তন সংরক্ষণ হবে না।',
				{ isDismissible: true }
			)
		);
	}
} )( window.wp, window.gnPublishChecks );
