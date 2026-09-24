<?php

// This file exists purely so WordPress knows what index.js depends on.
// Without it, WordPress would load index.js without knowing it needs
// wp.blocks, wp.element etc. to already be loaded first, and things
// would break in unpredictable ways depending on load order.
//
// The "version" number just needs to change whenever index.js changes,
// so browsers don't serve a stale cached copy. Bumping it by hand is
// fine at this scale, no build tool needed to generate it automatically.
return [
	'dependencies' => [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ],
	'version'      => '1.0.0',
];