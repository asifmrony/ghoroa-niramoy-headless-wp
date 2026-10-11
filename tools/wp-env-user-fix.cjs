/**
 * Preloaded into wp-env (see tools/wp-env.cjs).
 *
 * wp-env passes os.userInfo().username into its Docker build as HOST_USERNAME and runs
 * `useradd … $HOST_USERNAME`. A Windows account name with a space (e.g. "KHAN GADGET") makes
 * useradd fail, so the container user has no home and `composer global require` breaks the build.
 * This makes the name Docker-safe; uid/gid are untouched.
 */
'use strict';

const os = require( 'os' );
const original = os.userInfo;

os.userInfo = function userInfo( ...args ) {
	const info = original.apply( os, args );
	const safe =
		String( info.username )
			.toLowerCase()
			.replace( /[^a-z0-9_-]+/g, '-' )
			.replace( /^-+|-+$/g, '' ) || 'wpenv';
	return { ...info, username: safe };
};
