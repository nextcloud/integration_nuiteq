/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { config, enableAutoUnmount } from '@vue/test-utils'
import { afterEach } from 'vitest'

// a component left mounted keeps its listeners and its teleported markup in the
// document; unmounting after each test keeps them out of the next one. The
// guard is for --no-isolate, where the setup file runs once per worker.
if (!globalThis.__nuiteqAutoUnmount) {
	globalThis.__nuiteqAutoUnmount = true
	enableAutoUnmount(afterEach)
}

// The entry points assign the free variables webpack declares for them
globalThis.__webpack_nonce__ = ''
globalThis.__webpack_public_path__ = ''

// What a Nextcloud page provides and @nextcloud/router reads: without the app's
// web root, imagePath() resolves app images as if they were core ones.
window._oc_webroot = ''
window._oc_appswebroots = { integration_nuiteq: '/apps/integration_nuiteq' }
// @nextcloud/vue's app shell reads these
window._oc_capabilities = {}
window._oc_config = {}

/**
 * Return the source string with its placeholders filled, so an assertion can
 * read what a user would.
 *
 * A string asked for under any other app's id is never translated, however
 * well this app's catalogue carries it, so make that visible rather than
 * silent. @nextcloud/vue does not come through here: it builds its own
 * gettext and never reads these.
 *
 * @param app the app id the string was asked for under
 * @param text the source string
 * @param vars the placeholder values
 */
function t(app, text, vars) {
	const prefix = app === 'integration_nuiteq' ? '' : `[untranslatable: ${app}] `
	return prefix + (vars
		? text.replace(/\{(\w+)\}/g, (match, name) => (name in vars ? vars[name] : match))
		: text)
}

/**
 * The plural form of the source string, with %n replaced by the count.
 *
 * @param app the app id, ignored here
 * @param singular the singular source string
 * @param plural the plural source string
 * @param count how many
 * @param vars the placeholder values
 */
function n(app, singular, plural, count, vars) {
	return t(app, count === 1 ? singular : plural, vars).replaceAll('%n', count)
}

// The script blocks call the page globals; the templates go through the
// instance proxy, which the app fills with app.mixin({ methods: { t, n } }).
window.t = t
window.n = n
config.global.mocks = { t, n }
