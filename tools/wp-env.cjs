#!/usr/bin/env node
/**
 * Runs @wordpress/env with the username fix preloaded (tools/wp-env-user-fix.cjs).
 *
 *   node tools/wp-env.cjs start
 *   node tools/wp-env.cjs run cli wp gn setup
 *
 * Uses node_modules/@wordpress/env when installed; otherwise the version pinned in package.json
 * through npx (cached outside the project).
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const { spawnSync } = require( 'child_process' );

const root = path.resolve( __dirname, '..' );
// Forward slashes: NODE_OPTIONS treats backslashes as escapes.
const fix = path.join( __dirname, 'wp-env-user-fix.cjs' ).replace( /\\/g, '/' );
const env = {
	...process.env,
	NODE_OPTIONS: `${ process.env.NODE_OPTIONS || '' } --require "${ fix }"`.trim(),
};
const args = process.argv.slice( 2 );

const localBin = path.join( root, 'node_modules', '@wordpress', 'env', 'bin', 'wp-env' );
let result;

if ( fs.existsSync( localBin ) ) {
	result = spawnSync( process.execPath, [ localBin, ...args ], { stdio: 'inherit', env, cwd: root } );
} else {
	const version = require( path.join( root, 'package.json' ) ).devDependencies[ '@wordpress/env' ];
	const quoted = args.map( ( a ) => ( /[\s"]/.test( a ) ? JSON.stringify( a ) : a ) );
	result = spawnSync( 'npx', [ '-y', `@wordpress/env@${ version }`, ...quoted ], {
		stdio: 'inherit',
		env,
		cwd: root,
		shell: true,
	} );
}

process.exit( result.status ?? 1 );
